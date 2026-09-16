<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\OrderDetailRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\CustomerDueService;
use App\Services\OrderService;
use App\Services\StockHistoryService;
use App\Models\Order;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class POSController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface     $productRepo,
        private readonly OrderRepositoryInterface        $orderRepo,
        private readonly OrderDetailRepositoryInterface  $orderDetailRepo,
        private readonly CustomerRepositoryInterface     $customerRepo,
        private readonly StockHistoryService             $stockHistoryService,
        private readonly CustomerDueService              $customerDueService,
        private readonly OrderService                    $orderService,
    )
    {
    }

    /**
     * Create a POS sale. Supports full payment, partial payment (the remainder becomes
     * customer due) and full due (0 paid) -- due sales require a real customer_id, since
     * the walking-customer record (id 0) has nowhere to track a balance.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|integer',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $customerId = (int)($request['customer_id'] ?? 0);
        if ($customerId !== 0) {
            $customer = $this->customerRepo->getFirstWhere(params: ['id' => $customerId]);
            if (!$customer) {
                return response()->json(['errors' => [['code' => 'customer-001', 'message' => translate('customer_not_found')]]], 404);
            }
        }

        $products = [];
        $orderAmount = 0.0;
        foreach ($request['items'] as $index => $item) {
            $product = $this->productRepo->getFirstWhere(params: ['id' => $item['product_id']]);
            if (!$product) {
                return response()->json(['errors' => [['code' => 'product-001', 'message' => translate('product_not_found') . ' (item ' . ($index + 1) . ')']]], 404);
            }
            if ($product->product_type === 'physical' && (int)$product->current_stock < (int)$item['qty']) {
                return response()->json(['errors' => [['code' => 'pos-001', 'message' => translate('insufficient_stock_for') . ' ' . $product->name]]], 422);
            }

            $price = isset($item['price']) && $item['price'] !== '' ? (float)$item['price'] : (float)$product->unit_price;
            $qty = (int)$item['qty'];
            $orderAmount += $price * $qty;

            $products[] = ['product' => $product, 'qty' => $qty, 'price' => $price];
        }
        $orderAmount = round($orderAmount, 2);

        $paidAmount = round(min((float)$request['paid_amount'], $orderAmount), 2);
        if ($paidAmount < $orderAmount && $customerId === 0) {
            return response()->json(['errors' => [['code' => 'pos-002', 'message' => translate('a_customer_must_be_selected_for_a_due_sale')]]], 422);
        }

        $paymentStatus = $paidAmount >= $orderAmount ? 'paid' : ($paidAmount <= 0 ? 'due' : 'partial');
        $paymentMethod = $request['payment_method'] ?? ($paymentStatus === 'paid' ? 'cash' : 'due');
        $adminId = $request['admin']->id;

        $order = DB::transaction(function () use ($products, $orderAmount, $paidAmount, $paymentStatus, $paymentMethod, $customerId, $adminId, $request) {
            $orderId = 100000 + Order::count() + 1;
            while ($this->orderRepo->getFirstWhere(params: ['id' => $orderId])) {
                $orderId++;
            }

            foreach ($products as $line) {
                $product = $line['product'];
                $this->orderDetailRepo->add(data: [
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'product_details' => $product,
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                    'seller_id' => $product->user_id,
                    'tax' => 0,
                    'tax_model' => $product->tax_model ?? 'exclude',
                    'discount' => 0,
                    'discount_type' => 'discount_on_product',
                    'delivery_status' => 'delivered',
                    'payment_status' => $paymentStatus,
                    'variant' => null,
                    'variation' => null,
                ]);

                if ($product->product_type === 'physical') {
                    $this->stockHistoryService->recordChange(
                        productId: $product->id,
                        type: StockHistoryService::TYPE_ORDER,
                        quantityChange: -$line['qty'],
                        referenceNo: 'order-' . $orderId,
                        adminId: $adminId,
                    );
                }
            }

            $orderData = $this->orderService->getPOSOrderData(
                orderId: $orderId,
                cart: [],
                amount: $orderAmount,
                paidAmount: $paidAmount,
                paymentType: $paymentMethod,
                addedBy: 'admin',
                userId: $customerId,
            );
            $orderData['payment_status'] = $paymentStatus;
            if ($request['note']) {
                $orderData['order_note'] = $request['note'];
            }

            $this->orderRepo->add(data: $orderData);

            if ($paidAmount < $orderAmount) {
                $this->customerDueService->addDue(
                    userId: $customerId,
                    amount: round($orderAmount - $paidAmount, 2),
                    orderId: $orderId,
                    note: translate('pos_sale_due') . ' #' . $orderId,
                    adminId: $adminId,
                );
            }

            return $this->orderRepo->getFirstWhere(params: ['id' => $orderId], relations: ['customer', 'orderDetails']);
        });

        return response()->json([
            'order' => $order,
            'order_amount' => $orderAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => round($orderAmount - $paidAmount, 2),
            'payment_status' => $paymentStatus,
        ], 201);
    }
}

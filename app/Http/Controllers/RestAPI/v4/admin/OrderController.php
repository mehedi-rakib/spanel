<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct(private readonly OrderRepositoryInterface $orderRepo)
    {
    }

    public function index(Request $request)
    {
        $filters = array_filter([
            'order_status' => $request['order_status'],
            'payment_status' => $request['payment_status'],
        ], fn($value) => $value !== null && $value !== '');

        $orders = $this->orderRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $request['searchValue'],
            filters: $filters,
            relations: ['customer', 'orderDetails'],
            dataLimit: $request['limit'] ?? DEFAULT_DATA_LIMIT
        );

        return response()->json($orders, 200);
    }

    public function show(string|int $id)
    {
        $order = $this->orderRepo->getFirstWhere(params: ['id' => $id], relations: ['customer', 'orderDetails.product']);
        if (!$order) {
            return response()->json(['errors' => [['code' => 'order-001', 'message' => translate('order_not_found')]]], 404);
        }
        return response()->json($order, 200);
    }

    /**
     * Simplified status update: applies the stock-history-aware stock movement and the
     * order_status column. Side effects the web admin panel also performs on status change
     * (wallet transactions, notifications, loyalty points) are not duplicated here yet.
     */
    public function updateStatus(Request $request, string|int $id)
    {
        $validator = Validator::make($request->all(), [
            'order_status' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $order = $this->orderRepo->getFirstWhere(params: ['id' => $id]);
        if (!$order) {
            return response()->json(['errors' => [['code' => 'order-001', 'message' => translate('order_not_found')]]], 404);
        }

        $this->orderRepo->updateStockOnOrderStatusChange($id, $request['order_status']);
        $this->orderRepo->update(id: $id, data: ['order_status' => $request['order_status']]);

        $updatedOrder = $this->orderRepo->getFirstWhere(params: ['id' => $id]);
        return response()->json($updatedOrder, 200);
    }
}

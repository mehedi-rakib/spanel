<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RestAPI\v4\admin\Concerns\QueriesPurchaseBills;
use App\Http\Controllers\RestAPI\v4\admin\Concerns\QueriesSales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use QueriesPurchaseBills, QueriesSales;

    private function dateRange(Request $request): array
    {
        return [
            $request['from_date'] ?? now()->subDays(29)->format('Y-m-d'),
            $request['to_date'] ?? now()->format('Y-m-d'),
        ];
    }

    /**
     * Sales/revenue summary for a date range, plus the matching sale list
     * (paginated as `transactions`). Canceled/failed/returned orders are excluded.
     * Optional filters: `payment_status`, `customer_id`, `searchValue`.
     */
    public function sales(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request);

        $base = $this->salesOrdersQuery($fromDate, $toDate)
            ->when($request['payment_status'], fn($query) => $query->where('orders.payment_status', $request['payment_status']))
            ->when($request['customer_id'] !== null && $request['customer_id'] !== '', fn($query) => $query->where('orders.customer_id', (int)$request['customer_id']));

        $totals = (clone $base)->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(order_amount), 0) as total_sales,
            COALESCE(SUM(paid_amount), 0) as total_paid,
            COALESCE(SUM(GREATEST(order_amount - paid_amount, 0)), 0) as total_due
        ')->first();

        $byPaymentStatus = (clone $base)
            ->select('payment_status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(order_amount), 0) as total'))
            ->groupBy('payment_status')
            ->get();

        $searchValue = $request['searchValue'];
        $transactions = (clone $base)
            ->leftJoin('users', 'users.id', '=', 'orders.customer_id')
            ->when($searchValue, function ($query) use ($searchValue) {
                $query->where(function ($query) use ($searchValue) {
                    $query->where('orders.id', 'like', "%{$searchValue}%")
                        ->orWhere('users.f_name', 'like', "%{$searchValue}%")
                        ->orWhere('users.l_name', 'like', "%{$searchValue}%")
                        ->orWhere('users.name', 'like', "%{$searchValue}%")
                        ->orWhere('users.phone', 'like', "%{$searchValue}%");
                });
            })
            ->orderByDesc('orders.id')
            ->selectRaw("
                orders.id,
                orders.customer_id,
                {$this->customerNameSql()} as customer_name,
                orders.order_amount,
                orders.paid_amount,
                GREATEST(orders.order_amount - orders.paid_amount, 0) as balance,
                orders.payment_status,
                orders.payment_method,
                orders.order_status,
                orders.created_at
            ")
            ->paginate($request['limit'] ?? DEFAULT_DATA_LIMIT);

        $transactions->getCollection()->transform(function ($row) {
            $row->customer_id = (int)$row->customer_id;
            if (!$row->customer_name || $row->customer_id === 0) {
                $row->customer_name = translate('walking_customer');
            }
            $row->order_amount = round((float)$row->order_amount, 2);
            $row->paid_amount = round((float)$row->paid_amount, 2);
            $row->balance = round((float)$row->balance, 2);
            return $row;
        });

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_orders' => (int)$totals->total_orders,
            'total_sales' => round((float)$totals->total_sales, 2),
            'total_paid' => round((float)$totals->total_paid, 2),
            'total_due' => round((float)$totals->total_due, 2),
            'by_payment_status' => $byPaymentStatus,
            'transactions' => $transactions,
        ], 200);
    }

    /**
     * Supplier purchases for a date range: totals plus paginated purchase bills.
     * Optional filters: `supplier_id`, `searchValue`.
     */
    public function purchases(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request);
        $supplierId = $request['supplier_id'] ? (int)$request['supplier_id'] : null;

        $bills = $this->purchaseBillsQuery($fromDate, $toDate, $supplierId, $request['searchValue'])
            ->orderByDesc('created_at')
            ->paginate($request['limit'] ?? DEFAULT_DATA_LIMIT);
        $bills->getCollection()->transform(fn($bill) => $this->castPurchaseBill($bill));

        $totals = $this->purchaseTotals($fromDate, $toDate, $supplierId);

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_bills' => $totals->total_bills,
            'total_qty' => $totals->total_qty,
            'total_amount' => $totals->total_amount,
            'bills' => $bills,
        ], 200);
    }

    /**
     * Item-wise sales: quantity and amount sold per product in a date range,
     * best sellers (by amount) first.
     */
    public function itemSales(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request);
        $searchValue = $request['searchValue'];

        $base = $this->salesOrdersQuery($fromDate, $toDate)
            ->join('order_details', 'order_details.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_details.product_id')
            ->when($searchValue, function ($query) use ($searchValue) {
                $query->where(function ($query) use ($searchValue) {
                    $query->where('products.name', 'like', "%{$searchValue}%")
                        ->orWhere('products.code', 'like', "%{$searchValue}%");
                });
            });

        $totals = (clone $base)->selectRaw('
            COALESCE(SUM(order_details.qty), 0) as total_qty,
            COALESCE(SUM(order_details.qty * order_details.price), 0) as total_amount,
            COALESCE(SUM(order_details.qty * COALESCE(products.purchase_price, 0)), 0) as total_cost
        ')->first();

        $items = (clone $base)
            ->groupBy('order_details.product_id')
            ->selectRaw('
                order_details.product_id,
                MAX(products.name) as name,
                MAX(products.code) as code,
                COALESCE(SUM(order_details.qty), 0) as qty,
                COALESCE(SUM(order_details.qty * order_details.price), 0) as amount,
                COALESCE(SUM(order_details.qty * COALESCE(products.purchase_price, 0)), 0) as cost
            ')
            ->orderByDesc('amount')
            ->paginate($request['limit'] ?? DEFAULT_DATA_LIMIT);

        $items->getCollection()->transform(function ($row) {
            $row->product_id = (int)$row->product_id;
            $row->qty = (int)$row->qty;
            $row->amount = round((float)$row->amount, 2);
            $row->cost = round((float)$row->cost, 2);
            $row->profit = round($row->amount - $row->cost, 2);
            return $row;
        });

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_qty' => (int)$totals->total_qty,
            'total_amount' => round((float)$totals->total_amount, 2),
            'total_profit' => round((float)$totals->total_amount - (float)$totals->total_cost, 2),
            'items' => $items,
        ], 200);
    }

    /**
     * Current stock/inventory snapshot.
     */
    public function stock(Request $request)
    {
        $stockLimit = (int)(getWebConfig(name: 'stock_limit') ?? 10);

        $base = DB::table('products')->where('product_type', 'physical');

        $totals = (clone $base)->selectRaw('
            COUNT(*) as total_products,
            COALESCE(SUM(current_stock), 0) as total_units,
            COALESCE(SUM(CASE WHEN current_stock > 0 THEN current_stock * purchase_price ELSE 0 END), 0) as total_stock_value,
            SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
            SUM(CASE WHEN current_stock > 0 AND current_stock < ? THEN 1 ELSE 0 END) as low_stock_count
        ', [$stockLimit])->first();

        $lowStockProducts = (clone $base)
            ->where('current_stock', '<', $stockLimit)
            ->orderBy('current_stock')
            ->limit((int)($request['limit'] ?? 20))
            ->select(['id', 'name', 'code', 'current_stock', 'purchase_price', 'unit_price'])
            ->get();

        return response()->json([
            'total_products' => (int)$totals->total_products,
            'total_units' => (int)$totals->total_units,
            'total_stock_value' => round((float)$totals->total_stock_value, 2),
            'out_of_stock_count' => (int)$totals->out_of_stock_count,
            'low_stock_count' => (int)$totals->low_stock_count,
            'low_stock_products' => $lowStockProducts,
        ], 200);
    }

    /**
     * Revenue vs cost-of-goods for delivered/paid order lines within a date range.
     */
    public function profitLoss(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request);

        $row = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->whereDate('orders.created_at', '>=', $fromDate)
            ->whereDate('orders.created_at', '<=', $toDate)
            ->whereIn('orders.order_status', ['delivered', 'confirmed', 'processing', 'out_for_delivery'])
            ->selectRaw('
                COALESCE(SUM(order_details.price * order_details.qty), 0) as revenue,
                COALESCE(SUM(products.purchase_price * order_details.qty), 0) as cost
            ')->first();

        $revenue = round((float)$row->revenue, 2);
        $cost = round((float)$row->cost, 2);

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => round($revenue - $cost, 2),
        ], 200);
    }

    /**
     * Outstanding customer-due summary (see CustomerDueController for the full paginated list/ledger).
     */
    public function due(Request $request)
    {
        $totals = DB::table('users')->selectRaw('
            COALESCE(SUM(due_balance), 0) as total_outstanding_due,
            SUM(CASE WHEN due_balance > 0 THEN 1 ELSE 0 END) as customers_with_due_count
        ')->first();

        $topDebtors = DB::table('users')
            ->where('due_balance', '>', 0)
            ->orderByDesc('due_balance')
            ->limit((int)($request['limit'] ?? 10))
            ->select(['id', 'f_name', 'l_name', 'phone', 'due_balance'])
            ->get();

        return response()->json([
            'total_outstanding_due' => round((float)$totals->total_outstanding_due, 2),
            'customers_with_due_count' => (int)$totals->customers_with_due_count,
            'top_debtors' => $topDebtors,
        ], 200);
    }
}

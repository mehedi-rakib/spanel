<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private function dateRange(Request $request): array
    {
        return [
            $request['from_date'] ?? now()->subDays(29)->format('Y-m-d'),
            $request['to_date'] ?? now()->format('Y-m-d'),
        ];
    }

    /**
     * Sales/revenue summary for a date range.
     */
    public function sales(Request $request)
    {
        [$fromDate, $toDate] = $this->dateRange($request);

        $base = DB::table('orders')->whereDate('created_at', '>=', $fromDate)->whereDate('created_at', '<=', $toDate);

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

        return response()->json([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_orders' => (int)$totals->total_orders,
            'total_sales' => round((float)$totals->total_sales, 2),
            'total_paid' => round((float)$totals->total_paid, 2),
            'total_due' => round((float)$totals->total_due, 2),
            'by_payment_status' => $byPaymentStatus,
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
            COALESCE(SUM(current_stock * purchase_price), 0) as total_stock_value,
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

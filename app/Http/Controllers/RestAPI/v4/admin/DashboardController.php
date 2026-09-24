<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\StockHistoryRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RestAPI\v4\admin\Concerns\QueriesPurchaseBills;
use App\Http\Controllers\RestAPI\v4\admin\Concerns\QueriesSales;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use QueriesPurchaseBills, QueriesSales;

    public function __construct(
        private readonly StockHistoryRepositoryInterface $stockHistoryRepo,
    )
    {
    }

    public function index(Request $request)
    {
        $stockLimit = (int)(getWebConfig(name: 'stock_limit') ?? 10);
        $now = now();
        $monthStart = $now->copy()->startOfMonth()->format('Y-m-d');
        $monthEnd = $now->copy()->endOfMonth()->format('Y-m-d');

        $physicalProducts = DB::table('products')->where('product_type', 'physical');
        $inventory = (clone $physicalProducts)->selectRaw('
            COUNT(*) as total_items,
            COALESCE(SUM(CASE WHEN current_stock > 0 THEN current_stock * purchase_price ELSE 0 END), 0) as stock_value,
            SUM(CASE WHEN current_stock < ? THEN 1 ELSE 0 END) as low_stock_count
        ', [$stockLimit])->first();

        $lowStockItems = (clone $physicalProducts)
            ->where('current_stock', '<', $stockLimit)
            ->orderBy('current_stock')
            ->limit(3)
            ->select(['id', 'name', 'code', 'current_stock'])
            ->get();

        $monthSales = $this->salesOrdersQuery($monthStart, $monthEnd)->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(order_amount), 0) as total_sales,
            COALESCE(SUM(paid_amount), 0) as total_paid
        ')->first();

        $monthProfit = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->whereNotIn('orders.order_status', $this->nonSaleOrderStatuses())
            ->whereDate('orders.created_at', '>=', $monthStart)
            ->whereDate('orders.created_at', '<=', $monthEnd)
            ->selectRaw('COALESCE(SUM(order_details.price * order_details.qty), 0) - COALESCE(SUM(products.purchase_price * order_details.qty), 0) as profit')
            ->value('profit');

        return response()->json([
            // Original summary fields (kept for older app builds).
            'total_products' => DB::table('products')->count(),
            'total_orders' => DB::table('orders')->count(),
            'total_customers' => DB::table('users')->where('id', '!=', 0)->count(),
            'total_employees' => DB::table('admins')->where('id', '!=', 1)->count(),
            'low_stock_products' => (int)$inventory->low_stock_count,
            'recent_stock_movements' => $this->stockHistoryRepo->getListWhere(orderBy: ['id' => 'desc'], relations: ['product'], dataLimit: 10),

            'receivable' => round((float)DB::table('users')->sum('due_balance'), 2),
            'customers_with_due' => DB::table('users')->where('due_balance', '>', 0)->count(),
            'month' => [
                'label' => $now->format('M'),
                'from_date' => $monthStart,
                'to_date' => $monthEnd,
                'total_sales' => round((float)$monthSales->total_sales, 2),
                'total_orders' => (int)$monthSales->total_orders,
                'collected' => round((float)$monthSales->total_paid, 2),
                'purchases' => $this->purchaseTotals($monthStart, $monthEnd)->total_amount,
                'profit' => round((float)$monthProfit, 2),
            ],
            'sale_overview' => $this->saleOverview($now),
            'today' => $this->todaySummary(),
            'inventory' => [
                'stock_value' => round((float)$inventory->stock_value, 2),
                'total_items' => (int)$inventory->total_items,
                'low_stock_count' => (int)$inventory->low_stock_count,
                'low_stock_items' => $lowStockItems,
            ],
        ], 200);
    }

    /**
     * Monthly sale totals for the last 6 months of this year vs. the same months
     * last year, plus the current month's change vs. last month.
     */
    private function saleOverview(Carbon $now): array
    {
        $rangeStart = $now->copy()->startOfMonth()->subMonths(5);
        $lastYearStart = $rangeStart->copy()->subYear();

        $rows = $this->salesOrdersQuery()
            ->where(function ($query) use ($rangeStart, $lastYearStart, $now) {
                $query->whereBetween('orders.created_at', [$rangeStart, $now->copy()->endOfMonth()])
                    ->orWhereBetween('orders.created_at', [$lastYearStart, $now->copy()->subYear()->endOfMonth()]);
            })
            ->selectRaw("DATE_FORMAT(orders.created_at, '%Y-%m') as ym, COALESCE(SUM(order_amount), 0) as total")
            ->groupByRaw("DATE_FORMAT(orders.created_at, '%Y-%m')")
            ->pluck('total', 'ym');

        $series = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $rangeStart->copy()->addMonths($i);
            $series[] = [
                'month' => $month->format('M'),
                'current' => round((float)($rows[$month->format('Y-m')] ?? 0), 2),
                'last' => round((float)($rows[$month->copy()->subYear()->format('Y-m')] ?? 0), 2),
            ];
        }

        $thisMonth = $series[5]['current'];
        $previousMonth = $series[4]['current'];
        $changePercent = $previousMonth > 0 ? round((($thisMonth - $previousMonth) / $previousMonth) * 100, 1) : null;

        return [
            'current_year' => (int)$now->format('Y'),
            'last_year' => (int)$now->format('Y') - 1,
            'total_sale' => $thisMonth,
            'previous_month_sale' => $previousMonth,
            'change_percent' => $changePercent,
            'series' => $series,
        ];
    }

    private function todaySummary(): array
    {
        $today = now()->format('Y-m-d');
        $sales = $this->salesOrdersQuery($today, $today)->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(order_amount), 0) as total_sales,
            COALESCE(SUM(paid_amount), 0) as total_paid
        ')->first();
        $dueCollected = DB::table('customer_due_transactions')
            ->where('type', 'payment')
            ->whereDate('created_at', $today)
            ->sum('amount');

        return [
            'total_orders' => (int)$sales->total_orders,
            'total_sales' => round((float)$sales->total_sales, 2),
            'cash_in' => round((float)$sales->total_paid + (float)$dueCollected, 2),
        ];
    }
}

<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\AdminRepositoryInterface;
use App\Contracts\Repositories\CustomerRepositoryInterface;
use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockHistoryRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface      $productRepo,
        private readonly OrderRepositoryInterface         $orderRepo,
        private readonly CustomerRepositoryInterface      $customerRepo,
        private readonly AdminRepositoryInterface         $adminRepo,
        private readonly StockHistoryRepositoryInterface  $stockHistoryRepo,
    )
    {
    }

    public function index(Request $request)
    {
        $stockLimit = (int)(getWebConfig(name: 'stock_limit') ?? 10);

        $lowStockCount = $this->productRepo->getListWhere(
            filters: ['product_type' => 'physical'],
            dataLimit: 'all'
        )->where('current_stock', '<', $stockLimit)->count();

        return response()->json([
            'total_products' => $this->productRepo->getListWhere(dataLimit: 'all')->count(),
            'total_orders' => $this->orderRepo->getListWhere(dataLimit: 'all')->count(),
            'total_customers' => $this->customerRepo->getList(dataLimit: 'all')->count(),
            'total_employees' => $this->adminRepo->getEmployeeListWhere(filters: ['admin_role_id' => 'all'], dataLimit: 'all')->count(),
            'low_stock_products' => $lowStockCount,
            'recent_stock_movements' => $this->stockHistoryRepo->getListWhere(orderBy: ['id' => 'desc'], relations: ['product'], dataLimit: 10),
        ], 200);
    }
}

<?php
/*
|--------------------------------------------------------------------------
| Admin Mobile APP API Routes (v4)
|--------------------------------------------------------------------------
| Every endpoint meant for the future admin/employee mobile app lives here.
| Auth uses a bearer token (admins.auth_token), same pattern as the seller
| mobile app (see App\Http\Middleware\AdminApiAuthMiddleware).
|*/

use App\Http\Controllers\RestAPI\v4\admin\auth\LoginController;
use App\Http\Controllers\RestAPI\v4\admin\BrandController;
use App\Http\Controllers\RestAPI\v4\admin\CategoryController;
use App\Http\Controllers\RestAPI\v4\admin\CustomerController;
use App\Http\Controllers\RestAPI\v4\admin\CustomerDueController;
use App\Http\Controllers\RestAPI\v4\admin\DashboardController;
use App\Http\Controllers\RestAPI\v4\admin\EmployeeController;
use App\Http\Controllers\RestAPI\v4\admin\ExportController;
use App\Http\Controllers\RestAPI\v4\admin\OrderController;
use App\Http\Controllers\RestAPI\v4\admin\POSController;
use App\Http\Controllers\RestAPI\v4\admin\ProductController;
use App\Http\Controllers\RestAPI\v4\admin\PurchaseController;
use App\Http\Controllers\RestAPI\v4\admin\ReportController;
use App\Http\Controllers\RestAPI\v4\admin\StockHistoryController;
use App\Http\Controllers\RestAPI\v4\admin\SupplierController;
use App\Http\Controllers\RestAPI\v4\admin\TransactionController;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'RestAPI\v4\admin', 'prefix' => 'v4/admin', 'middleware' => ['api_lang']], function () {

    Route::group(['prefix' => 'auth', 'namespace' => 'auth'], function () {
        Route::controller(LoginController::class)->group(function () {
            Route::post('login', 'login');
        });
    });

    // Opened by the phone's browser / download manager, which can't send the bearer
    // token; access is granted by the short-lived signature from POST /exports instead.
    Route::get('exports/download', [ExportController::class, 'download'])
        ->middleware('signed:relative')
        ->name('api.v4.admin.exports.download');

    Route::group(['middleware' => ['admin_api_auth']], function () {
        Route::post('exports', [ExportController::class, 'create']);

        Route::controller(LoginController::class)->group(function () {
            Route::get('logout', 'logout');
            Route::get('me', 'me');
        });

        Route::controller(DashboardController::class)->group(function () {
            Route::get('dashboard', 'index');
        });

        Route::controller(CategoryController::class)->group(function () {
            Route::get('categories', 'index');
        });

        Route::controller(BrandController::class)->group(function () {
            Route::get('brands', 'index');
        });

        Route::controller(EmployeeController::class)->group(function () {
            Route::get('employees', 'index');
            Route::get('employees/{id}', 'show');
        });

        Route::controller(ProductController::class)->group(function () {
            // Registered before products/{id} (which is also numeric-only) so
            // "purchase" is never captured as a product id.
            Route::post('products/purchase', 'purchase');
            Route::get('products', 'index');
            Route::get('products/{id}', 'show')->whereNumber('id');
            Route::post('products', 'store');
            Route::post('products/{id}', 'update')->whereNumber('id');
            Route::delete('products/{id}', 'destroy')->whereNumber('id');
            Route::post('products/{id}/stock', 'updateStock')->whereNumber('id');
        });

        Route::controller(PurchaseController::class)->group(function () {
            Route::get('purchases', 'index');
            Route::get('purchases/{reference}', 'show');
        });

        Route::controller(TransactionController::class)->group(function () {
            Route::get('transactions', 'index');
        });

        Route::controller(StockHistoryController::class)->group(function () {
            Route::get('stock-history', 'index');
            Route::get('stock-history/{id}', 'show');
        });

        Route::controller(SupplierController::class)->group(function () {
            Route::get('suppliers', 'index');
            Route::get('suppliers/{id}', 'show');
            Route::post('suppliers', 'store');
            Route::post('suppliers/{id}', 'update');
            Route::delete('suppliers/{id}', 'destroy');
        });

        Route::controller(OrderController::class)->group(function () {
            Route::get('orders', 'index');
            Route::get('orders/{id}', 'show');
            Route::post('orders/{id}/status', 'updateStatus');
        });

        Route::controller(POSController::class)->group(function () {
            Route::post('pos/sale', 'store');
        });

        Route::controller(CustomerController::class)->group(function () {
            Route::get('customers', 'index');
            Route::get('customers/{id}', 'show');
            Route::post('customers', 'store');
            Route::post('customers/{id}', 'update');
        });

        Route::controller(CustomerDueController::class)->group(function () {
            Route::get('customer-dues', 'index');
            Route::get('customer-dues/{customerId}', 'show');
            Route::post('customer-dues/{customerId}/payments', 'store');
        });

        Route::controller(ReportController::class)->group(function () {
            Route::get('reports/sales', 'sales');
            Route::get('reports/stock', 'stock');
            Route::get('reports/profit-loss', 'profitLoss');
            Route::get('reports/due', 'due');
            Route::get('reports/purchases', 'purchases');
            Route::get('reports/item-sales', 'itemSales');
        });
    });
});

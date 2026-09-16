<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\StockHistoryRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StockHistoryController extends Controller
{
    public function __construct(private readonly StockHistoryRepositoryInterface $stockHistoryRepo)
    {
    }

    public function index(Request $request)
    {
        $filters = array_filter([
            'product_id' => $request['product_id'],
            'type' => $request['type'],
            'from_date' => $request['from_date'],
            'to_date' => $request['to_date'],
        ], fn($value) => $value !== null && $value !== '');

        $stockHistories = $this->stockHistoryRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $request['searchValue'],
            filters: $filters,
            relations: ['product', 'admin'],
            dataLimit: $request['limit'] ?? DEFAULT_DATA_LIMIT
        );

        return response()->json($stockHistories, 200);
    }
}

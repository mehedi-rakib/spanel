<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\StockHistoryRepositoryInterface;
use App\Enums\ViewPaths\Admin\StockHistory;
use App\Enums\WebConfigKey;
use App\Http\Controllers\BaseController;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StockHistoryController extends BaseController
{
    public function __construct(
        private readonly StockHistoryRepositoryInterface $stockHistoryRepo,
    )
    {
    }

    public function index(?Request $request, string $type = null): View
    {
        $filters = [
            'type' => $request['type'],
            'from_date' => $request['from_date'],
            'to_date' => $request['to_date'],
        ];
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');

        $stockHistories = $this->stockHistoryRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $request['searchValue'],
            filters: $filters,
            relations: ['product', 'admin'],
            dataLimit: getWebConfig(name: WebConfigKey::PAGINATION_LIMIT)
        );

        $searchValue = $request['searchValue'];
        return view(StockHistory::LIST[VIEW], compact('stockHistories', 'searchValue'));
    }
}

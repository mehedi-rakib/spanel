<?php

namespace App\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface StockHistoryRepositoryInterface extends RepositoryInterface
{
    public function getPurchaseGroups(string $searchValue = null, array $filters = [], int|string $dataLimit = DEFAULT_DATA_LIMIT): Collection|LengthAwarePaginator;

    public function getPurchaseDetails(string $referenceNo): Collection;
}

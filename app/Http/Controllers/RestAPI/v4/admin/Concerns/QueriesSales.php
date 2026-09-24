<?php

namespace App\Http\Controllers\RestAPI\v4\admin\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait QueriesSales
{
    /**
     * Orders in these states never became (or stopped being) a sale, so they are
     * left out of every sales/profit total the mobile app shows.
     */
    protected function nonSaleOrderStatuses(): array
    {
        return ['canceled', 'failed', 'returned'];
    }

    protected function salesOrdersQuery(?string $fromDate = null, ?string $toDate = null): Builder
    {
        return DB::table('orders')
            ->whereNotIn('orders.order_status', $this->nonSaleOrderStatuses())
            ->when($fromDate, fn($query) => $query->whereDate('orders.created_at', '>=', $fromDate))
            ->when($toDate, fn($query) => $query->whereDate('orders.created_at', '<=', $toDate));
    }

    protected function customerNameSql(string $table = 'users'): string
    {
        return "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', {$table}.f_name, {$table}.l_name)), ''), NULLIF({$table}.name, ''), {$table}.phone)";
    }
}

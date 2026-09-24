<?php

namespace App\Http\Controllers\RestAPI\v4\admin\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A "purchase bill" is not its own table: POST /products/purchase writes one
 * stock_histories row (type=purchase) per item, all sharing a reference_no.
 * This groups those rows back into bills. Rows written before reference_no
 * was always filled in get a synthetic per-row key (SH-<id>) so they still
 * show up as one-line bills instead of collapsing together.
 */
trait QueriesPurchaseBills
{
    protected function purchaseBillKeySql(): string
    {
        return "COALESCE(NULLIF(stock_histories.reference_no, ''), CONCAT('SH-', stock_histories.id))";
    }

    protected function purchaseBillsQuery(?string $fromDate = null, ?string $toDate = null, ?int $supplierId = null, ?string $searchValue = null): Builder
    {
        $billKey = $this->purchaseBillKeySql();

        return DB::table('stock_histories')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_histories.supplier_id')
            ->where('stock_histories.type', 'purchase')
            ->when($fromDate, fn($query) => $query->whereDate('stock_histories.created_at', '>=', $fromDate))
            ->when($toDate, fn($query) => $query->whereDate('stock_histories.created_at', '<=', $toDate))
            ->when($supplierId, fn($query) => $query->where('stock_histories.supplier_id', $supplierId))
            ->when($searchValue, function ($query) use ($searchValue) {
                $query->where(function ($query) use ($searchValue) {
                    $query->where('stock_histories.reference_no', 'like', "%{$searchValue}%")
                        ->orWhere('suppliers.name', 'like', "%{$searchValue}%")
                        ->orWhere('suppliers.shop_name', 'like', "%{$searchValue}%");
                });
            })
            ->selectRaw("
                {$billKey} as reference_no,
                stock_histories.supplier_id as supplier_id,
                MAX(suppliers.name) as supplier_name,
                MIN(stock_histories.created_at) as created_at,
                COUNT(*) as item_count,
                COALESCE(SUM(stock_histories.quantity_change), 0) as total_qty,
                COALESCE(SUM(stock_histories.quantity_change * COALESCE(stock_histories.unit_cost, 0)), 0) as total_amount
            ")
            ->groupByRaw("{$billKey}, stock_histories.supplier_id");
    }

    protected function purchaseTotals(?string $fromDate = null, ?string $toDate = null, ?int $supplierId = null): object
    {
        $row = DB::table('stock_histories')
            ->where('type', 'purchase')
            ->when($fromDate, fn($query) => $query->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($query) => $query->whereDate('created_at', '<=', $toDate))
            ->when($supplierId, fn($query) => $query->where('supplier_id', $supplierId))
            ->selectRaw("
                COUNT(DISTINCT {$this->purchaseBillKeySql()}) as total_bills,
                COALESCE(SUM(quantity_change), 0) as total_qty,
                COALESCE(SUM(quantity_change * COALESCE(unit_cost, 0)), 0) as total_amount
            ")->first();

        return (object)[
            'total_bills' => (int)$row->total_bills,
            'total_qty' => (int)$row->total_qty,
            'total_amount' => round((float)$row->total_amount, 2),
        ];
    }

    protected function castPurchaseBill(object $bill): object
    {
        $bill->supplier_id = $bill->supplier_id !== null ? (int)$bill->supplier_id : null;
        $bill->item_count = (int)$bill->item_count;
        $bill->total_qty = (int)$bill->total_qty;
        $bill->total_amount = round((float)$bill->total_amount, 2);
        return $bill;
    }
}

<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RestAPI\v4\admin\Concerns\QueriesPurchaseBills;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    use QueriesPurchaseBills;

    /**
     * Purchase bills (stock-in from suppliers), newest first.
     */
    public function index(Request $request)
    {
        $bills = $this->purchaseBillsQuery(
            fromDate: $request['from_date'],
            toDate: $request['to_date'],
            supplierId: $request['supplier_id'] ? (int)$request['supplier_id'] : null,
            searchValue: $request['searchValue'],
        )
            ->orderByDesc('created_at')
            ->paginate($request['limit'] ?? DEFAULT_DATA_LIMIT);

        $bills->getCollection()->transform(fn($bill) => $this->castPurchaseBill($bill));

        return response()->json($bills, 200);
    }

    /**
     * One purchase bill with its item lines. `reference` is the bill's reference_no
     * (or the synthetic SH-<id> key for single legacy rows without one).
     */
    public function show(string $reference)
    {
        $lines = DB::table('stock_histories')
            ->leftJoin('products', 'products.id', '=', 'stock_histories.product_id')
            ->where('stock_histories.type', 'purchase')
            ->whereRaw($this->purchaseBillKeySql() . ' = ?', [$reference])
            ->orderBy('stock_histories.id')
            ->select([
                'stock_histories.id',
                'stock_histories.product_id',
                'stock_histories.supplier_id',
                'stock_histories.quantity_change as qty',
                'stock_histories.unit_cost',
                'stock_histories.previous_stock',
                'stock_histories.new_stock',
                'stock_histories.note',
                'stock_histories.created_at',
                'products.name as product_name',
                'products.code as product_code',
            ])
            ->get();

        if ($lines->isEmpty()) {
            return response()->json(['errors' => [['code' => 'purchase-001', 'message' => translate('purchase_not_found')]]], 404);
        }

        $supplierId = $lines->first()->supplier_id;
        $supplier = $supplierId ? DB::table('suppliers')->where('id', $supplierId)->first() : null;

        $items = $lines->map(function ($line) {
            $line->qty = (int)$line->qty;
            $line->unit_cost = $line->unit_cost !== null ? (float)$line->unit_cost : null;
            $line->line_total = round($line->qty * (float)($line->unit_cost ?? 0), 2);
            return $line;
        });

        return response()->json([
            'reference_no' => $reference,
            'supplier' => $supplier,
            'created_at' => $lines->min('created_at'),
            'item_count' => $items->count(),
            'total_qty' => $items->sum('qty'),
            'total_amount' => round($items->sum('line_total'), 2),
            'items' => $items->values(),
        ], 200);
    }
}

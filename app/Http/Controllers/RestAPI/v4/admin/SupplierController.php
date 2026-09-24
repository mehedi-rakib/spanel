<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RestAPI\v4\admin\Concerns\QueriesPurchaseBills;
use App\Models\Supplier;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    use QueriesPurchaseBills;

    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->when($request['searchValue'], function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request['searchValue']}%")
                        ->orWhere('shop_name', 'like', "%{$request['searchValue']}%")
                        ->orWhere('phone', 'like', "%{$request['searchValue']}%");
                });
            })
            ->when($request->has('status'), fn($query) => $query->where('status', $request['status']))
            ->orderBy('name')
            ->paginate($request['limit'] ?? DEFAULT_DATA_LIMIT);

        return response()->json($suppliers, 200);
    }

    /**
     * Supplier profile plus all-time purchase totals and the 10 most recent bills.
     */
    public function show(string|int $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['errors' => [['code' => 'supplier-001', 'message' => translate('supplier_not_found')]]], 404);
        }

        $totals = $this->purchaseTotals(supplierId: $supplier->id);
        $recentBills = $this->purchaseBillsQuery(supplierId: $supplier->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn($bill) => $this->castPurchaseBill($bill));

        return response()->json([
            ...$supplier->toArray(),
            'total_purchase_amount' => $totals->total_amount,
            'total_purchase_bills' => $totals->total_bills,
            'recent_bills' => $recentBills,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $supplier = Supplier::create([
            'name' => $request['name'],
            'shop_name' => $request['shop_name'] ?? null,
            'phone' => $request['phone'] ?? null,
            'email' => $request['email'] ?? null,
            'address' => $request['address'] ?? null,
            'status' => 1,
            'admin_id' => $request['admin']->id,
        ]);

        return response()->json($supplier, 201);
    }

    public function update(Request $request, string|int $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['errors' => [['code' => 'supplier-001', 'message' => translate('supplier_not_found')]]], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $supplier->update([
            'name' => $request['name'],
            'shop_name' => $request['shop_name'] ?? null,
            'phone' => $request['phone'] ?? null,
            'email' => $request['email'] ?? null,
            'address' => $request['address'] ?? null,
        ]);

        return response()->json($supplier, 200);
    }

    public function destroy(string|int $id)
    {
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['errors' => [['code' => 'supplier-001', 'message' => translate('supplier_not_found')]]], 404);
        }

        $supplier->delete();
        return response()->json(['message' => translate('supplier_deleted_successfully')], 200);
    }
}

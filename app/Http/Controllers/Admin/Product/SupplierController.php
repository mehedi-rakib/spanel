<?php

namespace App\Http\Controllers\Admin\Product;

use App\Enums\ViewPaths\Admin\Supplier as SupplierView;
use App\Http\Controllers\BaseController;
use App\Models\Supplier;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends BaseController
{
    public function index(Request|null $request, string $type = null): View
    {
        $searchValue = $request['searchValue'];

        $suppliers = Supplier::query()
            ->when($searchValue, function ($query) use ($searchValue) {
                $query->where(function ($query) use ($searchValue) {
                    $query->where('name', 'like', "%{$searchValue}%")
                        ->orWhere('shop_name', 'like', "%{$searchValue}%")
                        ->orWhere('phone', 'like', "%{$searchValue}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(getWebConfig(name: 'pagination_limit'))
            ->appends(['searchValue' => $searchValue]);

        return view(SupplierView::LIST[VIEW], compact('suppliers', 'searchValue'));
    }

    public function add(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $supplier = Supplier::create([
            'name' => $request['name'],
            'shop_name' => $request['shop_name'],
            'phone' => $request['phone'],
            'email' => $request['email'],
            'address' => $request['address'],
            'status' => 1,
            'admin_id' => auth('admin')->id(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'message' => translate('supplier_added_successfully'),
                'supplier' => [
                    'id' => $supplier->id,
                    'text' => $supplier->name . ($supplier->phone ? ' (' . $supplier->phone . ')' : ''),
                ],
            ]);
        }

        Toastr::success(translate('supplier_added_successfully'));
        return back();
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:suppliers,id',
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        Supplier::where('id', $request['id'])->update([
            'name' => $request['name'],
            'shop_name' => $request['shop_name'],
            'phone' => $request['phone'],
            'email' => $request['email'],
            'address' => $request['address'],
        ]);

        Toastr::success(translate('supplier_updated_successfully'));
        return back();
    }

    public function statusUpdate(Request $request): JsonResponse
    {
        Supplier::where('id', $request['id'])->update(['status' => $request['status']]);
        return response()->json(['message' => translate('supplier_status_updated_successfully')], 200);
    }

    public function delete(string|int $id): JsonResponse
    {
        Supplier::where('id', $id)->delete();
        return response()->json(['message' => translate('supplier_deleted_successfully')]);
    }
}

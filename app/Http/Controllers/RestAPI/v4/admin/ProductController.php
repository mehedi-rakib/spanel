<?php

namespace App\Http\Controllers\RestAPI\v4\admin;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Services\StockHistoryService;
use App\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private const EDITABLE_FIELDS = [
        'name', 'code', 'category_id', 'sub_category_id', 'sub_sub_category_id',
        'brand_id', 'unit', 'minimum_order_qty', 'unit_price', 'purchase_price',
        'tax', 'discount', 'discount_type', 'details', 'status',
    ];

    public function __construct(
        private readonly ProductRepositoryInterface     $productRepo,
        private readonly TranslationRepositoryInterface $translationRepo,
        private readonly StockHistoryService            $stockHistoryService,
    )
    {
    }

    public function index(Request $request)
    {
        $filters = array_filter([
            'category_id' => $request['category_id'],
            'sub_category_id' => $request['sub_category_id'],
            'brand_id' => $request['brand_id'],
            'status' => $request['status'],
        ], fn($value) => $value !== null && $value !== '');

        $products = $this->productRepo->getListWhere(
            orderBy: ['id' => 'desc'],
            searchValue: $request['searchValue'],
            filters: $filters,
            dataLimit: $request['limit'] ?? DEFAULT_DATA_LIMIT
        );

        return response()->json($products, 200);
    }

    public function show(string|int $id)
    {
        $product = $this->productRepo->getFirstWhere(params: ['id' => $id], relations: ['category', 'brand', 'translations']);
        if (!$product) {
            return response()->json(['errors' => [['code' => 'product-001', 'message' => translate('product_not_found')]]], 404);
        }
        return response()->json($product, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'code' => 'required|string',
            'category_id' => 'required',
            'unit' => 'required|string',
            'unit_price' => 'required|numeric',
            'current_stock' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $data = [
            'added_by' => 'admin',
            'user_id' => $request['admin']->id,
            'name' => $request['name'],
            'code' => $request['code'],
            'slug' => Str::slug($request['name'], '-') . '-' . Str::random(6),
            'category_ids' => json_encode([['id' => (string)$request['category_id'], 'position' => 1]]),
            'category_id' => $request['category_id'],
            'sub_category_id' => $request['sub_category_id'] ?? 0,
            'sub_sub_category_id' => $request['sub_sub_category_id'] ?? 0,
            'brand_id' => $request['brand_id'] ?? null,
            'unit' => $request['unit'],
            'product_type' => 'physical',
            'minimum_order_qty' => $request['minimum_order_qty'] ?? 1,
            'unit_price' => $request['unit_price'],
            'purchase_price' => $request['purchase_price'] ?? 0,
            'tax' => $request['tax'] ?? 0,
            'tax_type' => 'percent',
            'discount' => $request['discount'] ?? 0,
            'discount_type' => $request['discount_type'] ?? 'percent',
            'current_stock' => $request['current_stock'],
            'details' => $request['details'] ?? null,
            'images' => json_encode(['def.png']),
            'thumbnail' => 'def.png',
            'status' => $request['status'] ?? 1,
            'request_status' => 1,
            'colors' => json_encode([]),
            'attributes' => json_encode([]),
            'choice_options' => json_encode([]),
            'variation' => json_encode([]),
            'video_provider' => 'youtube',
        ];

        $product = $this->productRepo->add(data: $data);

        $this->stockHistoryService->recordInitial(
            productId: $product->id,
            quantity: (int)$request['current_stock'],
            note: translate('initial_stock_from_mobile_app'),
            adminId: $request['admin']->id
        );

        return response()->json($product, 201);
    }

    public function update(Request $request, string|int $id)
    {
        $product = $this->productRepo->getFirstWhere(params: ['id' => $id]);
        if (!$product) {
            return response()->json(['errors' => [['code' => 'product-001', 'message' => translate('product_not_found')]]], 404);
        }

        $data = [];
        foreach (self::EDITABLE_FIELDS as $field) {
            if ($request->has($field)) {
                $data[$field] = $request[$field];
            }
        }
        // current_stock is intentionally excluded here too -- use the stock/purchase
        // endpoints so every change is captured in stock history, same rule as the
        // web admin product-edit form.

        if (count($data) > 0) {
            $this->productRepo->update(id: $id, data: $data);
        }

        $updatedProduct = $this->productRepo->getFirstWhere(params: ['id' => $id]);
        return response()->json($updatedProduct, 200);
    }

    public function destroy(string|int $id)
    {
        $product = $this->productRepo->getFirstWhere(params: ['id' => $id]);
        if (!$product) {
            return response()->json(['errors' => [['code' => 'product-001', 'message' => translate('product_not_found')]]], 404);
        }

        $this->translationRepo->delete(model: 'App\Models\Product', id: $id);
        $this->productRepo->delete(params: ['id' => $id]);

        return response()->json(['message' => translate('product_removed_successfully')], 200);
    }

    public function updateStock(Request $request, string|int $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity_change' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $history = $this->stockHistoryService->recordChange(
            productId: (int)$id,
            type: StockHistoryService::TYPE_ADJUSTMENT,
            quantityChange: (int)$request['quantity_change'],
            note: $request['note'] ?? null,
            adminId: $request['admin']->id,
        );

        if (!$history) {
            return response()->json(['errors' => [['code' => 'product-001', 'message' => translate('product_not_found')]]], 404);
        }

        return response()->json($history, 200);
    }

    public function purchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::validationErrorProcessor($validator)], 403);
        }

        $referenceNo = $request['reference_no'] ?? null;
        $results = [];
        foreach ($request['items'] as $item) {
            $unitCost = isset($item['unit_cost']) && $item['unit_cost'] !== '' ? (float)$item['unit_cost'] : null;

            $history = $this->stockHistoryService->recordChange(
                productId: (int)$item['product_id'],
                type: StockHistoryService::TYPE_PURCHASE,
                quantityChange: (int)$item['qty'],
                unitCost: $unitCost,
                referenceNo: $referenceNo,
                note: $item['note'] ?? null,
                adminId: $request['admin']->id,
            );

            if ($history && $unitCost !== null) {
                $this->productRepo->update(id: $item['product_id'], data: ['purchase_price' => $unitCost]);
            }

            if ($history) {
                $results[] = $history;
            }
        }

        return response()->json(['updated' => count($results), 'items' => $results], 200);
    }
}

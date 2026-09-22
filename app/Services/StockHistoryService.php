<?php

namespace App\Services;

use App\Contracts\Repositories\ProductRepositoryInterface;
use App\Contracts\Repositories\StockHistoryRepositoryInterface;
use App\Models\StockHistory;
use Illuminate\Support\Facades\DB;

class StockHistoryService
{
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_BULK_EDIT = 'bulk_edit';
    public const TYPE_BULK_IMPORT = 'bulk_import';
    public const TYPE_INITIAL_STOCK = 'initial_stock';
    public const TYPE_ORDER = 'order';
    public const TYPE_ORDER_CANCEL = 'order_cancel';
    public const TYPE_RETURN = 'return';

    public function __construct(
        private readonly ProductRepositoryInterface     $productRepo,
        private readonly StockHistoryRepositoryInterface $stockHistoryRepo,
    )
    {
    }

    /**
     * Adjusts a product's stock by a +/- delta and records the movement.
     */
    public function recordChange(
        int     $productId,
        string  $type,
        int     $quantityChange,
        ?float  $unitCost = null,
        ?string $referenceNo = null,
        ?string $note = null,
        ?int    $adminId = null,
        ?int    $supplierId = null,
    ): ?StockHistory
    {
        if ($quantityChange === 0) {
            return null;
        }

        return DB::transaction(function () use ($productId, $type, $quantityChange, $unitCost, $referenceNo, $note, $adminId, $supplierId) {
            $product = $this->productRepo->getFirstWhere(params: ['id' => $productId]);
            if (!$product) {
                return null;
            }

            $previousStock = (int)$product->current_stock;
            $newStock = max(0, $previousStock + $quantityChange);
            $actualChange = $newStock - $previousStock;

            $this->productRepo->update(id: $productId, data: ['current_stock' => $newStock]);

            return $this->stockHistoryRepo->add(data: [
                'product_id' => $productId,
                'supplier_id' => $supplierId,
                'type' => $type,
                'quantity_change' => $actualChange,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'unit_cost' => $unitCost,
                'reference_no' => $referenceNo,
                'note' => $note,
                'admin_id' => $adminId ?? auth('admin')->id(),
            ]);
        });
    }

    /**
     * Logs the starting stock for a product that was just created with that stock value already
     * set (e.g. bulk import) -- writes the history row only, does not touch current_stock again.
     */
    public function recordInitial(int $productId, int $quantity, ?string $note = null, ?int $adminId = null): ?StockHistory
    {
        if ($quantity === 0) {
            return null;
        }

        return $this->stockHistoryRepo->add(data: [
            'product_id' => $productId,
            'type' => self::TYPE_INITIAL_STOCK,
            'quantity_change' => $quantity,
            'previous_stock' => 0,
            'new_stock' => $quantity,
            'reference_no' => null,
            'note' => $note,
            'admin_id' => $adminId ?? auth('admin')->id(),
        ]);
    }

    /**
     * Sets a product's stock to an absolute value (e.g. from a manual stock-count correction)
     * and records the resulting movement.
     */
    public function recordAbsolute(
        int     $productId,
        string  $type,
        int     $newStockValue,
        ?string $referenceNo = null,
        ?string $note = null,
        ?int    $adminId = null,
    ): ?StockHistory
    {
        $product = $this->productRepo->getFirstWhere(params: ['id' => $productId]);
        if (!$product) {
            return null;
        }

        $delta = $newStockValue - (int)$product->current_stock;
        return $this->recordChange(
            productId: $productId,
            type: $type,
            quantityChange: $delta,
            referenceNo: $referenceNo,
            note: $note,
            adminId: $adminId,
        );
    }
}

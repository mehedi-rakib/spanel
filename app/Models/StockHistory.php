<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $supplier_id
 * @property string $type
 * @property int $quantity_change
 * @property int $previous_stock
 * @property int $new_stock
 * @property float|null $unit_cost
 * @property string|null $reference_no
 * @property string|null $note
 * @property int|null $admin_id
 */
class StockHistory extends Model
{
    protected $fillable = [
        'product_id',
        'supplier_id',
        'type',
        'quantity_change',
        'previous_stock',
        'new_stock',
        'unit_cost',
        'reference_no',
        'note',
        'admin_id',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'supplier_id' => 'integer',
        'type' => 'string',
        'quantity_change' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
        'unit_cost' => 'float',
        'reference_no' => 'string',
        'note' => 'string',
        'admin_id' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}

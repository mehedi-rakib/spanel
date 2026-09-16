<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $order_id
 * @property string $type
 * @property float $amount
 * @property float $balance_after
 * @property string|null $note
 * @property int|null $admin_id
 */
class CustomerDueTransaction extends Model
{
    public const TYPE_SALE_DUE = 'sale_due';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'user_id',
        'order_id',
        'type',
        'amount',
        'balance_after',
        'note',
        'admin_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'order_id' => 'integer',
        'type' => 'string',
        'amount' => 'float',
        'balance_after' => 'float',
        'note' => 'string',
        'admin_id' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}

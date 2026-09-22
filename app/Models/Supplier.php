<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $shop_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property int $status
 * @property int|null $admin_id
 */
class Supplier extends Model
{
    protected $fillable = [
        'name',
        'shop_name',
        'phone',
        'email',
        'address',
        'status',
        'admin_id',
    ];

    protected $casts = [
        'status' => 'integer',
        'admin_id' => 'integer',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function stockHistories(): HasMany
    {
        return $this->hasMany(StockHistory::class, 'supplier_id');
    }
}

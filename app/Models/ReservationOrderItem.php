<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRowVersion;
use App\Enums\ReservationOrderItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationOrderItem extends Model
{
    use HasRowVersion;
    protected $table = 'reservation_order_items';
    protected $primaryKey = 'order_item_id';

    protected $fillable = [
        'order_id',
        'item_id',
        'quantity',
        'unit_price',
        'currency',
        'line_total',
        'item_name_snapshot',
        'status',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'order_item_id' => 'int',
        'order_id' => 'int',
        'item_id' => 'int',
        'quantity' => 'int',
        'unit_price' => 'decimal:2',
        'currency' => 'string',
        'line_total' => 'decimal:2',
        'item_name_snapshot' => 'string',
        'status' => ReservationOrderItemStatus::class,
        'notes' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'updated_by' => 'integer',
        'row_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $quantity = max(0, (int) $model->quantity);
            $unitPrice = round((float) $model->unit_price, 2);
            $model->line_total = round($quantity * $unitPrice, 2);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReservationOrder::class, 'order_id', 'order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function scopeNotCancelled($query)
    {
        return $query->where('status', '!=', ReservationOrderItemStatus::Cancelled);
    }
}
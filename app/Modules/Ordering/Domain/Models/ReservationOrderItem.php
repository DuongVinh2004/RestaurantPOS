<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Domain\Models;

use App\Enums\ReservationOrderItemStatus;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Ordering\Application\Services\OrderItemRecipeSnapshotService;
use App\SharedKernel\Money\Money;
use App\Support\Persistence\HasRowVersion;
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
        'recipe_snapshot',
        'status',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'order_item_id' => 'int',
        'order_id' => 'int',
        'item_id' => 'int',
        'quantity' => 'int',
        'unit_price' => 'decimal:0',
        'currency' => 'string',
        'line_total' => 'decimal:0',
        'item_name_snapshot' => 'string',
        'recipe_snapshot' => 'array',
        'status' => ReservationOrderItemStatus::class,
        'notes' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'updated_by' => 'integer',
        'row_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->getAttribute('recipe_snapshot') !== null) {
                return;
            }

            app(OrderItemRecipeSnapshotService::class)->assignSnapshot($model);
        });

        static::saving(function (self $model): void {
            if (
                $model->exists
                && ! $model->isDirty('quantity')
                && ! $model->isDirty('unit_price')
                && $model->getAttribute('line_total') !== null
                && $model->getAttribute('line_total') !== ''
            ) {
                return;
            }

            $quantity = max(0, (int) $model->quantity);
            $unitPriceMinor = Money::minorUnits($model->unit_price);
            $model->line_total = Money::formatMinor($quantity * $unitPriceMinor);
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

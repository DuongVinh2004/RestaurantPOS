<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class MenuItemPrice extends Model
{
    protected $table = 'menu_item_prices';
    protected $primaryKey = 'price_id';

    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'price',
        'currency',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'price_id' => 'int',
        'item_id' => 'int',
        'price' => 'decimal:2',
        'currency' => 'string',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (!$model->item_id || !$model->effective_from) {
                return;
            }

            $start = $model->effective_from instanceof \DateTimeInterface
                ? $model->effective_from->format('Y-m-d H:i:s.u')
                : (string) $model->effective_from;
            $end = $model->effective_to instanceof \DateTimeInterface
                ? $model->effective_to->format('Y-m-d H:i:s.u')
                : ($model->effective_to !== null ? (string) $model->effective_to : null);

            $conflict = self::query()
                ->where('item_id', $model->item_id)
                ->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))
                ->where('effective_from', '<', $end ?? '9999-12-31 23:59:59.999999')
                ->where(function ($q) use ($start) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>', $start);
                })
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'effective_from' => 'Price range overlaps another price for the same item.',
                ]);
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function scopeEffectiveAt($query, \DateTimeInterface $at)
    {
        return $query
            ->where('effective_from', '<=', $at)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            });
    }
}

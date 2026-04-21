<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Models;

use App\Enums\VoucherDiscountType;
use App\Support\Persistence\HasRowVersion;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Voucher extends Model
{
    use HasRowVersion;

    protected $table = 'vouchers';

    protected $primaryKey = 'voucher_id';

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'free_item_id',
        'free_item_qty',
        'max_usage',
        'max_usage_per_user',
        'min_spend',
        'start_date',
        'expiry_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'voucher_id' => 'int',
        'description' => 'string',
        'discount_type' => VoucherDiscountType::class,
        'discount_value' => 'decimal:2',
        'free_item_id' => 'integer',
        'free_item_qty' => 'integer',
        'min_spend' => 'decimal:2',
        'max_usage' => 'int',
        'max_usage_per_user' => 'int',
        'start_date' => 'datetime',
        'expiry_date' => 'datetime',
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'row_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $discountType = $model->discount_type instanceof VoucherDiscountType
                ? $model->discount_type
                : VoucherDiscountType::from((string) $model->discount_type);

            if ($discountType === VoucherDiscountType::FreeItem) {
                $qty = (int) ($model->free_item_qty ?? 0);
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'free_item_qty' => 'FreeItem vouchers must have free_item_qty > 0.',
                    ]);
                }
            }
        });
    }

    public function freeItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'free_item_id', 'item_id');
    }

    public function userVouchers(): HasMany
    {
        return $this->hasMany(UserVoucher::class, 'voucher_id', 'voucher_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_vouchers',
            'voucher_id',
            'user_id',
            'voucher_id',
            'user_id'
        )
            ->using(UserVoucher::class)
            ->withPivot(['user_voucher_id', 'assigned_date', 'is_used', 'used_date']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValidAt($query, \DateTimeInterface $at)
    {
        return $query
            ->where(function ($q) use ($at) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', $at);
            });
    }
}

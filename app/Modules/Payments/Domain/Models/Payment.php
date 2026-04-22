<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use App\Enums\PaymentStatus;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasRowVersion;

    public const IDEMPOTENCY_KEY_MAX_LENGTH = 64;

    protected $table = 'payments';

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'branch_id',
        'reservation_id',
        'refund_of_payment_id',
        'amount',
        'currency',
        'payment_method',
        'payment_provider',
        'payment_type',
        'status',
        'transaction_code',
        'idempotency_key',
        'paid_at',
        'created_by',
        'updated_by',
        'notes',
        'provider_response_json',
    ];

    protected $casts = [
        'payment_id' => 'int',
        'branch_id' => 'int',
        'reservation_id' => 'int',
        'refund_of_payment_id' => 'int',
        'amount' => 'decimal:2',
        'currency' => 'string',
        'payment_method' => 'string',
        'payment_provider' => 'string',
        'payment_type' => 'string',
        'status' => PaymentStatus::class,
        'transaction_code' => 'string',
        'idempotency_key' => 'string',
        'paid_at' => 'datetime',
        'created_by' => 'int',
        'updated_by' => 'integer',
        'notes' => 'string',
        'provider_response_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'row_version' => 'integer',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function refundOfPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_of_payment_id', 'payment_id');
    }

    public function refundPayments(): HasMany
    {
        return $this->hasMany(self::class, 'refund_of_payment_id', 'payment_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function setStatusAttribute($value): void
    {
        if ($value instanceof PaymentStatus) {
            $this->attributes['status'] = $value->value;

            return;
        }

        $normalized = trim((string) $value);

        if ($normalized === 'Paid') {
            $normalized = PaymentStatus::Success->value;
        }

        $this->attributes['status'] = $normalized;
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', PaymentStatus::Success->value);
    }

    public function scopePendingLike($query)
    {
        return $query->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Partial->value]);
    }
}

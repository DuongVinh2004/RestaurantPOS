<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use App\Enums\ReservationBillPaymentSessionStatus;
use App\Enums\ReservationBillPaymentSettlementStatus;
use App\Support\Persistence\HasRowVersion;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationBillPaymentSession extends Model
{
    use HasRowVersion;

    protected $table = 'reservation_bill_payment_sessions';

    protected $primaryKey = 'bill_payment_session_id';

    protected $fillable = [
        'reservation_id',
        'order_id',
        'customer_user_id',
        'linked_payment_id',
        'provider_code',
        'provider_session_code',
        'provider_payment_code',
        'payment_method',
        'amount',
        'currency',
        'session_status',
        'settlement_status',
        'failure_code',
        'failure_message',
        'provider_payload_json',
        'idempotency_key',
        'provider_expires_at',
        'last_reconciled_at',
        'confirmed_at',
        'failed_at',
        'cancelled_at',
        'expired_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'bill_payment_session_id' => 'int',
        'reservation_id' => 'int',
        'order_id' => 'int',
        'customer_user_id' => 'int',
        'linked_payment_id' => 'int',
        'amount' => 'decimal:2',
        'provider_code' => 'string',
        'provider_session_code' => 'string',
        'provider_payment_code' => 'string',
        'payment_method' => 'string',
        'currency' => 'string',
        'session_status' => ReservationBillPaymentSessionStatus::class,
        'settlement_status' => ReservationBillPaymentSettlementStatus::class,
        'failure_code' => 'string',
        'failure_message' => 'string',
        'provider_payload_json' => 'array',
        'idempotency_key' => 'string',
        'provider_expires_at' => 'datetime',
        'last_reconciled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
        'created_by' => 'int',
        'updated_by' => 'int',
        'row_version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ReservationOrder::class, 'order_id', 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id', 'user_id');
    }

    public function linkedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'linked_payment_id', 'payment_id');
    }
}

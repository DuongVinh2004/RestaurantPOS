<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Models;

use App\Casts\LenientDepositStatusCast;
use App\Enums\ReservationDepositIntentStatus;
use App\Enums\ReservationStatus;
use App\Modules\CheckoutPayments\Domain\Models\BillingInvoice;
use App\Models\Concerns\HasRowVersion;
use App\Modules\BenefitsLoyalty\Domain\Models\LoyaltyPointTransaction;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\CheckoutPayments\Domain\Models\ReservationDepositPaymentSession;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Models\User;
use App\Modules\BenefitsLoyalty\Domain\Models\UserVoucher;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasRowVersion;

    protected $table = 'reservations';
    protected $primaryKey = 'reservation_id';

    protected $fillable = [
        'branch_id',
        'user_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'reservation_code',
        'reserved_at',
        'start_time',
        'end_time',
        'guest_count',
        'status',
        'source',
        'checked_in_at',
        'checked_out_at',
        'cancelled_at',
        'cancel_reason',
        'cancelled_by',
        'no_show_at',
        'deposit_required_amount',
        'deposit_paid_amount',
        'deposit_status',
        'deposit_requirement_acknowledged_at',
        'deposit_intent_status',
        'deposit_intent_submitted_at',
        'deposit_intent_revoked_at',
        'applied_user_voucher_id',
        'discount_amount',
        'final_bill_amount',
        'bill_currency',
        'billed_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reservation_id' => 'int',
        'branch_id' => 'int',
        'user_id' => 'int',
        'guest_name' => 'string',
        'guest_phone' => 'string',
        'guest_email' => 'string',
        'reservation_code' => 'string',
        'reserved_at' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'guest_count' => 'int',
        'status' => ReservationStatus::class,
        'source' => 'string',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancel_reason' => 'string',
        'cancelled_by' => 'int',
        'no_show_at' => 'datetime',
        'deposit_required_amount' => 'decimal:2',
        'deposit_paid_amount' => 'decimal:2',
        'deposit_status' => LenientDepositStatusCast::class,
        'deposit_requirement_acknowledged_at' => 'datetime',
        'deposit_intent_status' => ReservationDepositIntentStatus::class,
        'deposit_intent_submitted_at' => 'datetime',
        'deposit_intent_revoked_at' => 'datetime',
        'applied_user_voucher_id' => 'int',
        'discount_amount' => 'decimal:2',
        'final_bill_amount' => 'decimal:2',
        'bill_currency' => 'string',
        'billed_at' => 'datetime',
        'notes' => 'string',
        'created_by' => 'int',
        'updated_by' => 'int',
        'row_version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(
            RestaurantTable::class,
            'reservation_tables',
            'reservation_id',
            'table_id',
            'reservation_id',
            'table_id'
        )
            ->using(ReservationTable::class)
            ->withPivot(['reservation_table_id']);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ReservationOrder::class, 'reservation_id', 'reservation_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'reservation_id', 'reservation_id');
    }

    public function depositPaymentSessions(): HasMany
    {
        return $this->hasMany(ReservationDepositPaymentSession::class, 'reservation_id', 'reservation_id');
    }

    public function appliedUserVoucher(): BelongsTo
    {
        return $this->belongsTo(UserVoucher::class, 'applied_user_voucher_id', 'user_voucher_id');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class, 'reservation_id', 'reservation_id');
    }

    public function billingInvoice(): HasOne
    {
        return $this->hasOne(BillingInvoice::class, 'reservation_id', 'reservation_id');
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('reservation_code', $code);
    }

    public function scopeInTimeRange($query, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        return $query
            ->where('start_time', '<', $to)
            ->where('end_time', '>', $from);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ReservationStatus::activeDbValues());
    }

    public function scopeLatestStart($query)
    {
        return $query->orderByDesc('start_time')->orderByDesc('reservation_id');
    }

    public function hasGuestSnapshot(): bool
    {
        return $this->normalizeCustomerField($this->guest_name) !== null
            || $this->normalizeCustomerField($this->guest_phone) !== null
            || $this->normalizeCustomerField($this->guest_email) !== null;
    }

    public function customerDisplayName(): ?string
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return $this->normalizeCustomerField($user?->full_name ?? $this->guest_name);
    }

    public function customerEmail(): ?string
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return $this->normalizeCustomerField($user?->email ?? $this->guest_email);
    }

    public function customerPhone(): ?string
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return $this->normalizeCustomerField($user?->phone ?? $this->guest_phone);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function guestSnapshot(): ?array
    {
        if (! $this->hasGuestSnapshot()) {
            return null;
        }

        return [
            'full_name' => $this->normalizeCustomerField($this->guest_name),
            'phone' => $this->normalizeCustomerField($this->guest_phone),
            'email' => $this->normalizeCustomerField($this->guest_email),
            'is_snapshot_only' => $this->user_id === null,
        ];
    }

    private function normalizeCustomerField(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}

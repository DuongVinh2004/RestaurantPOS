<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Resources;

use App\Enums\ReservationStatus;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\Modules\Reservations\Application\Services\ReservationDepositReadService;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use App\SharedKernel\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StaffReservationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tables = $this->relationLoaded('tables')
            ? $this->tables->map(static function ($table): array {
                return [
                    'table_id' => (int) $table->table_id,
                    'table_code' => (string) ($table->table_code ?? ('#' . $table->table_id)),
                    'zone' => $table->zone,
                    'status' => $table->status?->value ?? (string) $table->status,
                    'seats' => isset($table->seats) ? (int) $table->seats : null,
                ];
            })->values()->all()
            : [];

        $tableIds = array_values(array_map(static fn (array $table): int => (int) $table['table_id'], $tables));

        $orders = $this->relationLoaded('orders')
            ? $this->orders->map(function ($order): array {
                return [
                    'order_id' => (int) $order->order_id,
                    'order_type' => $order->order_type?->value ?? (string) $order->order_type,
                    'status' => $order->status?->value ?? (string) $order->status,
                    'notes' => $order->notes,
                    'created_at' => $this->iso($order->created_at),
                    'created_by' => $order->created_by,
                    'items' => $order->relationLoaded('items')
                        ? $order->items->map(function ($item): array {
                            return [
                                'order_item_id' => (int) $item->order_item_id,
                                'item_id' => (int) $item->item_id,
                                'quantity' => (int) $item->quantity,
                                'status' => $item->status?->value ?? (string) $item->status,
                                'notes' => $item->notes,
                                'created_at' => $this->iso($item->created_at),
                                'item' => $item->relationLoaded('item') && $item->item
                                    ? [
                                        'item_id' => (int) $item->item->item_id,
                                        'code' => $item->item->code,
                                        'name' => $item->item->name,
                                    ]
                                    : null,
                            ];
                        })->values()->all()
                        : [],
                ];
            })->values()->all()
            : [];

        $payments = $this->relationLoaded('payments')
            ? $this->payments->map(function ($payment): array {
                return [
                    'payment_id' => (int) $payment->payment_id,
                    'refund_of_payment_id' => $payment->refund_of_payment_id !== null ? (int) $payment->refund_of_payment_id : null,
                    'amount' => (string) ($payment->amount ?? '0.00'),
                    'currency' => (string) ($payment->currency ?? 'VND'),
                    'payment_method' => $payment->payment_method,
                    'payment_provider' => $payment->payment_provider,
                    'payment_type' => $payment->payment_type,
                    'status' => $payment->status?->value ?? (string) $payment->status,
                    'paid_at' => $this->iso($payment->paid_at),
                    'created_at' => $this->iso($payment->created_at),
                    'updated_at' => $this->iso($payment->updated_at),
                    'transaction_code' => $payment->transaction_code,
                    'created_by' => $payment->created_by,
                    'notes' => $payment->notes,
                    'refund_target_payment_type' => PaymentSummary::resolveRefundTargetPaymentType($payment),
                    'provider_response_json' => $this->sanitizeProviderResponse($payment->provider_response_json),
                ];
            })->values()->all()
            : null;

        $paymentSummary = $this->relationLoaded('payments')
            ? $this->buildPaymentSummary()
            : null;

        return [
            'reservation_id' => (int) $this->reservation_id,
            'reservation_code' => (string) $this->reservation_code,
            'branch_id' => $this->branch_id !== null ? (int) $this->branch_id : null,
            'access_scope' => ReservationAccessScope::STAFF,
            'api_contract' => [
                'access_scope' => ReservationAccessScope::STAFF,
            ],
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'booking_time' => $this->iso($this->reserved_at ?? $this->created_at),
            'reserved_at' => $this->iso($this->reserved_at),
            'start_time' => $this->iso($this->start_time),
            'end_time' => $this->iso($this->end_time),
            'guest_count' => (int) $this->guest_count,
            'status' => $this->status?->value ?? (string) $this->status,
            'source' => $this->source ?? null,
            'checked_in_at' => $this->iso($this->checked_in_at),
            'checked_out_at' => $this->iso($this->checked_out_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'cancelled_by' => $this->cancelled_by !== null ? (int) $this->cancelled_by : null,
            'cancel_reason' => $this->cancel_reason,
            'no_show_at' => $this->iso($this->no_show_at),
            'deposit_status' => $this->deposit_status ?? null,
            'deposit_required_amount' => $this->formatMoney($this->deposit_required_amount),
            'deposit_paid_amount' => $this->formatMoney($this->deposit_paid_amount),
            'discount_amount' => $this->formatMoney($this->discount_amount),
            'final_bill_amount' => $this->formatMoney($this->final_bill_amount),
            'bill_currency' => $this->bill_currency ?? null,
            'billed_at' => $this->iso($this->billed_at),
            'notes' => $this->notes,
            'row_version' => (int) $this->row_version,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
            'status_flags' => $this->buildStatusFlags(),
            'customer_self_service' => [
                'scope' => ReservationAccessScope::STAFF,
                'can_attempt_cancel' => false,
                'can_attempt_reschedule' => false,
            ],
            'user' => $this->buildCustomerUserPayload(),
            'guest' => $this->buildGuestSnapshotPayload(),
            'table_ids' => $tableIds,
            'table_summary' => $this->buildTableSummary($tables, $tableIds),
            'tables' => $tables,
            'orders' => $orders,
            'payments' => $payments,
            'payment_summary' => $paymentSummary,
            'deposit_summary' => $this->buildDepositSummary(),
            'applied_voucher' => $this->buildAppliedVoucherPayload(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPaymentSummary(): array
    {
        $summary = PaymentSummary::fromPayments($this->payments);
        $currencyMeta = PaymentSummary::summarizeCurrencies($this->payments, (string) ($this->bill_currency ?: 'VND'));

        return [
            'deposit_captured' => Money::format($summary['deposit_captured_amount'] ?? 0, true),
            'deposit_refunded' => Money::format($summary['deposit_refunded_amount'] ?? 0, true),
            'deposit_net' => Money::format($summary['deposit_net_amount'] ?? 0, true),
            'final_captured' => Money::format($summary['final_captured_amount'] ?? 0, true),
            'final_refunded' => Money::format($summary['final_refunded_amount'] ?? 0, true),
            'final_net' => Money::format($summary['final_net_amount'] ?? 0, true),
            'captured_total' => Money::format($summary['captured_amount'] ?? 0, true),
            'refunded_total' => Money::format($summary['refunded_amount'] ?? 0, true),
            'net_paid_total' => Money::format($summary['net_paid_amount'] ?? 0, true),
            'currency' => $currencyMeta['currency'] ?? null,
            'currencies' => $currencyMeta['currencies'],
            'has_mixed_currencies' => (bool) ($currencyMeta['has_mixed_currencies'] ?? false),
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $tables
     * @param  list<int>  $tableIds
     * @return array<string,mixed>
     */
    private function buildTableSummary(array $tables, array $tableIds): array
    {
        return [
            'count' => count($tables),
            'table_ids' => $tableIds,
            'table_codes' => array_values(array_filter(array_map(static fn (array $table): string => (string) ($table['table_code'] ?? ''), $tables))),
            'zones' => array_values(array_unique(array_values(array_filter(array_map(static fn (array $table): ?string => $table['zone'] !== null && $table['zone'] !== '' ? (string) $table['zone'] : null, $tables))))),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildStatusFlags(): array
    {
        $status = (string) ($this->status?->value ?? $this->status ?? '');

        return [
            'is_active' => ReservationStatus::isActiveDbValue($status),
            'is_checked_in' => ReservationStatus::isCheckedInDbValue($status) || $this->checked_in_at !== null,
            'is_cancelled' => $status === ReservationStatus::Cancelled->value,
            'is_completed' => $status === ReservationStatus::Completed->value || $this->checked_out_at !== null,
            'is_no_show' => $status === ReservationStatus::NoShow->value || $this->no_show_at !== null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildCustomerUserPayload(): ?array
    {
        $hasLinkedUser = $this->relationLoaded('user') && $this->user !== null;
        $hasGuestSnapshot = method_exists($this->resource, 'hasGuestSnapshot') && $this->resource->hasGuestSnapshot();

        if (! $hasLinkedUser && ! $hasGuestSnapshot && $this->user_id === null) {
            return null;
        }

        return [
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'full_name' => method_exists($this->resource, 'customerDisplayName')
                ? $this->resource->customerDisplayName()
                : null,
            'email' => method_exists($this->resource, 'customerEmail')
                ? $this->resource->customerEmail()
                : null,
            'phone' => method_exists($this->resource, 'customerPhone')
                ? $this->resource->customerPhone()
                : null,
            'current_points' => $hasLinkedUser && $this->user->relationLoaded('points') && $this->user->points
                ? (int) $this->user->points->total_points
                : null,
            'current_tier' => $hasLinkedUser && $this->user->relationLoaded('currentTier') && $this->user->currentTier
                ? [
                    'tier_id' => (int) $this->user->currentTier->tier_id,
                    'tier_code' => (string) $this->user->currentTier->tier_code,
                    'tier_name' => (string) $this->user->currentTier->tier_name,
                    'min_points' => (int) $this->user->currentTier->min_points,
                ]
                : null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildGuestSnapshotPayload(): ?array
    {
        if (! method_exists($this->resource, 'guestSnapshot')) {
            return null;
        }

        return $this->resource->guestSnapshot();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildDepositSummary(): ?array
    {
        try {
            /** @var ReservationDepositReadService $service */
            $service = app(ReservationDepositReadService::class);

            return $service->buildSnapshot(
                $this->resource,
                $this->relationLoaded('payments') ? $this->payments : null,
                $this->relationLoaded('depositPaymentSessions') ? $this->depositPaymentSessions : null,
                null,
                (string) ($this->bill_currency ?? 'VND'),
                true,
            );
        } catch (\Throwable) {
            return [
                'status' => (string) ($this->deposit_status?->value ?? $this->deposit_status ?? ''),
                'required_amount' => $this->formatMoney($this->deposit_required_amount),
                'paid_amount' => $this->formatMoney($this->deposit_paid_amount),
                'remaining_amount' => $this->formatMoney(max(0.0, (float) ($this->deposit_required_amount ?? 0) - (float) ($this->deposit_paid_amount ?? 0))),
                'outstanding_amount' => $this->formatMoney(max(0.0, (float) ($this->deposit_required_amount ?? 0) - (float) ($this->deposit_paid_amount ?? 0))),
                'status_flags' => [
                    'has_open_payment_session' => false,
                ],
                'payment_session_summary' => [
                    'latest_session' => null,
                ],
            ];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildAppliedVoucherPayload(): ?array
    {
        if (! $this->relationLoaded('appliedUserVoucher') || ! $this->appliedUserVoucher) {
            return null;
        }

        $voucher = $this->appliedUserVoucher->relationLoaded('voucher') ? $this->appliedUserVoucher->voucher : null;

        return [
            'user_voucher_id' => (int) $this->appliedUserVoucher->user_voucher_id,
            'voucher_id' => (int) $this->appliedUserVoucher->voucher_id,
            'voucher_code' => $voucher?->code,
            'description' => $voucher?->description,
            'discount_type' => $voucher?->discount_type?->value ?? (string) ($voucher?->discount_type ?? ''),
            'discount_value' => $voucher?->discount_value !== null ? (string) $voucher->discount_value : null,
            'is_used' => (bool) ($this->appliedUserVoucher->is_used ?? false),
            'used_reservation_id' => $this->appliedUserVoucher->used_reservation_id !== null ? (int) $this->appliedUserVoucher->used_reservation_id : null,
            'used_amount' => $this->appliedUserVoucher->used_amount !== null ? (string) $this->appliedUserVoucher->used_amount : null,
            'locked_until' => $this->iso($this->appliedUserVoucher->locked_until),
        ];
    }

    private function formatMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::format($value, true);
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }

    private function sanitizeProviderResponse(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->sanitizeProviderResponse($item), $value);
        }

        if (is_object($value)) {
            return $this->sanitizeProviderResponse((array) $value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }
}

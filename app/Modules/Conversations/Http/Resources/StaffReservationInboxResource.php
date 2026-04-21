<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Resources;

use App\Enums\ReservationStatus;
use App\Modules\Payments\Application\Queries\StaffReservationDepositOperationalReadService;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StaffReservationInboxResource extends JsonResource
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
                    'table_code' => (string) $table->table_code,
                    'zone' => $table->zone,
                    'status' => $table->status?->value ?? (string) $table->status,
                    'seats' => isset($table->seats) ? (int) $table->seats : null,
                ];
            })->values()->all()
            : [];

        $user = $this->buildCustomerPayload();
        $guest = method_exists($this->resource, 'guestSnapshot') ? $this->resource->guestSnapshot() : null;

        $paymentSummary = $this->relationLoaded('payments')
            ? PaymentSummary::fromPayments($this->payments)
            : null;

        $depositSelfService = app(StaffReservationDepositOperationalReadService::class)->build(
            $this->resource,
            $paymentSummary ?? [],
        );

        $financials = null;
        if ($this->relationLoaded('payments') || $this->relationLoaded('appliedUserVoucher')) {
            $voucher = $this->relationLoaded('appliedUserVoucher') && $this->appliedUserVoucher !== null
                ? ($this->appliedUserVoucher->relationLoaded('voucher') ? $this->appliedUserVoucher->voucher : null)
                : null;

            $financials = [
                'deposit_status' => $this->deposit_status?->value ?? (string) ($this->deposit_status ?? ''),
                'deposit_required_amount' => $this->deposit_required_amount !== null ? (string) $this->deposit_required_amount : null,
                'deposit_paid_amount' => $this->deposit_paid_amount !== null ? (string) $this->deposit_paid_amount : null,
                'discount_amount' => $this->discount_amount !== null ? (string) $this->discount_amount : null,
                'final_bill_amount' => $this->final_bill_amount !== null ? (string) $this->final_bill_amount : null,
                'bill_currency' => $this->bill_currency,
                'billed_at' => $this->iso($this->billed_at),
                'applied_voucher' => $voucher ? [
                    'voucher_id' => (int) $voucher->voucher_id,
                    'voucher_code' => (string) $voucher->code,
                    'description' => $voucher->description,
                ] : null,
                'payment_summary' => $paymentSummary ? [
                    'captured_total' => \App\SharedKernel\Money\Money::format($paymentSummary['captured_amount'] ?? 0, true),
                    'refunded_total' => \App\SharedKernel\Money\Money::format($paymentSummary['refunded_amount'] ?? 0, true),
                    'net_paid_total' => \App\SharedKernel\Money\Money::format($paymentSummary['net_paid_amount'] ?? 0, true),
                    'deposit_net' => \App\SharedKernel\Money\Money::format($paymentSummary['deposit_net_amount'] ?? 0, true),
                    'final_net' => \App\SharedKernel\Money\Money::format($paymentSummary['final_net_amount'] ?? 0, true),
                ] : null,
            ];
        }

        $status = $this->status?->value ?? (string) $this->status;

        return [
            'reservation_id' => (int) $this->reservation_id,
            'reservation_code' => (string) $this->reservation_code,
            'status' => $status,
            'source' => $this->source,
            'guest_count' => (int) $this->guest_count,
            'start_time' => $this->iso($this->start_time),
            'end_time' => $this->iso($this->end_time),
            'checked_in_at' => $this->iso($this->checked_in_at),
            'checked_out_at' => $this->iso($this->checked_out_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'cancel_reason' => $this->cancel_reason,
            'no_show_at' => $this->iso($this->no_show_at),
            'notes' => $this->notes,
            'row_version' => (int) $this->row_version,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
            'user' => $user,
            'guest' => $guest,
            'table_ids' => array_values(array_map(static fn (array $table): int => (int) $table['table_id'], $tables)),
            'tables' => $tables,
            'summary' => [
                'table_count' => count($tables),
                'is_active' => ReservationStatus::isActiveDbValue($status),
                'is_checked_in' => ReservationStatus::isCheckedInDbValue($status),
                'is_cancelled' => $status === ReservationStatus::Cancelled->value,
                'is_completed' => $status === ReservationStatus::Completed->value,
                'deposit_acknowledged' => (bool) ($depositSelfService['requirement_acknowledged'] ?? false),
                'deposit_intent_submitted' => (bool) data_get($depositSelfService, 'flags.intent_submitted', false),
                'deposit_self_service_follow_up' => (bool) data_get($depositSelfService, 'follow_up.needs_staff_follow_up', false),
            ],
            'deposit_self_service' => $depositSelfService,
            'financials' => $financials,
        ];
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

    /**
     * @return array<string,mixed>|null
     */
    private function buildCustomerPayload(): ?array
    {
        $hasLinkedUser = $this->relationLoaded('user') && $this->user !== null;
        $hasGuestSnapshot = method_exists($this->resource, 'hasGuestSnapshot') && $this->resource->hasGuestSnapshot();

        if (! $hasLinkedUser && ! $hasGuestSnapshot && $this->user_id === null) {
            return null;
        }

        return [
            'user_id' => $this->user_id !== null ? (int) $this->user_id : null,
            'full_name' => method_exists($this->resource, 'customerDisplayName') ? $this->resource->customerDisplayName() : null,
            'email' => method_exists($this->resource, 'customerEmail') ? $this->resource->customerEmail() : null,
            'phone' => method_exists($this->resource, 'customerPhone') ? $this->resource->customerPhone() : null,
        ];
    }
}

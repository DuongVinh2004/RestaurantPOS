<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Resources;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\BranchScheduling\Http\Resources\Guest\RestaurantTableResource;
use App\Modules\Payments\Infrastructure\Internal\PaymentProviderPayloadSanitizer;
use App\Modules\Reservations\Application\Services\ReservationDepositReadService;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use App\Modules\Billing\Domain\ValueObjects\PaymentSummary;
use App\SharedKernel\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $accessScope = ReservationAccessScope::resolve($request);
        $isStaff = $accessScope === ReservationAccessScope::STAFF;
        $canViewIdentity = ReservationAccessScope::canViewIdentity($accessScope);
        $canViewDisplayName = ReservationAccessScope::canViewDisplayName($accessScope);
        $canViewContact = ReservationAccessScope::canViewContact($accessScope);
        $canViewLoyalty = ReservationAccessScope::canViewLoyalty($accessScope);
        $canViewFinancials = ReservationAccessScope::canViewFinancials($accessScope);
        $canViewVoucherDetails = ReservationAccessScope::canViewVoucherDetails($accessScope);

        $tables = $this->relationLoaded('tables')
            ? RestaurantTableResource::collection($this->tables)->resolve($request)
            : [];

        $tableIds = $this->relationLoaded('tables')
            ? $this->tables->pluck('table_id')->map(fn ($id) => (int) $id)->values()->all()
            : [];

        $tableSummary = $this->buildTableSummary();
        $customerSelfService = $this->buildCustomerSelfServiceState($request, $accessScope);
        $statusFlags = $this->buildStatusFlags();

        $orders = $this->relationLoaded('orders')
            ? $this->orders->map(function ($order) {
                return [
                    'order_id' => (int) $order->order_id,
                    'order_type' => $order->order_type?->value ?? (string) $order->order_type,
                    'status' => $order->status?->value ?? (string) $order->status,
                    'notes' => $order->notes,
                    'created_at' => $this->iso($order->created_at),
                    'created_by' => $order->created_by,
                    'items' => $order->relationLoaded('items')
                        ? $order->items->map(function ($it) {
                            return [
                                'order_item_id' => (int) $it->order_item_id,
                                'item_id' => (int) $it->item_id,
                                'quantity' => (int) $it->quantity,
                                'status' => $it->status?->value ?? (string) $it->status,
                                'notes' => $it->notes,
                                'created_at' => $this->iso($it->created_at),
                                'item' => $it->relationLoaded('item') && $it->item
                                    ? [
                                        'item_id' => (int) $it->item->item_id,
                                        'code' => $it->item->code,
                                        'name' => $it->item->name,
                                    ]
                                    : null,
                            ];
                        })->values()->all()
                        : [],
                ];
            })->values()->all()
            : [];

        $payments = null;
        $paymentSummary = null;

        if ($canViewFinancials) {
            $payments = $this->relationLoaded('payments')
                ? $this->payments->map(function ($p) use ($isStaff) {
                    $data = [
                        'payment_id' => (int) $p->payment_id,
                        'refund_of_payment_id' => $p->refund_of_payment_id !== null ? (int) $p->refund_of_payment_id : null,
                        'amount' => (string) ($p->amount ?? '0.00'),
                        'currency' => (string) ($p->currency ?? 'VND'),
                        'payment_method' => $p->payment_method,
                        'payment_provider' => $p->payment_provider,
                        'payment_type' => $p->payment_type,
                        'status' => $p->status?->value ?? (string) $p->status,
                        'paid_at' => $this->iso($p->paid_at),
                        'created_at' => $this->iso($p->created_at),
                        'updated_at' => $this->iso($p->updated_at),
                    ];

                    if ($isStaff) {
                        $data['transaction_code'] = $p->transaction_code;
                        $data['created_by'] = $p->created_by;
                        $data['notes'] = $p->notes;
                        $data['refund_target_payment_type'] = PaymentSummary::resolveRefundTargetPaymentType($p);
                        $data['provider_response_json'] = PaymentProviderPayloadSanitizer::sanitizePaymentResponseForPresentation($p->provider_response_json);
                    }

                    return $data;
                })->values()->all()
                : [];

            $paymentSummary = $this->relationLoaded('payments')
                ? $this->buildPaymentSummary()
                : $this->emptyPaymentSummary();
        }

        $user = $this->buildCustomerUserPayload($canViewIdentity, $canViewDisplayName, $canViewContact, $canViewLoyalty);
        $guest = $isStaff ? $this->buildGuestSnapshotPayload() : null;

        $appliedVoucher = null;
        if ($canViewVoucherDetails) {
            $appliedVoucher = $this->relationLoaded('appliedUserVoucher')
                ? $this->buildAppliedVoucherPayload()
                : null;
        }

        $depositSummary = $canViewFinancials ? $this->buildDepositSummary($accessScope) : null;

        return [
            'reservation_id' => (int) $this->reservation_id,
            'reservation_code' => $this->reservation_code,
            'branch_id' => $this->branch_id !== null ? (int) $this->branch_id : null,
            'access_scope' => $accessScope,
            'api_contract' => [
                'access_scope' => $accessScope,
            ],
            'user_id' => $canViewIdentity && $this->user_id !== null ? (int) $this->user_id : null,
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
            'deposit_status' => $canViewFinancials ? ($this->deposit_status ?? null) : null,
            'deposit_required_amount' => $canViewFinancials ? $this->formatMoney($this->deposit_required_amount) : null,
            'deposit_paid_amount' => $canViewFinancials ? $this->formatMoney($this->deposit_paid_amount) : null,
            'discount_amount' => $canViewFinancials ? $this->formatMoney($this->discount_amount) : null,
            'final_bill_amount' => $canViewFinancials ? $this->formatMoney($this->final_bill_amount) : null,
            'bill_currency' => $canViewFinancials ? ($this->bill_currency ?? null) : null,
            'billed_at' => $canViewFinancials ? $this->iso($this->billed_at) : null,
            'notes' => $this->notes,
            'row_version' => (int) $this->row_version,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
            'status_flags' => $statusFlags,
            'customer_self_service' => $customerSelfService,
            'user' => $user,
            'guest' => $guest,
            'table_ids' => $tableIds,
            'table_summary' => $tableSummary,
            'tables' => $tables,
            'orders' => $orders,
            'payments' => $payments,
            'payment_summary' => $paymentSummary,
            'deposit_summary' => $depositSummary,
            'applied_voucher' => $appliedVoucher,
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
     * @return array<string,mixed>
     */
    private function emptyPaymentSummary(): array
    {
        return [
            'deposit_captured' => Money::format(0, true),
            'deposit_refunded' => Money::format(0, true),
            'deposit_net' => Money::format(0, true),
            'final_captured' => Money::format(0, true),
            'final_refunded' => Money::format(0, true),
            'final_net' => Money::format(0, true),
            'captured_total' => Money::format(0, true),
            'refunded_total' => Money::format(0, true),
            'net_paid_total' => Money::format(0, true),
            'currency' => $this->bill_currency ?? null,
            'currencies' => [],
            'has_mixed_currencies' => false,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildAppliedVoucherPayload(): ?array
    {
        if (! $this->appliedUserVoucher) {
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


    /**
     * @return array<string,mixed>
     */
    private function buildTableSummary(): array
    {
        if (! $this->relationLoaded('tables')) {
            return [
                'count' => 0,
                'table_ids' => [],
                'table_codes' => [],
                'zones' => [],
            ];
        }

        $tables = collect($this->tables);

        return [
            'count' => $tables->count(),
            'table_ids' => $tables->pluck('table_id')->filter()->map(fn ($id) => (int) $id)->values()->all(),
            'table_codes' => $tables->map(fn ($table) => (string) ($table->table_code ?? ('#' . $table->table_id)))->values()->all(),
            'zones' => $tables->pluck('zone')->filter(fn ($zone) => $zone !== null && $zone !== '')->map(fn ($zone) => (string) $zone)->unique()->values()->all(),
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
     * @return array<string,mixed>
     */
    private function buildCustomerSelfServiceState(Request $request, string $accessScope): array
    {
        $status = (string) ($this->status?->value ?? $this->status ?? '');
        $startTime = $this->start_time instanceof \DateTimeInterface
            ? Carbon::instance($this->start_time)->utc()
            : ($this->start_time ? Carbon::parse((string) $this->start_time)->utc() : null);
        $now = Carbon::now('UTC');
        $cancelCutoffMinutes = $this->resolveBranchCutoffMinutes(
            $request,
            'cancellation',
            max(0, (int) config('booking.customer_reservation_cancellation_cutoff_minutes', 30)),
        );
        $rescheduleCutoffMinutes = $this->resolveBranchCutoffMinutes(
            $request,
            'reschedule',
            max(0, (int) config('booking.customer_reservation_reschedule_cutoff_minutes', 120)),
        );

        $canAttemptCancel = $accessScope !== ReservationAccessScope::STAFF
            && $status === ReservationStatus::Confirmed->value
            && $this->checked_in_at === null
            && ($startTime === null || $now->lt($startTime->copy()->subMinutes($cancelCutoffMinutes)));

        $canAttemptReschedule = $accessScope !== ReservationAccessScope::STAFF
            && $status === ReservationStatus::Confirmed->value
            && $this->checked_in_at === null
            && ($startTime === null || $now->lt($startTime->copy()->subMinutes($rescheduleCutoffMinutes)));

        return [
            'scope' => $accessScope,
            'can_attempt_cancel' => $canAttemptCancel,
            'can_attempt_reschedule' => $canAttemptReschedule,
        ];
    }

    private function resolveBranchCutoffMinutes(Request $request, string $action, int $defaultMinutes): int
    {
        $branchId = $this->branch_id !== null ? (int) $this->branch_id : 0;
        if ($branchId <= 0) {
            return $defaultMinutes;
        }

        $cacheKey = sprintf('reservation_resource.cutoff.%s.%d', $action, $branchId);
        if ($request->attributes->has($cacheKey)) {
            return (int) $request->attributes->get($cacheKey);
        }

        try {
            $branchSchedulingPolicyService = app(BranchSchedulingPolicyService::class);
            $resolvedMinutes = match ($action) {
                'cancellation' => max(0, $branchSchedulingPolicyService->customerCancellationCutoffMinutes($branchId, false)),
                'reschedule' => max(0, $branchSchedulingPolicyService->customerRescheduleCutoffMinutes($branchId, false)),
                default => $defaultMinutes,
            };
        } catch (\Throwable) {
            // Keep resource serialization resilient in unit tests that do not boot booking schema.
            $resolvedMinutes = $defaultMinutes;
        }

        $request->attributes->set($cacheKey, $resolvedMinutes);

        return $resolvedMinutes;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildDepositSummary(string $accessScope): ?array
    {
        try {
            /** @var ReservationDepositReadService $service */
            $service = app(ReservationDepositReadService::class);

            $snapshot = $service->buildSnapshot(
                $this->resource,
                $this->relationLoaded('payments') ? $this->payments : null,
                $this->relationLoaded('depositPaymentSessions') ? $this->depositPaymentSessions : null,
                null,
                (string) ($this->bill_currency ?? 'VND'),
                true,
            );

            return $this->presentCustomerFacingDepositSummary($snapshot, $accessScope);
        } catch (\Throwable) {
            $fallback = [
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

            return $this->presentCustomerFacingDepositSummary($fallback, $accessScope);
        }
    }

    /**
     * @param array<string,mixed> $summary
     * @return array<string,mixed>
     */
    private function presentCustomerFacingDepositSummary(array $summary, string $accessScope): array
    {
        if ($accessScope === ReservationAccessScope::STAFF) {
            return $summary;
        }

        $status = (string) ($summary['status'] ?? '');
        $requiredAmountMinor = Money::minorUnits($summary['required_amount'] ?? 0, true);
        $outstandingAmountMinor = Money::minorUnits($summary['outstanding_amount'] ?? 0, true);

        if ($status === 'Pending' && $requiredAmountMinor > 0 && $outstandingAmountMinor > 0) {
            $summary['status'] = 'Required';
        }

        return $summary;
    }

    private function buildCustomerUserPayload(
        bool $canViewIdentity,
        bool $canViewDisplayName,
        bool $canViewContact,
        bool $canViewLoyalty,
    ): ?array {
        $hasLinkedUser = $this->relationLoaded('user') && $this->user !== null;
        $hasGuestSnapshot = method_exists($this->resource, 'hasGuestSnapshot') && $this->resource->hasGuestSnapshot();

        if (! $hasLinkedUser && ! $hasGuestSnapshot && $this->user_id === null) {
            return null;
        }

        return [
            'user_id' => $canViewIdentity && $this->user_id !== null ? (int) $this->user_id : null,
            'full_name' => $canViewDisplayName && method_exists($this->resource, 'customerDisplayName')
                ? $this->resource->customerDisplayName()
                : null,
            'email' => $canViewContact && method_exists($this->resource, 'customerEmail')
                ? $this->resource->customerEmail()
                : null,
            'phone' => $canViewContact && method_exists($this->resource, 'customerPhone')
                ? $this->resource->customerPhone()
                : null,
            'current_points' => $canViewLoyalty && $hasLinkedUser && $this->user->relationLoaded('points') && $this->user->points
                ? (int) $this->user->points->total_points
                : null,
            'current_tier' => $canViewLoyalty && $hasLinkedUser && $this->user->relationLoaded('currentTier') && $this->user->currentTier
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
}

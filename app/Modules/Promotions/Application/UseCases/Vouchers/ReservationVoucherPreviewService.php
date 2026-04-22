<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\UseCases\Vouchers;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Promotions\Domain\Models\UserVoucher;
use App\Modules\Promotions\Domain\Models\Voucher;
use App\Modules\Promotions\Domain\Policies\VoucherRedemptionSupport;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReservationVoucherPreviewService
{
    /**
     * @return list<array<string,mixed>>
     */
    public function listVoucherOptions(Reservation $reservation): array
    {
        $reservation->loadMissing(['user', 'appliedUserVoucher.voucher']);

        $orders = $this->loadOrdersForReservation((int) $reservation->reservation_id);
        $voucherRows = UserVoucher::query()
            ->with(['voucher.freeItem'])
            ->where('user_id', (int) $reservation->user_id)
            ->orderBy('user_voucher_id')
            ->get();

        $aggregates = $this->buildApplicabilityAggregates($reservation, $voucherRows);

        return $voucherRows
            ->map(fn (UserVoucher $userVoucher) => $this->describeVoucherOption($reservation, $orders, $userVoucher, $aggregates))
            ->values()
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    public function describeVoucherOption(Reservation $reservation, Collection $orders, UserVoucher $userVoucher, ?array $aggregates = null): array
    {
        /** @var Voucher|null $voucher */
        $voucher = $userVoucher->voucher;
        $applicability = $this->evaluateVoucherApplicability($reservation, $orders, $userVoucher, $aggregates);
        $now = Carbon::now('UTC');
        $lockToken = trim((string) ($userVoucher->lock_token ?? ''));
        $reservationLockToken = $this->buildReservationLockToken((int) $reservation->reservation_id);
        $isLockedByOther = $lockToken !== ''
            && $lockToken !== $reservationLockToken
            && $userVoucher->locked_until !== null
            && $userVoucher->locked_until->greaterThan($now);

        return [
            'user_voucher_id' => (int) $userVoucher->user_voucher_id,
            'voucher_id' => (int) ($userVoucher->voucher_id ?? 0),
            'voucher_code' => (string) ($voucher?->code ?? ''),
            'description' => (string) ($voucher?->description ?? ''),
            'discount_type' => $voucher?->discount_type?->value ?? (string) ($voucher?->discount_type ?? ''),
            'discount_value' => $voucher?->discount_value !== null ? number_format((float) $voucher->discount_value, 2, '.', '') : null,
            'min_spend' => $voucher?->min_spend !== null ? Money::format($voucher->min_spend, true) : null,
            'free_item' => $voucher && (int) ($voucher->free_item_id ?? 0) > 0 ? [
                'item_id' => (int) $voucher->free_item_id,
                'quantity' => max(1, (int) ($voucher->free_item_qty ?? 1)),
                'item_name' => (string) ($voucher->freeItem?->name ?? ''),
            ] : null,
            'assigned_at' => $userVoucher->assigned_date?->utc()->toIso8601String(),
            'used_at' => $userVoucher->used_date?->utc()->toIso8601String(),
            'used_reservation_id' => $userVoucher->used_reservation_id !== null ? (int) $userVoucher->used_reservation_id : null,
            'is_used' => (bool) ($userVoucher->is_used ?? false),
            'current_status' => $this->resolveVoucherStatus($userVoucher),
            'is_locked_by_other' => $isLockedByOther,
            'locked_until' => $userVoucher->locked_until?->utc()->toIso8601String(),
            'is_currently_applied' => (int) ($reservation->applied_user_voucher_id ?? 0) === (int) $userVoucher->user_voucher_id,
            'preview_discount_amount' => Money::format($applicability['discount_amount'] ?? 0, true),
            'preview_subtotal_amount' => Money::format($applicability['subtotal'] ?? 0, true),
            'preview_currency' => (string) ($applicability['currency'] ?? 'VND'),
            'can_apply' => (bool) ($applicability['can_apply'] ?? false),
            'applicability_reason_codes' => array_values($applicability['reason_codes'] ?? []),
            'applicability_reasons' => array_values($applicability['reasons'] ?? []),
        ];
    }

    /**
     * @return array{can_apply:bool,reason_codes:list<string>,reasons:list<string>,discount_amount:float,subtotal:float,currency:string,other_active_reservation_id:?int}
     */
    public function evaluateVoucherApplicability(Reservation $reservation, Collection $orders, UserVoucher $userVoucher, ?array $aggregates = null): array
    {
        $reasonCodes = [];
        $reasons = [];
        $now = Carbon::now('UTC');
        $aggregates ??= $this->buildApplicabilityAggregates($reservation, collect([$userVoucher]));

        /** @var Voucher|null $voucher */
        $voucher = $userVoucher->voucher;
        if (! $voucher instanceof Voucher) {
            return [
                'can_apply' => false,
                'reason_codes' => ['voucher_missing'],
                'reasons' => ['Voucher data is missing.'],
                'discount_amount' => 0.0,
                'subtotal' => 0.0,
                'currency' => 'VND',
                'other_active_reservation_id' => null,
            ];
        }

        $preview = VoucherRedemptionSupport::calculateDiscount($voucher, $orders);
        $discountAmountMinor = Money::minorUnits($preview['discount_amount'] ?? 0, true);
        $subtotalMinor = Money::minorUnits($preview['subtotal'] ?? 0, true);
        $currency = (string) ($preview['currency'] ?? 'VND');

        $reservationStatus = (string) ($reservation->status?->value ?? $reservation->status);
        if (! in_array($reservationStatus, ReservationStatus::activeDbValues(), true)) {
            $reasonCodes[] = 'reservation_inactive';
            $reasons[] = 'Voucher can only be applied to active reservations.';
        }

        if (! (bool) ($voucher->is_active ?? false)) {
            $reasonCodes[] = 'inactive';
            $reasons[] = 'Voucher is inactive.';
        }

        if ($voucher->start_date && $voucher->start_date->greaterThan($now)) {
            $reasonCodes[] = 'not_active_yet';
            $reasons[] = 'Voucher is not active yet.';
        }

        if ($voucher->expiry_date && $voucher->expiry_date->lessThan($now)) {
            $reasonCodes[] = 'expired';
            $reasons[] = 'Voucher has expired.';
        }

        if ((bool) ($userVoucher->is_used ?? false)) {
            $reasonCodes[] = 'already_used';
            $reasons[] = 'Voucher has already been used.';
        }

        $lockToken = trim((string) ($userVoucher->lock_token ?? ''));
        $reservationLockToken = $this->buildReservationLockToken((int) $reservation->reservation_id);
        if ($lockToken !== '' && $lockToken !== $reservationLockToken && $userVoucher->locked_until && $userVoucher->locked_until->greaterThan($now)) {
            $reasonCodes[] = 'locked_by_other';
            $reasons[] = 'Voucher is currently locked by another reservation.';
        }

        $otherActiveReservationId = $this->lookupAggregateValue($aggregates['other_active_reservation_ids_by_user_voucher'] ?? [], (int) $userVoucher->user_voucher_id);
        if ($otherActiveReservationId !== null) {
            $reasonCodes[] = 'applied_elsewhere';
            $reasons[] = 'Voucher is already attached to another active reservation.';
        }

        $usedCount = $this->lookupAggregateValue($aggregates['used_count_by_voucher'] ?? [], (int) $voucher->voucher_id, 0);
        $maxUsage = $voucher->max_usage !== null ? (int) $voucher->max_usage : null;
        if ($maxUsage !== null && $usedCount >= $maxUsage) {
            $reasonCodes[] = 'max_usage_reached';
            $reasons[] = 'Voucher usage limit has been reached.';
        }

        $maxUsagePerUser = $voucher->max_usage_per_user !== null ? (int) $voucher->max_usage_per_user : null;
        if ($maxUsagePerUser !== null) {
            $usedCountByUser = $this->lookupAggregateValue($aggregates['used_count_by_voucher_for_user'] ?? [], (int) $voucher->voucher_id, 0);
            if ($usedCountByUser >= $maxUsagePerUser) {
                $reasonCodes[] = 'per_user_limit_reached';
                $reasons[] = 'Voucher per-user usage limit has been reached.';
            }
        }

        $minSpendMinor = Money::minorUnits($voucher->min_spend ?? 0, true);
        if ($subtotalMinor < $minSpendMinor) {
            $reasonCodes[] = 'min_spend_not_met';
            $reasons[] = sprintf('Voucher requires minimum spend %s.', Money::formatMinor($minSpendMinor));
        }

        if ($discountAmountMinor <= 0) {
            $reasonCodes[] = 'not_applicable_to_items';
            $reasons[] = 'Voucher is not applicable to current reservation items.';
        }

        return [
            'can_apply' => $reasonCodes === [],
            'reason_codes' => $reasonCodes,
            'reasons' => $reasons,
            'discount_amount' => Money::minorToFloat($discountAmountMinor),
            'subtotal' => Money::minorToFloat($subtotalMinor),
            'currency' => $currency,
            'other_active_reservation_id' => $otherActiveReservationId,
        ];
    }

    /**
     * @return Collection<int,ReservationOrder>
     */
    private function loadOrdersForReservation(int $reservationId): Collection
    {
        /** @var Collection<int,ReservationOrder> $orders */
        $orders = ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->with('items')
            ->orderBy('order_id')
            ->get();

        return $orders;
    }

    /**
     * @param  Collection<int,UserVoucher>  $voucherRows
     * @return array<string,array<int,int>>
     */
    private function buildApplicabilityAggregates(Reservation $reservation, Collection $voucherRows): array
    {
        $voucherIds = $voucherRows
            ->pluck('voucher_id')
            ->map(static fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        $userVoucherIds = $voucherRows
            ->pluck('user_voucher_id')
            ->map(static fn ($value) => (int) $value)
            ->filter(static fn (int $value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        $usedCountByVoucher = [];
        if ($voucherIds !== []) {
            $usedCountByVoucher = UserVoucher::query()
                ->selectRaw('voucher_id, COUNT(*) as aggregate_count')
                ->whereIn('voucher_id', $voucherIds)
                ->where('is_used', 1)
                ->groupBy('voucher_id')
                ->pluck('aggregate_count', 'voucher_id')
                ->mapWithKeys(static fn ($count, $voucherId) => [(int) $voucherId => (int) $count])
                ->all();
        }

        $usedCountByVoucherForUser = [];
        if ($voucherIds !== []) {
            $usedCountByVoucherForUser = UserVoucher::query()
                ->selectRaw('voucher_id, COUNT(*) as aggregate_count')
                ->whereIn('voucher_id', $voucherIds)
                ->where('user_id', (int) $reservation->user_id)
                ->where('is_used', 1)
                ->groupBy('voucher_id')
                ->pluck('aggregate_count', 'voucher_id')
                ->mapWithKeys(static fn ($count, $voucherId) => [(int) $voucherId => (int) $count])
                ->all();
        }

        $otherActiveReservationIdsByUserVoucher = [];
        if ($userVoucherIds !== []) {
            $otherActiveReservationIdsByUserVoucher = Reservation::query()
                ->select(['applied_user_voucher_id', 'reservation_id'])
                ->whereIn('applied_user_voucher_id', $userVoucherIds)
                ->where('reservation_id', '!=', (int) $reservation->reservation_id)
                ->whereIn('status', ReservationStatus::activeDbValues())
                ->orderBy('reservation_id')
                ->get()
                ->groupBy('applied_user_voucher_id')
                ->mapWithKeys(static function ($rows, $userVoucherId) {
                    $first = $rows->first();

                    return [(int) $userVoucherId => $first ? (int) $first->reservation_id : null];
                })
                ->all();
        }

        return [
            'used_count_by_voucher' => $usedCountByVoucher,
            'used_count_by_voucher_for_user' => $usedCountByVoucherForUser,
            'other_active_reservation_ids_by_user_voucher' => $otherActiveReservationIdsByUserVoucher,
        ];
    }

    private function lookupAggregateValue(array $map, int $key, ?int $default = null): ?int
    {
        if (! array_key_exists($key, $map)) {
            return $default;
        }

        return $map[$key] !== null ? (int) $map[$key] : null;
    }

    private function buildReservationLockToken(int $reservationId): string
    {
        return 'reservation:'.$reservationId;
    }

    private function resolveVoucherStatus(UserVoucher $userVoucher): string
    {
        /** @var Voucher|null $voucher */
        $voucher = $userVoucher->voucher;
        $now = Carbon::now('UTC');

        if ((bool) ($userVoucher->is_used ?? false)) {
            return 'Used';
        }

        if (! $voucher instanceof Voucher) {
            return 'Unavailable';
        }

        if (! (bool) ($voucher->is_active ?? false)) {
            return 'Inactive';
        }

        if ($voucher->start_date && $voucher->start_date->greaterThan($now)) {
            return 'Scheduled';
        }

        if ($voucher->expiry_date && $voucher->expiry_date->lessThan($now)) {
            return 'Expired';
        }

        if ($userVoucher->locked_until && $userVoucher->locked_until->greaterThan($now) && trim((string) ($userVoucher->lock_token ?? '')) !== '') {
            return 'Locked';
        }

        return 'Active';
    }
}

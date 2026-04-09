<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Services\ReservationFinancialSyncService;
use App\Services\ReservationLockService;
use App\Services\RuntimeSettingService;
use App\Support\AuditEvent;
use App\Support\DatabaseWriteConflictMapper;
use App\Support\PaymentSummary;
use App\Support\VoucherRedemptionSupport;
use App\Support\VoucherUsageGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffReservationVoucherService
{
    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
        private readonly RuntimeSettingService $runtimeSettings,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function listAvailableForReservation(int $reservationId): array
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->with(['user', 'appliedUserVoucher.voucher'])
            ->findOrFail($reservationId);

        $orders = $this->loadOrdersForReservation($reservationId);
        $voucherRows = UserVoucher::query()
            ->with('voucher')
            ->where('user_id', (int) $reservation->user_id)
            ->orderBy('user_voucher_id')
            ->get();

        return [
            'reservation' => $reservation,
            'available_vouchers' => $voucherRows->map(fn (UserVoucher $userVoucher) => $this->presentVoucherOption($reservation, $orders, $userVoucher))->values()->all(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function applyVoucher(
        int $reservationId,
        ?int $userVoucherId = null,
        ?string $voucherCode = null,
        ?int $expectedRowVersion = null,
        ?int $staffUserId = null
    ): array {
        $context = $this->getReservationLockContext($reservationId);

        try {
            return $this->locks->withLockKeys($context['lock_keys'], function () use ($reservationId, $userVoucherId, $voucherCode, $expectedRowVersion, $staffUserId) {
                return DB::transaction(function () use ($reservationId, $userVoucherId, $voucherCode, $expectedRowVersion, $staffUserId) {
                    /** @var Reservation $reservation */
                    $reservation = Reservation::query()
                        ->with(['user', 'appliedUserVoucher.voucher'])
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->assertReservationCanManageVoucher($reservation);
                    $this->assertReservationBillEditable($reservation);
                    $this->assertRowVersion($reservation, $expectedRowVersion);

                    $payments = Payment::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->get();
                    $paymentSummary = PaymentSummary::fromPayments($payments);
                    if ((float) ($paymentSummary['final_net_amount'] ?? 0.0) > 0.0001) {
                        throw ValidationException::withMessages([
                            'reservation' => ['Cannot apply voucher after final payment has been recorded.'],
                        ]);
                    }

                    $orders = $this->loadOrdersForReservation($reservationId, true);
                    $currentVoucherDiscount = $this->currentAppliedVoucherDiscountAmount($reservation, $orders);
                    $currentLoyaltyDiscount = $this->currentLoyaltyDiscountAmount($reservationId, true);
                    $manualDiscount = max(0.0, round((float) ($reservation->discount_amount ?? 0.0) - $currentVoucherDiscount - $currentLoyaltyDiscount, 2));

                    $candidate = $this->resolveCandidateUserVoucher($reservation, $userVoucherId, $voucherCode);
                    $discountMeta = $this->validateVoucherCandidate($reservation, $orders, $candidate);

                    $currentAppliedUserVoucherId = (int) ($reservation->applied_user_voucher_id ?? 0);
                    $candidateUserVoucherId = (int) $candidate->user_voucher_id;
                    $candidateDiscountAmount = round((float) ($discountMeta['discount_amount'] ?? 0.0), 2);
                    if ($currentAppliedUserVoucherId > 0
                        && $currentAppliedUserVoucherId === $candidateUserVoucherId
                        && abs(round($currentVoucherDiscount, 2) - $candidateDiscountAmount) <= 0.0001) {
                        AuditEvent::info('staff.reservation.voucher_apply_noop', [
                            'reservation_id' => $reservationId,
                            'user_voucher_id' => $candidateUserVoucherId,
                            'voucher_id' => (int) $candidate->voucher_id,
                            'voucher_code' => (string) ($candidate->voucher?->code ?? ''),
                            'actor_user_id' => $staffUserId,
                        ]);

                        $snapshotReservation = $reservation->fresh(['user', 'tables', 'orders.items.item', 'payments', 'appliedUserVoucher.voucher']) ?: $reservation;

                        return [
                            'reservation' => $snapshotReservation,
                            'voucher' => $this->presentVoucherOption($snapshotReservation, $orders, $candidate),
                        ];
                    }

                    if ($currentAppliedUserVoucherId > 0 && $currentAppliedUserVoucherId !== $candidateUserVoucherId) {
                        $this->releaseAppliedVoucherLock($reservation, $staffUserId);
                    }

                    $candidate->lock_token = $this->buildReservationLockToken($reservationId);
                    $candidate->locked_until = $this->resolveLockUntil($reservation);
                    $candidate->updated_by = $staffUserId;
                    $candidate->save();

                    $reservation->applied_user_voucher_id = (int) $candidate->user_voucher_id;
                    $reservation->updated_by = $staffUserId;
                    $this->applyReservationDiscountSnapshot($reservation, $orders, $manualDiscount + $currentLoyaltyDiscount + (float) ($discountMeta['discount_amount'] ?? 0.0));
                    $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $staffUserId);

                    AuditEvent::info('staff.reservation.voucher_applied', [
                        'reservation_id' => $reservationId,
                        'user_id' => (int) $reservation->user_id,
                        'user_voucher_id' => (int) $candidate->user_voucher_id,
                        'voucher_id' => (int) $candidate->voucher_id,
                        'voucher_code' => (string) ($candidate->voucher?->code ?? ''),
                        'discount_amount' => (float) ($discountMeta['discount_amount'] ?? 0.0),
                        'subtotal' => (float) ($discountMeta['subtotal'] ?? 0.0),
                        'actor_user_id' => $staffUserId,
                    ]);

                    return [
                        'reservation' => Reservation::query()
                            ->with(['user', 'tables', 'orders.items.item', 'payments', 'appliedUserVoucher.voucher'])
                            ->findOrFail($reservationId),
                        'voucher' => $this->presentVoucherOption($reservation->fresh(['appliedUserVoucher.voucher']) ?: $reservation, $orders, $candidate),
                    ];
                });
            });
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function removeVoucher(int $reservationId, ?int $expectedRowVersion = null, ?int $staffUserId = null): array
    {
        $context = $this->getReservationLockContext($reservationId);

        try {
            return $this->locks->withLockKeys($context['lock_keys'], function () use ($reservationId, $expectedRowVersion, $staffUserId) {
                return DB::transaction(function () use ($reservationId, $expectedRowVersion, $staffUserId) {
                    /** @var Reservation $reservation */
                    $reservation = Reservation::query()
                        ->with(['user', 'appliedUserVoucher.voucher'])
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->assertReservationCanManageVoucher($reservation);
                    $this->assertReservationBillEditable($reservation);
                    $this->assertRowVersion($reservation, $expectedRowVersion);

                    $payments = Payment::query()
                        ->where('reservation_id', $reservationId)
                        ->lockForUpdate()
                        ->get();
                    $paymentSummary = PaymentSummary::fromPayments($payments);
                    if ((float) ($paymentSummary['final_net_amount'] ?? 0.0) > 0.0001) {
                        throw ValidationException::withMessages([
                            'reservation' => ['Cannot remove voucher after final payment has been recorded.'],
                        ]);
                    }

                    $orders = $this->loadOrdersForReservation($reservationId, true);
                    $currentVoucherDiscount = $this->currentAppliedVoucherDiscountAmount($reservation, $orders);
                    $currentLoyaltyDiscount = $this->currentLoyaltyDiscountAmount($reservationId, true);
                    $manualDiscount = max(0.0, round((float) ($reservation->discount_amount ?? 0.0) - $currentVoucherDiscount - $currentLoyaltyDiscount, 2));

                    if ((int) ($reservation->applied_user_voucher_id ?? 0) <= 0) {
                        AuditEvent::info('staff.reservation.voucher_remove_noop', [
                            'reservation_id' => $reservationId,
                            'actor_user_id' => $staffUserId,
                        ]);

                        return [
                            'reservation' => Reservation::query()
                                ->with(['user', 'tables', 'orders.items.item', 'payments', 'appliedUserVoucher.voucher'])
                                ->findOrFail($reservationId),
                            'removed_voucher' => null,
                        ];
                    }

                    $removed = $this->releaseAppliedVoucherLock($reservation, $staffUserId, true);
                    $reservation->applied_user_voucher_id = null;
                    $reservation->updated_by = $staffUserId;
                    $this->applyReservationDiscountSnapshot($reservation, $orders, $manualDiscount + $currentLoyaltyDiscount);
                    $this->reservationFinancialSyncService->touchFinancialMutation($reservation, $staffUserId);

                    AuditEvent::info('staff.reservation.voucher_removed', [
                        'reservation_id' => $reservationId,
                        'user_id' => (int) $reservation->user_id,
                        'user_voucher_id' => $removed?->user_voucher_id,
                        'voucher_id' => $removed?->voucher_id,
                        'voucher_code' => (string) ($removed?->voucher?->code ?? ''),
                        'actor_user_id' => $staffUserId,
                    ]);

                    return [
                        'reservation' => Reservation::query()
                            ->with(['user', 'tables', 'orders.items.item', 'payments', 'appliedUserVoucher.voucher'])
                            ->findOrFail($reservationId),
                        'removed_voucher' => $removed ? $this->presentVoucherOption($reservation, $this->loadOrdersForReservation($reservationId), $removed) : null,
                    ];
                });
            });
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    /**
     * @return Collection<int,ReservationOrder>
     */
    private function loadOrdersForReservation(int $reservationId, bool $lock = false): Collection
    {
        $query = ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('status', [ReservationOrderStatus::Active->value, ReservationOrderStatus::Completed->value])
            ->with('items')
            ->orderBy('order_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var Collection<int,ReservationOrder> $orders */
        $orders = $query->get();

        return $orders;
    }


    private function persistReservationWithoutRowVersionBump(Reservation $reservation): void
    {
        $dirty = $reservation->getDirty();
        unset($dirty['row_version']);

        if ($dirty === []) {
            $reservation->syncOriginal();

            return;
        }

        if (! array_key_exists('updated_at', $dirty)) {
            $dirty['updated_at'] = Carbon::now('UTC');
            $reservation->updated_at = $dirty['updated_at'];
        }

        DB::table($reservation->getTable())
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->update($dirty);

        $reservation->refresh();
    }

    /**
     * @return array{lock_keys:array<int,string>}
     */
    private function getReservationLockContext(int $reservationId): array
    {
        $tableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $lockKeys = [config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation') . ':' . $reservationId];
        foreach ($tableIds as $tableId) {
            $lockKeys[] = config('booking.reservation_lock_prefix', 'booking:lock:table') . ':' . $tableId;
        }

        return ['lock_keys' => $lockKeys];
    }

    private function assertReservationCanManageVoucher(Reservation $reservation): void
    {
        $status = (string) ($reservation->status?->value ?? $reservation->status);
        if (! in_array($status, ReservationStatus::activeDbValues(), true)) {
            throw ValidationException::withMessages([
                'reservation' => 'Voucher can only be managed for Confirmed or checked-in (Reserved) reservations.',
            ]);
        }
    }


    private function assertReservationBillEditable(Reservation $reservation): void
    {
        if ($reservation->billed_at !== null || $reservation->final_bill_amount !== null) {
            throw ValidationException::withMessages([
                'reservation' => ['Reservation bill has already been closed for payment. Reopen the bill before changing voucher discounts.'],
            ]);
        }
    }

    private function assertRowVersion(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        $beforeVersion = (int) ($reservation->row_version ?? 1);
        if ($beforeVersion !== (int) $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }

    private function resolveCandidateUserVoucher(Reservation $reservation, ?int $userVoucherId, ?string $voucherCode): UserVoucher
    {
        $query = UserVoucher::query()
            ->with('voucher')
            ->where('user_id', (int) $reservation->user_id)
            ->lockForUpdate();

        if ($userVoucherId !== null && $userVoucherId > 0) {
            $query->where('user_voucher_id', $userVoucherId);
        } elseif (($voucherCode = trim((string) $voucherCode)) !== '') {
            $query->whereHas('voucher', function ($voucherQuery) use ($voucherCode) {
                $voucherQuery->where('code', $voucherCode);
            });
        } else {
            throw ValidationException::withMessages([
                'user_voucher_id' => 'Provide user_voucher_id or voucher_code.',
            ]);
        }

        /** @var UserVoucher|null $userVoucher */
        $userVoucher = $query->first();
        if (! $userVoucher || ! $userVoucher->voucher) {
            throw ValidationException::withMessages([
                'user_voucher_id' => 'Voucher assignment not found for this reservation user.',
            ]);
        }

        return $userVoucher;
    }

    /**
     * @return array{discount_amount:float,subtotal:float,currency:string}
     */
    private function validateVoucherCandidate(Reservation $reservation, Collection $orders, UserVoucher $userVoucher): array
    {
        $voucher = $userVoucher->voucher;
        if (! $voucher instanceof Voucher) {
            throw ValidationException::withMessages(['voucher' => 'Voucher not found.']);
        }

        $now = Carbon::now('UTC');
        if (! (bool) ($voucher->is_active ?? false)) {
            throw ValidationException::withMessages(['voucher' => 'Voucher is inactive.']);
        }
        if ($voucher->start_date && $voucher->start_date->greaterThan($now)) {
            throw ValidationException::withMessages(['voucher' => 'Voucher is not active yet.']);
        }
        if ($voucher->expiry_date && $voucher->expiry_date->lessThan($now)) {
            throw ValidationException::withMessages(['voucher' => 'Voucher has expired.']);
        }
        if ((bool) ($userVoucher->is_used ?? false)) {
            throw ValidationException::withMessages(['voucher' => 'Voucher has already been used.']);
        }

        $lockToken = trim((string) ($userVoucher->lock_token ?? ''));
        $lockedUntil = $userVoucher->locked_until;
        $reservationLockToken = $this->buildReservationLockToken((int) $reservation->reservation_id);
        if ($lockToken !== '' && $lockToken !== $reservationLockToken && $lockedUntil && $lockedUntil->greaterThan($now)) {
            throw ValidationException::withMessages(['voucher' => 'Voucher is currently locked by another reservation.']);
        }

        $otherActiveReservationId = Reservation::query()
            ->where('applied_user_voucher_id', (int) $userVoucher->user_voucher_id)
            ->where('reservation_id', '!=', (int) $reservation->reservation_id)
            ->whereIn('status', ReservationStatus::activeDbValues())
            ->orderBy('reservation_id')
            ->value('reservation_id');
        if ($otherActiveReservationId !== null) {
            throw ValidationException::withMessages([
                'voucher' => ['Voucher is already applied to another active reservation: ' . (int) $otherActiveReservationId],
            ]);
        }

        $voucher = VoucherUsageGuard::lockVoucherAndAssertCanConsume($voucher, (int) $reservation->user_id);
        $userVoucher->setRelation('voucher', $voucher);

        $discountMeta = VoucherRedemptionSupport::calculateDiscount($voucher, $orders);
        $subtotal = (float) ($discountMeta['subtotal'] ?? 0.0);
        $minSpend = round(max(0.0, (float) ($voucher->min_spend ?? 0.0)), 2);
        if ($subtotal + 0.0001 < $minSpend) {
            throw ValidationException::withMessages([
                'voucher' => sprintf('Voucher requires minimum spend %.2f.', $minSpend),
            ]);
        }
        if ((float) ($discountMeta['discount_amount'] ?? 0.0) <= 0.0001) {
            throw ValidationException::withMessages(['voucher' => 'Voucher is not applicable to current reservation items.']);
        }

        return [
            'discount_amount' => round((float) ($discountMeta['discount_amount'] ?? 0.0), 2),
            'subtotal' => round($subtotal, 2),
            'currency' => (string) ($discountMeta['currency'] ?? 'VND'),
        ];
    }

    private function buildReservationLockToken(int $reservationId): string
    {
        return 'reservation:' . $reservationId;
    }

    private function resolveLockUntil(Reservation $reservation): Carbon
    {
        $fallbackMinutes = max(1, $this->runtimeSettings->int('voucher.lock_minutes', (int) config('booking.voucher_lock_minutes', 180)));
        $fallback = Carbon::now('UTC')->addMinutes($fallbackMinutes);
        $endTime = $reservation->end_time ? $reservation->end_time->copy()->utc() : null;

        if ($endTime && $endTime->greaterThan(Carbon::now('UTC'))) {
            return $endTime->lessThan($fallback) ? $endTime : $fallback;
        }

        return $fallback;
    }

    private function releaseAppliedVoucherLock(Reservation $reservation, ?int $staffUserId, bool $allowNoVoucher = false): ?UserVoucher
    {
        $userVoucherId = (int) ($reservation->applied_user_voucher_id ?? 0);
        if ($userVoucherId <= 0) {
            if ($allowNoVoucher) {
                return null;
            }

            throw ValidationException::withMessages(['voucher' => 'No applied voucher found on reservation.']);
        }

        /** @var UserVoucher|null $userVoucher */
        $userVoucher = UserVoucher::query()
            ->with('voucher')
            ->where('user_voucher_id', $userVoucherId)
            ->lockForUpdate()
            ->first();

        if (! $userVoucher) {
            return null;
        }

        if (! (bool) ($userVoucher->is_used ?? false)) {
            $userVoucher->lock_token = null;
            $userVoucher->locked_until = null;
            $userVoucher->updated_by = $staffUserId;
            $userVoucher->save();
        }

        return $userVoucher;
    }


    private function currentLoyaltyDiscountAmount(int $reservationId, bool $lock = false): float
    {
        $query = DB::table('loyalty_point_transactions')
            ->where('reservation_id', $reservationId)
            ->where(function ($nested) {
                $nested->where(function ($q) {
                    $q->where('txn_type', 'Redeem')
                        ->where('reason', 'like', 'redeem.apply%');
                })->orWhere(function ($q) {
                    $q->where('txn_type', 'Adjust')
                        ->where('reason', 'like', 'redeem.release%');
                });
            })
            ->orderBy('txn_id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $transactions = $query->get(['txn_type', 'amount_basis']);
        $amount = 0.0;
        foreach ($transactions as $tx) {
            $basis = round(max(0.0, (float) ($tx->amount_basis ?? 0.0)), 2);
            if ($basis <= 0.0001) {
                continue;
            }

            if ((string) ($tx->txn_type ?? '') === 'Redeem') {
                $amount += $basis;
                continue;
            }

            $amount -= $basis;
        }

        return round(max(0.0, $amount), 2);
    }

    private function currentAppliedVoucherDiscountAmount(Reservation $reservation, Collection $orders): float
    {
        if (! $reservation->relationLoaded('appliedUserVoucher')) {
            $reservation->load('appliedUserVoucher.voucher');
        }

        $userVoucher = $reservation->appliedUserVoucher;
        if (! $userVoucher || ! $userVoucher->voucher) {
            return 0.0;
        }

        return round((float) (VoucherRedemptionSupport::calculateDiscount($userVoucher->voucher, $orders)['discount_amount'] ?? 0.0), 2);
    }

    private function applyReservationDiscountSnapshot(Reservation $reservation, Collection $orders, float $totalDiscount): void
    {
        $this->reservationFinancialSyncService->syncReservationDiscountSnapshot(
            reservation: $reservation,
            totalDiscount: $totalDiscount,
            lockOrders: true,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function presentVoucherOption(Reservation $reservation, Collection $orders, UserVoucher $userVoucher): array
    {
        $voucher = $userVoucher->voucher;
        $preview = $voucher instanceof Voucher
            ? VoucherRedemptionSupport::calculateDiscount($voucher, $orders)
            : ['discount_amount' => 0.0, 'subtotal' => 0.0, 'currency' => 'VND'];

        $now = Carbon::now('UTC');
        $lockToken = trim((string) ($userVoucher->lock_token ?? ''));
        $reservationLockToken = $this->buildReservationLockToken((int) $reservation->reservation_id);
        $isLockedByOther = $lockToken !== ''
            && $lockToken !== $reservationLockToken
            && $userVoucher->locked_until
            && $userVoucher->locked_until->greaterThan($now);

        return [
            'user_voucher_id' => (int) $userVoucher->user_voucher_id,
            'voucher_id' => (int) $userVoucher->voucher_id,
            'voucher_code' => (string) ($voucher?->code ?? ''),
            'description' => (string) ($voucher?->description ?? ''),
            'discount_type' => $voucher?->discount_type?->value ?? (string) ($voucher?->discount_type ?? ''),
            'discount_value' => $voucher?->discount_value !== null ? (string) $voucher->discount_value : null,
            'min_spend' => $voucher?->min_spend !== null ? (string) $voucher->min_spend : null,
            'is_used' => (bool) ($userVoucher->is_used ?? false),
            'is_locked_by_other' => $isLockedByOther,
            'locked_until' => $userVoucher->locked_until?->utc()->toIso8601String(),
            'preview_discount_amount' => number_format((float) ($preview['discount_amount'] ?? 0.0), 2, '.', ''),
            'preview_currency' => (string) ($preview['currency'] ?? 'VND'),
            'is_currently_applied' => (int) ($reservation->applied_user_voucher_id ?? 0) === (int) $userVoucher->user_voucher_id,
        ];
    }
}

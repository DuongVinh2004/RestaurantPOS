<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Models\Reservation;
use App\Models\UserVoucher;
use App\Services\LoyaltyPointsService;
use App\Services\Reservation\ReservationVoucherPreviewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerBenefitsService
{
    public function __construct(
        private readonly LoyaltyPointsService $loyaltyPointsService,
        private readonly ReservationVoucherPreviewService $reservationVoucherPreviewService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function getSelfLoyaltySummary(int $userId, int $limit = 20): array
    {
        return $this->loyaltyPointsService->getUserLoyaltySummary($userId, $limit);
    }

    public function listSelfVouchers(int $userId, array $filters = []): LengthAwarePaginator
    {
        $bucket = strtolower(trim((string) ($filters['bucket'] ?? 'active')));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $queryText = trim((string) ($filters['q'] ?? ''));
        $now = now('UTC');

        $query = UserVoucher::query()
            ->with(['voucher.freeItem'])
            ->where('user_id', $userId)
            ->orderByDesc('assigned_date')
            ->orderByDesc('user_voucher_id');

        if ($bucket === 'active') {
            $query
                ->where('is_used', 0)
                ->where(function ($lockQuery) use ($now): void {
                    $lockQuery
                        ->whereNull('locked_until')
                        ->orWhere('locked_until', '<=', $now)
                        ->orWhereNull('lock_token')
                        ->orWhere('lock_token', '');
                })
                ->whereHas('voucher', function ($voucherQuery) use ($now): void {
                    $voucherQuery
                        ->where('is_active', 1)
                        ->where(function ($dateQuery) use ($now): void {
                            $dateQuery->whereNull('start_date')->orWhere('start_date', '<=', $now);
                        })
                        ->where(function ($dateQuery) use ($now): void {
                            $dateQuery->whereNull('expiry_date')->orWhere('expiry_date', '>=', $now);
                        });
                });
        } elseif ($bucket === 'unused') {
            $query->where('is_used', 0);
        } elseif ($bucket === 'used') {
            $query->where('is_used', 1);
        }

        if ($queryText !== '') {
            $query->whereHas('voucher', function ($voucherQuery) use ($queryText) {
                $voucherQuery->where('code', 'like', '%' . $queryText . '%')
                    ->orWhere('description', 'like', '%' . $queryText . '%');
            });
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->appends([
            'bucket' => $bucket,
            'q' => $queryText,
            'per_page' => $perPage,
        ]);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (UserVoucher $userVoucher) => $this->presentOwnedVoucher($userVoucher))
        );

        return $paginator;
    }

    /**
     * @return array<string,mixed>
     */
    public function previewOwnedReservationBenefits(int $reservationId, int $userId): array
    {
        /** @var Reservation $reservation */
        $reservation = Reservation::query()
            ->with(['user.points', 'user.currentTier', 'appliedUserVoucher.voucher'])
            ->where('reservation_id', $reservationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $loyaltySummary = $this->loyaltyPointsService->getReservationLoyaltySummary($reservationId);
        $voucherOptions = $this->reservationVoucherPreviewService->listVoucherOptions($reservation);

        return [
            'reservation' => $loyaltySummary['reservation'] ?? null,
            'available_vouchers' => $voucherOptions,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function presentOwnedVoucher(UserVoucher $userVoucher): array
    {
        $voucher = $userVoucher->voucher;
        $now = now('UTC');
        $status = 'Unavailable';
        $isUsableNow = false;

        if ((bool) ($userVoucher->is_used ?? false)) {
            $status = 'Used';
        } elseif ($voucher) {
            if (! (bool) ($voucher->is_active ?? false)) {
                $status = 'Inactive';
            } elseif ($voucher->start_date && $voucher->start_date->greaterThan($now)) {
                $status = 'Scheduled';
            } elseif ($voucher->expiry_date && $voucher->expiry_date->lessThan($now)) {
                $status = 'Expired';
            } elseif ($userVoucher->locked_until && $userVoucher->locked_until->greaterThan($now) && trim((string) ($userVoucher->lock_token ?? '')) !== '') {
                $status = 'Locked';
            } else {
                $status = 'Active';
                $isUsableNow = true;
            }
        }

        return [
            'user_voucher_id' => (int) $userVoucher->user_voucher_id,
            'voucher_id' => (int) ($userVoucher->voucher_id ?? 0),
            'voucher_code' => (string) ($voucher?->code ?? ''),
            'description' => (string) ($voucher?->description ?? ''),
            'discount_type' => $voucher?->discount_type?->value ?? (string) ($voucher?->discount_type ?? ''),
            'discount_value' => $voucher?->discount_value !== null ? number_format((float) $voucher->discount_value, 2, '.', '') : null,
            'min_spend' => $voucher?->min_spend !== null ? number_format((float) $voucher->min_spend, 2, '.', '') : null,
            'free_item' => $voucher && (int) ($voucher->free_item_id ?? 0) > 0 ? [
                'item_id' => (int) $voucher->free_item_id,
                'quantity' => max(1, (int) ($voucher->free_item_qty ?? 1)),
                'item_name' => (string) ($voucher->freeItem?->name ?? ''),
            ] : null,
            'assigned_at' => $userVoucher->assigned_date?->utc()->toIso8601String(),
            'used_at' => $userVoucher->used_date?->utc()->toIso8601String(),
            'used_reservation_id' => $userVoucher->used_reservation_id !== null ? (int) $userVoucher->used_reservation_id : null,
            'starts_at' => $voucher?->start_date?->utc()->toIso8601String(),
            'expires_at' => $voucher?->expiry_date?->utc()->toIso8601String(),
            'is_used' => (bool) ($userVoucher->is_used ?? false),
            'current_status' => $status,
            'is_usable_now' => $isUsableNow,
            'is_locked' => $status === 'Locked',
            'locked_until' => $userVoucher->locked_until?->utc()->toIso8601String(),
            'row_version' => (int) ($userVoucher->row_version ?? 1),
        ];
    }
}

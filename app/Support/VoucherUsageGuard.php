<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Services\MetricsService;
final class VoucherUsageGuard
{

    public static function lockVoucherForUpdate(int $voucherId): Voucher
    {
        /** @var Voucher $voucher */
        $voucher = Voucher::query()
            ->where('voucher_id', $voucherId)
            ->lockForUpdate()
            ->firstOrFail();

        return $voucher;
    }

    public static function lockVoucherAndAssertCanConsume(Voucher $voucher, int $userId, ?int $excludingUserVoucherId = null): Voucher
    {
        /** @var Voucher $lockedVoucher */
        $lockedVoucher = Voucher::query()
            ->where('voucher_id', (int) $voucher->voucher_id)
            ->lockForUpdate()
            ->firstOrFail();

        self::assertCanConsume($lockedVoucher, $userId, $excludingUserVoucherId);

        return $lockedVoucher;
    }

    public static function assertCanConsume(Voucher $voucher, int $userId, ?int $excludingUserVoucherId = null): void
    {
        $voucherId = (int) $voucher->voucher_id;
        $excludingUserVoucherId = $excludingUserVoucherId !== null && $excludingUserVoucherId > 0
            ? $excludingUserVoucherId
            : null;

        $usedCountQuery = UserVoucher::query()
            ->where('voucher_id', $voucherId)
            ->where('is_used', 1);
        if ($excludingUserVoucherId !== null) {
            $usedCountQuery->where('user_voucher_id', '!=', $excludingUserVoucherId);
        }

        $maxUsage = $voucher->max_usage !== null ? (int) $voucher->max_usage : null;
        if ($maxUsage !== null && $usedCountQuery->count() >= $maxUsage) {
            self::reportQuotaViolation($voucher, $userId, 'global', $maxUsage, $excludingUserVoucherId);

            throw ValidationExceptionFactory::make([
                'voucher' => ['Voucher usage limit has been reached.'],
            ]);
        }

        $maxUsagePerUser = $voucher->max_usage_per_user !== null ? (int) $voucher->max_usage_per_user : null;
        if ($maxUsagePerUser === null) {
            return;
        }

        $usedCountByUserQuery = UserVoucher::query()
            ->where('voucher_id', $voucherId)
            ->where('user_id', $userId)
            ->where('is_used', 1);
        if ($excludingUserVoucherId !== null) {
            $usedCountByUserQuery->where('user_voucher_id', '!=', $excludingUserVoucherId);
        }

        if ($usedCountByUserQuery->count() >= $maxUsagePerUser) {
            self::reportQuotaViolation($voucher, $userId, 'per_user', $maxUsagePerUser, $excludingUserVoucherId);

            throw ValidationExceptionFactory::make([
                'voucher' => ['Voucher per-user usage limit has been reached.'],
            ]);
        }
    }

    private static function reportQuotaViolation(Voucher $voucher, int $userId, string $scope, ?int $limit, ?int $excludingUserVoucherId): void
    {
        AuditEvent::warning('voucher_usage_rejected', [
            'voucher_id' => (int) $voucher->voucher_id,
            'user_id' => $userId,
            'scope' => $scope,
            'limit' => $limit,
            'excluding_user_voucher_id' => $excludingUserVoucherId,
            'discount_type' => (string) ($voucher->discount_type?->value ?? $voucher->discount_type ?? ''),
            'code' => (string) ($voucher->code ?? ''),
        ]);

        try {
            if (! app()->bound(MetricsService::class)) {
                return;
            }

            app(MetricsService::class)->inc('voucher_usage_rejected_total', [
                'scope' => $scope,
                'discount_type' => (string) ($voucher->discount_type?->value ?? $voucher->discount_type ?? 'unknown'),
            ]);
        } catch (\Throwable) {
            // best-effort only
        }
    }
}

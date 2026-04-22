<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Policies;

class ReservationViewProfile
{
    public const STAFF = 'staff';

    public const OWNER = 'owner';

    public const SESSION = 'session';

    /**
     * @return list<string>
     */
    public static function relationsFor(string $scope): array
    {
        $scope = self::normalize($scope);

        return match ($scope) {
            self::STAFF, self::OWNER => [
                'tables',
                'user.points',
                'user.currentTier',
                'orders.items.item',
                'payments',
                'appliedUserVoucher.voucher',
            ],
            self::SESSION => [
                'tables',
                'user',
            ],
            default => [
                'tables',
            ],
        };
    }

    public static function normalize(?string $scope, bool $isStaff = false): string
    {
        $scope = strtolower(trim((string) $scope));

        if ($isStaff) {
            return self::STAFF;
        }

        return match ($scope) {
            self::STAFF, self::OWNER, self::SESSION => $scope,
            'customer' => self::OWNER,
            default => self::OWNER,
        };
    }

    public static function canViewUserIdentity(string $scope): bool
    {
        return self::normalize($scope) !== self::SESSION;
    }

    public static function canViewUserSummary(string $scope): bool
    {
        return true;
    }

    public static function canViewUserContact(string $scope): bool
    {
        return self::normalize($scope) !== self::SESSION;
    }

    public static function canViewUserLoyalty(string $scope): bool
    {
        return self::normalize($scope) !== self::SESSION;
    }

    public static function canViewFinancials(string $scope): bool
    {
        return self::normalize($scope) !== self::SESSION;
    }

    public static function canViewCancellationMetadata(string $scope): bool
    {
        return self::normalize($scope) !== self::SESSION;
    }

    public static function canViewVoucherDetails(string $scope): bool
    {
        return self::normalize($scope) !== self::SESSION;
    }
}

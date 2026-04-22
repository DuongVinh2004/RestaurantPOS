<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Policies;

use App\Support\Auth\RequestActorContext;
use Illuminate\Http\Request;

class ReservationAccessScope
{
    public const STAFF = 'staff';

    public const OWNER = 'owner';

    public const SESSION = 'session';

    public static function resolve(Request $request): string
    {
        $explicit = trim((string) $request->attributes->get('reservation_access_scope', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        return RequestActorContext::fromRequest($request)->accessScope();
    }

    public static function canViewIdentity(string $scope): bool
    {
        return in_array($scope, [self::STAFF, self::OWNER], true);
    }

    public static function canViewDisplayName(string $scope): bool
    {
        return in_array($scope, [self::STAFF, self::OWNER, self::SESSION], true);
    }

    public static function canViewContact(string $scope): bool
    {
        return in_array($scope, [self::STAFF, self::OWNER], true);
    }

    public static function canViewLoyalty(string $scope): bool
    {
        return in_array($scope, [self::STAFF, self::OWNER], true);
    }

    public static function canViewFinancials(string $scope): bool
    {
        return in_array($scope, [self::STAFF, self::OWNER], true);
    }

    public static function canViewVoucherDetails(string $scope): bool
    {
        return in_array($scope, [self::STAFF, self::OWNER], true);
    }
}

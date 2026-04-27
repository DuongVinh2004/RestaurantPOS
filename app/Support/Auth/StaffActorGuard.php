<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Support\ValidationExceptionFactory;

final class StaffActorGuard
{
    public const REQUIRED_MESSAGE = 'Authenticated staff actor is required.';

    public static function requireStaffUserId(?int $staffUserId, string $field = 'staff_user_id'): int
    {
        if ($staffUserId !== null && $staffUserId > 0) {
            return $staffUserId;
        }

        throw ValidationExceptionFactory::make([
            $field => [self::REQUIRED_MESSAGE],
        ]);
    }
}

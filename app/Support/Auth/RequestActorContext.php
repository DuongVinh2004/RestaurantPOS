<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use Illuminate\Http\Request;

final class RequestActorContext
{
    public const ATTR_TYPE = 'request_actor_type';

    public const ATTR_AUTH_MODE = 'request_actor_auth_mode';

    public const TYPE_STAFF = 'staff';

    public const TYPE_CUSTOMER_OWNER = 'customer_owner';

    public const TYPE_CUSTOMER_SESSION = 'customer_session';

    public function __construct(
        private readonly string $type,
        private readonly ?User $user = null,
        private readonly ?int $staffUserId = null,
        private readonly ?int $customerUserId = null,
        private readonly ?string $sessionId = null,
        private readonly ?string $authMode = null,
        private readonly ?int $customerAccessSessionId = null,
        private readonly ?int $staffApiKeyId = null,
        private readonly ?string $guestName = null,
        private readonly ?string $phone = null,
    ) {}

    public static function anonymous(): self
    {
        return new self('');
    }

    public static function staff(User $user, ?string $authMode = null, ?int $staffApiKeyId = null): self
    {
        $staffUserId = (int) ($user->user_id ?? 0);

        return new self(
            type: self::TYPE_STAFF,
            user: $user,
            staffUserId: $staffUserId > 0 ? $staffUserId : null,
            authMode: self::normalizeNullableString($authMode),
            staffApiKeyId: $staffApiKeyId !== null && $staffApiKeyId > 0 ? $staffApiKeyId : null,
        );
    }

    public static function customerOwner(
        User $user,
        ?string $authMode = null,
        ?string $sessionId = null,
        ?int $customerAccessSessionId = null,
        ?string $guestName = null,
        ?string $phone = null,
    ): self {
        $customerUserId = (int) ($user->user_id ?? 0);

        return new self(
            type: self::TYPE_CUSTOMER_OWNER,
            user: $user,
            customerUserId: $customerUserId > 0 ? $customerUserId : null,
            sessionId: self::normalizeNullableString($sessionId),
            authMode: self::normalizeNullableString($authMode),
            customerAccessSessionId: $customerAccessSessionId !== null && $customerAccessSessionId > 0 ? $customerAccessSessionId : null,
            guestName: self::normalizeNullableString($guestName),
            phone: self::normalizeNullableString($phone),
        );
    }

    public static function customerSession(
        ?string $sessionId,
        ?string $authMode = null,
        ?int $customerAccessSessionId = null,
        ?string $guestName = null,
        ?string $phone = null,
    ): self {
        $normalizedSessionId = self::normalizeNullableString($sessionId);
        if ($normalizedSessionId === null) {
            return self::anonymous();
        }

        return new self(
            type: self::TYPE_CUSTOMER_SESSION,
            sessionId: $normalizedSessionId,
            authMode: self::normalizeNullableString($authMode),
            customerAccessSessionId: $customerAccessSessionId !== null && $customerAccessSessionId > 0 ? $customerAccessSessionId : null,
            guestName: self::normalizeNullableString($guestName),
            phone: self::normalizeNullableString($phone),
        );
    }

    public static function fromRequest(Request $request): self
    {
        $explicitType = self::normalizeNullableString($request->attributes->get(self::ATTR_TYPE));

        if ($explicitType === self::TYPE_STAFF) {
            return new self(
                type: self::TYPE_STAFF,
                user: $request->user(),
                staffUserId: self::normalizePositiveInt($request->attributes->get('staff_actor_user_id')),
                authMode: self::normalizeNullableString($request->attributes->get('staff_auth_mode'))
                    ?? self::normalizeNullableString($request->attributes->get(self::ATTR_AUTH_MODE)),
                staffApiKeyId: self::normalizePositiveInt($request->attributes->get('staff_api_key_id')),
            );
        }

        if ($explicitType === self::TYPE_CUSTOMER_OWNER) {
            return new self(
                type: self::TYPE_CUSTOMER_OWNER,
                user: $request->user(),
                customerUserId: self::normalizePositiveInt($request->attributes->get('customer_actor_user_id')),
                sessionId: self::normalizeNullableString($request->attributes->get('customer_session_id')),
                authMode: self::normalizeNullableString($request->attributes->get('customer_auth_mode'))
                    ?? self::normalizeNullableString($request->attributes->get(self::ATTR_AUTH_MODE)),
                customerAccessSessionId: self::normalizePositiveInt($request->attributes->get('customer_access_session_id')),
                guestName: self::normalizeNullableString($request->attributes->get('customer_guest_name')),
                phone: self::normalizeNullableString($request->attributes->get('customer_phone')),
            );
        }

        if ($explicitType === self::TYPE_CUSTOMER_SESSION) {
            return new self(
                type: self::TYPE_CUSTOMER_SESSION,
                sessionId: self::normalizeNullableString($request->attributes->get('customer_session_id'))
                    ?? self::extractSessionId($request),
                authMode: self::normalizeNullableString($request->attributes->get('customer_auth_mode'))
                    ?? self::normalizeNullableString($request->attributes->get(self::ATTR_AUTH_MODE)),
                customerAccessSessionId: self::normalizePositiveInt($request->attributes->get('customer_access_session_id')),
                guestName: self::normalizeNullableString($request->attributes->get('customer_guest_name')),
                phone: self::normalizeNullableString($request->attributes->get('customer_phone')),
            );
        }

        if ((bool) $request->attributes->get('is_staff', false) || self::normalizePositiveInt($request->attributes->get('staff_actor_user_id')) !== null) {
            return new self(
                type: self::TYPE_STAFF,
                user: $request->user(),
                staffUserId: self::normalizePositiveInt($request->attributes->get('staff_actor_user_id')),
                authMode: self::normalizeNullableString($request->attributes->get('staff_auth_mode')),
                staffApiKeyId: self::normalizePositiveInt($request->attributes->get('staff_api_key_id')),
            );
        }

        if (self::normalizePositiveInt($request->attributes->get('customer_actor_user_id')) !== null) {
            return new self(
                type: self::TYPE_CUSTOMER_OWNER,
                user: $request->user(),
                customerUserId: self::normalizePositiveInt($request->attributes->get('customer_actor_user_id')),
                sessionId: self::normalizeNullableString($request->attributes->get('customer_session_id')),
                authMode: self::normalizeNullableString($request->attributes->get('customer_auth_mode')),
                customerAccessSessionId: self::normalizePositiveInt($request->attributes->get('customer_access_session_id')),
                guestName: self::normalizeNullableString($request->attributes->get('customer_guest_name')),
                phone: self::normalizeNullableString($request->attributes->get('customer_phone')),
            );
        }

        $sessionId = self::normalizeNullableString($request->attributes->get('customer_session_id'))
            ?? self::extractSessionId($request);

        if ((bool) $request->attributes->get('customer_session_flow', false) || $sessionId !== null) {
            return new self(
                type: self::TYPE_CUSTOMER_SESSION,
                sessionId: $sessionId,
                authMode: self::normalizeNullableString($request->attributes->get('customer_auth_mode')),
                customerAccessSessionId: self::normalizePositiveInt($request->attributes->get('customer_access_session_id')),
                guestName: self::normalizeNullableString($request->attributes->get('customer_guest_name')),
                phone: self::normalizeNullableString($request->attributes->get('customer_phone')),
            );
        }

        return self::anonymous();
    }

    public function applyToRequest(Request $request): void
    {
        $this->clearResolvedAttributes($request);

        if ($this->type === '') {
            return;
        }

        $request->attributes->set(self::ATTR_TYPE, $this->type);

        if ($this->authMode !== null) {
            $request->attributes->set(self::ATTR_AUTH_MODE, $this->authMode);
        }

        if ($this->isStaff()) {
            if ($this->user instanceof User) {
                $user = $this->user;
                $request->setUserResolver(static fn (): User => $user);
            }

            $request->attributes->set('is_staff', true);

            if ($this->staffUserId !== null) {
                $request->attributes->set('staff_actor_user_id', $this->staffUserId);
            }

            if ($this->user instanceof User) {
                $request->attributes->set('staff_actor_role_id', (int) ($this->user->role_id ?? 0));
                $request->attributes->set('staff_actor_role_name', (string) ($this->user->role?->role_name ?? ''));
            }

            if ($this->authMode !== null) {
                $request->attributes->set('staff_auth_mode', $this->authMode);
            }

            if ($this->staffApiKeyId !== null) {
                $request->attributes->set('staff_api_key_id', $this->staffApiKeyId);
            }

            return;
        }

        $request->attributes->set('is_staff', false);

        if ($this->isCustomerOwner()) {
            if ($this->user instanceof User) {
                $user = $this->user;
                $request->setUserResolver(static fn (): User => $user);
            }

            if ($this->customerUserId !== null) {
                $request->attributes->set('customer_actor_user_id', $this->customerUserId);
            }
        } else {
            $request->setUserResolver(static fn () => null);
            $request->attributes->set('customer_session_flow', true);
        }

        if ($this->sessionId !== null) {
            $request->attributes->set('customer_session_id', $this->sessionId);
        }

        if ($this->authMode !== null) {
            $request->attributes->set('customer_auth_mode', $this->authMode);
        }

        if ($this->customerAccessSessionId !== null) {
            $request->attributes->set('customer_access_session_id', $this->customerAccessSessionId);
        }

        if ($this->guestName !== null) {
            $request->attributes->set('customer_guest_name', $this->guestName);
        }

        if ($this->phone !== null) {
            $request->attributes->set('customer_phone', $this->phone);
        }
    }

    public function isStaff(): bool
    {
        return $this->type === self::TYPE_STAFF;
    }

    public function isCustomerOwner(): bool
    {
        return $this->type === self::TYPE_CUSTOMER_OWNER;
    }

    public function isCustomerSession(): bool
    {
        return $this->type === self::TYPE_CUSTOMER_SESSION;
    }

    public function customerUserId(): ?int
    {
        return $this->customerUserId;
    }

    public function staffUserId(): ?int
    {
        return $this->staffUserId;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function accessScope(): string
    {
        return match ($this->type) {
            self::TYPE_STAFF => ReservationAccessScope::STAFF,
            self::TYPE_CUSTOMER_OWNER => ReservationAccessScope::OWNER,
            self::TYPE_CUSTOMER_SESSION => ReservationAccessScope::SESSION,
            default => ReservationAccessScope::SESSION,
        };
    }

    public function authMode(): ?string
    {
        return $this->authMode;
    }

    private function clearResolvedAttributes(Request $request): void
    {
        foreach ([
            self::ATTR_TYPE,
            self::ATTR_AUTH_MODE,
            'is_staff',
            'staff_actor_user_id',
            'staff_actor_role_id',
            'staff_actor_role_name',
            'staff_auth_mode',
            'staff_api_key_id',
            'customer_actor_user_id',
            'customer_session_id',
            'customer_auth_mode',
            'customer_access_session_id',
            'customer_guest_name',
            'customer_phone',
            'customer_session_flow',
        ] as $attribute) {
            $request->attributes->remove($attribute);
        }
    }

    private static function extractSessionId(Request $request): ?string
    {
        foreach ([
            $request->header('X-Session-Id'),
            $request->input('session_id'),
            $request->query('session_id'),
        ] as $candidate) {
            $normalized = self::normalizeNullableString($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    private static function normalizePositiveInt(mixed $value): ?int
    {
        $normalized = (int) ($value ?? 0);

        return $normalized > 0 ? $normalized : null;
    }
}

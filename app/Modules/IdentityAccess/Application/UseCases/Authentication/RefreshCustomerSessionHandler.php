<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Domain\Models\CustomerAccessSession;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerAccessSessionPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Lam moi customer access session theo kieu rotate:
 * revoke session cu, giu lai metadata dang dung,
 * roi issue session moi de self-service khong mat ngu canh.
 */
class RefreshCustomerSessionHandler
{
    public function __construct(
        private readonly CustomerAccessSessionStore $customerAccessSessionStore,
        private readonly CustomerAccessSessionPayloadBuilder $customerAccessSessionPayloadBuilder,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $accessSessionId): array
    {
        // Pha 1: gate session cu van active truoc khi tao session thay the.
        $current = $this->requireActiveCustomerAccessSession($accessSessionId);

        $user = $current->user;
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'access_session' => ['Customer access session is no longer bound to a valid customer account.'],
            ]);
        }

        // Pha 2: session cu bi revoke ngay de khong con song song voi token moi.
        $this->customerAccessSessionStore->revokeSession($current);

        // Pha 3: tai su dung metadata cu de customer tiep tuc self-service ma khong mat guest/session context.
        $issued = $this->customerAccessSessionStore->issueForUser(
            $user,
            $this->customerSessionExpiry(),
            [
                'session_id' => $current->session_id,
                'guest_name' => $current->guest_name,
                'phone' => $current->phone,
                'session_meta_json' => $current->session_meta_json,
                'created_ip' => $current->created_ip,
                'user_agent' => $current->user_agent,
                'source' => 'customer_session_refresh',
            ],
        );

        // Audit giu du session cu va session moi de trace duoc chu ky refresh.
        AuditEvent::info('customer_password_login_refreshed', [
            'user_id' => (int) $user->user_id,
            'revoked_access_session_id' => (int) $current->getKey(),
            'replacement_access_session_id' => (int) $issued['access_session']->getKey(),
        ]);

        // Pha 4: tra payload moi kem plaintext token moi de client thay ngay.
        return $this->customerAccessSessionPayloadBuilder->build(
            $issued['access_session'],
            (string) $issued['plain_text_token'],
        );
    }

    private function requireActiveCustomerAccessSession(int $accessSessionId): CustomerAccessSession
    {
        // Gate active nay bao ve toan bo flow refresh khong dung session da het han/revoke.
        $session = $this->customerAccessSessionStore->showSession($accessSessionId);

        if ($session->revoked_at !== null || $session->expires_at === null || ! $session->expires_at->utc()->isFuture()) {
            throw ValidationException::withMessages([
                'access_session' => ['Customer access session is no longer active.'],
            ]);
        }

        $session->loadMissing('user.role');

        return $session;
    }

    private function customerSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('customer_auth.access_session_ttl_minutes', 60 * 24 * 14)));
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerAccessSessionPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Internal\PasswordLoginAuthenticator;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;

/**
 * Dieu phoi login customer theo model access session:
 * xac thuc tai khoan, issue session token tu phuc vu,
 * va tra payload dung contract ma FE/customer app can.
 */
class LoginCustomerHandler
{
    public function __construct(
        private readonly PasswordLoginAuthenticator $passwordLoginAuthenticator,
        private readonly CustomerAccessSessionStore $customerAccessSessionStore,
        private readonly CustomerAccessSessionPayloadBuilder $customerAccessSessionPayloadBuilder,
    ) {}

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function handle(string $identifier, string $password, array $context = []): array
    {
        // Pha 1: gate danh tinh. Chi role customer hop le moi duoc cap session moi.
        $user = $this->passwordLoginAuthenticator->authenticate(
            $identifier,
            $password,
            (array) config('customer_auth.allowed_role_ids', [3]),
            'customer',
        );

        // Pha 2: session context giu lai guest snapshot + metadata de self-service tiep tuc dung.
        $issued = $this->customerAccessSessionStore->issueForUser(
            $user,
            $this->customerSessionExpiry(),
            $this->customerSessionContext($user, $context),
        );

        // Audit chi ghi sau khi access session da duoc persist va co id ro rang.
        AuditEvent::info('customer_password_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'access_session_id' => (int) $issued['access_session']->getKey(),
        ]);

        // Pha 3: payload builder tra session model + plaintext token theo shape FE mong doi.
        return $this->customerAccessSessionPayloadBuilder->build(
            $issued['access_session'],
            (string) $issued['plain_text_token'],
        );
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function customerSessionContext(User $user, array $context): array
    {
        // Context nay la cau noi giua auth session va reservation self-service sau khi customer dang nhap.
        return [
            'session_id' => isset($context['session_id']) ? trim((string) $context['session_id']) : null,
            'guest_name' => trim((string) ($context['guest_name'] ?? $user->full_name ?? '')) ?: null,
            'phone' => trim((string) ($context['phone'] ?? $user->phone ?? '')) ?: null,
            // session_meta_json giu thong tin nguon goc va thiet bi, dong thoi loai cac field rong.
            'session_meta_json' => array_filter([
                'session_label' => trim((string) ($context['session_label'] ?? 'customer_password_login')) ?: null,
                'source' => 'customer_password_login',
                'device_id' => trim((string) ($context['device_id'] ?? '')) ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'created_ip' => trim((string) ($context['ip'] ?? '')) ?: null,
            'user_agent' => trim((string) ($context['user_agent'] ?? '')) ?: null,
            'source' => 'customer_password_login',
        ];
    }

    private function customerSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('customer_auth.access_session_ttl_minutes', 60 * 24 * 14)));
    }
}

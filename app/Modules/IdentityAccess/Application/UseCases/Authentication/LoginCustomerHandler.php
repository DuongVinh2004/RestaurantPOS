<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerAccessSessionPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Internal\PasswordLoginAuthenticator;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;

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
        $user = $this->passwordLoginAuthenticator->authenticate(
            $identifier,
            $password,
            (array) config('customer_auth.allowed_role_ids', [3]),
            'customer',
        );

        $issued = $this->customerAccessSessionStore->issueForUser(
            $user,
            $this->customerSessionExpiry(),
            $this->customerSessionContext($user, $context),
        );

        AuditEvent::info('customer_password_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'access_session_id' => (int) $issued['access_session']->getKey(),
        ]);

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
        return [
            'session_id' => isset($context['session_id']) ? trim((string) $context['session_id']) : null,
            'guest_name' => trim((string) ($context['guest_name'] ?? $user->full_name ?? '')) ?: null,
            'phone' => trim((string) ($context['phone'] ?? $user->phone ?? '')) ?: null,
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

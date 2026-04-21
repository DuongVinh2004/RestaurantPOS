<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Application\UseCases\ApiKeys\IssueStaffApiKeyHandler;
use App\Modules\IdentityAccess\Infrastructure\Internal\AuthenticatedStaffPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Internal\PasswordLoginAuthenticator;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;

class LoginStaffHandler
{
    public function __construct(
        private readonly PasswordLoginAuthenticator $passwordLoginAuthenticator,
        private readonly IssueStaffApiKeyHandler $issueStaffApiKeyHandler,
        private readonly AuthenticatedStaffPayloadBuilder $authenticatedStaffPayloadBuilder,
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
            (array) config('staff_auth.allowed_role_ids', [1, 2]),
            'staff',
        );

        $issued = $this->issueStaffApiKeyHandler->handle(
            (int) $user->user_id,
            $this->staffSessionLabel($context),
            $this->staffSessionExpiry(),
        );

        $record = $issued['record'];
        $record->loadMissing('user.role');

        AuditEvent::info('staff_password_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'staff_api_key_id' => (int) $record->getKey(),
        ]);

        return $this->authenticatedStaffPayloadBuilder->build(
            $record,
            (string) $issued['plaintext_key'],
        );
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function staffSessionLabel(array $context): string
    {
        $label = trim((string) ($context['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $device = trim((string) ($context['device_name'] ?? ''));
        if ($device !== '') {
            return 'Auth Session - '.$device;
        }

        return 'Auth Session';
    }

    private function staffSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.session_ttl_minutes', 720)));
    }
}

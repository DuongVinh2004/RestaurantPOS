<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Application\UseCases\ApiKeys\IssueStaffApiKeyHandler;
use App\Modules\IdentityAccess\Domain\Models\User;
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
        $user = $this->authenticateStaff($identifier, $password);

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
     * @return array{payload:array<string,mixed>,refresh_token:string}
     */
    public function handleBrowserSession(string $identifier, string $password, array $context = []): array
    {
        $user = $this->authenticateStaff($identifier, $password);

        $refresh = $this->issueStaffApiKeyHandler->handle(
            (int) $user->user_id,
            $this->browserRefreshSessionLabel($context),
            $this->staffSessionExpiry(),
        );

        $access = $this->issueStaffApiKeyHandler->handle(
            (int) $user->user_id,
            $this->browserAccessTokenLabel($context),
            $this->browserAccessExpiry(),
        );

        $accessRecord = $access['record'];
        $accessRecord->loadMissing('user.role');

        AuditEvent::info('staff_browser_session_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'refresh_staff_api_key_id' => (int) $refresh['record']->getKey(),
            'access_staff_api_key_id' => (int) $accessRecord->getKey(),
        ]);

        $payload = $this->authenticatedStaffPayloadBuilder->build(
            $accessRecord,
            (string) $access['plaintext_key'],
        );
        $payload['auth_mode'] = 'staff_browser_session';
        $payload['session_transport'] = 'refresh_cookie';

        return [
            'payload' => $payload,
            'refresh_token' => (string) $refresh['plaintext_key'],
        ];
    }

    private function authenticateStaff(string $identifier, string $password): User
    {
        return $this->passwordLoginAuthenticator->authenticate(
            $identifier,
            $password,
            (array) config('staff_auth.allowed_role_ids', [1, 2]),
            'staff',
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

    /**
     * @param  array<string,mixed>  $context
     */
    private function browserRefreshSessionLabel(array $context): string
    {
        return $this->prefixedLabel(
            (string) config('staff_auth.browser_session.refresh_label_prefix', 'Staff Browser Refresh Session'),
            $context,
        );
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function browserAccessTokenLabel(array $context): string
    {
        return $this->prefixedLabel(
            (string) config('staff_auth.browser_session.access_label_prefix', 'Staff Browser Access Token'),
            $context,
        );
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function prefixedLabel(string $prefix, array $context): string
    {
        $prefix = trim($prefix) !== '' ? trim($prefix) : 'Staff Browser Session';
        $device = trim((string) ($context['device_name'] ?? ''));
        if ($device !== '') {
            return $prefix.' - '.$device;
        }

        $label = trim((string) ($context['label'] ?? ''));
        if ($label !== '') {
            return $prefix.' - '.$label;
        }

        return $prefix;
    }

    private function staffSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.session_ttl_minutes', 720)));
    }

    private function browserAccessExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.browser_session.access_ttl_minutes', 5)));
    }
}

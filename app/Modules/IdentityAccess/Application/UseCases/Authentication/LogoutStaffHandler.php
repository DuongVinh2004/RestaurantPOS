<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Application\UseCases\ApiKeys\RevokeStaffApiKeyHandler;
use App\Modules\IdentityAccess\Infrastructure\Internal\AuthenticatedStaffPayloadBuilder;
use App\Support\AuditEvent;
use Throwable;

class LogoutStaffHandler
{
    public function __construct(
        private readonly RevokeStaffApiKeyHandler $revokeStaffApiKeyHandler,
        private readonly AuthenticatedStaffPayloadBuilder $authenticatedStaffPayloadBuilder,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $staffApiKeyId): array
    {
        $record = $this->revokeStaffApiKeyHandler->handle($staffApiKeyId, 'staff_password_logout');

        AuditEvent::info('staff_password_login_logged_out', [
            'staff_api_key_id' => (int) $record->getKey(),
            'user_id' => (int) ($record->user_id ?? 0),
        ]);

        return $this->authenticatedStaffPayloadBuilder->buildRevoked($record);
    }

    /**
     * @return array<string,mixed>
     */
    public function handleBrowserSession(int $refreshStaffApiKeyId, ?int $currentAccessStaffApiKeyId = null): array
    {
        $record = $this->revokeStaffApiKeyHandler->handle($refreshStaffApiKeyId, 'staff_browser_session_logout');

        if ($currentAccessStaffApiKeyId !== null && $currentAccessStaffApiKeyId > 0 && $currentAccessStaffApiKeyId !== $refreshStaffApiKeyId) {
            $this->revokeBestEffort($currentAccessStaffApiKeyId, 'staff_browser_access_logout');
        }

        AuditEvent::info('staff_browser_session_logged_out', [
            'refresh_staff_api_key_id' => (int) $record->getKey(),
            'access_staff_api_key_id' => $currentAccessStaffApiKeyId,
            'user_id' => (int) ($record->user_id ?? 0),
        ]);

        $payload = $this->authenticatedStaffPayloadBuilder->buildRevoked($record);
        $payload['auth_mode'] = 'staff_browser_session';
        $payload['session_transport'] = 'refresh_cookie';

        return $payload;
    }

    private function revokeBestEffort(int $staffApiKeyId, string $reason): void
    {
        try {
            $this->revokeStaffApiKeyHandler->handle($staffApiKeyId, $reason);
        } catch (Throwable $exception) {
            AuditEvent::warning('staff_browser_access_revoke_failed', [
                'staff_api_key_id' => $staffApiKeyId,
                'reason' => $reason,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

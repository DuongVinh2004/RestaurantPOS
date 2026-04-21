<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Application\UseCases\ApiKeys\RevokeStaffApiKeyHandler;
use App\Modules\IdentityAccess\Infrastructure\Internal\AuthenticatedStaffPayloadBuilder;
use App\Support\AuditEvent;

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
}

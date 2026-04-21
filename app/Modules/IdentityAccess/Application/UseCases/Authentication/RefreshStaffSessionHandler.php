<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Application\UseCases\ApiKeys\RotateStaffApiKeyHandler;
use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Infrastructure\Internal\AuthenticatedStaffPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RefreshStaffSessionHandler
{
    public function __construct(
        private readonly StaffApiKeyStore $staffApiKeyStore,
        private readonly RotateStaffApiKeyHandler $rotateStaffApiKeyHandler,
        private readonly AuthenticatedStaffPayloadBuilder $authenticatedStaffPayloadBuilder,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $staffApiKeyId): array
    {
        $current = $this->requireActiveStaffApiKey($staffApiKeyId);

        $rotated = $this->rotateStaffApiKeyHandler->handle(
            (int) $current->getKey(),
            (string) $current->label,
            $this->staffSessionExpiry(),
        );

        $replacement = $rotated['record'];
        $replacement->loadMissing('user.role');

        AuditEvent::info('staff_password_login_refreshed', [
            'user_id' => (int) ($replacement->user_id ?? 0),
            'revoked_staff_api_key_id' => (int) ($rotated['revoked']->getKey() ?? 0),
            'replacement_staff_api_key_id' => (int) $replacement->getKey(),
        ]);

        return $this->authenticatedStaffPayloadBuilder->build(
            $replacement,
            (string) $rotated['plaintext_key'],
        );
    }

    private function requireActiveStaffApiKey(int $staffApiKeyId): StaffApiKey
    {
        $record = $this->staffApiKeyStore->showKey($staffApiKeyId);

        if ($record->revoked_at !== null || ($record->expires_at !== null && ! $record->expires_at->utc()->isFuture())) {
            throw ValidationException::withMessages([
                'staff_api_key' => ['Staff auth session is no longer active.'],
            ]);
        }

        $record->loadMissing('user.role');

        return $record;
    }

    private function staffSessionExpiry(): Carbon
    {
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.session_ttl_minutes', 720)));
    }
}

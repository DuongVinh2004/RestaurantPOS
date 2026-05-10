<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Infrastructure\Internal\AuthenticatedStaffPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use Illuminate\Validation\ValidationException;

/**
 * Rehydrate phien staff hien tai:
 * khong issue token moi,
 * chi xac minh key con song va build lai payload actor cho client.
 */
class ShowCurrentStaffSessionHandler
{
    public function __construct(
        private readonly StaffApiKeyStore $staffApiKeyStore,
        private readonly AuthenticatedStaffPayloadBuilder $authenticatedStaffPayloadBuilder,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $staffApiKeyId): array
    {
        // Endpoint "me/current-session" di qua day: doc phien hien tai ma khong mutate session.
        return $this->authenticatedStaffPayloadBuilder->build(
            $this->requireActiveStaffApiKey($staffApiKeyId),
            null,
        );
    }

    private function requireActiveStaffApiKey(int $staffApiKeyId): StaffApiKey
    {
        // Dung chung gate active voi refresh flow de session da chet khong bao gio build duoc payload.
        $record = $this->staffApiKeyStore->showKey($staffApiKeyId);

        if ($record->revoked_at !== null || ($record->expires_at !== null && ! $record->expires_at->utc()->isFuture())) {
            throw ValidationException::withMessages([
                'staff_api_key' => ['Staff auth session is no longer active.'],
            ]);
        }

        $record->loadMissing('user.role');

        return $record;
    }
}

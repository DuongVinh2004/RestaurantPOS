<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\ApiKeys;

use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;

class RevokeStaffApiKeyHandler
{
    public function __construct(
        private readonly StaffApiKeyStore $staffApiKeyStore,
    ) {}

    public function handle(int $staffApiKeyId, ?string $reason = null): StaffApiKey
    {
        return $this->staffApiKeyStore->revokeKey($staffApiKeyId, $reason);
    }
}

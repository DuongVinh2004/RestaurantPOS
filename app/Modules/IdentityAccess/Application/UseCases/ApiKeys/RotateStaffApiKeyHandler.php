<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\ApiKeys;

use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use Carbon\CarbonInterface;

class RotateStaffApiKeyHandler
{
    public function __construct(
        private readonly StaffApiKeyStore $staffApiKeyStore,
    ) {}

    /**
     * @return array{
     *   revoked:\App\Modules\IdentityAccess\Domain\Models\StaffApiKey,
     *   record:\App\Modules\IdentityAccess\Domain\Models\StaffApiKey,
     *   plaintext_key:string
     * }
     */
    public function handle(int $staffApiKeyId, ?string $replacementLabel = null, ?CarbonInterface $expiresAt = null): array
    {
        return $this->staffApiKeyStore->rotateKey($staffApiKeyId, $replacementLabel, $expiresAt);
    }
}

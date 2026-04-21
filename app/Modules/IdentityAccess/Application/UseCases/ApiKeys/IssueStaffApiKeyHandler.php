<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\ApiKeys;

use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use Carbon\CarbonInterface;

class IssueStaffApiKeyHandler
{
    public function __construct(
        private readonly StaffApiKeyStore $staffApiKeyStore,
    ) {}

    /**
     * @return array{record:\App\Modules\IdentityAccess\Domain\Models\StaffApiKey, plaintext_key:string}
     */
    public function handle(int $userId, string $label, ?CarbonInterface $expiresAt = null): array
    {
        return $this->staffApiKeyStore->issueKey($userId, $label, $expiresAt);
    }
}

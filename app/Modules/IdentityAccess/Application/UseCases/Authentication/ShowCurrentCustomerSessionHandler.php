<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Domain\Models\CustomerAccessSession;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerAccessSessionPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use Illuminate\Validation\ValidationException;

class ShowCurrentCustomerSessionHandler
{
    public function __construct(
        private readonly CustomerAccessSessionStore $customerAccessSessionStore,
        private readonly CustomerAccessSessionPayloadBuilder $customerAccessSessionPayloadBuilder,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $accessSessionId): array
    {
        return $this->customerAccessSessionPayloadBuilder->build(
            $this->requireActiveCustomerAccessSession($accessSessionId),
            null,
        );
    }

    private function requireActiveCustomerAccessSession(int $accessSessionId): CustomerAccessSession
    {
        $session = $this->customerAccessSessionStore->showSession($accessSessionId);

        if ($session->revoked_at !== null || $session->expires_at === null || ! $session->expires_at->utc()->isFuture()) {
            throw ValidationException::withMessages([
                'access_session' => ['Customer access session is no longer active.'],
            ]);
        }

        $session->loadMissing('user.role');

        return $session;
    }
}

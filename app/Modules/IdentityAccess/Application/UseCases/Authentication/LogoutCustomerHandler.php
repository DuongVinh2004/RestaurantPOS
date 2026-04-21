<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Domain\Models\CustomerAccessSession;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerAccessSessionPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use App\Support\AuditEvent;
use Illuminate\Validation\ValidationException;

class LogoutCustomerHandler
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
        $session = $this->customerAccessSessionStore->revokeSession($accessSessionId);
        if (! $session instanceof CustomerAccessSession) {
            throw ValidationException::withMessages([
                'access_session' => ['Customer access session was not found.'],
            ]);
        }

        AuditEvent::info('customer_password_login_logged_out', [
            'access_session_id' => (int) $session->getKey(),
            'user_id' => (int) ($session->user_id ?? 0),
        ]);

        return $this->customerAccessSessionPayloadBuilder->buildRevoked($session);
    }
}

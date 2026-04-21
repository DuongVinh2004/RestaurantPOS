<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Infrastructure\Internal;

use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Support\Auth\RequestActorContext;
use Illuminate\Http\Request;

final class CustomerSessionRouteContract
{
    public function __construct(
        private readonly ReservationSessionAccessWorkflow $reservationSessionAccessWorkflow,
    ) {}

    public function allows(Request $request, ?RequestActorContext $actor = null): bool
    {
        $contract = $this->resolveContract($request);
        if ($contract === null) {
            return false;
        }

        $actor ??= RequestActorContext::fromRequest($request);
        $sessionId = $actor->sessionId();
        if ($sessionId === null) {
            return false;
        }

        if (! ((bool) ($contract['require_owned_hold'] ?? false))) {
            return true;
        }

        $holdId = trim((string) ($request->input('hold_id') ?? ''));
        if ($holdId === '') {
            return false;
        }

        return $this->reservationSessionAccessWorkflow->resolveUserIdFromOwnedHold($holdId, $sessionId) !== null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveContract(Request $request): ?array
    {
        $actionName = trim((string) ($request->route()?->getActionName() ?? ''));
        if ($actionName === '') {
            return null;
        }

        $contracts = (array) config('customer_auth.session_bound_route_contracts', []);
        $contract = $contracts[$actionName] ?? null;

        return is_array($contract) ? $contract : null;
    }
}

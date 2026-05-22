<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Application\Queries\CommandCenter\StaffCommandCenterHandler;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\FloorOperations\Http\Requests\Staff\CommandCenterRequest;
use Illuminate\Http\JsonResponse;

class CommandCenterController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffCommandCenterHandler $handler,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    public function index(CommandCenterRequest $request): JsonResponse
    {
        $actorUserId = $this->resolveStaffActorUserId($request);
        $filters = $request->validated();

        $requestedBranchId = isset($filters['branch_id']) ? (int) $filters['branch_id'] : null;

        $branchIds = $this->branchContextService->branchScopeOrAccessible(
            staffActorUserId: $actorUserId,
            requestedBranchId: $requestedBranchId,
            staffActorRoleId: (int) $request->attributes->get('staff_actor_role_id', 0) ?: null,
            staffActorRoleName: trim((string) $request->attributes->get('staff_actor_role_name', '')) ?: null,
        );

        if ($branchIds === []) {
            return response()->json([
                'data' => [
                    'summary' => [
                        'open_actions' => 0,
                        'high_priority' => 0,
                        'deposit_pending' => 0,
                        'preorder_pending' => 0,
                        'payment_pending' => 0,
                        'reservation_upcoming' => 0,
                    ],
                    'actions' => [],
                ],
                'meta' => ['limit' => $filters['limit'] ?? 50],
            ]);
        }

        $result = $this->handler->handle($branchIds, $filters);

        return response()->json([
            'data' => $result,
            'meta' => ['limit' => $filters['limit'] ?? 50],
        ]);
    }
}

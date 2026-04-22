<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\FloorOperations\Http\Resources\Staff\BranchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchContextController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actorUserId = $this->resolveStaffActorUserId($request);
        $branches = $this->branchContextService->accessibleBranches($actorUserId);
        $branchAccess = $this->branchContextService->branchAccessContext($actorUserId);

        return response()->json([
            'data' => BranchResource::collection($branches)->toArray($request),
            'meta' => [
                'action' => 'staff_branch_context',
                'count' => $branches->count(),
                'branch_access' => $branchAccess,
                'accessible_branch_ids' => $branchAccess['accessible_branch_ids'],
                'default_branch_id' => $branchAccess['default_branch_id'],
                'current_branch_id' => $branchAccess['current_branch_id'],
                'has_multi_branch_access' => $branchAccess['has_multi_branch_access'],
            ],
        ]);
    }
}

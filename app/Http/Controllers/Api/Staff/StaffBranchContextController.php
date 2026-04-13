<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Resources\BranchResource;
use App\Services\Staff\StaffBranchContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffBranchContextController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $branches = $this->branchContextService->accessibleBranches($this->resolveStaffActorUserId($request));

        return response()->json([
            'data' => BranchResource::collection($branches)->toArray($request),
            'meta' => [
                'action' => 'staff_branch_context',
                'count' => $branches->count(),
            ],
        ]);
    }
}

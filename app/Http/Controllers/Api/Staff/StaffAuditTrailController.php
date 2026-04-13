<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Requests\Staff\ListAuditTrailRequest;
use App\Services\AuditTrailQueryService;
use App\Services\Staff\StaffBranchContextService;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class StaffAuditTrailController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly AuditTrailQueryService $auditTrailQueryService,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    public function index(ListAuditTrailRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $actorUserId = $this->resolveStaffActorUserId($request);
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($branchId !== null) {
            try {
                $validated['branch_id'] = $this->branchContextService->assertAccessibleBranch($actorUserId, $branchId);
            } catch (ModelNotFoundException) {
                return ApiErrorResponse::json(
                    $request,
                    404,
                    'not_found',
                    'Branch not found.',
                );
            }
        }

        $paginator = $this->auditTrailQueryService->paginate($validated, $actorUserId);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'action' => 'staff_audit_trail_index',
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'filters' => $validated,
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\PrivacyAudit\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOps\Application\Services\StaffBranchContextService;
use App\Modules\PrivacyAudit\Application\Services\AuditTrailQueryService;
use App\Modules\PrivacyAudit\Http\Requests\Staff\ListAuditTrailRequest;
use App\Support\ApiErrorResponse;
use App\Support\Listing\ListingMetaFactory;
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
        $filterKeys = [
            'reservation_id',
            'order_id',
            'payment_id',
            'waiting_id',
            'table_id',
            'cashier_shift_id',
            'actor_user_id',
            'branch_id',
            'request_id',
            'q',
            'action',
            'actor_type',
            'subject_type',
            'subject_id',
            'date_from',
            'date_to',
        ];
        $filters = [];
        foreach ($filterKeys as $filterKey) {
            $filters[$filterKey] = $validated[$filterKey] ?? null;
        }
        $legacyAliases = [];
        foreach ($filterKeys as $filterKey) {
            $legacyAliases[$filterKey] = 'filter['.$filterKey.']';
        }

        return response()->json([
            'data' => $paginator->items(),
            'meta' => ListingMetaFactory::paginated($paginator, $filters, [
                'supported' => false,
                'value' => '-occurred_at',
                'by' => 'occurred_at',
                'dir' => 'desc',
            ], ListingMetaFactory::contract(
                $filterKeys,
                ['occurred_at', 'audit_id'],
                '-occurred_at',
                true,
                100,
                $legacyAliases,
            ), [
                'action' => 'staff_audit_trail_index',
                'page' => $paginator->currentPage(),
            ]),
        ]);
    }
}

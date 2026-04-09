<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListAuditTrailRequest;
use App\Services\AuditTrailQueryService;
use Illuminate\Http\JsonResponse;

class StaffAuditTrailController extends Controller
{
    public function __construct(
        private readonly AuditTrailQueryService $auditTrailQueryService,
    ) {}

    public function index(ListAuditTrailRequest $request): JsonResponse
    {
        $paginator = $this->auditTrailQueryService->paginate($request->validated());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'action' => 'staff_audit_trail_index',
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'filters' => $request->validated(),
            ],
        ]);
    }
}

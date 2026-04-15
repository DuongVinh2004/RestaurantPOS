<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Application\Services\ReportingSnapshotService;
use App\Modules\Reporting\Http\Requests\Admin\RebuildReportingSnapshotsRequest;
use Illuminate\Http\JsonResponse;

class AdminReportingController extends Controller
{
    public function __construct(
        private readonly ReportingSnapshotService $reportingSnapshotService,
    ) {
    }

    public function rebuild(RebuildReportingSnapshotsRequest $request): JsonResponse
    {
        $payload = $this->reportingSnapshotService->rebuild(
            $request->validated(),
            (int) ($request->attributes->get('staff_actor_user_id') ?? 0) ?: null,
        );

        return response()->json([
            'data' => $payload,
            'meta' => [
                'action' => 'admin_reporting_snapshots_rebuilt',
            ],
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Application\Queries\Operations\GetOperationsReportHandler;
use App\Modules\Reporting\Http\Concerns\BuildsReportingResponse;
use App\Modules\Reporting\Http\Requests\Staff\OperationsReportRequest;
use App\Modules\Reporting\Http\Resources\Staff\DailyOperationsSnapshotResource;
use Illuminate\Http\JsonResponse;

class OperationsReportController extends Controller
{
    use BuildsReportingResponse;

    public function __construct(
        private readonly GetOperationsReportHandler $getOperationsReportHandler,
    ) {}

    public function index(OperationsReportRequest $request): JsonResponse
    {
        $report = $this->getOperationsReportHandler->handle($request->validated());
        $paginator = $report['paginator'];

        return $this->paginatedReportResponse(
            DailyOperationsSnapshotResource::collection(collect($paginator->items()))->toArray($request),
            'staff_reporting_daily_operations_index',
            $paginator,
            $request->validated(),
            $report['snapshot_health'],
            ['branch_id', 'start_date', 'end_date'],
            ['business_date', 'branch_id', 'scheduled_reservation_count', 'completed_count', 'waiting_list_created_count', 'waiting_list_seated_count'],
        );
    }
}

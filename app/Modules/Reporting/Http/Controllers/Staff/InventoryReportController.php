<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Application\Queries\Inventory\GetInventoryReportHandler;
use App\Modules\Reporting\Http\Concerns\BuildsReportingResponse;
use App\Modules\Reporting\Http\Requests\Staff\InventoryReportRequest;
use App\Modules\Reporting\Http\Resources\Staff\DailyInventoryMovementSnapshotResource;
use Illuminate\Http\JsonResponse;

class InventoryReportController extends Controller
{
    use BuildsReportingResponse;

    public function __construct(
        private readonly GetInventoryReportHandler $getInventoryReportHandler,
    ) {}

    public function index(InventoryReportRequest $request): JsonResponse
    {
        $report = $this->getInventoryReportHandler->handle($request->validated());
        $paginator = $report['paginator'];

        return $this->paginatedReportResponse(
            DailyInventoryMovementSnapshotResource::collection(collect($paginator->items()))->toArray($request),
            'staff_reporting_daily_inventory_index',
            $paginator,
            $request->validated(),
            $report['snapshot_health'],
            ['branch_id', 'ingredient_id', 'start_date', 'end_date'],
            ['business_date', 'branch_id', 'ingredient_id', 'movement_count', 'net_quantity_delta', 'last_movement_at'],
        );
    }
}

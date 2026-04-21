<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Application\Queries\Sales\GetSalesReportHandler;
use App\Modules\Reporting\Http\Concerns\BuildsReportingResponse;
use App\Modules\Reporting\Http\Requests\Staff\SalesReportRequest;
use App\Modules\Reporting\Http\Resources\Staff\DailySalesSnapshotResource;
use Illuminate\Http\JsonResponse;

class SalesReportController extends Controller
{
    use BuildsReportingResponse;

    public function __construct(
        private readonly GetSalesReportHandler $getSalesReportHandler,
    ) {}

    public function index(SalesReportRequest $request): JsonResponse
    {
        $report = $this->getSalesReportHandler->handle($request->validated());
        $paginator = $report['paginator'];

        return $this->paginatedReportResponse(
            DailySalesSnapshotResource::collection(collect($paginator->items()))->toArray($request),
            'staff_reporting_daily_sales_index',
            $paginator,
            $request->validated(),
            $report['snapshot_health'],
            ['branch_id', 'currency', 'start_date', 'end_date'],
            ['business_date', 'branch_id', 'currency', 'gross_bill_amount', 'net_paid_amount', 'billed_reservation_count'],
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ListDailyInventoryReportingRequest;
use App\Http\Requests\Staff\ListDailyOperationReportingRequest;
use App\Http\Requests\Staff\ListDailySalesReportingRequest;
use App\Http\Resources\ReportingDailyInventoryMovementSnapshotResource;
use App\Http\Resources\ReportingDailyOperationSnapshotResource;
use App\Http\Resources\ReportingDailySalesSnapshotResource;
use App\Services\Reporting\ReportingSnapshotService;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class StaffReportingController extends Controller
{
    public function __construct(
        private readonly ReportingSnapshotService $reportingSnapshotService,
    ) {
    }

    public function dailySales(ListDailySalesReportingRequest $request): JsonResponse
    {
        $paginator = $this->reportingSnapshotService->paginateDailySales($request->validated());

        return $this->reportingJson(
            ReportingDailySalesSnapshotResource::collection(collect($paginator->items()))->toArray($request),
            'staff_reporting_daily_sales_index',
            $paginator,
            $request->validated(),
            $this->reportingSnapshotService->filteredSnapshotHealth('sales', $request->validated()),
            ['branch_id', 'currency', 'start_date', 'end_date'],
            ['business_date', 'branch_id', 'currency', 'gross_bill_amount', 'net_paid_amount', 'billed_reservation_count'],
        );
    }

    public function dailyOperations(ListDailyOperationReportingRequest $request): JsonResponse
    {
        $paginator = $this->reportingSnapshotService->paginateDailyOperations($request->validated());

        return $this->reportingJson(
            ReportingDailyOperationSnapshotResource::collection(collect($paginator->items()))->toArray($request),
            'staff_reporting_daily_operations_index',
            $paginator,
            $request->validated(),
            $this->reportingSnapshotService->filteredSnapshotHealth('operations', $request->validated()),
            ['branch_id', 'start_date', 'end_date'],
            ['business_date', 'branch_id', 'scheduled_reservation_count', 'completed_count', 'waiting_list_created_count', 'waiting_list_seated_count'],
        );
    }

    public function dailyInventory(ListDailyInventoryReportingRequest $request): JsonResponse
    {
        $paginator = $this->reportingSnapshotService->paginateDailyInventory($request->validated());

        return $this->reportingJson(
            ReportingDailyInventoryMovementSnapshotResource::collection(collect($paginator->items()))->toArray($request),
            'staff_reporting_daily_inventory_index',
            $paginator,
            $request->validated(),
            $this->reportingSnapshotService->filteredSnapshotHealth('inventory', $request->validated()),
            ['branch_id', 'ingredient_id', 'start_date', 'end_date'],
            ['business_date', 'branch_id', 'ingredient_id', 'movement_count', 'net_quantity_delta', 'last_movement_at'],
        );
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<string,mixed>  $filters
     * @param  list<string>  $filterKeys
     * @param  list<string>  $sortFields
     */
    private function reportingJson(array $rows, string $action, LengthAwarePaginator $paginator, array $filters, array $snapshotHealth, array $filterKeys, array $sortFields): JsonResponse
    {
        return response()->json([
            'data' => $rows,
            'meta' => ListingMetaFactory::paginated(
                $paginator,
                array_intersect_key($filters, array_flip($filterKeys)),
                [
                    'supported' => true,
                    'value' => (string) ($filters['sort'] ?? '-business_date'),
                    'by' => (string) ($filters['sort_by'] ?? 'business_date'),
                    'dir' => (string) ($filters['sort_dir'] ?? 'desc'),
                ],
                ListingMetaFactory::contract(
                    $filterKeys,
                    $sortFields,
                    '-business_date',
                    true,
                    100,
                    array_merge(
                        array_combine($filterKeys, array_map(static fn (string $key): string => 'filter[' . $key . ']', $filterKeys)) ?: [],
                        [
                            'sort_by' => 'sort',
                            'sort_dir' => 'sort',
                        ],
                    ),
                ),
                [
                    'action' => $action,
                    'snapshot_health' => $snapshotHealth,
                ],
            ),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }
}

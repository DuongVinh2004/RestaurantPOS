<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Queries\Sales;

use App\Modules\Reporting\Application\Workflows\ReportingSnapshotWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetSalesReportHandler
{
    public function __construct(
        private readonly ReportingSnapshotWorkflow $reportingSnapshotWorkflow,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return array{paginator:LengthAwarePaginator,snapshot_health:array<string,mixed>}
     */
    public function handle(
        array $filters = [],
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        $scopedFilters = $this->reportingSnapshotWorkflow->scopeFiltersForStaff(
            $filters,
            $staffActorUserId,
            $staffActorRoleId,
            $staffActorRoleName,
        );

        return [
            'paginator' => $this->reportingSnapshotWorkflow->paginateDailySales($scopedFilters),
            'snapshot_health' => $this->reportingSnapshotWorkflow->filteredSnapshotHealth('sales', $scopedFilters),
        ];
    }
}

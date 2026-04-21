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
    public function handle(array $filters = []): array
    {
        return [
            'paginator' => $this->reportingSnapshotWorkflow->paginateDailySales($filters),
            'snapshot_health' => $this->reportingSnapshotWorkflow->filteredSnapshotHealth('sales', $filters),
        ];
    }
}

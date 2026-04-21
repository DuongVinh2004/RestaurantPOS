<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Queries\Operations;

use App\Modules\Reporting\Application\Workflows\ReportingSnapshotWorkflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetOperationsReportHandler
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
            'paginator' => $this->reportingSnapshotWorkflow->paginateDailyOperations($filters),
            'snapshot_health' => $this->reportingSnapshotWorkflow->filteredSnapshotHealth('operations', $filters),
        ];
    }
}

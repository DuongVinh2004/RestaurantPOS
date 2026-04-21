<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Concerns;

use App\Support\Listing\ListingMetaFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait BuildsReportingResponse
{
    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<string,mixed>  $filters
     * @param  array<string,mixed>  $snapshotHealth
     * @param  list<string>  $filterKeys
     * @param  list<string>  $sortFields
     */
    protected function paginatedReportResponse(
        array $rows,
        string $action,
        LengthAwarePaginator $paginator,
        array $filters,
        array $snapshotHealth,
        array $filterKeys,
        array $sortFields,
    ): JsonResponse {
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
                        array_combine($filterKeys, array_map(static fn (string $key): string => 'filter['.$key.']', $filterKeys)) ?: [],
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

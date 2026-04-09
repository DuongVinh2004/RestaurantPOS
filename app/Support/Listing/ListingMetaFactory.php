<?php

declare(strict_types=1);

namespace App\Support\Listing;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListingMetaFactory
{
    /**
     * @param list<string> $allowedFilters
     * @param list<string> $allowedSorts
     * @param array<string, string> $legacyAliases
     * @return array<string, mixed>
     */
    public static function contract(
        array $allowedFilters,
        array $allowedSorts,
        ?string $defaultSort,
        bool $supportsPagination = true,
        int $maxPerPage = 100,
        array $legacyAliases = [],
    ): array {
        return [
            'parameters' => [
                'filter' => 'filter[{key}]',
                'sort' => 'sort',
                'page' => $supportsPagination ? 'page' : null,
                'per_page' => $supportsPagination ? 'per_page' : null,
            ],
            'filter_keys' => array_values($allowedFilters),
            'sort_fields' => array_values($allowedSorts),
            'default_sort' => $defaultSort,
            'pagination' => [
                'supported' => $supportsPagination,
                'max_per_page' => $supportsPagination ? $maxPerPage : null,
            ],
            'legacy_aliases' => $legacyAliases,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $sort
     * @param array<string, mixed> $contract
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        array $filters,
        array $sort,
        array $contract,
        array $extra = [],
    ): array {
        $pagination = self::paginationDetails($paginator);

        return array_merge([
            'filters' => $filters,
            'sort' => $sort,
            'pagination' => $pagination,
            'current_page' => $pagination['current_page'],
            'per_page' => $pagination['per_page'],
            'from' => $pagination['from'],
            'to' => $pagination['to'],
            'total' => $pagination['total'],
            'last_page' => $pagination['last_page'],
            'has_more_pages' => $pagination['has_more_pages'],
            'query_contract' => $contract,
        ], $extra);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $sort
     * @param array<string, mixed> $contract
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function legacyCollection(
        int $count,
        array $filters,
        array $sort,
        array $contract,
        array $extra = [],
    ): array {
        $pagination = [
            'mode' => 'legacy_unbounded',
            'current_page' => 1,
            'per_page' => $count,
            'from' => $count > 0 ? 1 : null,
            'to' => $count > 0 ? $count : null,
            'total' => $count,
            'last_page' => 1,
            'has_more_pages' => false,
        ];

        return array_merge([
            'filters' => $filters,
            'sort' => $sort,
            'pagination' => $pagination,
            'current_page' => $pagination['current_page'],
            'per_page' => $pagination['per_page'],
            'from' => $pagination['from'],
            'to' => $pagination['to'],
            'total' => $pagination['total'],
            'last_page' => $pagination['last_page'],
            'has_more_pages' => $pagination['has_more_pages'],
            'query_contract' => $contract,
        ], $extra);
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $sort
     * @param array<string, mixed> $contract
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function collection(
        array $filters,
        array $sort,
        array $contract,
        array $extra = [],
    ): array {
        return array_merge([
            'filters' => $filters,
            'sort' => $sort,
            'pagination' => [
                'mode' => 'none',
                'supported' => false,
            ],
            'query_contract' => $contract,
        ], $extra);
    }

    /**
     * @return array<string, int|bool|null>
     */
    private static function paginationDetails(LengthAwarePaginator $paginator): array
    {
        return [
            'mode' => 'paged',
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more_pages' => $paginator->hasMorePages(),
        ];
    }
}

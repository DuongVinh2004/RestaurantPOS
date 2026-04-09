<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait InteractsWithListingQuery
{
    public function wantsListingPagination(): bool
    {
        return $this->query->has('page') || $this->query->has('per_page');
    }

    /**
     * @return array{page:int,per_page:int}
     */
    protected function normalizeListingPagination(int $defaultPerPage = 25, int $maxPerPage = 100): array
    {
        $pageInput = $this->input('page');
        $perPageInput = $this->input('per_page');
        $page = ($pageInput === null || $pageInput === '')
            ? 1
            : (int) $pageInput;
        $perPage = ($perPageInput === null || $perPageInput === '')
            ? $defaultPerPage
            : (int) $perPageInput;

        return [
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param list<string> $allowedFields
     * @return array{sort:string,sort_by:string,sort_dir:string}
     */
    protected function normalizeListingSort(string $defaultField, array $allowedFields, string $defaultDirection = 'asc'): array
    {
        $sort = trim((string) $this->input('sort', ''));
        $sortByInput = trim((string) $this->input('sort_by', ''));
        $sortDirInput = strtolower(trim((string) $this->input('sort_dir', '')));

        if ($sort !== '') {
            $sortDir = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $sortBy = ltrim($sort, '-');
        } else {
            $sortBy = $sortByInput !== '' ? $sortByInput : $defaultField;
            $sortDir = $sortDirInput !== '' ? $sortDirInput : strtolower($defaultDirection);
        }

        if ($sort === '' && $sortByInput === '' && $sortDirInput === '') {
            $sortBy = in_array($defaultField, $allowedFields, true) ? $defaultField : $allowedFields[0];
            $sortDir = strtolower($defaultDirection) === 'desc' ? 'desc' : 'asc';
        }

        if ($sortDir === '') {
            $sortDir = strtolower($defaultDirection) === 'desc' ? 'desc' : 'asc';
        }

        return [
            'sort' => $sortDir === 'desc' ? '-' . $sortBy : $sortBy,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];
    }

    /**
     * @param list<string> $allowedFields
     * @return list<string>
     */
    protected function listingSortRuleValues(array $allowedFields): array
    {
        $values = [];

        foreach ($allowedFields as $field) {
            $values[] = $field;
            $values[] = '-' . $field;
        }

        return $values;
    }

    /**
     * @param list<string> $allowedKeys
     * @return list<string>
     */
    protected function listingFilterContainerRules(array $allowedKeys): array
    {
        if ($allowedKeys === []) {
            return ['sometimes', 'array'];
        }

        return ['sometimes', 'array:' . implode(',', $allowedKeys)];
    }

    protected function listingFilterInput(string $key, mixed $default = null): mixed
    {
        $filter = $this->input('filter');
        if (is_array($filter) && array_key_exists($key, $filter)) {
            return $filter[$key];
        }

        $filters = $this->input('filters');
        if (is_array($filters) && array_key_exists($key, $filters)) {
            return $filters[$key];
        }

        return $this->input($key, $default);
    }

    protected function normalizeListingString(string $key, ?string $default = null, bool $lowercase = false, bool $uppercase = false): ?string
    {
        $value = $this->listingFilterInput($key, $default);

        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if ($lowercase) {
            $normalized = strtolower($normalized);
        }

        if ($uppercase) {
            $normalized = strtoupper($normalized);
        }

        return $normalized;
    }

    protected function normalizeListingInteger(string $key): ?int
    {
        $value = $this->listingFilterInput($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function normalizeListingBoolean(string $key, ?bool $default = null, bool $defaultWhenMissing = false): ?bool
    {
        if (! $this->has($key) && ! $this->has('filter.' . $key) && ! $this->has('filters.' . $key)) {
            return $defaultWhenMissing ? $default : null;
        }

        if ($this->has('filter.' . $key)) {
            return $this->boolean('filter.' . $key);
        }

        if ($this->has('filters.' . $key)) {
            return $this->boolean('filters.' . $key);
        }

        return $this->boolean($key);
    }
}

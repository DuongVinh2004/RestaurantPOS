<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListAdminMenuItemPricesRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'as_of',
        'currency',
    ];

    private const SORT_FIELDS = [
        'effective_from',
        'effective_to',
        'price',
        'price_id',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pagination = $this->normalizeListingPagination(25, 100);
        $sort = $this->normalizeListingSort('effective_from', self::SORT_FIELDS, 'desc');

        $this->merge([
            'as_of' => $this->normalizeListingString('as_of'),
            'currency' => $this->normalizeListingString('currency', null, false, true),
            ...$pagination,
            ...$sort,
        ]);
    }

    public function rules(): array
    {
        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'as_of' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'max:10'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:' . implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:' . implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

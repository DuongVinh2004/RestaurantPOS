<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminMenuItemsRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'category_id',
        'is_available',
        'q',
        'as_of',
    ];

    private const SORT_FIELDS = [
        'name',
        'code',
        'item_id',
        'category_id',
        'updated_at',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pagination = $this->normalizeListingPagination(25, 100);
        $sort = $this->normalizeListingSort('name', self::SORT_FIELDS, 'asc');

        $this->merge([
            'category_id' => $this->normalizeListingInteger('category_id'),
            'is_available' => $this->normalizeListingBoolean('is_available'),
            'q' => $this->normalizeListingString('q'),
            'as_of' => $this->normalizeListingString('as_of'),
            ...$pagination,
            ...$sort,
        ]);
    }

    public function rules(): array
    {
        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::exists('menu_categories', 'category_id')],
            'is_available' => ['sometimes', 'nullable', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'as_of' => ['sometimes', 'nullable', 'date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:' . implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:' . implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

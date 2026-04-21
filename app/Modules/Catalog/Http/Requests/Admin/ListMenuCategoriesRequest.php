<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Support\Listing\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListMenuCategoriesRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'include_deleted',
        'q',
    ];

    private const SORT_FIELDS = [
        'sort_order',
        'name',
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
        $sort = $this->normalizeListingSort('sort_order', self::SORT_FIELDS, 'asc');

        $this->merge([
            'include_deleted' => $this->normalizeListingBoolean('include_deleted', false, true),
            'q' => $this->normalizeListingString('q'),
            ...$pagination,
            ...$sort,
        ]);
    }

    public function rules(): array
    {
        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'include_deleted' => ['sometimes', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:' . implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:' . implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

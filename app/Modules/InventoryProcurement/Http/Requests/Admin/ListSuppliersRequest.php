<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Requests\Admin;

use App\Support\Listing\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListSuppliersRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'is_active',
        'q',
    ];

    private const SORT_FIELDS = [
        'name',
        'code',
        'supplier_id',
        'updated_at',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pagination = $this->normalizeListingPagination(
            (int) config('booking.admin_inventory_page_default', 25),
            (int) config('booking.admin_inventory_page_max', 100),
        );
        $sort = $this->normalizeListingSort('name', self::SORT_FIELDS, 'asc');

        $this->merge([
            'is_active' => $this->normalizeListingBoolean('is_active'),
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
            'is_active' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('booking.admin_inventory_page_max', 100)],
            'sort' => ['nullable', 'string', 'in:'.implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:'.implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

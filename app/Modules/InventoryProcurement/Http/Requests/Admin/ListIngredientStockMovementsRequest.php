<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Http\Requests\Admin;

use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use App\Support\Listing\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListIngredientStockMovementsRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'movement_type',
        'branch_id',
    ];

    private const SORT_FIELDS = [
        'created_at',
        'movement_id',
        'movement_type',
        'quantity_delta',
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
        $sort = $this->normalizeListingSort('created_at', self::SORT_FIELDS, 'desc');

        $this->merge([
            'movement_type' => $this->normalizeListingString('movement_type'),
            'branch_id' => $this->normalizeListingInteger('branch_id'),
            ...$pagination,
            ...$sort,
        ]);
    }

    public function rules(): array
    {
        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'movement_type' => ['nullable', 'string', Rule::in(IngredientStockMovement::supportedTypes())],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('booking.admin_inventory_page_max', 100)],
            'sort' => ['nullable', 'string', 'in:'.implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:'.implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

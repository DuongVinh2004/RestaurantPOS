<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\Concerns\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAdminPurchaseOrdersRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'supplier_id',
        'branch_id',
        'purchase_order_status',
        'q',
    ];

    private const SORT_FIELDS = [
        'created_at',
        'ordered_at',
        'expected_at',
        'purchase_order_id',
        'purchase_order_status',
        'supplier_id',
        'branch_id',
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
            'supplier_id' => $this->normalizeListingInteger('supplier_id'),
            'branch_id' => $this->normalizeListingInteger('branch_id'),
            'purchase_order_status' => $this->normalizeListingString('purchase_order_status'),
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
            'supplier_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'purchase_order_status' => ['nullable', 'string', Rule::in(PurchaseOrderStatus::values())],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . (int) config('booking.admin_inventory_page_max', 100)],
            'sort' => ['nullable', 'string', 'in:' . implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:' . implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

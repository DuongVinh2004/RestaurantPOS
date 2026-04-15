<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Requests\Staff;

use App\Http\Requests\Concerns\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListStaffCashierShiftsRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'status',
        'branch_id',
        'shift_code',
        'terminal_code',
        'q',
    ];

    private const SORT_FIELDS = [
        'opened_at',
        'closed_at',
        'cashier_shift_id',
        'shift_code',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pagination = $this->normalizeListingPagination(12, 100);
        $sort = $this->normalizeListingSort('opened_at', self::SORT_FIELDS, 'desc');
        $status = $this->normalizeListingString('status', null, true);

        $this->merge([
            'status' => match ($status) {
                'open' => 'Open',
                'closed' => 'Closed',
                default => $status,
            },
            'branch_id' => $this->normalizeListingInteger('branch_id'),
            'shift_code' => $this->normalizeListingString('shift_code'),
            'terminal_code' => $this->normalizeListingString('terminal_code'),
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
            'status' => ['nullable', 'string', 'in:Open,Closed'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'shift_code' => ['nullable', 'string', 'max:80'],
            'terminal_code' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:'.implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

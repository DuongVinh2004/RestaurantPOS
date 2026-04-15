<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests\Staff;

use App\Http\Requests\Concerns\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListDailyOperationReportingRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'branch_id',
        'start_date',
        'end_date',
    ];

    private const SORT_FIELDS = [
        'business_date',
        'branch_id',
        'scheduled_reservation_count',
        'completed_count',
        'waiting_list_created_count',
        'waiting_list_seated_count',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pagination = $this->normalizeListingPagination(25, 100);
        $sort = $this->normalizeListingSort('business_date', self::SORT_FIELDS, 'desc');

        $this->merge([
            'branch_id' => $this->normalizeListingInteger('branch_id'),
            'start_date' => $this->normalizeListingString('start_date'),
            'end_date' => $this->normalizeListingString('end_date'),
            ...$pagination,
            ...$sort,
        ]);
    }

    public function rules(): array
    {
        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'in:' . implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:' . implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

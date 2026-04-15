<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Http\Requests\Staff;

use App\Http\Requests\Concerns\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListWaitingListRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'status',
        'active_only',
        'phone',
        'guest_name',
        'branch_id',
    ];

    private const SORT_FIELDS = [
        'priority',
        'requested_at',
        'notified_at',
        'guest_name',
        'guest_count',
        'waiting_id',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $pagination = $this->normalizeListingPagination(25, 100);
        $sort = $this->normalizeListingSort('priority', self::SORT_FIELDS, 'desc');

        $this->merge([
            'status' => $this->normalizeListingString('status'),
            'active_only' => $this->normalizeListingBoolean('active_only', true, true),
            'phone' => $this->normalizeListingString('phone'),
            'guest_name' => $this->normalizeListingString('guest_name'),
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
            'status' => ['nullable', 'string', 'in:Waiting,Notified,Seated,Cancelled'],
            'active_only' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30'],
            'guest_name' => ['nullable', 'string', 'max:200'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:' . implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:' . implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}

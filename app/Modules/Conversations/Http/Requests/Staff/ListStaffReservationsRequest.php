<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Requests\Staff;

use App\Enums\ReservationDepositIntentStatus;
use App\Enums\ReservationStatus;
use App\Support\Listing\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;

class ListStaffReservationsRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'bucket',
        'status',
        'reservation_code',
        'source',
        'q',
        'phone',
        'deposit_acknowledged',
        'deposit_intent_status',
        'user_id',
        'table_id',
        'start_from',
        'start_to',
        'include_financials',
    ];

    private const SORT_FIELDS = [
        'start_time',
        'end_time',
        'created_at',
        'updated_at',
        'reservation_id',
        'guest_count',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $bucket = $this->normalizeListingString('bucket', 'upcoming', true) ?? 'upcoming';
        $defaultSortDir = $bucket === 'history' ? 'desc' : 'asc';
        $pagination = $this->normalizeListingPagination(25, 100);
        $sort = $this->normalizeListingSort('start_time', self::SORT_FIELDS, $defaultSortDir);

        $this->merge([
            'bucket' => $bucket,
            'status' => $this->normalizeListingString('status'),
            'include_financials' => $this->normalizeListingBoolean('include_financials', false, true),
            'reservation_code' => $this->normalizeListingString('reservation_code'),
            'phone' => $this->normalizeListingString('phone'),
            'q' => $this->normalizeListingString('q'),
            'source' => $this->normalizeListingString('source'),
            'deposit_acknowledged' => $this->normalizeListingBoolean('deposit_acknowledged'),
            'deposit_intent_status' => $this->normalizeListingString('deposit_intent_status'),
            'user_id' => $this->normalizeListingInteger('user_id'),
            'table_id' => $this->normalizeListingInteger('table_id'),
            'start_from' => $this->normalizeListingString('start_from'),
            'start_to' => $this->normalizeListingString('start_to'),
            ...$sort,
            ...$pagination,
        ]);
    }

    public function rules(): array
    {
        $statuses = array_map(static fn (ReservationStatus $status): string => $status->value, ReservationStatus::cases());
        $depositIntentStatuses = ReservationDepositIntentStatus::values();

        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'bucket' => ['nullable', 'string', 'in:upcoming,history,all,today'],
            'status' => ['nullable', 'string', 'in:'.implode(',', $statuses)],
            'reservation_code' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'deposit_acknowledged' => ['nullable', 'boolean'],
            'deposit_intent_status' => ['nullable', 'string', 'in:'.implode(',', $depositIntentStatuses)],
            'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,user_id'],
            'table_id' => ['nullable', 'integer', 'min:1', 'exists:restaurant_tables,table_id'],
            'start_from' => ['nullable', 'date'],
            'start_to' => ['nullable', 'date', 'after_or_equal:start_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:'.implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'include_financials' => ['nullable', 'boolean'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Http\Requests\Staff;

use App\Enums\ReservationStatus;
use App\Support\Listing\InteractsWithListingQuery;
use App\Modules\FloorOperations\Http\Requests\Staff\BranchScopeRequest;

class ListFinancialReconciliationRequest extends BranchScopeRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'branch_id',
        'reservation_id',
        'reservation_code',
        'user_id',
        'status',
        'deposit_status',
        'payment_currency',
        'cashier_user_id',
        'activity_from',
        'activity_to',
        'has_discrepancy',
    ];

    private const SORT_FIELDS = [
        'reservation_id',
        'start_time',
        'updated_at',
        'final_bill_amount',
        'net_paid_amount',
        'refunded_amount',
        'last_payment_activity_at',
    ];

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $pagination = $this->normalizeListingPagination(25, 100);
        $sort = $this->normalizeListingSort('last_payment_activity_at', self::SORT_FIELDS, 'desc');
        $limitInput = $this->input('limit');
        $limit = ($limitInput === null || $limitInput === '')
            ? 500
            : (int) $limitInput;

        $this->merge([
            'reservation_id' => $this->normalizeListingInteger('reservation_id'),
            'reservation_code' => $this->normalizeListingString('reservation_code'),
            'user_id' => $this->normalizeListingInteger('user_id'),
            'status' => $this->normalizeListingString('status'),
            'deposit_status' => $this->normalizeListingString('deposit_status'),
            'payment_currency' => $this->normalizeListingString('payment_currency', null, false, true),
            'cashier_user_id' => $this->normalizeListingInteger('cashier_user_id'),
            'activity_from' => $this->normalizeListingString('activity_from'),
            'activity_to' => $this->normalizeListingString('activity_to'),
            'format' => strtolower(trim((string) $this->input('format', 'csv'))),
            'has_discrepancy' => $this->normalizeListingBoolean('has_discrepancy'),
            ...$sort,
            ...$pagination,
            'limit' => $limit,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            ...parent::rules(),
            'reservation_id' => ['nullable', 'integer', 'min:1', 'exists:reservations,reservation_id'],
            'reservation_code' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,user_id'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_map(static fn (ReservationStatus $status): string => $status->value, ReservationStatus::cases()))],
            'deposit_status' => ['nullable', 'string', 'in:NotRequired,Pending,Paid,Refunded,PartiallyRefunded,Forfeited'],
            'payment_currency' => ['nullable', 'string', 'max:10'],
            'cashier_user_id' => ['nullable', 'integer', 'min:1', 'exists:users,user_id'],
            'activity_from' => ['nullable', 'date'],
            'activity_to' => ['nullable', 'date', 'after_or_equal:activity_from'],
            'has_discrepancy' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', $this->listingSortRuleValues(self::SORT_FIELDS))],
            'sort_by' => ['nullable', 'string', 'in:'.implode(',', self::SORT_FIELDS)],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'format' => ['nullable', 'string', 'in:json,csv'],
        ];
    }
}



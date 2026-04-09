<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Enums\ReservationDepositIntentStatus;
use App\Enums\ReservationStatus;
use App\Http\Requests\Concerns\InteractsWithListingQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class ReservationTimelineRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'date',
        'start_date',
        'end_date',
        'from_time',
        'to_time',
        'status',
        'table_id',
        'zone',
        'q',
        'deposit_acknowledged',
        'deposit_intent_status',
        'slot_minutes',
        'lane_by',
        'include_candidate_tables',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $today = Carbon::now((string) config('app.timezone', 'UTC'))->toDateString();
        $date = $this->normalizeListingString('date');
        $startDate = $this->normalizeListingString('start_date');
        $endDate = $this->normalizeListingString('end_date');
        $slotMinutesInput = $this->listingFilterInput('slot_minutes');

        if ($date !== null && $date !== '') {
            $startDate = $date;
            $endDate = $date;
        }

        if ($startDate === null || $startDate === '') {
            $startDate = $today;
        }

        if ($endDate === null || $endDate === '') {
            $endDate = $startDate;
        }

        $this->merge([
            'date' => $date,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'from_time' => $this->normalizeListingString('from_time'),
            'to_time' => $this->normalizeListingString('to_time'),
            'status' => $this->normalizeListingString('status'),
            'table_id' => $this->normalizeListingInteger('table_id'),
            'zone' => $this->normalizeListingString('zone'),
            'q' => $this->normalizeListingString('q'),
            'deposit_acknowledged' => $this->normalizeListingBoolean('deposit_acknowledged'),
            'deposit_intent_status' => $this->normalizeListingString('deposit_intent_status'),
            'slot_minutes' => ($slotMinutesInput === null || $slotMinutesInput === '') ? 30 : (int) $slotMinutesInput,
            'lane_by' => $this->normalizeListingString('lane_by', 'slot', true) ?? 'slot',
            'include_candidate_tables' => $this->normalizeListingBoolean('include_candidate_tables', false, true),
        ]);
    }

    public function rules(): array
    {
        $statuses = array_map(static fn (ReservationStatus $status): string => $status->value, ReservationStatus::cases());
        $depositIntentStatuses = ReservationDepositIntentStatus::values();

        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'date' => ['nullable', 'date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'from_time' => ['nullable', 'date_format:H:i'],
            'to_time' => ['nullable', 'date_format:H:i', 'after:from_time'],
            'status' => ['nullable', 'string', 'in:' . implode(',', $statuses)],
            'table_id' => ['nullable', 'integer', 'min:1', 'exists:restaurant_tables,table_id'],
            'zone' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:120'],
            'deposit_acknowledged' => ['nullable', 'boolean'],
            'deposit_intent_status' => ['nullable', 'string', 'in:' . implode(',', $depositIntentStatuses)],
            'slot_minutes' => ['nullable', 'integer', 'in:15,30,60'],
            'lane_by' => ['nullable', 'string', 'in:slot,zone,table'],
            'include_candidate_tables' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');
            if (! is_string($startDate) || ! is_string($endDate) || $startDate === '' || $endDate === '') {
                return;
            }

            try {
                $from = Carbon::parse($startDate, 'UTC')->startOfDay();
                $to = Carbon::parse($endDate, 'UTC')->startOfDay();
            } catch (\Throwable) {
                return;
            }

            if ($from->diffInDays($to) > 7) {
                $validator->errors()->add('end_date', 'Timeline range cannot exceed 7 days.');
            }
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Requests\Staff;

use App\Support\Listing\InteractsWithListingQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class TableBoardRequest extends FormRequest
{
    use InteractsWithListingQuery;

    private const FILTER_KEYS = [
        'date',
        'from',
        'to',
        'branch_id',
        'zone',
        'include_holds',
        'group_by',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $timezone = (string) config('booking.multi_branch.default_branch_timezone', 'Asia/Ho_Chi_Minh');
        $date = $this->listingFilterInput('date');
        $from = $this->listingFilterInput('from');
        $to = $this->listingFilterInput('to');

        if (($from === null || $from === '') && is_string($date) && trim($date) !== '') {
            $from = Carbon::parse($date, $timezone)->startOfDay()->utc()->toDateTimeString();
        }

        if (($to === null || $to === '') && is_string($date) && trim($date) !== '') {
            $to = Carbon::parse($date, $timezone)->endOfDay()->utc()->toDateTimeString();
        }

        if ($from === null || $from === '') {
            $from = Carbon::now($timezone)->startOfDay()->utc()->toDateTimeString();
        }

        if ($to === null || $to === '') {
            $to = Carbon::now($timezone)->endOfDay()->utc()->toDateTimeString();
        }

        $this->merge([
            'date' => $this->normalizeListingString('date'),
            'from' => $from,
            'to' => $to,
            'branch_id' => $this->normalizeListingInteger('branch_id'),
            'zone' => $this->normalizeListingString('zone'),
            'include_holds' => $this->normalizeListingBoolean('include_holds', true, true),
            'group_by' => $this->normalizeListingString('group_by'),
        ]);
    }

    public function rules(): array
    {
        return [
            'filter' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'filters' => $this->listingFilterContainerRules(self::FILTER_KEYS),
            'date' => ['nullable', 'date'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'zone' => ['nullable', 'string', 'max:50'],
            'include_holds' => ['nullable', 'boolean'],
            'group_by' => ['nullable', 'string', 'in:zone,capacity,zone_capacity,status'],
        ];
    }
}



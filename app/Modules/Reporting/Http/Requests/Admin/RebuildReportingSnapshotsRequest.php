<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RebuildReportingSnapshotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->filled('branch_id') ? (int) $this->input('branch_id') : null,
            'include_sales' => $this->has('include_sales') ? $this->boolean('include_sales') : true,
            'include_operations' => $this->has('include_operations') ? $this->boolean('include_operations') : true,
            'include_inventory' => $this->has('include_inventory') ? $this->boolean('include_inventory') : true,
            'start_date' => $this->filled('start_date') ? trim((string) $this->input('start_date')) : null,
            'end_date' => $this->filled('end_date') ? trim((string) $this->input('end_date')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'include_sales' => ['nullable', 'boolean'],
            'include_operations' => ['nullable', 'boolean'],
            'include_inventory' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $start = $this->input('start_date');
            $end = $this->input('end_date');
            if (! is_string($start) || ! is_string($end)) {
                return;
            }

            $startDate = new \DateTimeImmutable($start);
            $endDate = new \DateTimeImmutable($end);
            $days = (int) $startDate->diff($endDate)->days;
            if ($days > (int) config('booking.reporting_snapshot_rebuild_max_days', 90)) {
                $validator->errors()->add('end_date', 'Reporting rebuild date range cannot exceed '.(int) config('booking.reporting_snapshot_rebuild_max_days', 90).' days.');
            }
        });
    }
}

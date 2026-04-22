<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class RescheduleReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date'],
            'guest_count' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'table_ids' => ['sometimes', 'array', 'min:1', 'max:20'],
            'table_ids.*' => ['integer', 'min:1', 'distinct'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'session_id' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasChange = false;
            foreach (['start_time', 'end_time', 'guest_count', 'notes', 'table_ids'] as $field) {
                if ($this->exists($field)) {
                    $hasChange = true;
                    break;
                }
            }

            if (! $hasChange) {
                $validator->errors()->add('payload', 'At least one reschedulable field is required.');

                return;
            }

            $startProvided = $this->exists('start_time') && $this->input('start_time') !== null && $this->input('start_time') !== '';
            $endProvided = $this->exists('end_time') && $this->input('end_time') !== null && $this->input('end_time') !== '';

            if ($startProvided && $endProvided) {
                try {
                    $start = Carbon::parse((string) $this->input('start_time'));
                    $end = Carbon::parse((string) $this->input('end_time'));
                    if ($end->lessThanOrEqualTo($start)) {
                        $validator->errors()->add('end_time', 'The end_time must be after start_time.');
                    }
                } catch (\Throwable) {
                    // base date rules surface validation errors
                }
            }
        });
    }
}

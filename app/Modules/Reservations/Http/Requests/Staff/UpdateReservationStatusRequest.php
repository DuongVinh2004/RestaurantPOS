<?php

namespace App\Modules\Reservations\Http\Requests\Staff;

use App\Enums\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReservationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_values(array_filter(
                    array_map(static fn (ReservationStatus $status) => $status->value, ReservationStatus::cases()),
                    static fn (string $status) => $status !== ReservationStatus::Completed->value,
                ))),
            ],
            'row_version' => ['required', 'integer', 'min:1'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}

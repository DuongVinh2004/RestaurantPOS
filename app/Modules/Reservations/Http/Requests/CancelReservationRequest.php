<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'cancel_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'session_id' => ['sometimes', 'string', 'max:100'],
        ];
    }
}

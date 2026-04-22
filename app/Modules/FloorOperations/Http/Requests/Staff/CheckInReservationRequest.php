<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class CheckInReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_ids' => ['nullable', 'array', 'min:1'],
            'table_ids.*' => ['integer', 'min:1'],
            'checked_in_at' => ['nullable', 'date'],
            'row_version' => ['required', 'integer', 'min:1'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class AssignSuggestedTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'min:1'],
            'row_version' => ['required', 'integer', 'min:1'],
            'board_from' => ['nullable', 'date'],
            'board_to' => ['nullable', 'date', 'after:board_from'],
            'zone' => ['nullable', 'string', 'max:50'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}



<?php

declare(strict_types=1);

namespace App\Modules\FloorOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MoveTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_table_id' => ['required', 'integer', 'min:1'],
            'to_table_id' => ['required', 'integer', 'min:1', 'different:from_table_id'],
            'moved_at' => ['nullable', 'date'],
            'row_version' => ['required', 'integer', 'min:1'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

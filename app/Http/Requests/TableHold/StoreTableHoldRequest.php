<?php

namespace App\Http\Requests\TableHold;

use Illuminate\Foundation\Http\FormRequest;

class StoreTableHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id'  => ['required', 'string', 'max:100'],
            'user_id'     => ['nullable', 'integer', 'min:1'],
            'branch_id'   => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],

            // dùng để check overlap với reservations/holds (DB chỉ lưu start_time)
            'start_time'  => ['required', 'date'],
            'end_time'    => ['required', 'date', 'after:start_time'],

            'table_ids'   => ['required', 'array', 'min:1'],
            'table_ids.*' => ['integer', 'distinct', 'exists:restaurant_tables,table_id'],

            // optional override expire_at
            'hold_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\FloorOps\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWalkInServiceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'guest_name' => ['required_without:user_id', 'nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'table_ids' => ['required', 'array', 'min:1', 'max:8'],
            'table_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:100'],
            'started_at' => ['nullable', 'date'],
            'service_minutes' => ['nullable', 'integer', 'min:30', 'max:480'],
            'notes' => ['nullable', 'string', 'max:500'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

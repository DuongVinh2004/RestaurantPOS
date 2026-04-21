<?php

declare(strict_types=1);

namespace App\Modules\Waitlist\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class CreateWaitlistEntryRequest extends FormRequest
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
            'guest_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:-999', 'max:999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

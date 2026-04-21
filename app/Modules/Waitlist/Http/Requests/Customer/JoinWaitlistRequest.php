<?php

declare(strict_types=1);

namespace App\Modules\Waitlist\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class JoinWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:50'],
            'guest_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

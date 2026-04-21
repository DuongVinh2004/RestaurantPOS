<?php

declare(strict_types=1);

namespace App\Modules\Waitlist\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class CancelWaitlistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['nullable', 'string', 'max:255'],
            'row_version' => ['required', 'integer', 'min:1'],
        ];
    }
}

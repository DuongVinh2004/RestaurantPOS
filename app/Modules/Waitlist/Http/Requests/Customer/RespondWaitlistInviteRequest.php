<?php

declare(strict_types=1);

namespace App\Modules\Waitlist\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RespondWaitlistInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row_version' => ['required', 'integer', 'min:1'],
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

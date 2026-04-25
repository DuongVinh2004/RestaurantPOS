<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'session_transport' => ['nullable', 'string', 'in:refresh_cookie'],
        ];
    }
}

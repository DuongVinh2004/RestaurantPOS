<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('session_id')) {
            return;
        }

        $sessionId = trim((string) ($this->header('X-Session-Id') ?? ''));
        if ($sessionId === '') {
            return;
        }

        $this->merge([
            'session_id' => $sessionId,
        ]);
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'guest_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'session_label' => ['nullable', 'string', 'max:120'],
        ];
    }
}

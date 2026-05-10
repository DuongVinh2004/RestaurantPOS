<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['full_name', 'email', 'phone', 'session_id', 'session_label', 'device_id'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $payload[$field] = trim((string) $this->input($field));
        }

        if (! $this->filled('session_id')) {
            $sessionId = trim((string) ($this->header('X-Session-Id') ?? ''));
            if ($sessionId !== '') {
                $payload['session_id'] = $sessionId;
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        $passwordMin = max(8, (int) config('customer_auth.register_password_min_length', 8));

        return [
            'full_name' => ['required', 'string', 'max:200'],
            'email' => [
                'nullable',
                'required_without:phone',
                'email',
                'max:200',
                Rule::unique('users', 'email'),
            ],
            'phone' => [
                'nullable',
                'required_without:email',
                'string',
                'max:30',
                Rule::unique('users', 'phone'),
            ],
            'password' => ['required', 'string', 'min:'.$passwordMin, 'max:255', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'max:255'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'session_label' => ['nullable', 'string', 'max:120'],
            'device_id' => ['nullable', 'string', 'max:120'],
        ];
    }
}

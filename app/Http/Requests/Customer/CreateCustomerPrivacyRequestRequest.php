<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCustomerPrivacyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'request_type' => strtolower((string) $this->input('request_type', 'anonymize')),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'request_type' => ['required', 'string', Rule::in(['anonymize'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

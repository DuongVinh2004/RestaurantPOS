<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ListCustomerVouchersRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'bucket' => strtolower(trim((string) ($this->input('bucket', 'active')))),
            'q' => trim((string) ($this->input('q', ''))),
            'per_page' => $this->input('per_page', 20),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'bucket' => ['sometimes', 'in:active,unused,used,all'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}

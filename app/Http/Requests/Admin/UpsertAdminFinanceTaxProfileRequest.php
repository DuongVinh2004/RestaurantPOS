<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAdminFinanceTaxProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tax_code' => strtoupper(trim((string) $this->input('tax_code', ''))),
            'tax_name' => trim((string) $this->input('tax_name', '')),
            'invoice_prefix' => strtoupper(trim((string) $this->input('invoice_prefix', ''))),
            'seller_name' => trim((string) $this->input('seller_name', '')),
            'seller_tax_id' => $this->filled('seller_tax_id') ? trim((string) $this->input('seller_tax_id')) : null,
            'seller_address' => $this->filled('seller_address') ? trim((string) $this->input('seller_address')) : null,
            'prices_include_tax' => $this->boolean('prices_include_tax', true),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'tax_code' => ['required', 'string', 'max:40'],
            'tax_name' => ['required', 'string', 'max:120'],
            'tax_rate_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'prices_include_tax' => ['required', 'boolean'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'seller_name' => ['required', 'string', 'max:150'],
            'seller_tax_id' => ['nullable', 'string', 'max:50'],
            'seller_address' => ['nullable', 'string', 'max:255'],
            'expected_updated_at' => ['nullable', 'date'],
        ];
    }
}

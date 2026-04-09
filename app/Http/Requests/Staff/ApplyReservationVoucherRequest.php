<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ApplyReservationVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_voucher_id' => ['nullable', 'integer', 'min:1'],
            'voucher_code' => ['nullable', 'string', 'max:100'],
            'row_version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('voucher_code') && is_string($this->input('voucher_code'))) {
            $this->merge([
                'voucher_code' => trim((string) $this->input('voucher_code')),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasId = $this->filled('user_voucher_id');
            $hasCode = $this->filled('voucher_code');

            if (! $hasId && ! $hasCode) {
                $validator->errors()->add('user_voucher_id', 'Provide user_voucher_id or voucher_code.');
            }
        });
    }
}

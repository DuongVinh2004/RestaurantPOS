<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewCustomerPrivacyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'decision' => strtolower((string) $this->input('decision')),
            'mode' => strtolower((string) $this->input('mode', 'commit')),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'mode' => ['required', 'string', Rule::in(['dry_run', 'commit'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('decision') === 'reject' && $this->input('mode') !== 'commit') {
                $validator->errors()->add('mode', 'Reject flow only supports commit mode.');
            }
        });
    }
}

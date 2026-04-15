<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ListOperationalRealtimeChangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'after_version' => $this->filled('after_version') ? (int) $this->input('after_version') : 0,
            'limit' => $this->filled('limit') ? (int) $this->input('limit') : 20,
        ]);
    }

    public function rules(): array
    {
        return [
            'after_version' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

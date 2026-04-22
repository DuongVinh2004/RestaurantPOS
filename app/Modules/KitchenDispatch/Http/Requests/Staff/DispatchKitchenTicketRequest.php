<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class DispatchKitchenTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('row_version')) {
            $this->merge([
                'row_version' => (int) $this->input('row_version'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'row_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

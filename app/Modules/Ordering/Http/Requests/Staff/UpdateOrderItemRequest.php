<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'qty' => $this->input('qty', $this->input('quantity')),
            'note' => $this->exists('note') ? $this->input('note') : $this->input('notes'),
        ]);
    }

    public function rules(): array
    {
        return [
            'qty' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'note' => ['sometimes', 'nullable', 'string', 'max:200'],
            'order_row_version' => ['required', 'integer', 'min:1'],
            'row_version' => ['required', 'integer', 'min:1'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->exists('qty') && ! $this->exists('note')) {
                $validator->errors()->add('item', 'At least one mutable field must be provided.');
            }
        });
    }
}


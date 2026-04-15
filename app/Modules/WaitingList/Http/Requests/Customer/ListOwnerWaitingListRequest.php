<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ListOwnerWaitingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active_only' => $this->boolean('active_only', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:Waiting,Notified,Seated,Cancelled'],
            'active_only' => ['nullable', 'boolean'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class LinkConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reservation_id' => $this->filled('reservation_id') ? (int) $this->input('reservation_id') : null,
            'waiting_list_id' => $this->filled('waiting_list_id') ? (int) $this->input('waiting_list_id') : null,
            'customer_user_id' => $this->filled('customer_user_id') ? (int) $this->input('customer_user_id') : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'reservation_id' => ['nullable', 'required_without_all:waiting_list_id,customer_user_id', 'integer', 'min:1', 'exists:reservations,reservation_id'],
            'waiting_list_id' => ['nullable', 'required_without_all:reservation_id,customer_user_id', 'integer', 'min:1', 'exists:waiting_list,waiting_id'],
            'customer_user_id' => ['nullable', 'required_without_all:reservation_id,waiting_list_id', 'integer', 'min:1', 'exists:users,user_id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

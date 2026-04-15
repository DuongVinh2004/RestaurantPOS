<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class AddConversationInternalNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'message_text' => trim((string) $this->input('message_text')),
            'related_reservation_id' => $this->filled('related_reservation_id') ? (int) $this->input('related_reservation_id') : null,
            'related_order_id' => $this->filled('related_order_id') ? (int) $this->input('related_order_id') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'message_text' => ['required', 'string', 'min:1', 'max:5000'],
            'related_reservation_id' => ['nullable', 'integer', 'min:1', 'exists:reservations,reservation_id'],
            'related_order_id' => ['nullable', 'integer', 'min:1', 'exists:reservation_orders,order_id'],
        ];
    }
}

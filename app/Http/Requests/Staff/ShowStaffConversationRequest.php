<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ShowStaffConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $messageLimit = (int) $this->input('message_limit', 50);
        $eventLimit = (int) $this->input('event_limit', 50);

        $this->merge([
            'message_limit' => max(1, min($messageLimit, 200)),
            'event_limit' => max(1, min($eventLimit, 200)),
            'include_closed_assignments' => $this->boolean('include_closed_assignments', true),
        ]);
    }

    public function rules(): array
    {
        return [
            'message_limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'event_limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'include_closed_assignments' => ['nullable', 'boolean'],
        ];
    }
}

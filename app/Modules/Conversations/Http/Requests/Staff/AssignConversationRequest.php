<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class AssignConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'agent_user_id' => $this->filled('agent_user_id') ? (int) $this->input('agent_user_id') : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'agent_user_id' => ['required', 'integer', 'min:1', 'exists:users,user_id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

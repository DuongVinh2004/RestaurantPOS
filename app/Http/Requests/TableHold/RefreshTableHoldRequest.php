<?php

namespace App\Http\Requests\TableHold;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTableHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }



    protected function prepareForValidation(): void
    {
        $sessionId = trim((string) ($this->input('session_id')
            ?? $this->query('session_id')
            ?? $this->header('X-Session-Id')
            ?? ''));

        if ($sessionId !== '') {
            $this->merge(['session_id' => $sessionId]);
        }
    }

    public function rules(): array
    {
        return [
            'session_id'     => ['nullable', 'string', 'max:100'],
            'extend_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'row_version'    => ['nullable', 'integer', 'min:1'],
        ];
    }
}

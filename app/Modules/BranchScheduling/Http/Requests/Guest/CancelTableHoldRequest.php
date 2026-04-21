<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class CancelTableHoldRequest extends FormRequest
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
            'session_id' => ['nullable', 'string', 'max:100'],
            'row_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

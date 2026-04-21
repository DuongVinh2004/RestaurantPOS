<?php

namespace App\Modules\BranchScheduling\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

class CheckTableAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'        => ['required', 'date'],
            'to'          => ['required', 'date', 'after:from'],

            'branch_id'   => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'zone'        => ['nullable', 'string', 'max:50'],
            'template_id' => ['nullable', 'integer', 'exists:table_templates,template_id'],
            'min_seats'   => ['nullable', 'integer', 'min:1'],
            'guest_count' => ['nullable', 'integer', 'min:1'],

            // session_id để bỏ qua hold của chính session khi xem available
            'session_id'  => ['nullable', 'string', 'max:100'],

            // gợi ý combo bàn
            'suggest'         => ['nullable', 'boolean'],
            'max_suggestions' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'zone' => is_string($this->zone) ? trim($this->zone) : $this->zone,
        ]);
    }
}

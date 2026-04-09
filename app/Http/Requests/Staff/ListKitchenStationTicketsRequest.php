<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Enums\KitchenTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListKitchenStationTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_terminal' => $this->has('include_terminal') ? $this->boolean('include_terminal') : false,
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(array_map(static fn (KitchenTicketStatus $status): string => $status->value, KitchenTicketStatus::cases()))],
            'include_terminal' => ['nullable', 'boolean'],
        ];
    }
}

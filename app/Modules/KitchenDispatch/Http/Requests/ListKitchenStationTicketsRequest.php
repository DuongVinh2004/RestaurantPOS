<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Http\Requests;

use App\Enums\KitchenTicketStatus;
use App\Modules\FloorOps\Http\Requests\BranchScopeRequest;
use Illuminate\Validation\Rule;

class ListKitchenStationTicketsRequest extends BranchScopeRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'include_terminal' => $this->has('include_terminal') ? $this->boolean('include_terminal') : false,
        ]);
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => ['nullable', 'string', Rule::in(array_map(static fn (KitchenTicketStatus $status): string => $status->value, KitchenTicketStatus::cases()))],
            'include_terminal' => ['nullable', 'boolean'],
        ]);
    }
}

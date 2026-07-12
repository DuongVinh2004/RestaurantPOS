<?php

namespace App\Modules\Reservations\Http\Requests\Customer;

use App\Support\Auth\RequestActorContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = RequestActorContext::fromRequest($this);

        return $actor->isStaff()
            || $actor->isCustomerOwner()
            || $actor->isCustomerSession();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('session_id') && $this->header('X-Session-Id')) {
            $this->merge([
                'session_id' => $this->header('X-Session-Id'),
            ]);
        }
    }

    public function rules(): array
    {
        $actor = RequestActorContext::fromRequest($this);
        $isStaff = $actor->isStaff();

        return [
            'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,user_id'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'guest_name' => [
                'nullable',
                'string',
                'max:200',
                Rule::requiredIf(fn () => $isStaff && ! $this->filled('user_id')),
            ],
            'guest_phone' => [
                'nullable',
                'string',
                'max:50',
                Rule::requiredIf(fn () => $isStaff && ! $this->filled('user_id')),
            ],
            'guest_email' => [
                'nullable',
                'email:rfc',
                'max:200',
            ],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'hold_id' => ['nullable', 'uuid'],
            'session_id' => ['required_with:hold_id', 'string', 'max:100'],
            'table_ids' => ['required_without:hold_id', 'array', 'min:1', 'max:20'],
            'table_ids.*' => ['integer', 'min:1', 'distinct'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'pre_order_items' => ['sometimes', 'array', 'max:100'],
            'pre_order_items.*.item_id' => ['required_with:pre_order_items', 'integer', 'min:1'],
            'pre_order_items.*.quantity' => ['required_with:pre_order_items', 'integer', 'min:1', 'max:1000'],
        ];
    }
}

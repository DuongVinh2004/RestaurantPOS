<?php

namespace App\Modules\Reservations\Http\Requests;

use App\Support\RequestActorContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = RequestActorContext::fromRequest($this);

        return $actor->isStaff()
            || $actor->isCustomerOwner()
            || $actor->isCustomerSession();
    }

    public function rules(): array
    {
        $actor = RequestActorContext::fromRequest($this);
        $isStaff = $actor->isStaff();

        return [
            'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,user_id'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'guest_name' => [
                $isStaff ? 'nullable' : 'prohibited',
                'string',
                'max:200',
                Rule::requiredIf(fn () => $isStaff && ! $this->filled('user_id')),
                Rule::prohibitedIf(fn () => $isStaff && $this->filled('user_id')),
            ],
            'guest_phone' => [
                $isStaff ? 'nullable' : 'prohibited',
                'string',
                'max:50',
                Rule::requiredIf(fn () => $isStaff && ! $this->filled('user_id')),
                Rule::prohibitedIf(fn () => $isStaff && $this->filled('user_id')),
            ],
            'guest_email' => [
                $isStaff ? 'nullable' : 'prohibited',
                'email:rfc',
                'max:200',
                Rule::prohibitedIf(fn () => $isStaff && $this->filled('user_id')),
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

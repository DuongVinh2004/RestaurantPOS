<?php

namespace App\Http\Requests\Reservation;

use App\Support\RequestActorContext;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            // Với customer authenticated hoặc session-owned hold: user_id sẽ lấy từ auth/session ownership (không tin client).
            // Với staff key: user_id là bắt buộc.
            'user_id'      => ['nullable', 'integer', 'min:1', 'exists:users,user_id'],
            'branch_id'    => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],

            'start_time'   => ['required', 'date'],
            'end_time'     => ['required', 'date', 'after:start_time'],
            'guest_count'  => ['required', 'integer', 'min:1', 'max:1000'],

            'hold_id'      => ['nullable', 'uuid'],
            'session_id'   => ['required_with:hold_id', 'string', 'max:100'],

            'table_ids'    => ['required_without:hold_id', 'array', 'min:1', 'max:20'],
            'table_ids.*'  => ['integer', 'min:1', 'distinct'],

            'notes'        => ['nullable', 'string', 'max:1000'],

            'pre_order_items'              => ['sometimes', 'array', 'max:100'],
            'pre_order_items.*.item_id'    => ['required_with:pre_order_items', 'integer', 'min:1'],
            'pre_order_items.*.quantity'   => ['required_with:pre_order_items', 'integer', 'min:1', 'max:1000'],
        ];
    }
}

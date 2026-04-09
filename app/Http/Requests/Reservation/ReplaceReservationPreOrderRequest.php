<?php

declare(strict_types=1);

namespace App\Http\Requests\Reservation;

use App\Support\RequestActorContext;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceReservationPreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = RequestActorContext::fromRequest($this);

        return $actor->isStaff() || $actor->isCustomerOwner();
    }

    public function rules(): array
    {
        return [
            'pre_order_items' => ['required', 'array', 'min:1', 'max:100'],
            'pre_order_items.*.item_id' => ['required_with:pre_order_items', 'integer', 'min:1'],
            'pre_order_items.*.quantity' => ['required_with:pre_order_items', 'integer', 'min:1', 'max:1000'],
        ];
    }
}

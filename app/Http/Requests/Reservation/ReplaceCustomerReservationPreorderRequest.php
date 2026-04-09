<?php

declare(strict_types=1);

namespace App\Http\Requests\Reservation;

class ReplaceCustomerReservationPreorderRequest extends PreviewCustomerReservationPreorderRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'row_version' => ['required', 'integer', 'min:1'],
            'pre_order_row_version' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}

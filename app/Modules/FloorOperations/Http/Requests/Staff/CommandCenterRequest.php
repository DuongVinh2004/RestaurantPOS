<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class CommandCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'string', 'in:high,normal,low'],
            'type' => ['nullable', 'string', 'in:reservation_upcoming,reservation_needs_check_in,deposit_pending,deposit_expired,preorder_pending,bill_payment_pending,checkout_pending,waiting_list_pending'],
            'horizon_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

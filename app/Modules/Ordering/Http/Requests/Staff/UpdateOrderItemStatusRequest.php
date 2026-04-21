<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:InProgress,Served,Cancelled'],
            'order_row_version' => ['required', 'integer', 'min:1'],
            'row_version' => ['required', 'integer', 'min:1'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}


<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class RemoveReservationVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'row_version' => ['required', 'integer', 'min:1'],
        ];
    }
}

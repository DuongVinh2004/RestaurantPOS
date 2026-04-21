<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class AdjustUserLoyaltyPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}

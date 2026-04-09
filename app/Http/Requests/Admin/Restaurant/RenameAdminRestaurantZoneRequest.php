<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class RenameAdminRestaurantZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_zone' => ['nullable', 'string', 'max:50'],
            'to_zone' => ['nullable', 'string', 'max:50'],
        ];
    }
}

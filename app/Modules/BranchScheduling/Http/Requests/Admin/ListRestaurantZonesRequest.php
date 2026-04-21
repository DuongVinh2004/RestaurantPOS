<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListRestaurantZonesRequest extends FormRequest
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
            'include_unzoned' => ['nullable', 'boolean'],
            'include_deleted' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'include_unzoned' => $this->boolean('include_unzoned', true),
            'include_deleted' => $this->boolean('include_deleted'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Requests\Admin\Benefits;

use Illuminate\Foundation\Http\FormRequest;

class ListAdminLoyaltyTiersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'include_inactive' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('include_inactive')) {
            $this->merge([
                'include_inactive' => $this->boolean('include_inactive'),
            ]);
        }
    }
}

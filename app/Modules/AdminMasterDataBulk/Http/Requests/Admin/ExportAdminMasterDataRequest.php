<?php

declare(strict_types=1);

namespace App\Modules\AdminMasterDataBulk\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportAdminMasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'format' => strtolower((string) $this->input('format', 'csv')),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(['csv', 'json'])],
        ];
    }
}

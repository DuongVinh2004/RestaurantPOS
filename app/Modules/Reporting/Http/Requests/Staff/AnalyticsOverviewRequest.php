<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'granularity' => ['nullable', 'string', 'in:hour,day'],
        ];
    }
}

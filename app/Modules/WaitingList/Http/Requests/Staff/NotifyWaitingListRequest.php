<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class NotifyWaitingListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'min:1'],
            'hold_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'row_version' => ['required', 'integer', 'min:1'],
        ];
    }
}

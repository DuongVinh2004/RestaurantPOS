<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RefreshSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

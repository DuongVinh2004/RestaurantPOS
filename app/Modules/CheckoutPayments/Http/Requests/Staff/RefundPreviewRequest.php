<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RefundPreviewRequest extends FormRequest
{
    private const REFUND_SCOPES = ['deposit', 'final', 'all'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refund_scope' => ['nullable', 'string', Rule::in(self::REFUND_SCOPES)],
            'refund_amount' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'cancel_after_payment' => ['nullable', 'boolean'],
        ];
    }
}

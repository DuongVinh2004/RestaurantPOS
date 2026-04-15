<?php

declare(strict_types=1);

namespace App\Modules\PrivacyAudit\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ListAuditTrailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $perPage = (int) $this->input('per_page', 50);
        $page = max(1, (int) $this->input('page', 1));

        $this->merge([
            'reservation_id' => $this->filled('reservation_id') ? (int) $this->input('reservation_id') : null,
            'order_id' => $this->filled('order_id') ? (int) $this->input('order_id') : null,
            'payment_id' => $this->filled('payment_id') ? (int) $this->input('payment_id') : null,
            'waiting_id' => $this->filled('waiting_id') ? (int) $this->input('waiting_id') : null,
            'table_id' => $this->filled('table_id') ? (int) $this->input('table_id') : null,
            'cashier_shift_id' => $this->filled('cashier_shift_id') ? (int) $this->input('cashier_shift_id') : null,
            'actor_user_id' => $this->filled('actor_user_id') ? (int) $this->input('actor_user_id') : null,
            'branch_id' => $this->filled('branch_id') ? (int) $this->input('branch_id') : null,
            'request_id' => $this->filled('request_id') ? trim((string) $this->input('request_id')) : null,
            'q' => $this->filled('q') ? trim((string) $this->input('q')) : null,
            'action' => $this->filled('action') ? trim((string) $this->input('action')) : null,
            'actor_type' => $this->filled('actor_type') ? trim((string) $this->input('actor_type')) : null,
            'subject_type' => $this->filled('subject_type') ? trim((string) $this->input('subject_type')) : null,
            'subject_id' => $this->filled('subject_id') ? trim((string) $this->input('subject_id')) : null,
            'per_page' => max(1, min($perPage, 100)),
            'page' => $page,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'reservation_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'payment_id' => ['nullable', 'integer', 'min:1'],
            'waiting_id' => ['nullable', 'integer', 'min:1'],
            'table_id' => ['nullable', 'integer', 'min:1'],
            'cashier_shift_id' => ['nullable', 'integer', 'min:1'],
            'actor_user_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'request_id' => ['nullable', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:50'],
            'actor_type' => ['nullable', 'string', 'max:40'],
            'subject_type' => ['nullable', 'string', 'max:50', 'required_with:subject_id'],
            'subject_id' => ['nullable', 'string', 'max:64', 'required_with:subject_type'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

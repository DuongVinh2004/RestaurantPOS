<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests\Staff;

use App\Modules\Cashiering\Http\Requests\Staff\ListFinancialReconciliationRequest;

class ListAccountingExportRequest extends ListFinancialReconciliationRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'only_invoiced' => $this->has('only_invoiced') ? $this->boolean('only_invoiced') : null,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'only_invoiced' => ['nullable', 'boolean'],
        ]);
    }
}

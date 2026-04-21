<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Http\Requests\Staff;

use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class OpenCashierShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_float_amount' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'currency' => ['nullable', 'string', 'max:10'],
            'terminal_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'staff_user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branchId = $this->input('branch_id');
            if ($branchId === null || $branchId === '') {
                return;
            }

            $staffActorUserId = (int) $this->attributes->get('staff_actor_user_id', 0);
            if ($staffActorUserId <= 0) {
                return;
            }

            try {
                app(StaffBranchContextService::class)->assertCashierShiftBranchEligible($staffActorUserId, $branchId);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ((array) $messages as $message) {
                        $validator->errors()->add((string) $field, (string) $message);
                    }
                }
            }
        });
    }
}


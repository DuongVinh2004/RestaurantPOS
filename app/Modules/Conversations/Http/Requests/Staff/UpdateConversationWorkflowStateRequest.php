<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Requests\Staff;

use App\Enums\StaffConversationWorkflowState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversationWorkflowStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $workflowState = StaffConversationWorkflowState::tryFromInput(
            $this->filled('workflow_state') ? (string) $this->input('workflow_state') : null,
        );
        $expectedWorkflowState = StaffConversationWorkflowState::tryFromInput(
            $this->filled('expected_workflow_state') ? (string) $this->input('expected_workflow_state') : null,
        );

        $this->merge([
            'workflow_state' => $workflowState?->value ?? ($this->filled('workflow_state') ? trim((string) $this->input('workflow_state')) : null),
            'expected_workflow_state' => $expectedWorkflowState?->value ?? ($this->filled('expected_workflow_state') ? trim((string) $this->input('expected_workflow_state')) : null),
            'reason' => $this->filled('reason') ? trim((string) $this->input('reason')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'workflow_state' => [
                'required',
                'string',
                Rule::in([
                    StaffConversationWorkflowState::Open->value,
                    StaffConversationWorkflowState::Triaged->value,
                    StaffConversationWorkflowState::PendingCustomer->value,
                    StaffConversationWorkflowState::Resolved->value,
                    StaffConversationWorkflowState::Closed->value,
                ]),
            ],
            'expected_workflow_state' => ['nullable', 'string', Rule::in(StaffConversationWorkflowState::values())],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

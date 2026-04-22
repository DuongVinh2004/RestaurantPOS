<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Requests\Staff;

use App\Enums\ConversationChannel;
use App\Enums\ConversationStatus;
use App\Enums\StaffConversationWorkflowState;
use Illuminate\Foundation\Http\FormRequest;

class ListStaffConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $perPage = (int) $this->input('per_page', 25);
        $page = max(1, (int) $this->input('page', 1));
        $workflowState = StaffConversationWorkflowState::tryFromInput(
            $this->filled('workflow_state') ? (string) $this->input('workflow_state') : null
        );

        $this->merge([
            'status' => $this->filled('status') ? trim((string) $this->input('status')) : null,
            'workflow_state' => $workflowState?->value ?? ($this->filled('workflow_state') ? trim((string) $this->input('workflow_state')) : null),
            'inbox_view' => strtolower(trim((string) $this->input('inbox_view', 'all'))),
            'channel' => $this->filled('channel') ? trim((string) $this->input('channel')) : null,
            'assigned_agent_user_id' => $this->filled('assigned_agent_user_id') ? (int) $this->input('assigned_agent_user_id') : null,
            'assignment_state' => strtolower(trim((string) $this->input('assignment_state', 'all'))),
            'branch_id' => $this->filled('branch_id') ? (int) $this->input('branch_id') : null,
            'reservation_id' => $this->filled('reservation_id') ? (int) $this->input('reservation_id') : null,
            'waiting_list_id' => $this->filled('waiting_list_id') ? (int) $this->input('waiting_list_id') : null,
            'user_id' => $this->filled('user_id') ? (int) $this->input('user_id') : null,
            'q' => $this->filled('q') ? trim((string) $this->input('q')) : null,
            'created_from' => $this->filled('created_from') ? trim((string) $this->input('created_from')) : null,
            'created_to' => $this->filled('created_to') ? trim((string) $this->input('created_to')) : null,
            'sort_by' => strtolower(trim((string) $this->input('sort_by', 'latest_activity'))),
            'sort_dir' => strtolower(trim((string) $this->input('sort_dir', 'desc'))),
            'per_page' => max(1, min($perPage, 100)),
            'page' => $page,
        ]);
    }

    public function rules(): array
    {
        $statuses = array_map(static fn (ConversationStatus $status): string => $status->value, ConversationStatus::cases());
        $channels = array_map(static fn (ConversationChannel $channel): string => $channel->value, ConversationChannel::cases());

        return [
            'status' => ['nullable', 'string', 'in:'.implode(',', $statuses)],
            'workflow_state' => ['nullable', 'string', 'in:'.implode(',', StaffConversationWorkflowState::values())],
            'inbox_view' => ['nullable', 'string', 'in:all,unassigned,overdue,waiting_on_customer,resolved_today'],
            'channel' => ['nullable', 'string', 'in:'.implode(',', $channels)],
            'assigned_agent_user_id' => ['nullable', 'integer', 'min:1', 'exists:users,user_id'],
            'assignment_state' => ['nullable', 'string', 'in:all,assigned,unassigned,mine'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,branch_id'],
            'reservation_id' => ['nullable', 'integer', 'min:1', 'exists:reservations,reservation_id'],
            'waiting_list_id' => ['nullable', 'integer', 'min:1', 'exists:waiting_list,waiting_id'],
            'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,user_id'],
            'q' => ['nullable', 'string', 'max:160'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort_by' => ['nullable', 'string', 'in:latest_activity,created_at,message_count'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

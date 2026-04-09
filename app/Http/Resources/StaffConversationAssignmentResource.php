<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StaffConversationAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $agent = null;
        if ($this->relationLoaded('agent') && $this->agent !== null) {
            $agent = [
                'user_id' => (int) $this->agent->user_id,
                'full_name' => $this->agent->full_name,
                'phone' => $this->agent->phone,
                'email' => $this->agent->email,
                'role_name' => $this->agent->relationLoaded('role') && $this->agent->role !== null
                    ? (string) $this->agent->role->role_name
                    : null,
            ];
        }

        return [
            'assignment_id' => (int) $this->assignment_id,
            'conversation_id' => (string) $this->conversation_id,
            'agent_user_id' => (int) $this->agent_user_id,
            'agent' => $agent,
            'assigned_at' => $this->iso($this->assigned_at),
            'released_at' => $this->iso($this->released_at),
            'is_active' => (bool) $this->is_active,
            'notes' => $this->notes,
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}

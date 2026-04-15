<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StaffConversationEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $byUser = null;
        if ($this->relationLoaded('byUser') && $this->byUser !== null) {
            $byUser = [
                'user_id' => (int) $this->byUser->user_id,
                'full_name' => $this->byUser->full_name,
                'phone' => $this->byUser->phone,
                'email' => $this->byUser->email,
                'role_name' => $this->byUser->relationLoaded('role') && $this->byUser->role !== null
                    ? (string) $this->byUser->role->role_name
                    : null,
            ];
        }

        return [
            'event_id' => (int) $this->event_id,
            'conversation_id' => (string) $this->conversation_id,
            'event_type' => (string) $this->event_type,
            'event_by_user_id' => $this->event_by_user_id !== null ? (int) $this->event_by_user_id : null,
            'by_user' => $byUser,
            'event_data' => $this->event_data,
            'created_at' => $this->iso($this->created_at),
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

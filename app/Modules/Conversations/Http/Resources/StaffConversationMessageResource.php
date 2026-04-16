<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Http\Resources;

use App\Modules\Conversations\Application\Services\StaffConversationFileAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class StaffConversationMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fileAccessService = app(StaffConversationFileAccessService::class);
        $conversationId = (string) $this->conversation_id;
        $senderUser = null;
        if ($this->relationLoaded('senderUser') && $this->senderUser !== null) {
            $senderUser = [
                'user_id' => (int) $this->senderUser->user_id,
                'full_name' => $this->senderUser->full_name,
                'phone' => $this->senderUser->phone,
                'email' => $this->senderUser->email,
                'role_name' => $this->senderUser->relationLoaded('role') && $this->senderUser->role !== null
                    ? (string) $this->senderUser->role->role_name
                    : null,
            ];
        }

        $attachment = $fileAccessService->preferredAttachmentPayload($this->resource);
        $files = $this->relationLoaded('files')
            ? $this->files->map(static function ($file) use ($fileAccessService, $conversationId): array {
                return $fileAccessService->filePayload($file, $conversationId);
            })->values()->all()
            : [];

        $entities = $this->relationLoaded('entities')
            ? $this->entities->map(static function ($entity): array {
                return [
                    'message_entity_id' => (int) $entity->message_entity_id,
                    'entity_type' => (string) $entity->entity_type,
                    'entity_text' => (string) $entity->entity_text,
                    'entity_normalized' => $entity->entity_normalized,
                    'extra_json' => $entity->extra_json,
                    'created_at' => $entity->created_at instanceof \DateTimeInterface
                        ? Carbon::instance($entity->created_at)->utc()->toIso8601String()
                        : ($entity->created_at !== null ? Carbon::parse((string) $entity->created_at)->utc()->toIso8601String() : null),
                ];
            })->values()->all()
            : [];

        return [
            'message_id' => (int) $this->message_id,
            'conversation_id' => $conversationId,
            'sender' => (string) $this->sender,
            'sender_id' => $this->sender_id !== null ? (int) $this->sender_id : null,
            'sender_user' => $senderUser,
            'message_text' => (string) $this->message_text,
            'message_type' => (string) $this->message_type,
            'is_internal_note' => (bool) $this->is_internal_note,
            'attachment_url' => $attachment['access_url'] ?? null,
            'attachment' => $attachment,
            'is_processed' => (bool) $this->is_processed,
            'processing_status' => $this->processing_status,
            'confidence' => $this->confidence !== null ? (string) $this->confidence : null,
            'related_reservation_id' => $this->related_reservation_id !== null ? (int) $this->related_reservation_id : null,
            'related_order_id' => $this->related_order_id !== null ? (int) $this->related_order_id : null,
            'created_at' => $this->iso($this->created_at),
            'files' => $files,
            'entities' => $entities,
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

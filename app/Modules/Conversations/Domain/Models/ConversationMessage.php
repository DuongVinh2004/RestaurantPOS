<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Domain\Models;

use App\Enums\MessageSender;
use App\Enums\MessageType;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ConversationMessage extends Model
{
    protected $table = 'conversation_messages';
    protected $primaryKey = 'message_id';

    public const UPDATED_AT = null;

    private const ALLOWED_PROCESSING_STATUSES = [
        'pending',
        'processed',
        'failed',
        'ignored',
        'skipped',
        'reviewed',
    ];

    protected $fillable = [
        'conversation_id',
        'sender',
        'sender_id',
        'message_text',
        'message_type',
        'is_internal_note',
        'attachment_url',
        'is_processed',
        'processing_status',
        'confidence',
        'related_reservation_id',
        'related_order_id',
    ];

    protected $casts = [
        'message_id' => 'int',
        'conversation_id' => 'string',
        'sender' => 'string',
        'sender_id' => 'int',
        'message_text' => 'string',
        'message_type' => 'string',
        'is_internal_note' => 'bool',
        'attachment_url' => 'string',
        'processing_status' => 'string',
        'created_at' => 'datetime',
        'is_processed' => 'bool',
        'confidence' => 'decimal:4',
        'related_reservation_id' => 'int',
        'related_order_id' => 'int',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->sender = self::normalizeSender($model->sender);
            $model->message_type = self::normalizeMessageType($model->message_type);
            $model->processing_status = self::normalizeProcessingStatus($model->processing_status);
        });
    }

    private static function normalizeSender(mixed $value): string
    {
        if ($value instanceof MessageSender) {
            return $value->value;
        }

        $normalized = strtolower(trim((string) $value));
        return match ($normalized) {
            'user', 'agent', 'system' => $normalized,
            default => throw ValidationException::withMessages([
                'sender' => 'Unsupported message sender.',
            ]),
        };
    }

    private static function normalizeMessageType(mixed $value): string
    {
        if ($value instanceof MessageType) {
            return $value->value;
        }

        $normalized = strtolower(trim((string) $value));
        return match ($normalized) {
            'text', 'image', 'file', 'location', 'unknown' => $normalized,
            default => throw ValidationException::withMessages([
                'message_type' => 'Unsupported message type.',
            ]),
        };
    }

    private static function normalizeProcessingStatus(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        if (!in_array($normalized, self::ALLOWED_PROCESSING_STATUSES, true)) {
            throw ValidationException::withMessages([
                'processing_status' => 'Unsupported processing status.',
            ]);
        }

        return $normalized;
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id', 'user_id');
    }

    public function relatedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'related_reservation_id', 'reservation_id');
    }

    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(ReservationOrder::class, 'related_order_id', 'order_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ConversationFile::class, 'message_id', 'message_id');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(MessageEntity::class, 'message_id', 'message_id');
    }

    public function senderEnum(): ?MessageSender
    {
        return MessageSender::tryFrom(strtolower((string) $this->sender));
    }

    public function messageTypeEnum(): ?MessageType
    {
        return MessageType::tryFrom(strtolower((string) $this->message_type));
    }

    public function preferredAttachmentUrl(): ?string
    {
        $file = $this->relationLoaded('files')
            ? $this->files->first()
            : $this->files()->orderBy('file_id')->first();

        if ($file !== null && trim((string) $file->file_url) !== '') {
            return (string) $file->file_url;
        }

        $legacy = trim((string) ($this->attachment_url ?? ''));
        return $legacy !== '' ? $legacy : null;
    }

    public function scopeForConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    public function scopeProcessingStatus($query, ?string $status)
    {
        $status = trim((string) $status);
        if ($status === '') {
            return $query;
        }

        return $query->where('processing_status', strtolower($status));
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('message_id');
    }

    public function scopeInternalNotes($query)
    {
        return $query->where('is_internal_note', true);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationChannel;
use App\Enums\ConversationStatus;
use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Conversation extends Model
{
    use UsesUuidPrimaryKey;

    protected $table = 'conversations';
    protected $primaryKey = 'conversation_id';

    public const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'branch_id',
        'user_id',
        'customer_session_id',
        'session_id',
        'channel',
        'status',
        'intent_detected',
        'linked_reservation_id',
        'linked_waiting_list_id',
        'closed_at',
    ];

    protected $casts = [
        'conversation_id' => 'string',
        'branch_id' => 'int',
        'user_id' => 'int',
        'customer_session_id' => 'string',
        'session_id' => 'string',
        'channel' => ConversationChannel::class,
        'status' => ConversationStatus::class,
        'intent_detected' => 'string',
        'linked_reservation_id' => 'int',
        'linked_waiting_list_id' => 'int',
        'created_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->channel = self::normalizeChannel($model->channel);
            $model->status = self::normalizeStatus($model->status);
        });
    }

    private static function normalizeChannel(mixed $value): string
    {
        if ($value instanceof ConversationChannel) {
            return $value->value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'webchat' => ConversationChannel::WebChat->value,
            'facebook' => ConversationChannel::Facebook->value,
            'zalo' => ConversationChannel::Zalo->value,
            'whatsapp' => ConversationChannel::Whatsapp->value,
            'instagram' => ConversationChannel::Instagram->value,
            'line' => ConversationChannel::Line->value,
            'other' => ConversationChannel::Other->value,
            default => throw ValidationException::withMessages([
                'channel' => 'Unsupported conversation channel.',
            ]),
        };
    }

    private static function normalizeStatus(mixed $value): string
    {
        if ($value instanceof ConversationStatus) {
            return $value->value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'open' => ConversationStatus::Open->value,
            'pending' => ConversationStatus::Pending->value,
            'closed' => ConversationStatus::Closed->value,
            'spam' => ConversationStatus::Spam->value,
            default => throw ValidationException::withMessages([
                'status' => 'Unsupported conversation status.',
            ]),
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function linkedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'linked_reservation_id', 'reservation_id');
    }

    public function linkedWaitingList(): BelongsTo
    {
        return $this->belongsTo(WaitingList::class, 'linked_waiting_list_id', 'waiting_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AgentAssignment::class, 'conversation_id', 'conversation_id');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AgentAssignment::class, 'conversation_id', 'conversation_id')
            ->where('is_active', true)
            ->latest('assignment_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id', 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class, 'conversation_id', 'conversation_id')
            ->latestOfMany('message_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class, 'conversation_id', 'conversation_id');
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(ConversationAnalysis::class, 'conversation_id', 'conversation_id');
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(ConversationAnalysis::class, 'conversation_id', 'conversation_id')
            ->latestOfMany('analysis_id');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithStatus($query, ConversationStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', ConversationStatus::Open);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', ConversationStatus::Closed);
    }

    public function scopeBySession($query, ?string $sessionId)
    {
        $sessionId = trim((string) $sessionId);
        if ($sessionId === '') {
            return $query;
        }

        return $query->where('session_id', $sessionId);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at');
    }
}

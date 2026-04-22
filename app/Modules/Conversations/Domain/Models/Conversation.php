<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Domain\Models;

use App\Enums\ConversationChannel;
use App\Enums\ConversationStatus;
use App\Enums\StaffConversationWorkflowState;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Waitlist\Domain\Models\WaitlistEntry;
use App\Support\Persistence\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
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
        'workflow_state',
        'workflow_state_reason',
        'workflow_state_changed_at',
        'first_triaged_at',
        'resolved_at',
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
        'workflow_state' => StaffConversationWorkflowState::class,
        'workflow_state_reason' => 'string',
        'intent_detected' => 'string',
        'linked_reservation_id' => 'int',
        'linked_waiting_list_id' => 'int',
        'created_at' => 'datetime',
        'workflow_state_changed_at' => 'datetime',
        'first_triaged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->channel = self::normalizeChannel($model->channel);
            $model->status = self::normalizeStatus($model->status);
            $model->workflow_state = self::normalizeWorkflowState(
                $model->workflow_state ?? self::defaultWorkflowStateForStatus($model->status)
            );
            $model->workflow_state_reason = self::normalizeNullableString($model->workflow_state_reason);
            if ($model->workflow_state_changed_at === null) {
                $model->workflow_state_changed_at = now('UTC');
            }
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

    private static function normalizeWorkflowState(mixed $value): string
    {
        if ($value instanceof StaffConversationWorkflowState) {
            return $value->value;
        }

        $normalized = StaffConversationWorkflowState::tryFromInput((string) $value);
        if ($normalized instanceof StaffConversationWorkflowState) {
            return $normalized->value;
        }

        throw ValidationException::withMessages([
            'workflow_state' => 'Unsupported conversation workflow state.',
        ]);
    }

    private static function defaultWorkflowStateForStatus(mixed $status): string
    {
        return match (self::normalizeStatus($status)) {
            ConversationStatus::Pending->value => StaffConversationWorkflowState::PendingCustomer->value,
            ConversationStatus::Closed->value => StaffConversationWorkflowState::Closed->value,
            default => StaffConversationWorkflowState::Open->value,
        };
    }

    private static function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }

    public function workflowState(): StaffConversationWorkflowState
    {
        $resolved = $this->workflow_state instanceof StaffConversationWorkflowState
            ? $this->workflow_state
            : StaffConversationWorkflowState::tryFromInput((string) $this->workflow_state);

        if (
            $this->relationLoaded('activeAssignment')
            && $this->activeAssignment !== null
            && ! in_array($resolved, [
                StaffConversationWorkflowState::PendingCustomer,
                StaffConversationWorkflowState::Resolved,
                StaffConversationWorkflowState::Closed,
            ], true)
        ) {
            return StaffConversationWorkflowState::Assigned;
        }

        if ($resolved instanceof StaffConversationWorkflowState) {
            return $resolved;
        }

        $status = $this->status?->value ?? (string) $this->status;
        if ($status === ConversationStatus::Closed->value) {
            return StaffConversationWorkflowState::Closed;
        }

        if ($status === ConversationStatus::Pending->value) {
            return StaffConversationWorkflowState::PendingCustomer;
        }

        return StaffConversationWorkflowState::Open;
    }

    public function workflowStateValue(): string
    {
        return $this->workflowState()->value;
    }

    public function workflowStateReasonValue(): ?string
    {
        return $this->workflow_state_reason
            ?: match ($this->workflowState()) {
                StaffConversationWorkflowState::Assigned => 'assigned',
                StaffConversationWorkflowState::PendingCustomer => 'waiting_for_customer',
                StaffConversationWorkflowState::Resolved => 'resolved',
                StaffConversationWorkflowState::Closed => 'closed',
                StaffConversationWorkflowState::Triaged => 'triaged',
                default => 'open',
            };
    }

    /**
     * @return list<string>
     */
    public function workflowAllowedActions(): array
    {
        return match ($this->workflowState()) {
            StaffConversationWorkflowState::Open => ['assign', 'triage', 'mark_pending_customer', 'close'],
            StaffConversationWorkflowState::Triaged => ['assign', 'mark_pending_customer', 'resolve', 'close'],
            StaffConversationWorkflowState::Assigned => ['unassign', 'mark_pending_customer', 'resolve', 'close'],
            StaffConversationWorkflowState::PendingCustomer => ['assign', 'triage', 'resolve', 'close'],
            StaffConversationWorkflowState::Resolved => ['reopen', 'close'],
            StaffConversationWorkflowState::Closed => ['reopen'],
        };
    }

    public function isWorkflowTerminal(): bool
    {
        return $this->workflowState()->isQueueTerminal();
    }

    public function workflowStateChangedAtValue(): ?Carbon
    {
        if ($this->workflow_state_changed_at instanceof Carbon) {
            return $this->workflow_state_changed_at->copy();
        }

        if ($this->resolved_at instanceof Carbon) {
            return $this->resolved_at->copy();
        }

        if ($this->closed_at instanceof Carbon) {
            return $this->closed_at->copy();
        }

        return $this->created_at instanceof Carbon ? $this->created_at->copy() : null;
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
        return $this->belongsTo(WaitlistEntry::class, 'linked_waiting_list_id', 'waiting_id');
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

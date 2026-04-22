<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationEvent extends Model
{
    protected $table = 'conversation_events';

    protected $primaryKey = 'event_id';

    public const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'event_type',
        'event_by_user_id',
        'event_data',
    ];

    protected $casts = [
        // bigint unsigned
        'event_id' => 'int',
        'conversation_id' => 'string',

        'event_type' => 'string',
        'event_by_user_id' => 'int',

        // json
        'event_data' => 'array',

        // datetime(6)
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function byUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'event_by_user_id', 'user_id');
    }

    public function scopeType($query, ?string $type)
    {
        $type = trim((string) $type);
        if ($type === '') {
            return $query;
        }

        return $query->where('event_type', $type);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('event_id');
    }
}

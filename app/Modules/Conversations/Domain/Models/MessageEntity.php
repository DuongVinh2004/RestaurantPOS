<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageEntity extends Model
{
    protected $table = 'message_entities';
    protected $primaryKey = 'message_entity_id';

    public const UPDATED_AT = null;

    protected $fillable = [
        'message_id',
        'entity_type',
        'entity_text',
        'entity_normalized',
        'extra_json',
    ];

    protected $casts = [
        // bigint unsigned
        'message_entity_id' => 'int',
        'message_id' => 'int',

        'entity_type' => 'string',
        'entity_text' => 'string',
        'entity_normalized' => 'string',

        // json
        'extra_json' => 'array',

        // datetime(6)
        'created_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id', 'message_id');
    }

    public function scopeType($query, ?string $type)
    {
        $type = trim((string) $type);
        if ($type === '') {
            return $query;
        }

        return $query->where('entity_type', $type);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('message_entity_id');
    }
}

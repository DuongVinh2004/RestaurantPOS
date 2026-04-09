<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationFile extends Model
{
    protected $table = 'conversation_files';
    protected $primaryKey = 'file_id';

    // Schema tóm tắt: chỉ có created_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'message_id',
        'file_url',
        'mime_type',
    ];

    protected $casts = [
        'file_id' => 'int',
        'message_id' => 'int',
        'file_url' => 'string',
        'mime_type' => 'string',
        'created_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id', 'message_id');
    }
}

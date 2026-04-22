<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationAnalysis extends Model
{
    protected $table = 'conversation_analyses';

    protected $primaryKey = 'analysis_id';

    public const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id',
        'analyzer_name',
        'is_spam',
        'quality_score',
        'extracted_info',
    ];

    protected $casts = [
        // int unsigned
        'analysis_id' => 'int',
        'conversation_id' => 'string',

        'analyzer_name' => 'string',
        'is_spam' => 'bool',

        // decimal(5,4)
        'quality_score' => 'decimal:4',

        // json
        'extracted_info' => 'array',

        // datetime(6)
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function scopeSpam($query)
    {
        return $query->where('is_spam', true);
    }

    public function scopeHam($query)
    {
        return $query->where('is_spam', false);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('analysis_id');
    }
}

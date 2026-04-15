<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationAggregate extends Model
{
    protected $table = 'conversation_aggregates';
    protected $primaryKey = 'agg_id';

    public const UPDATED_AT = null;

    protected $fillable = [
        'agg_date',
        'hour',
        'channel',
        'total_conversations',
        'total_messages',
        'total_spam',
        'orders_extracted',
        'top_items',
    ];

    protected $casts = [
        'agg_id' => 'int',
        'agg_date' => 'date',
        'hour' => 'int',

        // varchar(50) nullable
        'channel' => 'string',

        'total_conversations' => 'int',
        'total_messages' => 'int',
        'total_spam' => 'int',
        'orders_extracted' => 'int',

        // json
        'top_items' => 'array',

        // datetime(6)
        'created_at' => 'datetime',
    ];

    public function scopeOnDate($query, \DateTimeInterface $date)
    {
        return $query->whereDate('agg_date', $date);
    }

    public function scopeBetweenDates($query, \DateTimeInterface $from, \DateTimeInterface $to)
    {
        return $query->whereBetween('agg_date', [$from, $to]);
    }

    public function scopeChannel($query, ?string $channel)
    {
        $channel = trim((string) $channel);
        if ($channel === '') {
            return $query;
        }

        return $query->where('channel', $channel);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('agg_date')->orderByDesc('hour')->orderByDesc('agg_id');
    }
}

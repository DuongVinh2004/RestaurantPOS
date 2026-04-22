<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeliveryAttempt extends Model
{
    protected $table = 'notification_delivery_attempts';

    protected $primaryKey = 'attempt_id';

    public $timestamps = false;

    protected $fillable = [
        'outbox_id',
        'channel',
        'provider_key',
        'attempt_number',
        'status',
        'recipient',
        'provider_message_id',
        'provider_status',
        'error_code',
        'error_message',
        'request_payload_json',
        'response_payload_json',
        'attempted_at',
        'completed_at',
        'created_at',
    ];

    protected $casts = [
        'attempt_id' => 'int',
        'outbox_id' => 'int',
        'attempt_number' => 'int',
        'request_payload_json' => 'array',
        'response_payload_json' => 'array',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(NotificationOutbox::class, 'outbox_id', 'outbox_id');
    }
}

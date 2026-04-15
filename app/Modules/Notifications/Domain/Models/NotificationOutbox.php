<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Models;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationOutbox extends Model
{
    protected $table = 'notification_outbox';
    protected $primaryKey = 'outbox_id';
    public $timestamps = false;

    protected $fillable = [
        'channel',
        'recipient',
        'recipient_user_id',
        'template_key',
        'idempotency_key',
        'dedupe_key',
        'payload_json',
        'status',
        'processing_token',
        'locked_until',
        'locked_by',
        'attempt_count',
        'last_attempted_at',
        'next_retry_at',
        'last_error',
        'related_reservation_id',
        'created_at',
        'sent_at',
    ];

    protected $casts = [
        'outbox_id' => 'int',
        'payload_json' => 'array',
        'attempt_count' => 'int',
        'recipient_user_id' => 'int',
        'related_reservation_id' => 'int',
        'locked_until' => 'datetime',
        'last_attempted_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'related_reservation_id', 'reservation_id');
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id', 'user_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(NotificationDeliveryAttempt::class, 'outbox_id', 'outbox_id');
    }
}

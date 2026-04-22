<?php

declare(strict_types=1);

namespace App\Modules\Waitlist\Domain\Models;

use App\Enums\WaitingListCustomerResponseStatus;
use App\Enums\WaitingListStatus;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    use HasRowVersion;

    protected $table = 'waiting_list';

    protected $primaryKey = 'waiting_id';

    protected $fillable = [
        'branch_id',
        'user_id',
        'customer_session_id',
        'guest_name',
        'phone',
        'guest_count',
        'requested_at',
        'status',
        'priority',
        'notified_at',
        'notify_expires_at',
        'customer_response_status',
        'customer_responded_at',
        'customer_confirmed_arrival_at',
        'notified_by',
        'seated_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'waiting_id' => 'int',
        'branch_id' => 'int',
        'user_id' => 'int',
        'customer_session_id' => 'string',
        'guest_name' => 'string',
        'phone' => 'string',
        'guest_count' => 'int',

        'requested_at' => 'datetime',
        'status' => WaitingListStatus::class,
        'priority' => 'int',
        'notified_at' => 'datetime',
        'notify_expires_at' => 'datetime',
        'customer_response_status' => WaitingListCustomerResponseStatus::class,
        'customer_responded_at' => 'datetime',
        'customer_confirmed_arrival_at' => 'datetime',
        'notified_by' => 'int',
        'created_at' => 'datetime',
        'seated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'cancel_reason' => 'string',
        'notes' => 'string',
        'updated_at' => 'datetime',
        'updated_by' => 'integer',
        'row_version' => 'integer', ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [WaitingListStatus::Waiting, WaitingListStatus::Notified]);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', WaitingListStatus::Waiting);
    }

    public function scopeLatestRequest($query)
    {
        return $query->orderByDesc('requested_at')->orderByDesc('waiting_id');
    }
}

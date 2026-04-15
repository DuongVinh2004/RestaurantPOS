<?php

declare(strict_types=1);

namespace App\Modules\PrivacyAudit\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPrivacyRequest extends Model
{
    public const TYPE_ANONYMIZE = 'Anonymize';

    public const STATUS_REQUESTED = 'Requested';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_FAILED = 'Failed';

    protected $table = 'customer_privacy_requests';
    protected $primaryKey = 'customer_privacy_request_id';

    protected $fillable = [
        'user_id',
        'request_type',
        'status',
        'requested_by_actor_type',
        'requested_by_user_id',
        'requested_via',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'processed_at',
        'resolution_notes',
        'result_summary_json',
    ];

    protected $casts = [
        'customer_privacy_request_id' => 'int',
        'user_id' => 'int',
        'requested_by_user_id' => 'int',
        'reviewed_by' => 'int',
        'result_summary_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id', 'user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    /**
     * @return list<string>
     */
    public static function requestableTypes(): array
    {
        return [
            self::TYPE_ANONYMIZE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function knownStatuses(): array
    {
        return [
            self::STATUS_REQUESTED,
            self::STATUS_REJECTED,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
        ];
    }
}

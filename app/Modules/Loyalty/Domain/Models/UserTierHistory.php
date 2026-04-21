<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTierHistory extends Model
{
    protected $table = 'user_tier_history';

    protected $primaryKey = 'history_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'from_tier_id',
        'to_tier_id',
        'reason',
        'effective_at',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'history_id' => 'int',
        'user_id' => 'int',
        'from_tier_id' => 'int',
        'to_tier_id' => 'int',
        'reason' => 'string',
        'effective_at' => 'datetime',
        'created_by' => 'int',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function fromTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'from_tier_id', 'tier_id');
    }

    public function toTier(): BelongsTo
    {
        return $this->belongsTo(LoyaltyTier::class, 'to_tier_id', 'tier_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}

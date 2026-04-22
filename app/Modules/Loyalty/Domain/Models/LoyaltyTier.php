<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyTier extends Model
{
    use HasRowVersion;

    protected $table = 'loyalty_tiers';

    protected $primaryKey = 'tier_id';

    protected $fillable = [
        'tier_code',
        'tier_name',
        'min_points',
        'benefits_json',
        'is_active',
    ];

    protected $casts = [
        'tier_id' => 'int',
        'tier_code' => 'string',
        'tier_name' => 'string',
        'min_points' => 'int',
        'benefits_json' => 'array',
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'row_version' => 'int',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'current_tier_id', 'tier_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\FeatureFlags\Domain\Models;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureFlag extends Model
{
    use HasRowVersion;

    protected $table = 'feature_flags';

    protected $primaryKey = 'feature_flag_id';

    protected $fillable = [
        'feature_key',
        'environment',
        'branch_id',
        'enabled',
        'reason',
        'updated_by',
    ];

    protected $casts = [
        'feature_flag_id' => 'int',
        'feature_key' => 'string',
        'environment' => 'string',
        'branch_id' => 'int',
        'enabled' => 'bool',
        'reason' => 'string',
        'updated_by' => 'int',
        'row_version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'user_id');
    }
}

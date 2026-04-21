<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Support\Persistence\HasRowVersion;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPoint extends Model
{
    use HasRowVersion;

    protected $table = 'user_points';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'total_points',
        'last_updated',
        'updated_by',
    ];

    protected $casts = [
        'user_id' => 'int',
        'total_points' => 'int',
        'last_updated' => 'datetime',
        'updated_by' => 'int',
        'row_version' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

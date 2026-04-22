<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    protected $primaryKey = 'notification_preference_id';

    protected $fillable = [
        'user_id',
        'channel',
        'is_enabled',
        'quiet_hours_start_minute',
        'quiet_hours_end_minute',
    ];

    protected $casts = [
        'notification_preference_id' => 'int',
        'user_id' => 'int',
        'is_enabled' => 'bool',
        'quiet_hours_start_minute' => 'int',
        'quiet_hours_end_minute' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

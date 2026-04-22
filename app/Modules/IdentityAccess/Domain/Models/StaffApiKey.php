<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffApiKey extends Model
{
    protected $table = 'staff_api_keys';

    protected $primaryKey = 'staff_api_key_id';

    protected $fillable = [
        'user_id',
        'label',
        'key_hash',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'staff_api_key_id' => 'int',
        'user_id' => 'int',
        'label' => 'string',
        'key_hash' => 'string',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopeActive($query)
    {
        $now = now();

        return $query
            ->whereNull('revoked_at')
            ->where(static function ($inner) use ($now): void {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            });
    }
}

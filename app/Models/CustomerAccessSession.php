<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccessSession extends Model
{
    use HasRowVersion;

    protected $table = 'customer_access_sessions';
    protected $primaryKey = 'access_session_id';

    protected $fillable = [
        'user_id',
        'session_id',
        'guest_name',
        'phone',
        'token_hash',
        'token_last_eight',
        'session_meta_json',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'created_ip',
        'user_agent',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'access_session_id' => 'int',
        'user_id' => 'int',
        'session_id' => 'string',
        'guest_name' => 'string',
        'phone' => 'string',
        'token_last_eight' => 'string',
        'session_meta_json' => 'array',
        'created_ip' => 'string',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'row_version' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopeActive($query)
    {
        $now = now('UTC');

        return $query
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $primaryKey = 'audit_id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'actor_user_id' => 'int',
        'before_json' => 'array',
        'after_json' => 'array',
        'summary_json' => 'array',
        'meta_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id', 'user_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(AuditLogSubject::class, 'audit_id', 'audit_id');
    }
}

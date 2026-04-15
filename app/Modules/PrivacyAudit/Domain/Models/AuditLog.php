<?php

declare(strict_types=1);

namespace App\Modules\PrivacyAudit\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $audit_id
 * @property string|null $action
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property int|null $actor_user_id
 * @property string|null $actor_type
 * @property array<string, mixed>|null $before_json
 * @property array<string, mixed>|null $after_json
 * @property array<string, mixed>|null $summary_json
 * @property array<string, mixed>|null $meta_json
 */
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

<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentAssignment extends Model
{
    protected $table = 'agent_assignments';

    protected $primaryKey = 'assignment_id';

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'agent_user_id',
        'assigned_at',
        'released_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'assignment_id' => 'int',
        'conversation_id' => 'string',
        'agent_user_id' => 'int',
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
        'is_active' => 'bool',
        'active_conversation_id' => 'string',
        'notes' => 'string',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id', 'user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('assigned_at')->orderByDesc('assignment_id');
    }
}

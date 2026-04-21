<?php

declare(strict_types=1);

namespace App\Modules\PrivacyCompliance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLogSubject extends Model
{
    protected $table = 'audit_log_subjects';

    protected $primaryKey = 'audit_subject_id';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'audit_id' => 'int',
        'created_at' => 'datetime',
    ];

    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'audit_id', 'audit_id');
    }
}

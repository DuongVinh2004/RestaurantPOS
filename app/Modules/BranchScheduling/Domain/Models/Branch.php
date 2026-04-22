<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Domain\Models;

use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasRowVersion;

    protected $table = 'branches';

    protected $primaryKey = 'branch_id';

    protected $fillable = [
        'branch_code',
        'branch_name',
        'description',
        'timezone',
        'currency',
        'business_hours',
        'closure_windows',
        'booking_policy',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'branch_id' => 'int',
        'branch_code' => 'string',
        'branch_name' => 'string',
        'description' => 'string',
        'timezone' => 'string',
        'currency' => 'string',
        'business_hours' => 'array',
        'closure_windows' => 'array',
        'booking_policy' => 'array',
        'is_active' => 'bool',
        'is_default' => 'bool',
        'row_version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

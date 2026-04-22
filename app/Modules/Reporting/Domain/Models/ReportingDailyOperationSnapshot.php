<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain\Models;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingDailyOperationSnapshot extends Model
{
    protected $table = 'reporting_daily_operation_snapshots';

    protected $primaryKey = 'snapshot_id';

    protected $fillable = [
        'branch_id',
        'business_date',
        'scheduled_reservation_count',
        'scheduled_guest_count',
        'scheduled_minutes_total',
        'checked_in_count',
        'completed_count',
        'cancelled_count',
        'no_show_count',
        'turn_count',
        'turn_minutes_total',
        'waiting_list_created_count',
        'waiting_list_notified_count',
        'waiting_list_seated_count',
        'waiting_list_cancelled_count',
        'waiting_list_confirmed_arrival_count',
        'refreshed_at',
    ];

    protected $casts = [
        'snapshot_id' => 'int',
        'branch_id' => 'int',
        'business_date' => 'date',
        'scheduled_reservation_count' => 'int',
        'scheduled_guest_count' => 'int',
        'scheduled_minutes_total' => 'int',
        'checked_in_count' => 'int',
        'completed_count' => 'int',
        'cancelled_count' => 'int',
        'no_show_count' => 'int',
        'turn_count' => 'int',
        'turn_minutes_total' => 'int',
        'waiting_list_created_count' => 'int',
        'waiting_list_notified_count' => 'int',
        'waiting_list_seated_count' => 'int',
        'waiting_list_cancelled_count' => 'int',
        'waiting_list_confirmed_arrival_count' => 'int',
        'refreshed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }
}

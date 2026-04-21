<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Domain\Models;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Support\Persistence\HasRowVersion;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashierShift extends Model
{
    use HasRowVersion;

    protected $table = 'cashier_shifts';

    protected $primaryKey = 'cashier_shift_id';

    protected $fillable = [
        'branch_id',
        'shift_code',
        'cashier_user_id',
        'status',
        'currency',
        'terminal_code',
        'opening_float_amount',
        'expected_cash_amount',
        'actual_cash_amount',
        'cash_discrepancy_amount',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'opening_note',
        'closing_note',
    ];

    protected $casts = [
        'cashier_shift_id' => 'int',
        'branch_id' => 'int',
        'cashier_user_id' => 'int',
        'active_cashier_user_id' => 'int',
        'status' => 'string',
        'currency' => 'string',
        'terminal_code' => 'string',
        'opening_float_amount' => 'decimal:2',
        'expected_cash_amount' => 'decimal:2',
        'actual_cash_amount' => 'decimal:2',
        'cash_discrepancy_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opened_by' => 'int',
        'closed_by' => 'int',
        'opening_note' => 'string',
        'closing_note' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'row_version' => 'int',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function cashierUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id', 'user_id');
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by', 'user_id');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by', 'user_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Domain\Models;

use App\Enums\TableHoldStatus;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\Persistence\HasRowVersion;
use App\Support\Persistence\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableHold extends Model
{
    use HasRowVersion;
    use UsesUuidPrimaryKey;

    protected $table = 'table_holds';

    protected $primaryKey = 'hold_id';

    protected $fillable = [
        'hold_id',
        'branch_id',
        'session_id',
        'user_id',
        'confirmed_reservation_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'hold_status',
        'expire_at',
        'updated_by',
    ];

    protected $casts = [
        'hold_id' => 'string',
        'branch_id' => 'int',
        'session_id' => 'string',
        'user_id' => 'int',
        'confirmed_reservation_id' => 'int',
        'start_time' => 'datetime',
        'expire_at' => 'datetime',
        'created_at' => 'datetime',
        'hold_status' => TableHoldStatus::class,
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'updated_at' => 'datetime',
        'updated_by' => 'integer',
        'row_version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function confirmedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'confirmed_reservation_id', 'reservation_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(TableHoldDetail::class, 'hold_id', 'hold_id');
    }

    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(
            RestaurantTable::class,
            'table_hold_details',
            'hold_id',
            'table_id',
            'hold_id',
            'table_id'
        )->withPivot(['hold_detail_id']);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('hold_status', [
            TableHoldStatus::Holding,
            TableHoldStatus::Pending,
            TableHoldStatus::Confirmed,
        ]);
    }

    public function scopeNotExpired($query)
    {
        $now = now('UTC');

        return $query->where(function ($q) use ($now) {
            $q->where('hold_status', TableHoldStatus::Confirmed->value)
                ->orWhere(function ($activeQuery) use ($now) {
                    $activeQuery->whereIn('hold_status', [
                        TableHoldStatus::Holding->value,
                        TableHoldStatus::Pending->value,
                    ])->where(function ($expiryQuery) use ($now) {
                        $expiryQuery->whereNull('expire_at')
                            ->orWhere('expire_at', '>', $now);
                    });
                });
        });
    }
}

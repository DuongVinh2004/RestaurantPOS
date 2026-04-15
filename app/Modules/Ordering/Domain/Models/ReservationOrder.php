<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Domain\Models;

use App\Casts\ReservationOrderTypeCast;
use App\Enums\ReservationOrderStatus;
use App\Models\Concerns\HasRowVersion;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationOrder extends Model
{
    use HasRowVersion;

    protected $table = 'reservation_orders';

    protected $primaryKey = 'order_id';

    protected $fillable = [
        'reservation_id',
        'order_type',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_id' => 'int',
        'reservation_id' => 'int',
        'order_type' => ReservationOrderTypeCast::class,
        'status' => ReservationOrderStatus::class,
        'notes' => 'string',
        'created_by' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'updated_by' => 'integer',
        'row_version' => 'integer',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReservationOrderItem::class, 'order_id', 'order_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', ReservationOrderStatus::Active);
    }
}

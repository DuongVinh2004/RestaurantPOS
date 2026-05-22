<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Domain\Models;

use App\Enums\PreorderStatus;
use App\Models\User;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Preorder extends Model
{
    protected $table = 'preorders';
    protected $primaryKey = 'preorder_id';

    protected $fillable = [
        'reservation_id',
        'customer_user_id',
        'status',
        'notes',
        'submitted_at',
        'confirmed_at',
        'rejected_at',
        'cancelled_at',
        'converted_at',
        'row_version',
    ];

    protected $casts = [
        'status' => PreorderStatus::class,
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'converted_at' => 'datetime',
        'row_version' => 'integer',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PreorderItem::class, 'preorder_id', 'preorder_id');
    }
}

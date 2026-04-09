<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVoucher extends Model
{
    use HasRowVersion;

    protected $table = 'user_vouchers';
    protected $primaryKey = 'user_voucher_id';


    protected $fillable = [
        'user_id',
        'voucher_id',
        'assigned_date',
        'is_used',
        'used_date',
        'used_reservation_id',
        'used_amount',
        'lock_token',
        'locked_until',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'user_voucher_id' => 'int',
        'user_id' => 'int',
        'voucher_id' => 'int',
        'assigned_date' => 'datetime',
        'is_used' => 'bool',
        'used_date' => 'datetime',
        'used_reservation_id' => 'int',
        'used_amount' => 'decimal:2',
        'lock_token' => 'string',
        'locked_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'row_version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id', 'voucher_id');
    }

    public function usedReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'used_reservation_id', 'reservation_id');
    }

    public function scopeUnused($query)
    {
        return $query->where('is_used', false);
    }
}
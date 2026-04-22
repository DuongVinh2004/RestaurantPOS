<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class LoyaltyPointTransaction extends Model
{
    protected $table = 'loyalty_point_transactions';

    protected $primaryKey = 'txn_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'reservation_id',
        'txn_type',
        'points',
        'amount_basis',
        'currency',
        'reason',
        'created_at',
        'created_by',
    ];

    protected $casts = [
        'txn_id' => 'int',
        'user_id' => 'int',
        'reservation_id' => 'int',
        'points' => 'int',
        'amount_basis' => 'decimal:2',
        'currency' => 'string',
        'reason' => 'string',
        'created_at' => 'datetime',
        'created_by' => 'int',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $type = trim((string) $model->txn_type);
            $points = (int) $model->points;

            if ($type === 'Earn' && $points <= 0) {
                throw ValidationException::withMessages(['points' => 'Earn transactions must use positive points.']);
            }

            if ($type === 'Redeem' && $points >= 0) {
                throw ValidationException::withMessages(['points' => 'Redeem transactions must use negative points.']);
            }

            if ($type === 'Adjust' && $points === 0) {
                throw ValidationException::withMessages(['points' => 'Adjust transactions must use non-zero points.']);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Domain\Models;

use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ReservationTable extends Pivot
{
    protected $table = 'reservation_tables';

    protected $primaryKey = 'reservation_table_id';

    public $timestamps = false;

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'reservation_id',
        'table_id',
    ];

    protected $casts = [
        'reservation_table_id' => 'int',
        'reservation_id' => 'int',
        'table_id' => 'int',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id', 'table_id');
    }
}

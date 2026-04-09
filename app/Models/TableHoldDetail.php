<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableHoldDetail extends Model
{
    protected $table = 'table_hold_details';
    protected $primaryKey = 'hold_detail_id';

    public $timestamps = false;

    protected $fillable = [
        'hold_id',
        'table_id',
    ];

    protected $casts = [
        'hold_detail_id' => 'int',
        'hold_id' => 'string',
        'table_id' => 'int',
    ];

    public function hold(): BelongsTo
    {
        return $this->belongsTo(TableHold::class, 'hold_id', 'hold_id');
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id', 'table_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Domain\Models;

use App\Enums\KitchenStationOutputMode;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenStation extends Model
{
    protected $table = 'kitchen_stations';

    protected $primaryKey = 'station_id';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'description',
        'output_mode',
        'printer_target',
        'is_active',
    ];

    protected $casts = [
        'station_id' => 'int',
        'branch_id' => 'int',
        'output_mode' => KitchenStationOutputMode::class,
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function categoryRoutes(): HasMany
    {
        return $this->hasMany(KitchenStationCategoryRoute::class, 'station_id', 'station_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(KitchenOrderItemTicket::class, 'station_id', 'station_id');
    }
}

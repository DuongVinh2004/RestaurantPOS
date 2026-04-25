<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Domain\Models;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\Catalog\Domain\Models\MenuCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenStationCategoryRoute extends Model
{
    protected $table = 'kitchen_station_category_routes';

    protected $primaryKey = 'route_id';

    protected $fillable = [
        'station_id',
        'branch_id',
        'category_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'route_id' => 'int',
        'station_id' => 'int',
        'branch_id' => 'int',
        'category_id' => 'int',
        'sort_order' => 'int',
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'station_id', 'station_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id', 'category_id');
    }
}

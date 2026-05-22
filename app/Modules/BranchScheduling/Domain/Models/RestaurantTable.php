<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Domain\Models;

use App\Enums\RestaurantTableStatus;
use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RestaurantTable extends Model
{
    use HasRowVersion;

    protected $table = 'restaurant_tables';

    protected $primaryKey = 'table_id';

    protected $fillable = [
        'branch_id',
        'table_code',
        'template_id',
        'zone',
        'pos_x',
        'pos_y',
        'status',
        'description',
        'is_deleted',
        'price',
        'qr_payment_token',
    ];

    protected $casts = [
        'table_id' => 'int',
        'branch_id' => 'int',
        'template_id' => 'int',
        'pos_x' => 'int',
        'pos_y' => 'int',
        'status' => RestaurantTableStatus::class,
        'is_deleted' => 'bool',
        'row_version' => 'int',
        'price' => 'decimal:2',
        'qr_payment_token' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TableTemplate::class, 'template_id', 'template_id');
    }

    public function holdDetails(): HasMany
    {
        return $this->hasMany(TableHoldDetail::class, 'table_id', 'table_id');
    }

    public function holds(): BelongsToMany
    {
        return $this->belongsToMany(
            TableHold::class,
            'table_hold_details',
            'table_id',
            'hold_id',
            'table_id',
            'hold_id'
        )->withPivot(['hold_detail_id']);
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', RestaurantTableStatus::Available->value)->where('is_deleted', false);
    }

    public function scopeInZone($query, ?string $zone)
    {
        $zone = trim((string) $zone);
        if ($zone === '') {
            return $query;
        }

        return $query->whereRaw('TRIM(COALESCE(zone, "")) = ?', [$zone]);
    }
}

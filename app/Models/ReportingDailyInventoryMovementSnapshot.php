<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingDailyInventoryMovementSnapshot extends Model
{
    protected $table = 'reporting_daily_inventory_movement_snapshots';
    protected $primaryKey = 'snapshot_id';

    protected $fillable = [
        'branch_id',
        'business_date',
        'ingredient_id',
        'unit_code',
        'movement_count',
        'purchase_receipt_movement_count',
        'stock_in_quantity',
        'stock_out_quantity',
        'adjustment_increase_quantity',
        'adjustment_decrease_quantity',
        'wastage_quantity',
        'net_quantity_delta',
        'last_movement_at',
        'refreshed_at',
    ];

    protected $casts = [
        'snapshot_id' => 'int',
        'branch_id' => 'int',
        'business_date' => 'date',
        'ingredient_id' => 'int',
        'movement_count' => 'int',
        'purchase_receipt_movement_count' => 'int',
        'stock_in_quantity' => 'decimal:3',
        'stock_out_quantity' => 'decimal:3',
        'adjustment_increase_quantity' => 'decimal:3',
        'adjustment_decrease_quantity' => 'decimal:3',
        'wastage_quantity' => 'decimal:3',
        'net_quantity_delta' => 'decimal:3',
        'last_movement_at' => 'datetime',
        'refreshed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }
}

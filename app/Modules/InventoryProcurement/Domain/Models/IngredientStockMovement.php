<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Domain\Models;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientStockMovement extends Model
{
    public const TYPE_STOCK_IN = 'StockIn';

    public const TYPE_STOCK_OUT = 'StockOut';

    public const TYPE_ADJUSTMENT_INCREASE = 'AdjustmentIncrease';

    public const TYPE_ADJUSTMENT_DECREASE = 'AdjustmentDecrease';

    public const TYPE_WASTAGE = 'Wastage';

    protected $table = 'ingredient_stock_movements';

    protected $primaryKey = 'movement_id';

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'ingredient_id',
        'movement_type',
        'quantity_delta',
        'unit_code',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'movement_id' => 'int',
        'branch_id' => 'int',
        'ingredient_id' => 'int',
        'quantity_delta' => 'decimal:3',
        'created_by' => 'int',
        'created_at' => 'datetime',
    ];

    /**
     * @return list<string>
     */
    public static function supportedTypes(): array
    {
        return [
            self::TYPE_STOCK_IN,
            self::TYPE_STOCK_OUT,
            self::TYPE_ADJUSTMENT_INCREASE,
            self::TYPE_ADJUSTMENT_DECREASE,
            self::TYPE_WASTAGE,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}

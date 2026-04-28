<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\InventoryProcurement\Domain\Models\Ingredient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemRecipe extends Model
{
    protected $table = 'menu_item_recipes';

    protected $primaryKey = 'recipe_line_id';

    protected $fillable = [
        'item_id',
        'ingredient_id',
        'quantity',
        'unit_code',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'recipe_line_id' => 'int',
        'item_id' => 'int',
        'ingredient_id' => 'int',
        'quantity' => 'decimal:3',
        'sort_order' => 'int',
        'row_version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }
}

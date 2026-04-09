<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    protected $table = 'ingredients';
    protected $primaryKey = 'ingredient_id';

    protected $fillable = [
        'code',
        'name',
        'unit_code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'ingredient_id' => 'int',
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function recipeLines(): HasMany
    {
        return $this->hasMany(MenuItemRecipe::class, 'ingredient_id', 'ingredient_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(IngredientStockMovement::class, 'ingredient_id', 'ingredient_id');
    }
}

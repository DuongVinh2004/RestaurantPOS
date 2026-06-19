<?php

namespace App\Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MenuModifier extends Model
{
    protected $table = 'menu_modifiers';

    protected $primaryKey = 'modifier_id';

    protected $fillable = [
        'group_id',
        'name',
        'description',
        'price_adjustment',
        'is_active',
        'sort_order',
        'row_version',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'price_adjustment' => 'decimal:0',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'row_version' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(MenuModifierGroup::class, 'group_id', 'group_id');
    }
}

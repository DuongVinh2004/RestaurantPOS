<?php

namespace App\Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MenuModifierGroup extends Model
{
    protected $table = 'menu_modifier_groups';
    protected $primaryKey = 'group_id';

    protected $fillable = [
        'name',
        'description',
        'min_selections',
        'max_selections',
        'is_active',
        'row_version',
    ];

    protected $casts = [
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'is_active' => 'boolean',
        'row_version' => 'integer',
    ];

    public function modifiers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MenuModifier::class, 'group_id', 'group_id')->orderBy('sort_order');
    }

    public function items()
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_modifier_groups', 'group_id', 'item_id')
            ->withPivot('sort_order');
    }
}

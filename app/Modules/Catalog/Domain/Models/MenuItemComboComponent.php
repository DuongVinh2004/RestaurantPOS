<?php

namespace App\Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItemComboComponent extends Model
{
    protected $table = 'menu_item_combo_components';

    protected $primaryKey = 'combo_component_id';

    protected $fillable = [
        'combo_item_id',
        'component_item_id',
        'quantity',
    ];

    protected $casts = [
        'combo_component_id' => 'int',
        'combo_item_id' => 'int',
        'component_item_id' => 'int',
        'quantity' => 'int',
    ];

    public function comboItem()
    {
        return $this->belongsTo(MenuItem::class, 'combo_item_id', 'item_id');
    }

    public function componentItem()
    {
        return $this->belongsTo(MenuItem::class, 'component_item_id', 'item_id');
    }
}

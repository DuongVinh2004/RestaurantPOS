<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Domain\Models;

use App\Modules\Catalog\Domain\Models\MenuItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreorderItem extends Model
{
    protected $table = 'preorder_items';
    protected $primaryKey = 'preorder_item_id';

    protected $fillable = [
        'preorder_id',
        'menu_item_id',
        'item_name_snapshot',
        'unit_price_snapshot',
        'quantity',
        'line_total_snapshot',
        'currency',
        'notes',
    ];

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(Preorder::class, 'preorder_id', 'preorder_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id', 'item_id');
    }
}

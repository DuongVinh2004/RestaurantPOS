<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    protected $table = 'purchase_order_lines';

    protected $primaryKey = 'po_line_id';

    protected $fillable = [
        'purchase_order_id',
        'ingredient_id',
        'ordered_quantity',
        'received_quantity',
        'unit_code',
        'unit_cost',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'po_line_id' => 'int',
        'purchase_order_id' => 'int',
        'ingredient_id' => 'int',
        'ordered_quantity' => 'decimal:3',
        'received_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:3',
        'sort_order' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class, 'purchase_order_line_id', 'po_line_id');
    }
}

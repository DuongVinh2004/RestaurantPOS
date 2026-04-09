<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptLine extends Model
{
    protected $table = 'purchase_receipt_lines';
    protected $primaryKey = 'receipt_line_id';

    public $timestamps = false;

    protected $fillable = [
        'receipt_id',
        'purchase_order_line_id',
        'ingredient_id',
        'received_quantity',
        'unit_code',
        'unit_cost',
        'stock_movement_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'receipt_line_id' => 'int',
        'receipt_id' => 'int',
        'purchase_order_line_id' => 'int',
        'ingredient_id' => 'int',
        'received_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:3',
        'stock_movement_id' => 'int',
        'created_at' => 'datetime',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'receipt_id', 'receipt_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id', 'po_line_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id', 'ingredient_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(IngredientStockMovement::class, 'stock_movement_id', 'movement_id');
    }
}

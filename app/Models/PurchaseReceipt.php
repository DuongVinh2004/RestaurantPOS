<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseReceiptStatus;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceipt extends Model
{
    protected $table = 'purchase_receipts';

    protected $primaryKey = 'receipt_id';

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'purchase_order_id',
        'receipt_code',
        'receipt_status',
        'received_at',
        'supplier_document_no',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'receipt_id' => 'int',
        'branch_id' => 'int',
        'purchase_order_id' => 'int',
        'receipt_status' => PurchaseReceiptStatus::class,
        'received_at' => 'datetime',
        'created_by' => 'int',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReceiptLine::class, 'receipt_id', 'receipt_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Domain\Models;

use App\Enums\PurchaseOrderStatus;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $table = 'purchase_orders';

    protected $primaryKey = 'purchase_order_id';

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'order_code',
        'purchase_order_status',
        'ordered_at',
        'expected_at',
        'received_at',
        'supplier_reference',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_order_id' => 'int',
        'branch_id' => 'int',
        'supplier_id' => 'int',
        'purchase_order_status' => PurchaseOrderStatus::class,
        'ordered_at' => 'datetime',
        'expected_at' => 'datetime',
        'received_at' => 'datetime',
        'created_by' => 'int',
        'updated_by' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class, 'purchase_order_id', 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'user_id');
    }
}

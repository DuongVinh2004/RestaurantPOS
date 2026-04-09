<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingDailySalesSnapshot extends Model
{
    protected $table = 'reporting_daily_sales_snapshots';
    protected $primaryKey = 'snapshot_id';

    protected $fillable = [
        'branch_id',
        'business_date',
        'currency',
        'billed_reservation_count',
        'billed_guest_count',
        'gross_bill_amount',
        'discount_amount',
        'billed_total_amount',
        'invoice_issued_count',
        'invoiced_total_amount',
        'invoiced_tax_amount',
        'payment_row_count',
        'refund_row_count',
        'captured_amount',
        'refunded_amount',
        'net_paid_amount',
        'deposit_net_amount',
        'final_net_amount',
        'cashier_shift_closed_count',
        'cash_discrepancy_amount',
        'refreshed_at',
    ];

    protected $casts = [
        'snapshot_id' => 'int',
        'branch_id' => 'int',
        'business_date' => 'date',
        'billed_reservation_count' => 'int',
        'billed_guest_count' => 'int',
        'gross_bill_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'billed_total_amount' => 'decimal:2',
        'invoice_issued_count' => 'int',
        'invoiced_total_amount' => 'decimal:2',
        'invoiced_tax_amount' => 'decimal:2',
        'payment_row_count' => 'int',
        'refund_row_count' => 'int',
        'captured_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'net_paid_amount' => 'decimal:2',
        'deposit_net_amount' => 'decimal:2',
        'final_net_amount' => 'decimal:2',
        'cashier_shift_closed_count' => 'int',
        'cash_discrepancy_amount' => 'decimal:2',
        'refreshed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }
}

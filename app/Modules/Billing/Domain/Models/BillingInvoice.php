<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Models;

use App\Support\Persistence\HasRowVersion;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingInvoice extends Model
{
    use HasRowVersion;

    protected $table = 'billing_invoices';

    protected $primaryKey = 'billing_invoice_id';

    protected $fillable = [
        'reservation_id',
        'invoice_number',
        'invoice_status',
        'subtotal_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'tax_code',
        'tax_name',
        'tax_rate_percentage',
        'prices_include_tax',
        'taxable_amount',
        'tax_amount',
        'seller_name',
        'seller_tax_id',
        'seller_address',
        'issued_at',
        'issued_by',
        'voided_at',
        'voided_by',
        'metadata_json',
    ];

    protected $casts = [
        'billing_invoice_id' => 'int',
        'reservation_id' => 'int',
        'invoice_number' => 'string',
        'invoice_status' => 'string',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'currency' => 'string',
        'tax_code' => 'string',
        'tax_name' => 'string',
        'tax_rate_percentage' => 'decimal:3',
        'prices_include_tax' => 'boolean',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'seller_name' => 'string',
        'seller_tax_id' => 'string',
        'seller_address' => 'string',
        'issued_at' => 'datetime',
        'issued_by' => 'int',
        'voided_at' => 'datetime',
        'voided_by' => 'int',
        'metadata_json' => 'array',
        'row_version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by', 'user_id');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by', 'user_id');
    }
}

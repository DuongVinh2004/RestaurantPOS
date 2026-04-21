<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Models;

use App\Enums\PaymentProviderWebhookReceiptStatus;
use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;

class PaymentProviderWebhookReceipt extends Model
{
    use HasRowVersion;

    protected $table = 'payment_provider_webhook_receipts';

    protected $primaryKey = 'payment_provider_webhook_receipt_id';

    protected $fillable = [
        'provider_code',
        'provider_event_code',
        'provider_session_code',
        'payment_scope',
        'event_type',
        'delivery_status',
        'request_signature',
        'request_headers_json',
        'request_body',
        'provider_payload_json',
        'processed_at',
        'failure_message',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'payment_provider_webhook_receipt_id' => 'int',
        'provider_code' => 'string',
        'provider_event_code' => 'string',
        'provider_session_code' => 'string',
        'payment_scope' => 'string',
        'event_type' => 'string',
        'delivery_status' => PaymentProviderWebhookReceiptStatus::class,
        'request_signature' => 'string',
        'request_headers_json' => 'array',
        'request_body' => 'string',
        'provider_payload_json' => 'array',
        'processed_at' => 'datetime',
        'failure_message' => 'string',
        'created_by' => 'int',
        'updated_by' => 'int',
        'row_version' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

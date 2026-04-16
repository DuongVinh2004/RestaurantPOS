<?php

declare(strict_types=1);

namespace App\Modules\CheckoutPayments\Http\Resources;

use App\Modules\CheckoutPayments\Support\PaymentProviderPayloadSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CustomerReservationBillPaymentSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bill_payment_session_id' => (int) $this->bill_payment_session_id,
            'reservation_id' => (int) $this->reservation_id,
            'order_id' => $this->order_id !== null ? (int) $this->order_id : null,
            'provider_code' => (string) $this->provider_code,
            'provider_session_code' => (string) $this->provider_session_code,
            'provider_payment_code' => $this->provider_payment_code !== null ? (string) $this->provider_payment_code : null,
            'payment_method' => $this->payment_method !== null ? (string) $this->payment_method : null,
            'amount' => $this->amount !== null ? (string) $this->amount : null,
            'currency' => $this->currency !== null ? (string) $this->currency : null,
            'session_status' => $this->session_status?->value ?? (string) $this->session_status,
            'settlement_status' => $this->settlement_status?->value ?? (string) $this->settlement_status,
            'linked_payment_id' => $this->linked_payment_id !== null ? (int) $this->linked_payment_id : null,
            'failure_code' => $this->failure_code !== null ? (string) $this->failure_code : null,
            'failure_message' => $this->failure_message !== null ? (string) $this->failure_message : null,
            'provider_payload' => PaymentProviderPayloadSanitizer::sanitizeSessionPayloadForPresentation($this->provider_payload_json),
            'provider_expires_at' => $this->iso($this->provider_expires_at),
            'last_reconciled_at' => $this->iso($this->last_reconciled_at),
            'confirmed_at' => $this->iso($this->confirmed_at),
            'failed_at' => $this->iso($this->failed_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'expired_at' => $this->iso($this->expired_at),
            'row_version' => (int) ($this->row_version ?? 1),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}

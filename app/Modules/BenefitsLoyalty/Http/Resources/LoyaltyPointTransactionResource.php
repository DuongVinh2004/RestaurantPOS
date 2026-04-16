<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Resources;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class LoyaltyPointTransactionResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        $txnId = data_get($resource, 'txn_id');
        $userId = data_get($resource, 'user_id');
        $reservationId = data_get($resource, 'reservation_id');
        $amountBasis = data_get($resource, 'amount_basis');
        $createdBy = data_get($resource, 'created_by');

        return [
            'txn_id' => $txnId !== null ? (int) $txnId : null,
            'user_id' => $userId !== null ? (int) $userId : null,
            'reservation_id' => $reservationId !== null ? (int) $reservationId : null,
            'txn_type' => ($value = data_get($resource, 'txn_type')) !== null ? (string) $value : null,
            'points' => (int) (data_get($resource, 'points') ?? 0),
            'amount_basis' => $amountBasis !== null ? Money::format($amountBasis, true) : null,
            'currency' => (string) (data_get($resource, 'currency') ?? 'VND'),
            'reason' => data_get($resource, 'reason'),
            'created_at' => $this->serializeDateTime(data_get($resource, 'created_at')),
            'created_by' => $createdBy !== null ? (int) $createdBy : null,
        ];
    }

    private function serializeDateTime(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc()->toIso8601String();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->toIso8601String();
    }
}

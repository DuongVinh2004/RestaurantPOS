<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class CustomerReservationDepositPreviewResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $deposit = (array) data_get($this->resource, 'deposit', []);
        $rawPayments = $deposit['payments'] ?? [];
        $payments = ($rawPayments instanceof Collection ? $rawPayments : collect($rawPayments))
            ->map(function (mixed $payment): array {
                if (! $payment instanceof Payment) {
                    return [];
                }

                return [
                    'payment_id' => (int) $payment->payment_id,
                    'refund_of_payment_id' => $payment->refund_of_payment_id !== null ? (int) $payment->refund_of_payment_id : null,
                    'amount' => number_format((float) ($payment->amount ?? 0.0), 2, '.', ''),
                    'currency' => (string) ($payment->currency ?? 'VND'),
                    'payment_method' => $payment->payment_method,
                    'payment_provider' => $payment->payment_provider,
                    'payment_type' => (string) ($payment->payment_type ?? ''),
                    'status' => (string) ($payment->status?->value ?? $payment->status),
                    'paid_at' => $payment->paid_at?->utc()->toIso8601String(),
                    'created_at' => $payment->created_at?->utc()->toIso8601String(),
                    'updated_at' => $payment->updated_at?->utc()->toIso8601String(),
                ];
            })
            ->filter(fn (array $row): bool => $row !== [])
            ->values()
            ->all();

        $requiredAmount = number_format((float) ($deposit['required_amount'] ?? 0.0), 2, '.', '');
        $selfService = (array) ($deposit['self_service'] ?? []);

        return [
            'reservation' => (new ReservationResource(data_get($this->resource, 'reservation')))->resolve($request),
            'deposit' => [
                'status' => $deposit['status'] ?? null,
                'required_amount' => $requiredAmount,
                'paid_amount' => number_format((float) ($deposit['paid_amount'] ?? 0.0), 2, '.', ''),
                'remaining_amount' => number_format((float) ($deposit['remaining_amount'] ?? $deposit['outstanding_amount'] ?? 0.0), 2, '.', ''),
                'outstanding_amount' => number_format((float) ($deposit['outstanding_amount'] ?? 0.0), 2, '.', ''),
                'deposit_required' => (float) $requiredAmount > 0.0001,
                'currency' => $deposit['currency'] ?? null,
                'currencies' => array_values((array) ($deposit['currencies'] ?? [])),
                'has_mixed_currencies' => (bool) ($deposit['has_mixed_currencies'] ?? false),
                'status_flags' => (array) ($deposit['status_flags'] ?? []),
                'payment_summary' => (array) ($deposit['payment_summary'] ?? []),
                'payment_session_summary' => (array) ($deposit['payment_session_summary'] ?? []),
                'payments' => $payments,
                'self_service' => [
                    'supported' => (bool) ($selfService['supported'] ?? false),
                    'deposit_required' => (bool) ($selfService['deposit_required'] ?? false),
                    'outstanding_amount' => number_format((float) ($selfService['outstanding_amount'] ?? 0.0), 2, '.', ''),
                    'requirement_acknowledged' => (bool) ($selfService['requirement_acknowledged'] ?? false),
                    'acknowledged_at' => $selfService['acknowledged_at'] ?? null,
                    'intent_status' => $selfService['intent_status'] ?? 'None',
                    'intent_submitted_at' => $selfService['intent_submitted_at'] ?? null,
                    'intent_revoked_at' => $selfService['intent_revoked_at'] ?? null,
                    'actionable' => (bool) ($selfService['actionable'] ?? false),
                    'can_acknowledge' => (bool) ($selfService['can_acknowledge'] ?? false),
                    'can_submit_intent' => (bool) ($selfService['can_submit_intent'] ?? false),
                    'can_revoke_intent' => (bool) ($selfService['can_revoke_intent'] ?? false),
                    'actual_payment_recorded' => (bool) ($selfService['actual_payment_recorded'] ?? false),
                    'final_payment_recorded' => (bool) ($selfService['final_payment_recorded'] ?? false),
                    'requires_staff_payment_collection' => (bool) ($selfService['requires_staff_payment_collection'] ?? false),
                    'next_step' => $selfService['next_step'] ?? null,
                ],
            ],
        ];
    }
}

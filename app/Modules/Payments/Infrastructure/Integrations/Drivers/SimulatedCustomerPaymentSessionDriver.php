<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\Drivers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SimulatedCustomerPaymentSessionDriver
{
    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function createSession(
        string $sessionPrefix,
        string $paymentPath,
        int $reservationId,
        int $customerUserId,
        array $payload,
        int $ttlMinutes,
        string $instructions
    ): array {
        $providerSessionCode = trim($sessionPrefix).Str::lower(Str::uuid()->toString());
        $expiresAt = Carbon::now('UTC')->addMinutes(max(1, $ttlMinutes));

        return [
            'provider_code' => 'simulated',
            'provider_session_code' => $providerSessionCode,
            'provider_payment_code' => null,
            'payment_method' => $this->resolvePaymentMethod($payload),
            'session_status' => 'Pending',
            'provider_expires_at' => $expiresAt,
            'failure_code' => null,
            'failure_message' => null,
            'provider_payload' => [
                'mode' => 'simulated',
                'provider_action' => 'confirm_or_refresh',
                'payment_url' => 'simulated://'.trim($paymentPath, '/').'/'.$providerSessionCode,
                'instructions' => $instructions,
                'reservation_id' => $reservationId,
                'customer_user_id' => $customerUserId,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,mixed>  $existingPayload
     * @return array<string,mixed>
     */
    public function mutateSession(
        string $providerSessionCode,
        ?string $providerPaymentCode,
        ?string $paymentMethod,
        string $currentStatus,
        array $existingPayload,
        mixed $providerExpiresAt,
        array $payload,
        bool $isConfirm,
        string $failureMessage
    ): array {
        $normalizedCurrentStatus = trim($currentStatus) !== '' ? trim($currentStatus) : 'Pending';

        if (in_array($normalizedCurrentStatus, ['Succeeded', 'Failed', 'Cancelled', 'Expired'], true)) {
            return [
                'provider_code' => 'simulated',
                'provider_session_code' => $providerSessionCode,
                'provider_payment_code' => $providerPaymentCode,
                'payment_method' => $paymentMethod,
                'session_status' => $normalizedCurrentStatus,
                'provider_expires_at' => $providerExpiresAt,
                'failure_code' => $existingPayload['failure_code'] ?? null,
                'failure_message' => $existingPayload['failure_message'] ?? null,
                'provider_payload' => array_merge($existingPayload, [
                    'mode' => 'simulated',
                    'sticky_terminal' => true,
                    'confirmed_via_api' => $isConfirm,
                ]),
            ];
        }

        $outcome = strtolower(trim((string) ($payload['simulation_outcome'] ?? 'pending')));
        $status = match ($outcome) {
            'succeeded' => 'Succeeded',
            'failed' => 'Failed',
            default => 'Pending',
        };

        return [
            'provider_code' => 'simulated',
            'provider_session_code' => $providerSessionCode,
            'provider_payment_code' => $status === 'Succeeded'
                ? ($providerPaymentCode ?: ('sim-pay-'.Str::lower(Str::uuid()->toString())))
                : null,
            'payment_method' => $paymentMethod,
            'session_status' => $status,
            'provider_expires_at' => $providerExpiresAt,
            'failure_code' => $status === 'Failed' ? 'simulated_failure' : null,
            'failure_message' => $status === 'Failed' ? $failureMessage : null,
            'provider_payload' => array_merge($existingPayload, [
                'mode' => 'simulated',
                'confirmed_via_api' => $isConfirm,
                'last_outcome' => $outcome,
            ]),
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function resolvePaymentMethod(array $payload): string
    {
        $paymentMethod = trim((string) ($payload['payment_method'] ?? 'Online'));

        return $paymentMethod !== '' ? $paymentMethod : 'Online';
    }
}

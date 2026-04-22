<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Guards;

use App\Enums\PaymentSessionScope;
use App\Modules\Payments\Domain\Models\ReservationBillPaymentSession;
use App\Modules\Payments\Domain\Models\ReservationDepositPaymentSession;
use Illuminate\Validation\ValidationException;
use ValueError;

class PaymentSessionScopeGuard
{
    public function assertProviderSessionCodeIsAvailable(string $providerCode, string $providerSessionCode, PaymentSessionScope $scope): void
    {
        $providerCode = trim($providerCode);
        $providerSessionCode = trim($providerSessionCode);

        if ($providerCode === '' || $providerSessionCode === '') {
            throw ValidationException::withMessages([
                'provider_session_code' => ['Payment provider session code must be present before the session can be persisted.'],
            ]);
        }

        $presence = $this->detectScopePresence($providerCode, $providerSessionCode);
        if (! $presence['deposit_exists'] && ! $presence['bill_exists']) {
            return;
        }

        $existingScopes = [];
        if ($presence['deposit_exists']) {
            $existingScopes[] = PaymentSessionScope::Deposit->value;
        }
        if ($presence['bill_exists']) {
            $existingScopes[] = PaymentSessionScope::Bill->value;
        }

        throw ValidationException::withMessages([
            'provider_session_code' => [
                sprintf(
                    'Provider session code is already bound to an existing [%s] payment session for this provider.',
                    implode(', ', $existingScopes),
                ),
            ],
        ]);
    }

    public function resolveVerifiedScope(string $providerCode, string $providerSessionCode, mixed $declaredScope): PaymentSessionScope
    {
        $providerCode = trim($providerCode);
        $providerSessionCode = trim($providerSessionCode);
        $presence = $this->detectScopePresence($providerCode, $providerSessionCode);

        if ($presence['deposit_exists'] && $presence['bill_exists']) {
            throw ValidationException::withMessages([
                'payment_scope' => ['Webhook provider_session_code is ambiguous because it exists in both deposit and bill payment session scopes.'],
            ]);
        }

        if (is_string($declaredScope) && trim($declaredScope) !== '') {
            try {
                $scope = PaymentSessionScope::from(trim($declaredScope));
            } catch (ValueError) {
                throw ValidationException::withMessages([
                    'payment_scope' => ['Webhook payload payment_scope is invalid for the referenced provider session.'],
                ]);
            }

            $scopeExists = match ($scope) {
                PaymentSessionScope::Deposit => $presence['deposit_exists'],
                PaymentSessionScope::Bill => $presence['bill_exists'],
            };

            if (! $scopeExists) {
                throw ValidationException::withMessages([
                    'payment_scope' => ['Webhook payload payment_scope does not match the stored payment session scope.'],
                ]);
            }

            return $scope;
        }

        return match (true) {
            $presence['deposit_exists'] => PaymentSessionScope::Deposit,
            $presence['bill_exists'] => PaymentSessionScope::Bill,
            default => throw ValidationException::withMessages([
                'payment_scope' => ['Webhook payload must identify a valid payment_scope for the referenced provider session.'],
            ]),
        };
    }

    /**
     * @return array{deposit_exists:bool,bill_exists:bool}
     */
    private function detectScopePresence(string $providerCode, string $providerSessionCode): array
    {
        return [
            'deposit_exists' => ReservationDepositPaymentSession::query()
                ->where('provider_code', $providerCode)
                ->where('provider_session_code', $providerSessionCode)
                ->exists(),
            'bill_exists' => ReservationBillPaymentSession::query()
                ->where('provider_code', $providerCode)
                ->where('provider_session_code', $providerSessionCode)
                ->exists(),
        ];
    }
}

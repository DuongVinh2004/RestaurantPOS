<?php

declare(strict_types=1);

namespace App\Services\PaymentIntegration;

use Illuminate\Validation\ValidationException;

class PaymentProviderRegistry
{
    public function __construct(
        private readonly PaymentProviderRolloutConfig $rolloutConfig,
        private readonly SimulatedPaymentProviderAdapter $simulatedProvider,
        private readonly GenericHttpHmacPaymentProviderAdapter $genericHttpHmacProvider,
    ) {}

    public function resolve(?string $providerCode = null): PaymentProviderAdapter
    {
        $providerCode = strtolower(trim((string) $providerCode));
        if ($providerCode === '') {
            $providerCode = $this->rolloutConfig->defaultProviderCode();
        }

        $provider = match ($providerCode) {
            'simulated' => $this->simulatedProvider,
            'generic_http_hmac' => $this->genericHttpHmacProvider,
            default => throw ValidationException::withMessages([
                'provider_code' => ['Unsupported payment provider.'],
            ]),
        };

        if (! $this->rolloutConfig->isEnabled($providerCode)) {
            throw ValidationException::withMessages([
                'provider_code' => ['Payment provider is disabled.'],
            ]);
        }

        return $provider;
    }
}

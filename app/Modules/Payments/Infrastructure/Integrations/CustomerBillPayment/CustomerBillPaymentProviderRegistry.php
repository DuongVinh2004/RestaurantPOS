<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\CustomerBillPayment;

use App\Modules\Payments\Domain\Models\Payment;
use App\Enums\PaymentSessionScope;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRolloutConfig;
use Illuminate\Validation\ValidationException;

class CustomerBillPaymentProviderRegistry
{
    public function __construct(
        private readonly PaymentProviderRolloutConfig $rolloutConfig,
        private readonly SimulatedCustomerBillPaymentProvider $simulatedProvider,
        private readonly GenericHttpHmacCustomerBillPaymentProvider $genericHttpHmacProvider,
    ) {}

    public function defaultProviderCode(): string
    {
        return $this->rolloutConfig->defaultProviderCode(PaymentSessionScope::Bill);
    }

    public function resolve(?string $providerCode = null): CustomerBillPaymentProvider
    {
        $providerCode = strtolower(trim((string) $providerCode));
        if ($providerCode === '') {
            $providerCode = $this->defaultProviderCode();
        }

        $provider = match ($providerCode) {
            'simulated' => $this->simulatedProvider,
            'generic_http_hmac' => $this->genericHttpHmacProvider,
            default => throw ValidationException::withMessages([
                'provider_code' => ['Unsupported customer bill payment provider.'],
            ]),
        };

        if (! $this->rolloutConfig->isEnabled($providerCode)) {
            throw ValidationException::withMessages([
                'provider_code' => ['Customer bill payment provider is disabled.'],
            ]);
        }

        return $provider;
    }
}

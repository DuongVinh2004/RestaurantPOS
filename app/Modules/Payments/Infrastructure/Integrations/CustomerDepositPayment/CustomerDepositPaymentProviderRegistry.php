<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\CustomerDepositPayment;

use App\Enums\PaymentSessionScope;
use App\Modules\Payments\Infrastructure\Integrations\PaymentProviders\PaymentProviderRolloutConfig;
use Illuminate\Validation\ValidationException;

class CustomerDepositPaymentProviderRegistry
{
    public function __construct(
        private readonly PaymentProviderRolloutConfig $rolloutConfig,
        private readonly SimulatedCustomerDepositPaymentProvider $simulatedProvider,
        private readonly GenericHttpHmacCustomerDepositPaymentProvider $genericHttpHmacProvider,
        private readonly VNPayCustomerDepositPaymentProvider $vnpayProvider,
        private readonly MoMoCustomerDepositPaymentProvider $momoProvider,
    ) {}

    public function defaultProviderCode(): string
    {
        return $this->rolloutConfig->defaultProviderCode(PaymentSessionScope::Deposit);
    }

    public function resolve(?string $providerCode = null): CustomerDepositPaymentProvider
    {
        $providerCode = strtolower(trim((string) $providerCode));
        if ($providerCode === '') {
            $providerCode = $this->defaultProviderCode();
        }

        $provider = match ($providerCode) {
            'simulated' => $this->simulatedProvider,
            'generic_http_hmac' => $this->genericHttpHmacProvider,
            'vnpay' => $this->vnpayProvider,
            'momo' => $this->momoProvider,
            default => throw ValidationException::withMessages([
                'provider_code' => ['Unsupported customer deposit payment provider.'],
            ]),
        };

        if (! $this->rolloutConfig->isEnabled($providerCode)) {
            throw ValidationException::withMessages([
                'provider_code' => ['Customer deposit payment provider is disabled.'],
            ]);
        }

        return $provider;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PaymentIntegration;

use App\Enums\PaymentSessionScope;
use App\Modules\CheckoutPayments\Infrastructure\CustomerBillPayment\CustomerBillPaymentProviderRegistry;
use App\Modules\CheckoutPayments\Infrastructure\CustomerDepositPayment\CustomerDepositPaymentProviderRegistry;
use App\Modules\CheckoutPayments\Infrastructure\PaymentProviders\PaymentProviderRegistry;
use App\Modules\CheckoutPayments\Infrastructure\PaymentProviders\PaymentProviderRolloutConfig;
use Tests\TestCase;

final class PaymentProviderRegistryRolloutContractTest extends TestCase
{
    public function test_payment_provider_registry_uses_central_default_provider_when_route_context_does_not_supply_one(): void
    {
        config()->set('booking.payment_providers.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.simulated.enabled', true);

        self::assertSame('generic_http_hmac', app(PaymentProviderRegistry::class)->resolve()->code());
    }

    public function test_customer_scope_registries_resolve_scope_defaults_from_rollout_contract(): void
    {
        config()->set('booking.payment_providers.default_provider', 'simulated');
        config()->set('booking.payment_providers.scopes.deposit.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.scopes.bill.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.simulated.enabled', true);

        self::assertSame('generic_http_hmac', app(CustomerDepositPaymentProviderRegistry::class)->defaultProviderCode());
        self::assertSame('generic_http_hmac', app(CustomerDepositPaymentProviderRegistry::class)->resolve()->code());
        self::assertSame('generic_http_hmac', app(CustomerBillPaymentProviderRegistry::class)->defaultProviderCode());
        self::assertSame('generic_http_hmac', app(CustomerBillPaymentProviderRegistry::class)->resolve()->code());
    }

    public function test_customer_self_pay_rollout_can_be_intentionally_disabled_for_day_one(): void
    {
        config()->set('booking.payment_providers.customer_self_pay.enabled', false);
        config()->set('booking.payment_providers.scopes.deposit.default_provider', 'generic_http_hmac');

        $status = app(PaymentProviderRolloutConfig::class)->customerSelfPayStatus(PaymentSessionScope::Deposit);

        self::assertFalse($status['ok']);
        self::assertSame('customer_self_pay_disabled', $status['reason_code']);
        self::assertSame(
            'Customer self-pay is intentionally disabled for this rollout. Use staff settlement.',
            $status['message']
        );
    }

    public function test_customer_self_pay_blocks_simulated_provider_in_production_like_environment(): void
    {
        config()->set('app.env', 'production');
        config()->set('booking.payment_providers.customer_self_pay.enabled', true);
        config()->set('booking.payment_providers.customer_self_pay.allow_simulated_in_production_like', false);
        config()->set('booking.payment_providers.scopes.bill.default_provider', 'simulated');
        config()->set('booking.payment_providers.providers.simulated.enabled', true);

        $status = app(PaymentProviderRolloutConfig::class)->customerSelfPayStatus(PaymentSessionScope::Bill);

        self::assertFalse($status['ok']);
        self::assertSame('simulated_provider_blocked', $status['reason_code']);
    }

    public function test_customer_self_pay_requires_generic_http_hmac_readiness_when_enabled(): void
    {
        config()->set('app.env', 'production');
        config()->set('booking.payment_providers.customer_self_pay.enabled', true);
        config()->set('booking.payment_providers.scopes.deposit.default_provider', 'generic_http_hmac');
        config()->set('booking.payment_providers.providers.generic_http_hmac.enabled', true);
        config()->set('booking.payment_providers.providers.generic_http_hmac.base_url', '');
        config()->set('booking.payment_providers.providers.generic_http_hmac.request.secret', '');
        config()->set('booking.payment_providers.providers.generic_http_hmac.webhook.secret', '');
        config()->set('booking.payment_providers.providers.generic_http_hmac.deposit.create_endpoint', '');

        $status = app(PaymentProviderRolloutConfig::class)->customerSelfPayStatus(PaymentSessionScope::Deposit);

        self::assertFalse($status['ok']);
        self::assertSame('provider_configuration_incomplete', $status['reason_code']);
        self::assertArrayHasKey('base_url', $status['meta']['readiness']['issues']);
        self::assertArrayHasKey('request.secret', $status['meta']['readiness']['issues']);
        self::assertArrayHasKey('webhook.secret', $status['meta']['readiness']['issues']);
    }
}

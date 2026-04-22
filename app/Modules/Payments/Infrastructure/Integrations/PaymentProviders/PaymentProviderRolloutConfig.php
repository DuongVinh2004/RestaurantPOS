<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Integrations\PaymentProviders;

use App\Enums\PaymentSessionScope;
use Illuminate\Support\Arr;

class PaymentProviderRolloutConfig
{
    public function defaultProviderCode(?PaymentSessionScope $scope = null): string
    {
        $candidates = [];

        if ($scope !== null) {
            $candidates[] = config(sprintf('booking.payment_providers.scopes.%s.default_provider', $scope->value));
            $candidates[] = match ($scope) {
                PaymentSessionScope::Deposit => config('booking.customer_deposit_payment_default_provider'),
                PaymentSessionScope::Bill => config('booking.customer_bill_payment_default_provider'),
            };
        }

        $candidates[] = config('booking.payment_providers.default_provider', 'simulated');

        foreach ($candidates as $candidate) {
            $value = strtolower(trim((string) $candidate));
            if ($value !== '') {
                return $value;
            }
        }

        return 'simulated';
    }

    public function isEnabled(string $providerCode): bool
    {
        $providerCode = strtolower(trim($providerCode));

        return $providerCode !== ''
            && (bool) config(sprintf('booking.payment_providers.providers.%s.enabled', $providerCode), false);
    }

    /**
     * @return array{
     *   ok: bool,
     *   provider_code: string,
     *   message: string,
     *   reason_code: string|null,
     *   meta: array<string,mixed>
     * }
     */
    public function customerSelfPayStatus(PaymentSessionScope $scope, ?string $providerCode = null): array
    {
        $providerCode = strtolower(trim((string) $providerCode));
        if ($providerCode === '') {
            $providerCode = $this->defaultProviderCode($scope);
        }

        $environment = (string) config('app.env', 'production');
        $productionLikeEnvironments = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            (array) config('booking.payment_providers.customer_self_pay.production_like_environments', ['production', 'staging'])
        ), static fn (string $value): bool => $value !== ''));
        $isProductionLike = in_array($environment, $productionLikeEnvironments, true);
        $meta = [
            'scope' => $scope->value,
            'provider_code' => $providerCode,
            'environment' => $environment,
            'production_like_environments' => $productionLikeEnvironments,
            'is_production_like' => $isProductionLike,
        ];

        if (! (bool) config('booking.payment_providers.customer_self_pay.enabled', false)) {
            return [
                'ok' => false,
                'provider_code' => $providerCode,
                'message' => 'Customer self-pay is intentionally disabled for this rollout. Use staff settlement.',
                'reason_code' => 'customer_self_pay_disabled',
                'meta' => $meta,
            ];
        }

        if (! $this->isEnabled($providerCode)) {
            return [
                'ok' => false,
                'provider_code' => $providerCode,
                'message' => 'Customer self-pay provider is disabled. Use staff settlement.',
                'reason_code' => 'provider_disabled',
                'meta' => $meta,
            ];
        }

        if ($providerCode === 'simulated' && $isProductionLike && ! (bool) config('booking.payment_providers.customer_self_pay.allow_simulated_in_production_like', false)) {
            return [
                'ok' => false,
                'provider_code' => $providerCode,
                'message' => 'Customer self-pay is disabled because the simulated payment provider is blocked in production-like environments.',
                'reason_code' => 'simulated_provider_blocked',
                'meta' => $meta,
            ];
        }

        if ($providerCode === 'generic_http_hmac') {
            $readiness = $this->genericHttpHmacSelfPayReadiness($scope);
            if (! ($readiness['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'provider_code' => $providerCode,
                    'message' => 'Customer self-pay is disabled until the generic_http_hmac payment provider configuration is complete.',
                    'reason_code' => 'provider_configuration_incomplete',
                    'meta' => array_merge($meta, [
                        'readiness' => $readiness,
                    ]),
                ];
            }
        }

        return [
            'ok' => true,
            'provider_code' => $providerCode,
            'message' => 'Customer self-pay is enabled.',
            'reason_code' => null,
            'meta' => $meta,
        ];
    }

    /**
     * @return array{ok:bool,issues:array<string,string>,mode:string,scope_create_endpoint:string}
     */
    private function genericHttpHmacSelfPayReadiness(PaymentSessionScope $scope): array
    {
        $config = (array) config('booking.payment_providers.providers.generic_http_hmac', []);
        $issues = [];
        $mode = strtolower(trim((string) Arr::get($config, 'mode', '')));
        if ($mode === '' || ! in_array($mode, ['sandbox', 'live'], true)) {
            $issues['mode'] = 'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_MODE must be sandbox or live.';
        }

        if (trim((string) Arr::get($config, 'base_url', '')) === '') {
            $issues['base_url'] = 'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_BASE_URL must not be empty.';
        }

        $scopeCreateEndpoint = $this->firstConfiguredString([
            Arr::get($config, $scope->value.'.create_endpoint'),
            Arr::get($config, $scope->value.'.session_create_endpoint'),
            Arr::get($config, 'create_endpoint'),
            Arr::get($config, 'session_create_endpoint'),
            Arr::get($config, 'endpoints.create'),
        ]);
        if ($scopeCreateEndpoint === '') {
            $issues['create_endpoint'] = sprintf('A %s create endpoint must be configured for generic_http_hmac self-pay.', $scope->value);
        }

        $requestSecret = $this->firstConfiguredString([
            Arr::get($config, 'request.secret'),
            Arr::get($config, 'signing_secret'),
            Arr::get($config, 'api_secret'),
        ]);
        if ($requestSecret === '') {
            $issues['request.secret'] = 'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_REQUEST_SECRET or PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_SIGNING_SECRET must not be empty.';
        }

        $webhookSecret = $this->firstConfiguredString([
            Arr::get($config, 'webhook.secret'),
            Arr::get($config, 'webhook_secret'),
        ]);
        if ($webhookSecret === '') {
            $issues['webhook.secret'] = 'PAYMENT_PROVIDER_GENERIC_HTTP_HMAC_WEBHOOK_SECRET must not be empty.';
        }

        return [
            'ok' => $issues === [],
            'issues' => $issues,
            'mode' => $mode,
            'scope_create_endpoint' => $scopeCreateEndpoint,
        ];
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private function firstConfiguredString(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}

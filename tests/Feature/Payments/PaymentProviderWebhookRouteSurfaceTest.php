<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class PaymentProviderWebhookRouteSurfaceTest extends TestCase
{
    public function test_payment_provider_webhook_endpoint_is_registered_to_runtime_surface(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(static fn (IlluminateRoute $route): bool => in_array('POST', $route->methods(), true)
                && in_array(trim($route->uri(), '/'), [
                    'v1/payments/providers/{provider_code}/webhooks',
                    'api/v1/payments/providers/{provider_code}/webhooks',
                ], true));

        self::assertNotNull($route, 'Expected webhook route [POST v1/payments/providers/{provider_code}/webhooks] is not registered.');
        self::assertSame(
            'App\\Modules\\Payments\\Http\\Controllers\\Webhook\\PaymentProviderWebhookController@handle',
            $route->getActionName(),
            'Webhook route drifted to an unexpected controller action.',
        );
        self::assertContains(
            'redis.throttle:payment.webhooks,120,60,ip',
            $route->gatherMiddleware(),
            'Webhook route must be rate-limited because it is intentionally public before provider signature validation.',
        );
    }
}

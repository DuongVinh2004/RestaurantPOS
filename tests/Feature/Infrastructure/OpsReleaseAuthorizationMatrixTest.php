<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Http\Middleware\StaffApiKeyMiddleware;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpsReleaseAuthorizationMatrixTest extends TestCase
{
    public function test_ops_release_routes_expose_explicit_public_and_privileged_access_layers(): void
    {
        $publicHealth = $this->findRouteByUriSuffix('v1/health');
        $detailedHealth = $this->findRouteByUriSuffix('v1/health/detailed');
        $redisHealth = $this->findRouteByUriSuffix('v1/health/redis');
        $metrics = $this->findRouteByUriSuffix('v1/metrics');

        self::assertInstanceOf(IlluminateRoute::class, $publicHealth);
        self::assertInstanceOf(IlluminateRoute::class, $detailedHealth);
        self::assertInstanceOf(IlluminateRoute::class, $redisHealth);
        self::assertInstanceOf(IlluminateRoute::class, $metrics);

        self::assertNotContains(StaffApiKeyMiddleware::class, $publicHealth->gatherMiddleware());
        self::assertFalse($this->hasCapabilityMiddleware($publicHealth, 'ops.health.view'));
        self::assertFalse($this->hasCapabilityMiddleware($publicHealth, 'ops.metrics.view'));

        self::assertContains(StaffApiKeyMiddleware::class, $detailedHealth->gatherMiddleware());
        self::assertTrue($this->hasCapabilityMiddleware($detailedHealth, 'ops.health.view'));
        self::assertContains(StaffApiKeyMiddleware::class, $redisHealth->gatherMiddleware());
        self::assertTrue($this->hasCapabilityMiddleware($redisHealth, 'ops.health.view'));

        self::assertContains(StaffApiKeyMiddleware::class, $metrics->gatherMiddleware());
        self::assertTrue($this->hasCapabilityMiddleware($metrics, 'ops.metrics.view'));
        self::assertFalse($this->hasCapabilityMiddleware($metrics, 'ops.health.view'));
    }

    private function findRouteByUriSuffix(string $suffix): ?IlluminateRoute
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $suffix || str_ends_with($route->uri(), '/'.$suffix)) {
                return $route;
            }
        }

        return null;
    }

    private function hasCapabilityMiddleware(IlluminateRoute $route, string $capability): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if ($middleware === 'staff.capability:'.$capability) {
                return true;
            }
        }

        return false;
    }
}

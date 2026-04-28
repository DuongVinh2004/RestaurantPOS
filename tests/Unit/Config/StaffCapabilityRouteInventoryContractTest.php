<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Http\Middleware\RequireStaffCapability;
use App\Http\Middleware\StaffApiKeyMiddleware;
use App\Http\Middleware\StaffRefreshSessionMiddleware;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StaffCapabilityRouteInventoryContractTest extends TestCase
{
    /**
     * @var array<string,string>
     */
    private const STAFF_AUTH_LIFECYCLE_ROUTE_EXCEPTIONS = [
        'GET api/v1/auth/staff/me' => 'Staff auth lifecycle route uses StaffApiKeyMiddleware but is not a business capability surface.',
        'POST api/v1/auth/staff/login' => 'Staff auth lifecycle route issues staff sessions and cannot require a prior staff capability.',
        'POST api/v1/auth/staff/logout' => 'Staff auth lifecycle route revokes the current staff session rather than mutating business state.',
        'POST api/v1/auth/staff/refresh' => 'Staff auth lifecycle route rotates staff session credentials rather than mutating business state.',
    ];

    /**
     * @var array<string,array{capability:string,idempotency:string}>
     */
    private const HIGH_RISK_MUTATION_ROUTES = [
        'POST api/v1/staff/orders/{order_id}/pay' => [
            'capability' => 'settlement.manage',
            'idempotency' => 'idempotency:staff.order-pay',
        ],
        'POST api/v1/staff/orders/{order_id}/checkout' => [
            'capability' => 'settlement.manage',
            'idempotency' => 'idempotency:staff.checkout',
        ],
        'POST api/v1/staff/orders/{order_id}/settlement/finalize' => [
            'capability' => 'settlement.manage',
            'idempotency' => 'idempotency:staff.checkout',
        ],
        'POST api/v1/staff/reservations/{reservation_id}/refund' => [
            'capability' => 'payment.refund',
            'idempotency' => 'idempotency:staff.reservation-refund',
        ],
        'POST api/v1/staff/reservations/{reservation_id}/refund-cancel' => [
            'capability' => 'payment.refund',
            'idempotency' => 'idempotency:staff.reservation-refund-cancel',
        ],
        'POST api/v1/staff/cashier/shifts/open' => [
            'capability' => 'cashier.shift.manage',
            'idempotency' => 'idempotency:staff.cashier-shift.open',
        ],
        'POST api/v1/staff/cashier/shifts/{shift_id}/close' => [
            'capability' => 'cashier.shift.manage',
            'idempotency' => 'idempotency:staff.cashier-shift.close',
        ],
        'POST api/v1/admin/inventory/ingredients' => [
            'capability' => 'inventory.manage',
            'idempotency' => 'idempotency:admin.inventory-ingredients.store',
        ],
        'PATCH api/v1/admin/inventory/ingredients/{id}' => [
            'capability' => 'inventory.manage',
            'idempotency' => 'idempotency:admin.inventory-ingredients.update',
        ],
        'POST api/v1/admin/inventory/ingredients/{id}/movements' => [
            'capability' => 'inventory.manage',
            'idempotency' => 'idempotency:admin.inventory-movements.store',
        ],
    ];

    public function test_staff_capability_middleware_alias_is_registered(): void
    {
        $middleware = app('router')->getMiddleware();

        $this->assertSame(RequireStaffCapability::class, $middleware['staff.capability'] ?? null);
    }

    public function test_live_staff_capability_routes_match_the_configured_inventory(): void
    {
        $expected = collect((array) config('staff_capabilities.route_capabilities', []))
            ->sortKeys()
            ->all();

        $actual = collect(Route::getRoutes()->getRoutes())
            ->mapWithKeys(function ($route): array {
                $capability = collect($route->gatherMiddleware())
                    ->first(fn (string $middleware): bool => str_starts_with($middleware, 'staff.capability:'));

                if ($capability === null) {
                    return [];
                }

                return [strtoupper((string) collect($route->methods())->reject(fn (string $method): bool => strtoupper($method) === 'HEAD')->values()->all()[0]).' '.(string) $route->uri() => substr($capability, strlen('staff.capability:'))];
            })
            ->sortKeys()
            ->all();

        $this->assertSame($expected, $actual);
    }

    public function test_privileged_staff_and_admin_routes_are_staff_authenticated_and_capability_guarded(): void
    {
        $routeCapabilities = (array) config('staff_capabilities.route_capabilities', []);
        $knownCapabilities = array_fill_keys((array) config('staff_capabilities.known_capabilities', []), true);
        $missingStaffAuth = [];
        $missingCapability = [];
        $missingInventory = [];
        $unknownCapability = [];

        $privilegedRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => $this->isPrivilegedStaffOrAdminRoute($this->routeSignature($route)))
            ->reject(fn (IlluminateRoute $route): bool => $this->isStaffAuthLifecycleRoute($this->routeSignature($route)));

        $this->assertGreaterThan(0, $privilegedRoutes->count(), 'No privileged staff/admin routes were checked.');

        $privilegedRoutes->each(function (IlluminateRoute $route) use ($routeCapabilities, $knownCapabilities, &$missingStaffAuth, &$missingCapability, &$missingInventory, &$unknownCapability): void {
            $signature = $this->routeSignature($route);
            $capability = $this->staffCapabilityForRoute($route);

            if (! $this->hasStaffAuthMiddleware($route)) {
                $missingStaffAuth[] = $signature;
            }

            if ($capability === null) {
                $missingCapability[] = $signature;

                return;
            }

            if (! array_key_exists($signature, $routeCapabilities)) {
                $missingInventory[] = $signature;
            }

            if ($capability !== '*' && ! isset($knownCapabilities[$capability])) {
                $unknownCapability[$signature] = $capability;
            }
        });

        $this->assertSame([], $missingStaffAuth, 'Privileged staff/admin routes must use staff authentication.');
        $this->assertSame([], $missingCapability, 'Privileged staff/admin routes must declare a staff capability.');
        $this->assertSame([], $missingInventory, 'Privileged staff/admin routes must be listed in staff_capabilities.route_capabilities.');
        $this->assertSame([], $unknownCapability, 'Privileged staff/admin routes must use known capabilities.');
    }

    public function test_staff_auth_lifecycle_route_exceptions_are_documented(): void
    {
        foreach (self::STAFF_AUTH_LIFECYCLE_ROUTE_EXCEPTIONS as $signature => $reason) {
            $this->assertNotSame('', trim($reason), 'Staff auth lifecycle exception must include a reason: '.$signature);
        }
    }

    public function test_high_risk_staff_admin_mutations_have_auth_capability_and_idempotency_guards(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->keyBy(fn (IlluminateRoute $route): string => $this->routeSignature($route));

        foreach (self::HIGH_RISK_MUTATION_ROUTES as $signature => $expected) {
            /** @var IlluminateRoute|null $route */
            $route = $routes->get($signature);

            $this->assertNotNull($route, 'High-risk mutation route is missing: '.$signature);
            $this->assertTrue($this->hasStaffAuthMiddleware($route), 'High-risk mutation route must require staff auth: '.$signature);
            $this->assertSame($expected['capability'], $this->staffCapabilityForRoute($route), 'Unexpected capability guard for '.$signature);
            $this->assertContains($expected['idempotency'], $route->gatherMiddleware(), 'Missing idempotency guard for '.$signature);
        }
    }

    public function test_route_alias_inventory_points_to_existing_routes_with_matching_capabilities(): void
    {
        $routeCapabilities = (array) config('staff_capabilities.route_capabilities', []);
        $routeAliases = (array) config('staff_capabilities.route_aliases', []);
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route): string => strtoupper((string) collect($route->methods())->reject(fn (string $method): bool => strtoupper($method) === 'HEAD')->values()->all()[0]).' '.(string) $route->uri());

        foreach ($routeAliases as $alias => $canonical) {
            $alias = (string) $alias;
            $canonical = (string) $canonical;

            $this->assertArrayHasKey($alias, $routeCapabilities, 'Alias route capability inventory is missing ['.$alias.'].');
            $this->assertArrayHasKey($canonical, $routeCapabilities, 'Canonical route capability inventory is missing ['.$canonical.'].');
            $this->assertSame($routeCapabilities[$canonical], $routeCapabilities[$alias], 'Alias ['.$alias.'] does not share the canonical capability of ['.$canonical.'].');
            $this->assertNotNull($routes->get($alias), 'Live alias route ['.$alias.'] is missing.');
            $this->assertNotNull($routes->get($canonical), 'Live canonical route ['.$canonical.'] is missing.');
            $this->assertSame(
                $this->controllerClassFromActionName($routes->get($canonical)->getActionName()),
                $this->controllerClassFromActionName($routes->get($alias)->getActionName()),
                'Live alias route ['.$alias.'] does not point to the same controller as ['.$canonical.'].'
            );
        }
    }

    public function test_route_alias_config_matches_the_locked_route_inventory_alias_groups(): void
    {
        $configuredCapabilityRoutes = array_fill_keys(
            array_keys((array) config('staff_capabilities.route_capabilities', [])),
            true
        );
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/route_inventory_gate.json')), true, 512, JSON_THROW_ON_ERROR);
        $fixtureAliases = collect((array) ($fixture['alias_groups'] ?? []))
            ->flatMap(function (array $group): array {
                $canonical = strtoupper((string) ($this->methodForUri((string) ($group['canonical'] ?? '')) ?? 'GET')).' '.(string) ($group['canonical'] ?? '');
                $aliases = [];

                foreach ((array) ($group['aliases'] ?? []) as $alias) {
                    if (is_string($alias)) {
                        $alias = ['uri' => $alias];
                    }

                    $aliasUri = (string) ($alias['uri'] ?? '');
                    if ($aliasUri === '') {
                        continue;
                    }

                    $aliasSignature = strtoupper((string) ($this->methodForUri($aliasUri) ?? 'GET')).' '.$aliasUri;
                    $aliases[$aliasSignature] = $canonical;
                }

                return $aliases;
            })
            ->filter(fn (string $canonical): bool => isset($configuredCapabilityRoutes[$canonical]))
            ->sortKeys()
            ->all();

        $this->assertSame(
            collect((array) config('staff_capabilities.route_aliases', []))->sortKeys()->all(),
            $fixtureAliases
        );
    }

    private function controllerClassFromActionName(string $actionName): string
    {
        return explode('@', $actionName)[0] ?? $actionName;
    }

    private function methodForUri(string $uri): ?string
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($runtimeRoute): bool => (string) $runtimeRoute->uri() === $uri);

        if ($route === null) {
            return null;
        }

        return collect($route->methods())
            ->reject(fn (string $method): bool => strtoupper($method) === 'HEAD')
            ->map(fn (string $method): string => strtoupper($method))
            ->first();
    }

    private function routeSignature(IlluminateRoute $route): string
    {
        return strtoupper((string) collect($route->methods())->reject(fn (string $method): bool => strtoupper($method) === 'HEAD')->values()->all()[0]).' '.(string) $route->uri();
    }

    private function isPrivilegedStaffOrAdminRoute(string $signature): bool
    {
        [, $uri] = explode(' ', $signature, 2);

        return str_starts_with($uri, 'api/v1/staff/')
            || str_starts_with($uri, 'api/v1/admin/')
            || in_array($uri, [
                'api/v1/health/detailed',
                'api/v1/health/redis',
                'api/v1/metrics',
            ], true)
            || $signature === 'PATCH api/v1/reservations/{id}/status';
    }

    private function isStaffAuthLifecycleRoute(string $signature): bool
    {
        return array_key_exists($signature, self::STAFF_AUTH_LIFECYCLE_ROUTE_EXCEPTIONS);
    }

    private function hasStaffAuthMiddleware(IlluminateRoute $route): bool
    {
        $middleware = $route->gatherMiddleware();

        return in_array(StaffApiKeyMiddleware::class, $middleware, true)
            || in_array(StaffRefreshSessionMiddleware::class, $middleware, true);
    }

    private function staffCapabilityForRoute(IlluminateRoute $route): ?string
    {
        $middleware = collect($route->gatherMiddleware())
            ->first(fn (string $middleware): bool => str_starts_with($middleware, 'staff.capability:'));

        return is_string($middleware)
            ? substr($middleware, strlen('staff.capability:'))
            : null;
    }
}

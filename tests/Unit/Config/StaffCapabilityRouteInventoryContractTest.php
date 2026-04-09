<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Http\Middleware\RequireStaffCapability;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StaffCapabilityRouteInventoryContractTest extends TestCase
{
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
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ApiRouteSurfaceIntegrityTest extends TestCase
{
    public function test_api_route_surface_matches_the_canonical_route_gate_fixture(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/route_inventory_gate.json')), true, 512, JSON_THROW_ON_ERROR);
        $expected = collect((array) ($fixture['expected_routes'] ?? []))
            ->map(fn (array $route): string => strtoupper((string) $route['method']).' '.(string) $route['uri'])
            ->sort()
            ->values()
            ->all();

        $actual = collect(Route::getRoutes()->getRoutes())
            ->flatMap(function ($route): array {
                $uri = (string) $route->uri();
                if (! str_starts_with($uri, 'api/')) {
                    return [];
                }

                return collect($route->methods())
                    ->reject(fn (string $method): bool => strtoupper($method) === 'HEAD')
                    ->map(fn (string $method): string => strtoupper($method).' '.$uri)
                    ->values()
                    ->all();
            })
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expected, $actual);
    }

    public function test_declared_route_aliases_resolve_to_the_expected_controller_contract(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/route_inventory_gate.json')), true, 512, JSON_THROW_ON_ERROR);
        $routes = collect(Route::getRoutes()->getRoutes())->keyBy(fn ($route): string => (string) $route->uri());

        foreach ((array) ($fixture['alias_groups'] ?? []) as $group) {
            $canonical = (string) ($group['canonical'] ?? '');
            $canonicalRoute = $routes->get($canonical);

            $this->assertNotNull($canonicalRoute, 'Missing canonical route ['.$canonical.'].');

            $expectedCanonicalAction = (string) ($group['canonical_action'] ?? '');
            if ($expectedCanonicalAction !== '') {
                $this->assertSame($expectedCanonicalAction, $canonicalRoute->getActionName(), 'Canonical route ['.$canonical.'] drifted from its expected action.');
            }

            foreach ((array) ($group['aliases'] ?? []) as $aliasDefinition) {
                if (is_string($aliasDefinition)) {
                    $aliasDefinition = ['uri' => $aliasDefinition];
                }

                $alias = (string) ($aliasDefinition['uri'] ?? '');
                $aliasRoute = $routes->get($alias);

                $this->assertNotNull($aliasRoute, 'Missing alias route ['.$alias.'].');
                $this->assertSame(
                    $this->controllerClassFromActionName($canonicalRoute->getActionName()),
                    $this->controllerClassFromActionName($aliasRoute->getActionName()),
                    'Alias route ['.$alias.'] drifted away from canonical controller ['.$canonical.'].'
                );

                $expectedAliasAction = (string) ($aliasDefinition['action'] ?? '');
                if ($expectedAliasAction !== '') {
                    $this->assertSame($expectedAliasAction, $aliasRoute->getActionName(), 'Alias route ['.$alias.'] drifted from its expected wrapper action.');
                }
            }
        }
    }

    private function controllerClassFromActionName(string $actionName): string
    {
        return explode('@', $actionName)[0] ?? $actionName;
    }
}

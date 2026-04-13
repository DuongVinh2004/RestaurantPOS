<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteContractReconcilerService
{
    public function __construct(
        private readonly RouteInventoryGateService $routeInventory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function reconcile(?string $routeInventoryPath = null, ?string $staffCapabilitiesPath = null): array
    {
        $definition = $this->routeInventory->definition($routeInventoryPath);
        $staffCapabilities = $this->loadStaffCapabilitiesContract($staffCapabilitiesPath);
        $runtimeRoutes = $this->runtimeApiRoutes();
        $runtimeCapabilities = $this->runtimeCapabilityRoutes($runtimeRoutes);
        $generatedAliasGroups = $this->buildGeneratedAliasGroups(
            routeAliases: (array) ($staffCapabilities['route_aliases'] ?? []),
            runtimeRoutes: $runtimeRoutes,
        );

        $routeInventoryDrift = $this->compareRouteInventory(
            definition: $definition,
            runtimeRoutes: $runtimeRoutes,
            generatedAliasGroups: $generatedAliasGroups['groups'],
        );

        $capabilityDrift = $this->compareCapabilityInventory(
            staffCapabilities: $staffCapabilities,
            runtimeCapabilities: $runtimeCapabilities,
            runtimeRoutes: $runtimeRoutes,
        );

        $routeInventoryCandidate = $this->buildRouteInventoryCandidate(
            definition: $definition,
            runtimeRoutes: $runtimeRoutes,
            generatedAliasGroups: $generatedAliasGroups['groups'],
        );

        $staffCapabilityCandidate = $this->buildStaffCapabilityCandidate(
            staffCapabilities: $staffCapabilities,
            runtimeCapabilities: $runtimeCapabilities,
        );

        $routeInventoryIssueCount = $this->countIssues($routeInventoryDrift) + count((array) ($generatedAliasGroups['issues'] ?? []));
        $staffCapabilityIssueCount = $this->countIssues($capabilityDrift);
        $issueCount = $routeInventoryIssueCount + $staffCapabilityIssueCount;

        return [
            'ok' => $issueCount === 0,
            'status' => $issueCount === 0 ? 'ok' : 'drift',
            'notes' => [
                'Runtime route inventory is derived from live Laravel routes; nothing is rewritten unless an explicit --write flag is passed.',
                'route_capabilities is generated from runtime routes carrying staff.capability:* middleware; capability_aliases is preserved as explicit config and is not inferred from route middleware.',
                'Locked route inventory alias_groups is generated from config/staff_capabilities.php route_aliases plus runtime controller actions to keep alias metadata reviewable and frozen.',
            ],
            'route_inventory' => [
                'path' => (string) $definition['definition_path'],
                'drift' => array_merge($routeInventoryDrift, [
                    'alias_source_issues' => (array) ($generatedAliasGroups['issues'] ?? []),
                ]),
                'candidate' => $routeInventoryCandidate,
            ],
            'staff_capabilities' => [
                'path' => (string) ($staffCapabilities['definition_path'] ?? 'config/staff_capabilities.php'),
                'mapping_source' => 'Runtime routes carrying staff.capability:* middleware.',
                'alias_handling' => [
                    'route_aliases_source' => 'config/staff_capabilities.php route_aliases',
                    'capability_aliases_source' => 'config/staff_capabilities.php capability_aliases',
                    'route_capabilities_are_not_canonicalized_through_capability_aliases' => true,
                ],
                'drift' => $capabilityDrift,
                'candidate' => $staffCapabilityCandidate,
            ],
            'summary' => [
                'runtime_api_route_count' => count($runtimeRoutes),
                'runtime_staff_capability_route_count' => count($runtimeCapabilities),
                'locked_route_inventory_count' => count((array) ($definition['expected_routes'] ?? [])),
                'locked_staff_capability_route_count' => count((array) ($staffCapabilities['route_capabilities'] ?? [])),
                'route_inventory_issue_count' => $routeInventoryIssueCount,
                'staff_capability_issue_count' => $staffCapabilityIssueCount,
                'issue_count' => $issueCount,
            ],
            'writes' => [
                'route_inventory' => null,
                'staff_capabilities' => null,
            ],
        ];
    }

    /**
     * @return array{writes: array{route_inventory: string|null, staff_capabilities: string|null}}
     */
    public function writeReconciledArtifacts(
        bool $writeRouteInventory = false,
        bool $writeStaffCapabilities = false,
        ?array $report = null,
        ?string $routeInventoryPath = null,
        ?string $staffCapabilitiesPath = null,
    ): array {
        $report ??= $this->reconcile($routeInventoryPath, $staffCapabilitiesPath);

        $writes = [
            'route_inventory' => null,
            'staff_capabilities' => null,
        ];

        if ($writeRouteInventory) {
            $writes['route_inventory'] = $this->writeRouteInventoryDefinition(
                candidate: (array) ($report['route_inventory']['candidate'] ?? []),
                relativePath: $routeInventoryPath !== null && trim($routeInventoryPath) !== ''
                    ? trim($routeInventoryPath)
                    : (string) ($report['route_inventory']['path'] ?? $this->routeInventoryPath()),
            );
        }

        if ($writeStaffCapabilities) {
            $writes['staff_capabilities'] = $this->writeStaffCapabilitiesConfig(
                candidate: (array) ($report['staff_capabilities']['candidate'] ?? []),
                relativePath: $staffCapabilitiesPath !== null && trim($staffCapabilitiesPath) !== ''
                    ? trim($staffCapabilitiesPath)
                    : (string) ($report['staff_capabilities']['path'] ?? $this->staffCapabilitiesPath()),
            );
        }

        return ['writes' => $writes];
    }

    /**
     * @return array<string, array{signature: string, method: string, uri: string, action: string, middleware: list<string>}>
     */
    private function runtimeApiRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = trim((string) $route->uri(), '/');
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $middleware = array_values(array_filter(
                array_map(static fn ($value) => is_string($value) ? trim($value) : '', $route->gatherMiddleware()),
                static fn (string $value): bool => $value !== ''
            ));

            foreach ($this->httpMethodsForRoute($route) as $method) {
                $signature = $this->routeSignature($method, $uri);
                $routes[$signature] = [
                    'signature' => $signature,
                    'method' => $method,
                    'uri' => $uri,
                    'action' => $route->getActionName(),
                    'middleware' => $middleware,
                ];
            }
        }

        ksort($routes);

        return $routes;
    }

    /**
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, middleware: list<string>}>  $runtimeRoutes
     * @return array<string, array{signature: string, method: string, uri: string, action: string, capability: string, middleware: list<string>}>
     */
    private function runtimeCapabilityRoutes(array $runtimeRoutes): array
    {
        $capabilities = [];

        foreach ($runtimeRoutes as $signature => $route) {
            $capability = $this->extractStaffCapability((array) ($route['middleware'] ?? []));
            if ($capability === null) {
                continue;
            }

            $capabilities[$signature] = [
                'signature' => $signature,
                'method' => (string) $route['method'],
                'uri' => (string) $route['uri'],
                'action' => (string) $route['action'],
                'capability' => $capability,
                'middleware' => (array) $route['middleware'],
            ];
        }

        ksort($capabilities);

        return $capabilities;
    }

    /**
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, middleware: list<string>}>  $runtimeRoutes
     * @param  list<array{canonical: string, canonical_action?: string, aliases: list<array{uri: string, action?: string}>}>  $generatedAliasGroups
     * @return array<string, mixed>
     */
    private function compareRouteInventory(array $definition, array $runtimeRoutes, array $generatedAliasGroups): array
    {
        $lockedRoutes = [];
        foreach ((array) ($definition['expected_routes'] ?? []) as $entry) {
            $signature = $this->routeSignature((string) ($entry['method'] ?? 'GET'), (string) ($entry['uri'] ?? ''));
            $lockedRoutes[$signature] = $entry;
        }

        ksort($lockedRoutes);

        $missingFromLocked = [];
        $missingFromRuntime = [];
        $actionMismatches = [];

        foreach ($runtimeRoutes as $signature => $route) {
            if (! array_key_exists($signature, $lockedRoutes)) {
                $missingFromLocked[] = [
                    'signature' => $signature,
                    'action' => $route['action'],
                ];

                continue;
            }

            $lockedAction = (string) ($lockedRoutes[$signature]['action'] ?? '');
            if ($lockedAction !== (string) $route['action']) {
                $actionMismatches[] = [
                    'signature' => $signature,
                    'locked_action' => $lockedAction,
                    'runtime_action' => (string) $route['action'],
                ];
            }
        }

        foreach ($lockedRoutes as $signature => $locked) {
            if (! array_key_exists($signature, $runtimeRoutes)) {
                $missingFromRuntime[] = [
                    'signature' => $signature,
                    'action' => (string) ($locked['action'] ?? ''),
                ];
            }
        }

        return array_merge(
            [
                'missing_from_locked' => $missingFromLocked,
                'missing_from_runtime' => $missingFromRuntime,
                'action_mismatches' => $actionMismatches,
            ],
            $this->compareAliasGroups(
                lockedAliasGroups: (array) ($definition['alias_groups'] ?? []),
                generatedAliasGroups: $generatedAliasGroups,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $staffCapabilities
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, capability: string, middleware: list<string>}>  $runtimeCapabilities
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, middleware: list<string>}>  $runtimeRoutes
     * @return array<string, mixed>
     */
    private function compareCapabilityInventory(array $staffCapabilities, array $runtimeCapabilities, array $runtimeRoutes): array
    {
        $configuredRouteCapabilities = $this->normalizeStringMap($staffCapabilities['route_capabilities'] ?? []);
        $knownCapabilities = $this->normalizeStringList($staffCapabilities['known_capabilities'] ?? []);
        $routeAliases = $this->normalizeStringMap($staffCapabilities['route_aliases'] ?? []);

        $missingFromConfig = [];
        $missingFromRuntime = [];
        $capabilityMismatches = [];

        foreach ($runtimeCapabilities as $signature => $route) {
            if (! array_key_exists($signature, $configuredRouteCapabilities)) {
                $missingFromConfig[] = [
                    'signature' => $signature,
                    'capability' => (string) $route['capability'],
                    'action' => (string) $route['action'],
                ];

                continue;
            }

            if ((string) $configuredRouteCapabilities[$signature] !== (string) $route['capability']) {
                $capabilityMismatches[] = [
                    'signature' => $signature,
                    'configured_capability' => (string) $configuredRouteCapabilities[$signature],
                    'runtime_capability' => (string) $route['capability'],
                ];
            }
        }

        foreach ($configuredRouteCapabilities as $signature => $capability) {
            if (! array_key_exists($signature, $runtimeCapabilities)) {
                $missingFromRuntime[] = [
                    'signature' => $signature,
                    'configured_capability' => (string) $capability,
                ];
            }
        }

        $runtimeKnownCapabilities = array_values(array_unique(array_map(
            static fn (array $route): string => (string) ($route['capability'] ?? ''),
            $runtimeCapabilities
        )));
        sort($runtimeKnownCapabilities);

        $unknownCapabilities = array_values(array_diff($runtimeKnownCapabilities, $knownCapabilities));
        sort($unknownCapabilities);

        return [
            'missing_from_config' => $missingFromConfig,
            'missing_from_runtime' => $missingFromRuntime,
            'capability_mismatches' => $capabilityMismatches,
            'unknown_capabilities' => $unknownCapabilities,
            'routes_missing_capability_middleware' => $this->runtimeRoutesMissingCapabilityMiddleware($runtimeRoutes),
            'alias_mismatches' => $this->routeAliasMismatches(
                routeAliases: $routeAliases,
                configuredRouteCapabilities: $configuredRouteCapabilities,
                runtimeCapabilities: $runtimeCapabilities,
            ),
        ];
    }

    /**
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, middleware: list<string>}>  $runtimeRoutes
     * @param  list<array{canonical: string, canonical_action?: string, aliases: list<array{uri: string, action?: string}>}>  $generatedAliasGroups
     * @return array<string, mixed>
     */
    private function buildRouteInventoryCandidate(array $definition, array $runtimeRoutes, array $generatedAliasGroups): array
    {
        $lockedRoutes = [];
        foreach ((array) ($definition['expected_routes'] ?? []) as $entry) {
            $signature = $this->routeSignature((string) ($entry['method'] ?? 'GET'), (string) ($entry['uri'] ?? ''));
            $lockedRoutes[$signature] = $entry;
        }

        $expectedRoutes = [];

        foreach ($runtimeRoutes as $signature => $route) {
            $locked = (array) ($lockedRoutes[$signature] ?? []);
            $normalized = [
                'key' => trim((string) ($locked['key'] ?? $this->generatedRouteKey((string) $route['method'], (string) $route['uri']))),
                'method' => (string) $route['method'],
                'uri' => (string) $route['uri'],
                'action' => (string) $route['action'],
            ];

            $middlewareContains = $this->normalizeStringList($locked['middleware_contains'] ?? []);
            if ($middlewareContains !== []) {
                $normalized['middleware_contains'] = $middlewareContains;
            }

            $middlewareExcludes = $this->normalizeStringList($locked['middleware_excludes'] ?? []);
            if ($middlewareExcludes !== []) {
                $normalized['middleware_excludes'] = $middlewareExcludes;
            }

            $expectedRoutes[] = $normalized;
        }

        return [
            'suite' => (string) ($definition['suite'] ?? 'route_inventory'),
            'description' => (string) ($definition['description'] ?? 'Canonical route inventory gate for RestaurantPOS runtime surface.'),
            'expected_routes' => array_values($expectedRoutes),
            'smoke_requests' => array_values((array) ($definition['smoke_requests'] ?? [])),
            'alias_groups' => array_values($generatedAliasGroups),
        ];
    }

    /**
     * @param  array<string, mixed>  $staffCapabilities
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, capability: string, middleware: list<string>}>  $runtimeCapabilities
     * @return array<string, mixed>
     */
    private function buildStaffCapabilityCandidate(array $staffCapabilities, array $runtimeCapabilities): array
    {
        $runtimeKnownCapabilities = array_values(array_unique(array_map(
            static fn (array $route): string => (string) ($route['capability'] ?? ''),
            $runtimeCapabilities
        )));
        sort($runtimeKnownCapabilities);

        $knownCapabilities = array_values(array_unique(array_merge(
            $this->normalizeStringList($staffCapabilities['known_capabilities'] ?? []),
            $runtimeKnownCapabilities,
        )));
        sort($knownCapabilities);

        $routeCapabilities = [];
        foreach ($runtimeCapabilities as $signature => $route) {
            $routeCapabilities[$signature] = (string) $route['capability'];
        }
        ksort($routeCapabilities);

        return [
            'known_capabilities' => $knownCapabilities,
            'route_capabilities' => $routeCapabilities,
            'route_aliases' => $this->normalizeStringMap($staffCapabilities['route_aliases'] ?? []),
            'capability_aliases' => $this->normalizeCapabilityAliasMap($staffCapabilities['capability_aliases'] ?? []),
        ];
    }

    /**
     * @param  array<string, string>  $routeAliases
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, middleware: list<string>}>  $runtimeRoutes
     * @return array{groups: list<array{canonical: string, canonical_action: string, aliases: list<array{uri: string, action: string}>}>, issues: list<array<string, string>>}
     */
    private function buildGeneratedAliasGroups(array $routeAliases, array $runtimeRoutes): array
    {
        $groups = [];
        $issues = [];

        foreach ($this->normalizeStringMap($routeAliases) as $aliasSignature => $canonicalSignature) {
            $aliasRoute = $runtimeRoutes[$aliasSignature] ?? null;
            $canonicalRoute = $runtimeRoutes[$canonicalSignature] ?? null;

            if (! is_array($aliasRoute) || ! is_array($canonicalRoute)) {
                $issues[] = [
                    'alias_signature' => $aliasSignature,
                    'canonical_signature' => $canonicalSignature,
                    'message' => 'Alias mapping does not resolve to both runtime routes.',
                ];

                continue;
            }

            $canonicalUri = (string) $canonicalRoute['uri'];
            if (! array_key_exists($canonicalUri, $groups)) {
                $groups[$canonicalUri] = [
                    'canonical' => $canonicalUri,
                    'canonical_action' => (string) $canonicalRoute['action'],
                    'aliases' => [],
                ];
            }

            $groups[$canonicalUri]['aliases'][] = [
                'uri' => (string) $aliasRoute['uri'],
                'action' => (string) $aliasRoute['action'],
            ];
        }

        foreach ($groups as &$group) {
            usort($group['aliases'], static fn (array $left, array $right): int => strcmp((string) ($left['uri'] ?? ''), (string) ($right['uri'] ?? '')));
        }
        unset($group);

        ksort($groups);

        return [
            'groups' => array_values($groups),
            'issues' => $issues,
        ];
    }

    /**
     * @param  list<array{canonical: string, canonical_action?: string, aliases: list<array{uri: string, action?: string}>}>  $lockedAliasGroups
     * @param  list<array{canonical: string, canonical_action?: string, aliases: list<array{uri: string, action?: string}>}>  $generatedAliasGroups
     * @return array<string, mixed>
     */
    private function compareAliasGroups(array $lockedAliasGroups, array $generatedAliasGroups): array
    {
        $locked = $this->flattenAliasGroups($lockedAliasGroups);
        $generated = $this->flattenAliasGroups($generatedAliasGroups);

        $missingFromLocked = [];
        $missingFromSource = [];
        $actionMismatches = [];

        foreach ($generated as $index => $entry) {
            if (! array_key_exists($index, $locked)) {
                $missingFromLocked[] = $entry;

                continue;
            }

            if (
                (string) ($locked[$index]['canonical_action'] ?? '') !== (string) ($entry['canonical_action'] ?? '')
                || (string) ($locked[$index]['alias_action'] ?? '') !== (string) ($entry['alias_action'] ?? '')
            ) {
                $actionMismatches[] = [
                    'canonical' => (string) ($entry['canonical'] ?? ''),
                    'alias' => (string) ($entry['alias'] ?? ''),
                    'locked_canonical_action' => (string) ($locked[$index]['canonical_action'] ?? ''),
                    'generated_canonical_action' => (string) ($entry['canonical_action'] ?? ''),
                    'locked_alias_action' => (string) ($locked[$index]['alias_action'] ?? ''),
                    'generated_alias_action' => (string) ($entry['alias_action'] ?? ''),
                ];
            }
        }

        foreach ($locked as $index => $entry) {
            if (! array_key_exists($index, $generated)) {
                $missingFromSource[] = $entry;
            }
        }

        return [
            'alias_groups_missing_from_locked' => $missingFromLocked,
            'alias_groups_missing_from_source' => $missingFromSource,
            'alias_group_action_mismatches' => $actionMismatches,
        ];
    }

    /**
     * @param  list<array{canonical: string, canonical_action?: string, aliases: list<array{uri: string, action?: string}>}>  $groups
     * @return array<string, array{canonical: string, alias: string, canonical_action: string, alias_action: string}>
     */
    private function flattenAliasGroups(array $groups): array
    {
        $flattened = [];

        foreach ($groups as $group) {
            $canonical = trim((string) ($group['canonical'] ?? ''));
            $canonicalAction = trim((string) ($group['canonical_action'] ?? ''));

            foreach ((array) ($group['aliases'] ?? []) as $alias) {
                $aliasUri = trim((string) ($alias['uri'] ?? ''));
                if ($canonical === '' || $aliasUri === '') {
                    continue;
                }

                $flattened[$canonical.'|'.$aliasUri] = [
                    'canonical' => $canonical,
                    'alias' => $aliasUri,
                    'canonical_action' => $canonicalAction,
                    'alias_action' => trim((string) ($alias['action'] ?? '')),
                ];
            }
        }

        ksort($flattened);

        return $flattened;
    }

    /**
     * @param  array<string, string>  $routeAliases
     * @param  array<string, string>  $configuredRouteCapabilities
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, capability: string, middleware: list<string>}>  $runtimeCapabilities
     * @return list<array<string, string>>
     */
    private function routeAliasMismatches(array $routeAliases, array $configuredRouteCapabilities, array $runtimeCapabilities): array
    {
        $mismatches = [];

        foreach ($routeAliases as $aliasSignature => $canonicalSignature) {
            if (! array_key_exists($aliasSignature, $configuredRouteCapabilities)) {
                $mismatches[] = [
                    'alias_signature' => $aliasSignature,
                    'canonical_signature' => $canonicalSignature,
                    'issue' => 'missing_configured_alias_capability',
                ];
            }

            if (! array_key_exists($canonicalSignature, $configuredRouteCapabilities)) {
                $mismatches[] = [
                    'alias_signature' => $aliasSignature,
                    'canonical_signature' => $canonicalSignature,
                    'issue' => 'missing_configured_canonical_capability',
                ];
            }

            if (
                array_key_exists($aliasSignature, $configuredRouteCapabilities)
                && array_key_exists($canonicalSignature, $configuredRouteCapabilities)
                && (string) $configuredRouteCapabilities[$aliasSignature] !== (string) $configuredRouteCapabilities[$canonicalSignature]
            ) {
                $mismatches[] = [
                    'alias_signature' => $aliasSignature,
                    'canonical_signature' => $canonicalSignature,
                    'issue' => 'configured_capability_mismatch',
                    'alias_capability' => (string) $configuredRouteCapabilities[$aliasSignature],
                    'canonical_capability' => (string) $configuredRouteCapabilities[$canonicalSignature],
                ];
            }

            if (! array_key_exists($aliasSignature, $runtimeCapabilities)) {
                $mismatches[] = [
                    'alias_signature' => $aliasSignature,
                    'canonical_signature' => $canonicalSignature,
                    'issue' => 'missing_runtime_alias_route',
                ];
            }

            if (! array_key_exists($canonicalSignature, $runtimeCapabilities)) {
                $mismatches[] = [
                    'alias_signature' => $aliasSignature,
                    'canonical_signature' => $canonicalSignature,
                    'issue' => 'missing_runtime_canonical_route',
                ];
            }

            if (
                array_key_exists($aliasSignature, $runtimeCapabilities)
                && array_key_exists($canonicalSignature, $runtimeCapabilities)
                && (string) ($runtimeCapabilities[$aliasSignature]['capability'] ?? '') !== (string) ($runtimeCapabilities[$canonicalSignature]['capability'] ?? '')
            ) {
                $mismatches[] = [
                    'alias_signature' => $aliasSignature,
                    'canonical_signature' => $canonicalSignature,
                    'issue' => 'runtime_capability_mismatch',
                    'alias_capability' => (string) ($runtimeCapabilities[$aliasSignature]['capability'] ?? ''),
                    'canonical_capability' => (string) ($runtimeCapabilities[$canonicalSignature]['capability'] ?? ''),
                ];
            }
        }

        return $mismatches;
    }

    /**
     * @param  array<string, array{signature: string, method: string, uri: string, action: string, middleware: list<string>}>  $runtimeRoutes
     * @return list<array<string, string>>
     */
    private function runtimeRoutesMissingCapabilityMiddleware(array $runtimeRoutes): array
    {
        $missing = [];

        foreach ($runtimeRoutes as $route) {
            $uri = (string) ($route['uri'] ?? '');
            if (! $this->isCapabilityControlledRuntimeRoute($uri)) {
                continue;
            }

            $middleware = (array) ($route['middleware'] ?? []);
            if (! $this->hasStaffApiKeyMiddleware($middleware) || $this->extractStaffCapability($middleware) !== null) {
                continue;
            }

            $missing[] = [
                'signature' => (string) ($route['signature'] ?? ''),
                'action' => (string) ($route['action'] ?? ''),
            ];
        }

        return $missing;
    }

    private function isCapabilityControlledRuntimeRoute(string $uri): bool
    {
        return str_starts_with($uri, 'api/v1/staff/') || str_starts_with($uri, 'api/v1/admin/');
    }

    private function hasStaffApiKeyMiddleware(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if (str_contains((string) $item, 'StaffApiKeyMiddleware')) {
                return true;
            }
        }

        return false;
    }

    private function extractStaffCapability(array $middleware): ?string
    {
        foreach ($middleware as $item) {
            $value = trim((string) $item);
            if (str_starts_with($value, 'staff.capability:')) {
                $capability = trim(substr($value, strlen('staff.capability:')));

                return $capability !== '' ? $capability : null;
            }
        }

        return null;
    }

    private function generatedRouteKey(string $method, string $uri): string
    {
        return Str::slug(strtolower($method.' '.preg_replace('#^api/#', '', trim($uri, '/'))), '_');
    }

    /**
     * @return list<string>
     */
    private function httpMethodsForRoute(IlluminateRoute $route): array
    {
        $methods = array_values(array_filter(
            array_map('strtoupper', $route->methods()),
            static fn (string $method): bool => $method !== 'HEAD'
        ));

        sort($methods);

        return array_values(array_unique($methods));
    }

    private function routeSignature(string $method, string $uri): string
    {
        return strtoupper(trim($method)).' '.trim($uri, '/');
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = array_values(array_filter(array_map(
            static fn ($item) => is_scalar($item) ? trim((string) $item) : '',
            $value,
        ), static fn (string $item): bool => $item !== ''));

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function normalizeStringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalizedKey = trim((string) $key);
            $normalizedValue = is_scalar($item) ? trim((string) $item) : '';

            if ($normalizedKey === '' || $normalizedValue === '') {
                continue;
            }

            $normalized[$normalizedKey] = $normalizedValue;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array<string, list<string>>
     */
    private function normalizeCapabilityAliasMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $normalizedValues = is_array($item)
                ? $this->normalizeStringList($item)
                : $this->normalizeStringList([$item]);

            if ($normalizedValues === []) {
                continue;
            }

            $normalized[$normalizedKey] = $normalizedValues;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadStaffCapabilitiesContract(?string $relativePath = null): array
    {
        $relativePath = $this->staffCapabilitiesPath($relativePath);
        $absolutePath = base_path($relativePath);

        if (! File::exists($absolutePath)) {
            throw new \RuntimeException(sprintf('Staff capabilities config [%s] is missing.', $relativePath));
        }

        $loaded = (static fn (string $path): mixed => require $path)($absolutePath);
        if (! is_array($loaded)) {
            throw new \RuntimeException(sprintf('Staff capabilities config [%s] did not return an array.', $relativePath));
        }

        $loaded['definition_path'] = $relativePath;
        $loaded['known_capabilities'] = $this->normalizeStringList($loaded['known_capabilities'] ?? []);
        $loaded['route_capabilities'] = $this->normalizeStringMap($loaded['route_capabilities'] ?? []);
        $loaded['route_aliases'] = $this->normalizeStringMap($loaded['route_aliases'] ?? []);
        $loaded['capability_aliases'] = $this->normalizeCapabilityAliasMap($loaded['capability_aliases'] ?? []);

        return $loaded;
    }

    private function routeInventoryPath(?string $relativePath = null): string
    {
        $relativePath = trim((string) ($relativePath ?? ''));

        return $relativePath !== ''
            ? $relativePath
            : trim((string) config('booking_release.route_inventory_gate.definition_path', 'tests/fixtures/route_inventory_gate.json'));
    }

    private function staffCapabilitiesPath(?string $relativePath = null): string
    {
        $relativePath = trim((string) ($relativePath ?? ''));

        return $relativePath !== '' ? $relativePath : 'config/staff_capabilities.php';
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function writeRouteInventoryDefinition(array $candidate, string $relativePath): string
    {
        $absolutePath = base_path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($candidate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $relativePath;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function writeStaffCapabilitiesConfig(array $candidate, string $relativePath): string
    {
        $absolutePath = base_path($relativePath);
        if (! File::exists($absolutePath)) {
            throw new \RuntimeException(sprintf('Staff capabilities config [%s] is missing.', $relativePath));
        }

        $content = File::get($absolutePath);
        $content = $this->replacePhpArrayBlock($content, 'known_capabilities', (array) ($candidate['known_capabilities'] ?? []));
        $content = $this->replacePhpArrayBlock($content, 'route_capabilities', (array) ($candidate['route_capabilities'] ?? []));
        $content = $this->replacePhpArrayBlock($content, 'route_aliases', (array) ($candidate['route_aliases'] ?? []));
        $content = $this->replacePhpArrayBlock($content, 'capability_aliases', (array) ($candidate['capability_aliases'] ?? []));

        File::put($absolutePath, $content);

        return $relativePath;
    }

    private function replacePhpArrayBlock(string $content, string $key, array $replacement): string
    {
        $needle = "'".$key."' => [";
        $start = strpos($content, $needle);
        if ($start === false) {
            throw new \RuntimeException(sprintf('Unable to find [%s] array in staff capabilities config.', $key));
        }

        $openBracket = strpos($content, '[', $start + strlen("'".$key."' => "));
        if ($openBracket === false) {
            throw new \RuntimeException(sprintf('Unable to find opening array bracket for [%s].', $key));
        }

        $closeBracket = $this->findMatchingBracket($content, $openBracket);
        $replacementArray = $this->exportPhpArray($replacement, 1);

        return substr($content, 0, $start)
            ."'".$key."' => ".$replacementArray
            .substr($content, $closeBracket + 1);
    }

    private function findMatchingBracket(string $content, int $openBracket): int
    {
        $depth = 0;
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $escaped = false;

        $length = strlen($content);
        for ($index = $openBracket; $index < $length; $index++) {
            $char = $content[$index];

            if ($inSingleQuote) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === '\'') {
                    $inSingleQuote = false;
                }

                continue;
            }

            if ($inDoubleQuote) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === '"') {
                    $inDoubleQuote = false;
                }

                continue;
            }

            if ($char === '\'') {
                $inSingleQuote = true;

                continue;
            }

            if ($char === '"') {
                $inDoubleQuote = true;

                continue;
            }

            if ($char === '[') {
                $depth++;

                continue;
            }

            if ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return $index;
                }
            }
        }

        throw new \RuntimeException('Unable to locate matching closing bracket while rewriting staff capabilities config.');
    }

    private function exportPhpArray(mixed $value, int $indentLevel): string
    {
        if (! is_array($value) || $value === []) {
            return '[]';
        }

        $indent = str_repeat('    ', $indentLevel);
        $childIndent = str_repeat('    ', $indentLevel + 1);
        $lines = ['['];
        $isList = array_is_list($value);

        foreach ($value as $key => $item) {
            $line = $childIndent;
            if (! $isList) {
                $line .= $this->exportPhpScalar($key).' => ';
            }

            $line .= is_array($item)
                ? $this->exportPhpArray($item, $indentLevel + 1)
                : $this->exportPhpScalar($item);

            $line .= ',';
            $lines[] = $line;
        }

        $lines[] = $indent.']';

        return implode(PHP_EOL, $lines);
    }

    private function exportPhpScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'".str_replace(['\\', '\''], ['\\\\', '\\\''], (string) $value)."'";
    }

    /**
     * @param  array<string, mixed>  $issues
     */
    private function countIssues(array $issues): int
    {
        $count = 0;

        foreach ($issues as $value) {
            if (! is_array($value)) {
                continue;
            }

            $count += count($value);
        }

        return $count;
    }
}

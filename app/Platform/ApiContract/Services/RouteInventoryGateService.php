<?php

declare(strict_types=1);

namespace App\Platform\ApiContract\Services;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class RouteInventoryGateService
{
    /**
     * @return array{
     *   suite: string,
     *   description: string,
     *   definition_path: string,
     *   expected_routes: list<array{
     *     key: string,
     *     method: string,
     *     uri: string,
     *     action: string,
     *     middleware_contains?: list<string>,
     *     middleware_excludes?: list<string>
     *   }>,
     *   smoke_requests: list<array{key: string, method: string, uri: string, allowed_statuses: list<int>}>,
     *   alias_groups: list<array{
     *     canonical: string,
     *     canonical_action?: string,
     *     aliases: list<array{uri: string, action?: string}>
     *   }>
     * }
     */
    public function definition(?string $relativeDefinitionPath = null): array
    {
        $relativeDefinitionPath = trim((string) ($relativeDefinitionPath ?? config('booking_release.route_inventory_gate.definition_path', 'tests/fixtures/route_inventory_gate.json')));
        $definitionAbsolutePath = base_path($relativeDefinitionPath);

        if (! File::exists($definitionAbsolutePath)) {
            throw new \RuntimeException(sprintf('Route inventory gate definition [%s] is missing.', $relativeDefinitionPath));
        }

        $raw = File::get($definitionAbsolutePath);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException(sprintf('Route inventory gate definition [%s] is not valid JSON.', $relativeDefinitionPath));
        }

        $suite = trim((string) ($decoded['suite'] ?? ''));
        $description = trim((string) ($decoded['description'] ?? ''));

        $expectedRoutes = array_values(array_filter(array_map(function ($entry) {
            if (! is_array($entry)) {
                return null;
            }

            $key = trim((string) ($entry['key'] ?? ''));
            $method = strtoupper(trim((string) ($entry['method'] ?? '')));
            $uri = trim((string) ($entry['uri'] ?? ''));
            $action = trim((string) ($entry['action'] ?? ''));

            if ($key === '' || $method === '' || $uri === '' || $action === '') {
                return null;
            }

            $normalized = [
                'key' => $key,
                'method' => $method,
                'uri' => $this->canonicalFixtureUri($uri),
                'action' => $action,
            ];

            $middlewareContains = $this->normalizeStringList($entry['middleware_contains'] ?? []);
            if ($middlewareContains !== []) {
                $normalized['middleware_contains'] = $middlewareContains;
            }

            $middlewareExcludes = $this->normalizeStringList($entry['middleware_excludes'] ?? []);
            if ($middlewareExcludes !== []) {
                $normalized['middleware_excludes'] = $middlewareExcludes;
            }

            return $normalized;
        }, (array) ($decoded['expected_routes'] ?? []))));

        $smokeRequests = array_values(array_filter(array_map(function ($entry) {
            if (! is_array($entry)) {
                return null;
            }

            $key = trim((string) ($entry['key'] ?? ''));
            $method = strtoupper(trim((string) ($entry['method'] ?? '')));
            $uri = trim((string) ($entry['uri'] ?? ''));
            $allowedStatuses = array_values(array_filter(array_map(
                static fn ($status) => is_numeric($status) ? (int) $status : null,
                (array) ($entry['allowed_statuses'] ?? [])
            ), static fn (?int $status): bool => $status !== null));

            if ($key === '' || $method === '' || $uri === '' || $allowedStatuses === []) {
                return null;
            }

            return [
                'key' => $key,
                'method' => $method,
                'uri' => $this->canonicalFixtureUri($uri),
                'allowed_statuses' => $allowedStatuses,
            ];
        }, (array) ($decoded['smoke_requests'] ?? []))));

        $aliasGroups = array_values(array_filter(array_map(function ($group) {
            if (! is_array($group)) {
                return null;
            }

            $canonical = trim((string) ($group['canonical'] ?? ''));
            if ($canonical === '') {
                return null;
            }

            $normalized = [
                'canonical' => $this->canonicalFixtureUri($canonical),
                'aliases' => array_values(array_filter(array_map(function ($aliasDefinition) {
                    if (is_string($aliasDefinition)) {
                        $aliasDefinition = ['uri' => $aliasDefinition];
                    }

                    if (! is_array($aliasDefinition)) {
                        return null;
                    }

                    $uri = trim((string) ($aliasDefinition['uri'] ?? ''));
                    if ($uri === '') {
                        return null;
                    }

                    $normalizedAlias = [
                        'uri' => $this->canonicalFixtureUri($uri),
                    ];

                    $action = trim((string) ($aliasDefinition['action'] ?? ''));
                    if ($action !== '') {
                        $normalizedAlias['action'] = $action;
                    }

                    return $normalizedAlias;
                }, (array) ($group['aliases'] ?? [])))),
            ];

            $canonicalAction = trim((string) ($group['canonical_action'] ?? ''));
            if ($canonicalAction !== '') {
                $normalized['canonical_action'] = $canonicalAction;
            }

            if ($normalized['aliases'] === []) {
                return null;
            }

            usort($normalized['aliases'], static fn (array $left, array $right): int => strcmp((string) ($left['uri'] ?? ''), (string) ($right['uri'] ?? '')));

            return $normalized;
        }, (array) ($decoded['alias_groups'] ?? []))));

        usort($aliasGroups, static fn (array $left, array $right): int => strcmp((string) ($left['canonical'] ?? ''), (string) ($right['canonical'] ?? '')));

        if ($suite === '' || $expectedRoutes === [] || $smokeRequests === []) {
            throw new \RuntimeException(sprintf('Route inventory gate definition [%s] is missing suite metadata or required entries.', $relativeDefinitionPath));
        }

        return [
            'suite' => $suite,
            'description' => $description !== '' ? $description : 'Canonical route inventory gate for public API surface drift detection.',
            'definition_path' => $relativeDefinitionPath,
            'expected_routes' => $expectedRoutes,
            'smoke_requests' => $smokeRequests,
            'alias_groups' => $aliasGroups,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   suite: string,
     *   description: string,
     *   definition_path: string,
     *   checks: array{
     *     route_action_methods: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>},
     *     expected_routes: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>},
     *     public_controllers: array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     *   },
     *   summary: array{route_count: int, expected_route_count: int, public_controller_count: int, error_count: int, warning_count: int},
     *   meta: array<string, mixed>
     * }
     */
    public function inspect(): array
    {
        $definition = $this->definition();
        $publicControllers = $this->discoverPublicControllers();

        $checks = [
            'route_action_methods' => $this->checkApiControllerRoutesReferenceExistingMethods(),
            'expected_routes' => $this->checkExpectedRoutes($definition['expected_routes']),
            'public_controllers' => $this->checkPublicControllersNotOrphaned($publicControllers, $definition['expected_routes']),
            'api_alias_deprecation_plan' => $this->checkApiAliasDeprecationPlan($definition['expected_routes'], $definition['alias_groups']),
        ];

        $errors = 0;
        $warnings = 0;

        foreach ($checks as $check) {
            if (! ($check['ok'] ?? false)) {
                if (($check['severity'] ?? 'error') === 'warning') {
                    $warnings++;
                } else {
                    $errors++;
                }
            }
        }

        return [
            'ok' => ($errors === 0),
            'suite' => $definition['suite'],
            'description' => $definition['description'],
            'definition_path' => $definition['definition_path'],
            'checks' => $checks,
            'summary' => [
                'route_count' => count(Route::getRoutes()->getRoutes()),
                'expected_route_count' => count($definition['expected_routes']),
                'public_controller_count' => count($publicControllers),
                'api_alias_deprecation_count' => count((array) config('booking.api_alias_deprecations.routes', [])),
                'error_count' => $errors,
                'warning_count' => $warnings,
            ],
            'meta' => [
                'generated_at_utc' => now('UTC')->toIso8601String(),
                'public_controllers' => $publicControllers,
                'smoke_request_count' => count($definition['smoke_requests']),
                'alias_deprecation_plan' => $this->aliasDeprecationPlan(),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function discoverPublicControllers(): array
    {
        $prefixes = array_values(array_filter(array_map(
            static fn ($value) => is_scalar($value) ? trim((string) $value) : '',
            (array) config('booking_release.route_inventory_gate.public_controller_prefixes', ['Customer'])
        ), static fn (string $value): bool => $value !== ''));

        $exactNames = array_values(array_filter(array_map(
            static fn ($value) => is_scalar($value) ? trim((string) $value) : '',
            (array) config('booking_release.route_inventory_gate.public_controller_exact', ['PaymentProviderWebhookController'])
        ), static fn (string $value): bool => $value !== ''));

        $exclusions = array_values(array_filter(array_map(
            static fn ($value) => is_scalar($value) ? trim((string) $value) : '',
            (array) config('booking_release.route_inventory_gate.public_controller_exclusions', [])
        ), static fn (string $value): bool => $value !== ''));

        $controllers = collect($this->publicControllerRoots())
            ->flatMap(function (array $root) use ($prefixes, $exactNames): array {
                $path = (string) ($root['path'] ?? '');
                $namespace = (string) ($root['namespace'] ?? '');

                if ($path === '' || $namespace === '' || ! File::isDirectory($path)) {
                    return [];
                }

                return collect(File::allFiles($path))
                    ->map(static function ($file) use ($path, $namespace): array {
                        $relativePath = trim(str_replace([$path, DIRECTORY_SEPARATOR], ['', '\\'], $file->getPathname()), '\\');

                        return [
                            'basename' => $file->getFilenameWithoutExtension(),
                            'relative_path' => $relativePath,
                            'fqcn' => $namespace.'\\'.preg_replace('/\\.php$/', '', $relativePath),
                        ];
                    })
                    ->filter(function (array $candidate) use ($prefixes, $exactNames): bool {
                        $basename = (string) ($candidate['basename'] ?? '');
                        $relativePath = (string) ($candidate['relative_path'] ?? '');

                        if (in_array($basename, $exactNames, true)) {
                            return true;
                        }

                        foreach ($prefixes as $prefix) {
                            if (str_starts_with($basename, $prefix)) {
                                return true;
                            }
                        }

                        return str_starts_with($relativePath, 'Customer\\');
                    })
                    ->pluck('fqcn')
                    ->all();
            })
            ->reject(static fn (string $fqcn): bool => in_array($fqcn, $exclusions, true))
            ->filter(static fn (string $fqcn): bool => class_exists($fqcn))
            ->values()
            ->all();

        sort($controllers);

        return array_values($controllers);
    }

    /**
     * @return list<array{path: string, namespace: string}>
     */
    private function publicControllerRoots(): array
    {
        $roots = [
            [
                'path' => app_path('Http/Controllers/Api'),
                'namespace' => 'App\\Http\\Controllers\\Api',
            ],
        ];

        foreach (File::directories(app_path('Modules')) as $modulePath) {
            $controllerPath = $modulePath.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers';

            if (File::isDirectory($controllerPath)) {
                $roots[] = [
                    'path' => $controllerPath,
                    'namespace' => 'App\\Modules\\'.basename($modulePath).'\\Http\\Controllers',
                ];
            }
        }

        foreach (File::directories(app_path('Platform')) as $platformPath) {
            $controllerPath = $platformPath.DIRECTORY_SEPARATOR.'Http';

            if (File::isDirectory($controllerPath)) {
                $roots[] = [
                    'path' => $controllerPath,
                    'namespace' => 'App\\Platform\\'.basename($platformPath).'\\Http',
                ];
            }
        }

        return $roots;
    }

    /**
     * @return list<string>
     */
    private function routeActionControllerPrefixes(): array
    {
        return [
            'App\\Http\\Controllers\\Api\\',
            'App\\Modules\\',
            'App\\Platform\\',
        ];
    }

    private function isApiControllerAction(string $action): bool
    {
        if ($action === 'Closure') {
            return false;
        }

        foreach ($this->routeActionControllerPrefixes() as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{key: string, method: string, uri: string, action: string, middleware_contains?: list<string>, middleware_excludes?: list<string>}>  $expectedRoutes
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function checkExpectedRoutes(array $expectedRoutes): array
    {
        $missingRoutes = [];
        $actionMismatches = [];
        $middlewareMismatches = [];

        foreach ($expectedRoutes as $expected) {
            $route = $this->findRoute($expected['method'], $expected['uri']);

            if ($route === null) {
                $missingRoutes[] = $expected;

                continue;
            }

            $actualAction = $route->getActionName();
            if ($actualAction !== $expected['action']) {
                $actionMismatches[] = [
                    'key' => $expected['key'],
                    'method' => $expected['method'],
                    'uri' => $expected['uri'],
                    'expected_action' => $expected['action'],
                    'actual_action' => $actualAction,
                ];

                continue;
            }

            $actualMiddleware = array_values(array_filter(
                array_map(static fn ($value) => is_string($value) ? trim($value) : '', $route->gatherMiddleware()),
                static fn (string $value): bool => $value !== ''
            ));

            $missingMiddleware = [];
            foreach ((array) ($expected['middleware_contains'] ?? []) as $required) {
                if (! in_array($required, $actualMiddleware, true)) {
                    $missingMiddleware[] = $required;
                }
            }

            $unexpectedMiddleware = [];
            foreach ((array) ($expected['middleware_excludes'] ?? []) as $forbidden) {
                if (in_array($forbidden, $actualMiddleware, true)) {
                    $unexpectedMiddleware[] = $forbidden;
                }
            }

            if ($missingMiddleware !== [] || $unexpectedMiddleware !== []) {
                $mismatch = [
                    'key' => $expected['key'],
                    'method' => $expected['method'],
                    'uri' => $expected['uri'],
                ];

                if ($missingMiddleware !== []) {
                    $mismatch['missing_middleware'] = array_values($missingMiddleware);
                }

                if ($unexpectedMiddleware !== []) {
                    $mismatch['unexpected_middleware'] = array_values($unexpectedMiddleware);
                }

                $middlewareMismatches[] = $mismatch;
            }
        }

        if ($missingRoutes !== [] || $actionMismatches !== [] || $middlewareMismatches !== []) {
            return $this->error(
                'Expected runtime routes drifted from the locked inventory.',
                [
                    'missing_routes' => $missingRoutes,
                    'action_mismatches' => $actionMismatches,
                    'middleware_mismatches' => $middlewareMismatches,
                    'expected_route_count' => count($expectedRoutes),
                ]
            );
        }

        return $this->ok(
            'Expected runtime routes match the locked inventory.',
            [
                'expected_route_count' => count($expectedRoutes),
            ]
        );
    }

    /**
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function checkApiControllerRoutesReferenceExistingMethods(): array
    {
        $missing = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();

            if (! is_string($action) || ! $this->isApiControllerAction($action)) {
                continue;
            }

            if (str_contains($action, '@')) {
                [$class, $method] = explode('@', $action, 2);

                if (! class_exists($class) || ! method_exists($class, $method)) {
                    $missing[] = [
                        'method' => $this->primaryHttpMethod($route),
                        'uri' => trim($route->uri(), '/'),
                        'action' => $action,
                    ];
                }

                continue;
            }

            if (! class_exists($action) || ! method_exists($action, '__invoke')) {
                $missing[] = [
                    'method' => $this->primaryHttpMethod($route),
                    'uri' => trim($route->uri(), '/'),
                    'action' => $action,
                ];
            }
        }

        if ($missing !== []) {
            return $this->error(
                'One or more API routes point to controller methods that do not exist.',
                [
                    'missing_actions' => $missing,
                    'missing_action_count' => count($missing),
                ]
            );
        }

        return $this->ok('All API controller routes reference existing methods.');
    }

    /**
     * @param  list<string>  $controllers
     * @param  list<array{key: string, method: string, uri: string, action: string, middleware_contains?: list<string>, middleware_excludes?: list<string>}>  $expectedRoutes
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function checkPublicControllersNotOrphaned(array $controllers, array $expectedRoutes): array
    {
        $lockedControllers = [];

        foreach ($expectedRoutes as $expected) {
            $action = $expected['action'];
            if (! str_contains($action, '@')) {
                continue;
            }

            [$controller] = explode('@', $action, 2);
            $lockedControllers[] = $controller;
        }

        $lockedControllers = array_values(array_unique($lockedControllers));
        sort($lockedControllers);

        $missing = [];
        $unlocked = [];

        foreach ($controllers as $controller) {
            $hasRoute = collect(Route::getRoutes()->getRoutes())
                ->contains(static function (IlluminateRoute $route) use ($controller): bool {
                    $action = $route->getActionName();

                    return $action === $controller
                        || str_starts_with($action, $controller.'@');
                });

            if (! in_array($controller, $lockedControllers, true)) {
                if ($hasRoute) {
                    $unlocked[] = $controller;
                }

                continue;
            }

            if (! $hasRoute) {
                $missing[] = $controller;
            }
        }

        if ($missing !== [] || $unlocked !== []) {
            return $this->error(
                'Public-facing controller inventory drifted from the locked runtime contract.',
                [
                    'orphaned_controllers' => $missing,
                    'unlocked_public_controllers' => $unlocked,
                    'locked_public_controller_count' => count($lockedControllers),
                    'public_controller_count' => count($controllers),
                ]
            );
        }

        return $this->ok(
            'Public-facing controllers referenced by the locked inventory are connected to runtime routes.',
            [
                'locked_public_controller_count' => count($lockedControllers),
                'unlocked_public_controllers' => $unlocked,
                'public_controller_count' => count($controllers),
            ]
        );
    }

    /**
     * @param  list<array{key: string, method: string, uri: string, action: string, middleware_contains?: list<string>, middleware_excludes?: list<string>}>  $expectedRoutes
     * @param  list<array{canonical: string, canonical_action?: string, aliases: list<array{uri: string, action?: string}>}>  $aliasGroups
     * @return array{ok: bool, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function checkApiAliasDeprecationPlan(array $expectedRoutes, array $aliasGroups): array
    {
        $configuredRoutes = array_values(array_filter(
            (array) config('booking.api_alias_deprecations.routes', []),
            static fn ($definition): bool => is_array($definition)
        ));

        $routeMethods = [];
        foreach ($expectedRoutes as $expected) {
            $routeMethods[$this->canonicalFixtureUri($expected['uri'])] = strtoupper((string) $expected['method']);
        }

        $lockedAliases = [];
        foreach ($aliasGroups as $group) {
            $canonical = $this->canonicalFixtureUri((string) ($group['canonical'] ?? ''));
            $canonicalMethod = (string) ($routeMethods[$canonical] ?? '');

            foreach ((array) ($group['aliases'] ?? []) as $alias) {
                if (! is_array($alias)) {
                    continue;
                }

                $aliasUri = $this->canonicalFixtureUri((string) ($alias['uri'] ?? ''));
                $aliasMethod = (string) ($routeMethods[$aliasUri] ?? $canonicalMethod);
                if ($aliasUri === '' || $aliasMethod === '') {
                    continue;
                }

                $lockedAliases[$this->routeSignature($aliasMethod, $aliasUri)] = [
                    'method' => $aliasMethod,
                    'uri' => $aliasUri,
                    'canonical' => $this->routeSignature($canonicalMethod !== '' ? $canonicalMethod : $aliasMethod, $canonical),
                ];
            }
        }

        $configuredAliases = [];
        $invalidDefinitions = [];
        foreach ($configuredRoutes as $definition) {
            $aliasSignature = $this->normalizeRouteSignature((string) ($definition['deprecated_alias'] ?? ''));
            $canonicalSignature = $this->normalizeRouteSignature((string) ($definition['canonical_route'] ?? ''));
            $key = trim((string) ($definition['key'] ?? ''));

            if ($key === '' || $aliasSignature === '' || $canonicalSignature === '') {
                $invalidDefinitions[] = $definition;

                continue;
            }

            $configuredAliases[$aliasSignature] = [
                'key' => $key,
                'deprecated_alias' => $aliasSignature,
                'canonical_route' => $canonicalSignature,
                'minimum_evidence' => array_values((array) ($definition['minimum_evidence'] ?? [])),
                'removal_criteria' => (string) ($definition['removal_criteria'] ?? ''),
            ];
        }

        $missingPlans = array_values(array_diff(array_keys($lockedAliases), array_keys($configuredAliases)));
        $orphanedPlans = array_values(array_diff(array_keys($configuredAliases), array_keys($lockedAliases)));
        $missingEvidence = array_values(array_filter(
            $configuredAliases,
            static fn (array $definition): bool => $definition['minimum_evidence'] === [] || trim((string) $definition['removal_criteria']) === ''
        ));

        if ($missingPlans !== [] || $orphanedPlans !== [] || $invalidDefinitions !== [] || $missingEvidence !== []) {
            return $this->error(
                'API alias deprecation plan is incomplete for the locked alias inventory.',
                [
                    'missing_plans' => $missingPlans,
                    'orphaned_plans' => $orphanedPlans,
                    'invalid_definitions' => $invalidDefinitions,
                    'missing_evidence_or_criteria' => $missingEvidence,
                ]
            );
        }

        return $this->ok(
            'API alias deprecation plan covers every locked alias and records removal evidence requirements.',
            [
                'route_alias_count' => count($configuredAliases),
                'idempotency_compatibility_input_count' => count((array) config('booking.api_alias_deprecations.idempotency_inputs', [])),
                'observation_release_cycles' => (int) config('booking.api_alias_deprecations.observation_release_cycles', 1),
                'audit_log_event' => (string) config('booking.api_alias_deprecations.audit_log_event', 'api_deprecated_alias_used'),
                'idempotency_compatibility_event' => (string) config('booking.api_alias_deprecations.idempotency_compatibility_event', 'idempotency_compatibility_key_used'),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function aliasDeprecationPlan(): array
    {
        return [
            'observation_release_cycles' => (int) config('booking.api_alias_deprecations.observation_release_cycles', 1),
            'audit_log_event' => (string) config('booking.api_alias_deprecations.audit_log_event', 'api_deprecated_alias_used'),
            'idempotency_compatibility_event' => (string) config('booking.api_alias_deprecations.idempotency_compatibility_event', 'idempotency_compatibility_key_used'),
            'routes' => array_values((array) config('booking.api_alias_deprecations.routes', [])),
            'idempotency_inputs' => array_values((array) config('booking.api_alias_deprecations.idempotency_inputs', [])),
        ];
    }

    private function findRoute(string $method, string $uri): ?IlluminateRoute
    {
        $normalizedCandidates = $this->uriCandidates($uri);

        return collect(Route::getRoutes()->getRoutes())
            ->first(static fn (IlluminateRoute $route): bool => in_array($method, $route->methods(), true)
                && in_array(trim($route->uri(), '/'), $normalizedCandidates, true));
    }

    private function canonicalFixtureUri(string $uri): string
    {
        $normalized = trim($uri, '/');

        if ($normalized === '') {
            return $normalized;
        }

        if (! str_starts_with($normalized, 'api/')) {
            return 'api/'.$normalized;
        }

        return $normalized;
    }

    private function routeSignature(string $method, string $uri): string
    {
        return strtoupper(trim($method)).' /'.$this->canonicalFixtureUri($uri);
    }

    private function normalizeRouteSignature(string $signature): string
    {
        $signature = trim($signature);
        if ($signature === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $signature, 2);
        if (! is_array($parts) || count($parts) !== 2) {
            return '';
        }

        return $this->routeSignature((string) $parts[0], (string) $parts[1]);
    }

    /**
     * @return list<string>
     */
    private function uriCandidates(string $uri): array
    {
        $normalized = trim($uri, '/');
        $candidates = [$normalized];

        if (! str_starts_with($normalized, 'api/')) {
            $candidates[] = 'api/'.$normalized;
        }

        return array_values(array_unique($candidates));
    }

    private function primaryHttpMethod(IlluminateRoute $route): string
    {
        $methods = array_values(array_filter(
            $route->methods(),
            static fn (string $method): bool => $method !== 'HEAD'
        ));

        return (string) ($methods[0] ?? 'GET');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{ok: true, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function ok(string $message, array $meta = []): array
    {
        return $meta === []
            ? ['ok' => true, 'severity' => 'info', 'message' => $message]
            : ['ok' => true, 'severity' => 'info', 'message' => $message, 'meta' => $meta];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{ok: false, severity: string, message: string, meta?: array<string, mixed>}
     */
    private function error(string $message, array $meta = []): array
    {
        return $meta === []
            ? ['ok' => false, 'severity' => 'error', 'message' => $message]
            : ['ok' => false, 'severity' => 'error', 'message' => $message, 'meta' => $meta];
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
}

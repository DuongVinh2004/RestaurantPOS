<?php

declare(strict_types=1);

namespace App\Platform\ApiContract\Services;

use App\Platform\ApiContract\Services\RouteInventoryGateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;

class OpenApiSpecService
{
    public function __construct(
        private readonly RouteInventoryGateService $routeInventory,
        private readonly FormRequestSchemaFactory $formRequestSchemas,
        private readonly ApiContractMetadataRegistry $metadata,
    ) {}

    /**
     * @return array{
     *   spec: array<string,mixed>,
     *   report: array<string,mixed>,
     *   path: string
     * }
     */
    public function export(?string $relativePath = null): array
    {
        $spec = $this->build();
        $relativePath ??= (string) config('booking_release.api_contract.openapi_path', 'storage/app/booking_release/openapi-v1.json');
        $absolutePath = base_path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'spec' => $spec,
            'report' => $this->report($spec),
            'path' => $relativePath,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function build(): array
    {
        $definition = $this->routeInventory->definition();
        $aliasMap = [];
        foreach ((array) ($definition['alias_groups'] ?? []) as $group) {
            $canonicalUri = '/'.ltrim((string) ($group['canonical'] ?? ''), '/');
            foreach ((array) ($group['aliases'] ?? []) as $alias) {
                if (is_array($alias)) {
                    $aliasUri = '/'.ltrim((string) ($alias['uri'] ?? ''), '/');
                    if ($aliasUri !== '/') {
                        $aliasMap[$aliasUri] = ltrim($canonicalUri, '/');
                    }
                } else {
                    $aliasUri = '/'.ltrim((string) $alias, '/');
                    if ($aliasUri !== '/') {
                        $aliasMap[$aliasUri] = ltrim($canonicalUri, '/');
                    }
                }
            }
        }

        $paths = [];
        $requestSchemas = [];
        $priorityOperations = $this->metadata->priorityOperations();

        foreach ((array) ($definition['expected_routes'] ?? []) as $routeDefinition) {
            $method = strtoupper((string) ($routeDefinition['method'] ?? 'GET'));
            $uri = (string) ($routeDefinition['uri'] ?? '');
            $path = '/'.ltrim($uri, '/');
            $runtimeRoute = $this->findRoute($method, $uri);
            if (! $runtimeRoute instanceof IlluminateRoute) {
                continue;
            }

            $operation = $this->buildOperation(
                routeDefinition: $routeDefinition,
                runtimeRoute: $runtimeRoute,
                aliasMap: $aliasMap,
                requestSchemas: $requestSchemas,
                metadata: $priorityOperations[$method.' '.$uri] ?? [],
            );

            $paths[$path][strtolower($method)] = $operation;
        }

        ksort($paths);
        ksort($requestSchemas);

        $schemas = array_merge($this->metadata->componentSchemas(), $requestSchemas);
        ksort($schemas);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'RestaurantPOS API Contract',
                'version' => 'v1',
                'description' => 'Runtime-backed OpenAPI contract generated from Laravel routes, FormRequest rules, and priority flow metadata.',
            ],
            'servers' => [
                ['url' => '/'],
            ],
            'tags' => $this->buildTags($paths),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => $this->securitySchemes(),
                'schemas' => $schemas,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $spec
     * @return array<string,mixed>
     */
    public function report(array $spec): array
    {
        $fallbackRoutes = [];
        $fullRoutes = [];
        $authModes = [];
        $tags = [];

        foreach ((array) ($spec['paths'] ?? []) as $path => $operations) {
            foreach ((array) $operations as $method => $operation) {
                $signature = strtoupper((string) $method).' '.ltrim((string) $path, '/');
                if ((string) ($operation['x-contract-grade'] ?? 'fallback') === 'full') {
                    $fullRoutes[] = $signature;
                } else {
                    $fallbackRoutes[] = $signature;
                }

                $authMode = (string) ($operation['x-auth-mode'] ?? 'public');
                $authModes[$authMode] = ($authModes[$authMode] ?? 0) + 1;

                foreach ((array) ($operation['tags'] ?? []) as $tag) {
                    $tags[(string) $tag] = ($tags[(string) $tag] ?? 0) + 1;
                }
            }
        }

        ksort($authModes);
        ksort($tags);
        sort($fallbackRoutes);
        sort($fullRoutes);

        return [
            'summary' => [
                'path_count' => count((array) ($spec['paths'] ?? [])),
                'full_contract_operation_count' => count($fullRoutes),
                'fallback_operation_count' => count($fallbackRoutes),
            ],
            'fallback_operations' => $fallbackRoutes,
            'full_contract_operations' => $fullRoutes,
            'known_envelope_exceptions' => $this->metadata->knownEnvelopeExceptions(),
            'auth_mode_breakdown' => $authModes,
            'tag_breakdown' => $tags,
        ];
    }

    /**
     * @param  array<string,mixed>  $routeDefinition
     * @param  array<string,string>  $aliasMap
     * @param  array<string,array<string,mixed>>  $requestSchemas
     * @param  array<string,mixed>  $metadata
     * @return array<string,mixed>
     */
    private function buildOperation(array $routeDefinition, IlluminateRoute $runtimeRoute, array $aliasMap, array &$requestSchemas, array $metadata): array
    {
        $method = strtoupper((string) ($routeDefinition['method'] ?? 'GET'));
        $uri = (string) ($routeDefinition['uri'] ?? '');
        $action = (string) ($routeDefinition['action'] ?? $runtimeRoute->getActionName());
        $middleware = array_values(array_map('strval', $runtimeRoute->gatherMiddleware()));
        $reflection = $this->reflectAction($action);
        $requestClass = $reflection['form_request'] ?? null;

        $parameters = array_merge(
            $this->pathParameters($uri, $reflection['path_parameter_types'] ?? []),
            $requestClass !== null && in_array($method, ['GET', 'DELETE'], true)
                ? $this->describeRequestClass($requestClass, $requestSchemas)['query_parameters']
                : [],
            (array) ($metadata['parameters'] ?? []),
            $this->idempotencyParameters($middleware),
        );

        $isDeprecated = isset($aliasMap['/'.ltrim($uri, '/')]);
        $canonicalRoute = $isDeprecated ? $aliasMap['/'.ltrim($uri, '/')] : null;
        $description = (string) ($metadata['description'] ?? 'Runtime route mirrored from Laravel route inventory.');

        if ($isDeprecated && $canonicalRoute !== null) {
            $description .= "\n\n> **Deprecated Alias**\n>\n> This is a legacy route endpoint preserved for backwards compatibility. Please use the exact canonical route: `/".$canonicalRoute.'`';
        }

        $operation = [
            'operationId' => (string) ($routeDefinition['key'] ?? Str::slug($method.' '.$uri, '_')),
            'summary' => (string) ($metadata['summary'] ?? $this->defaultSummary($method, $uri)),
            'description' => $description,
            'tags' => (array) ($metadata['tags'] ?? [$this->defaultTag($uri)]),
            'parameters' => $parameters,
            'responses' => $this->responsesForOperation($method, $uri, $middleware, $metadata),
            'security' => $metadata['security'] ?? $this->inferSecurity($uri, $middleware),
            'deprecated' => $isDeprecated,
            'x-route-key' => (string) ($routeDefinition['key'] ?? ''),
            'x-action' => $action,
            'x-auth-mode' => (string) ($metadata['auth_mode'] ?? $this->inferAuthMode($uri, $middleware)),
            'x-contract-grade' => (string) ($metadata['contract_grade'] ?? 'fallback'),
            'x-runtime-middleware' => $middleware,
        ];

        if ($isDeprecated && $canonicalRoute !== null) {
            $operation['x-canonical-route'] = '/'.ltrim($canonicalRoute, '/');
        }

        if ($requestClass !== null && ! in_array($method, ['GET', 'DELETE'], true) && ! isset($metadata['request_body'])) {
            $requestInfo = $this->describeRequestClass($requestClass, $requestSchemas);
            $content = [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/'.$requestInfo['schema_name']],
                ],
            ];
            if (isset($metadata['request_example'])) {
                $content['application/json']['example'] = $metadata['request_example'];
            } elseif ($requestInfo['request_example'] !== null) {
                $content['application/json']['example'] = $requestInfo['request_example'];
            }

            $operation['requestBody'] = [
                'required' => (bool) (($requestInfo['schema']['required'] ?? []) !== []),
                'content' => $content,
            ];
        }

        if (isset($metadata['request_body'])) {
            $operation['requestBody'] = $metadata['request_body'];
        }

        if (($metadata['envelope_exception'] ?? false) === true) {
            $operation['x-envelope-exception'] = true;
        }

        return $operation;
    }

    private function findRoute(string $method, string $uri): ?IlluminateRoute
    {
        $normalized = trim($uri, '/');

        return collect(Route::getRoutes()->getRoutes())
            ->first(static fn (IlluminateRoute $route): bool => in_array($method, $route->methods(), true)
                && trim($route->uri(), '/') === $normalized);
    }

    /**
     * @return array{
     *   form_request?: class-string<FormRequest>,
     *   path_parameter_types: array<string,string>
     * }
     */
    private function reflectAction(string $action): array
    {
        $result = ['path_parameter_types' => []];

        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return $result;
        }

        [$class, $method] = explode('@', $action, 2);
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return $result;
        }

        $reflection = new ReflectionMethod($class, $method);
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
            if ($typeName !== null && is_a($typeName, FormRequest::class, true)) {
                $result['form_request'] = $typeName;

                continue;
            }

            if (in_array($typeName, ['int', 'string'], true)) {
                $result['path_parameter_types'][$parameter->getName()] = $typeName;
            }
        }

        return $result;
    }

    /**
     * @param  class-string<FormRequest>  $requestClass
     * @param  array<string,array<string,mixed>>  $requestSchemas
     * @return array{
     *   schema_name:string,
     *   schema:array<string,mixed>,
     *   query_parameters:list<array<string,mixed>>,
     *   request_example:array<string,mixed>|null
     * }
     */
    private function describeRequestClass(string $requestClass, array &$requestSchemas): array
    {
        $requestInfo = $this->formRequestSchemas->describe($requestClass);
        $requestSchemas[$requestInfo['schema_name']] = $requestInfo['schema'];

        return $requestInfo;
    }

    /**
     * @param  array<string,string>  $parameterTypes
     * @return list<array<string,mixed>>
     */
    private function pathParameters(string $uri, array $parameterTypes): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        $parameters = [];

        foreach ((array) ($matches[1] ?? []) as $name) {
            $type = $parameterTypes[(string) $name] ?? (Str::contains((string) $name, 'id') ? 'int' : 'string');
            $parameters[] = [
                'name' => (string) $name,
                'in' => 'path',
                'required' => true,
                'schema' => [
                    'type' => $type === 'int' ? 'integer' : 'string',
                ],
            ];
        }

        return $parameters;
    }

    /**
     * @param  list<string>  $middleware
     * @return list<array<string,mixed>>
     */
    private function idempotencyParameters(array $middleware): array
    {
        foreach ($middleware as $item) {
            if (str_starts_with($item, 'idempotency:')) {
                return [[
                    'name' => 'Idempotency-Key',
                    'in' => 'header',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                    'description' => 'Canonical idempotency header. Compatibility aliases X-Idempotency-Key and body field idempotency_key are also accepted by the current implementation.',
                ]];
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $middleware
     * @param  array<string,mixed>  $metadata
     * @return array<string,mixed>
     */
    private function responsesForOperation(string $method, string $uri, array $middleware, array $metadata): array
    {
        $responses = [];
        $definitions = (array) ($metadata['responses'] ?? []);

        if ($definitions === []) {
            $status = $this->defaultSuccessStatus($method, $uri);
            $definitions = [$status => ['schema' => Str::startsWith($method.' '.$uri, 'GET api/v1') ? 'GenericDataEnvelope' : 'GenericDataEnvelope']];
        }

        foreach ($definitions as $status => $definition) {
            $status = (string) $status;
            $responses[$status] = [
                'description' => $this->responseDescription((int) $status),
            ];

            if (isset($definition['schema'])) {
                $responses[$status]['content'] = [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/'.(string) $definition['schema'],
                        ],
                    ],
                ];
            }

            if (in_array((int) $status, [200, 201], true) && $this->hasIdempotency($middleware)) {
                $responses[$status]['headers']['Idempotency-Replayed'] = [
                    'schema' => ['type' => 'string', 'enum' => ['true', 'false']],
                    'description' => 'Indicates whether the successful response was replayed from the idempotency cache.',
                ];
            }
        }

        return $responses;
    }

    /**
     * @param  list<string>  $middleware
     * @return list<array<string,string>>
     */
    private function inferSecurity(string $uri, array $middleware): array
    {
        if (str_starts_with($uri, 'api/v1/payments/providers/') || str_starts_with($uri, 'api/v1/health')) {
            return [];
        }

        if ($this->containsMiddleware($middleware, 'StaffApiKeyMiddleware')) {
            return [['StaffApiKey' => []]];
        }

        if ($this->containsMiddleware($middleware, 'CustomerOrStaffMiddleware')) {
            return [['CustomerAccessToken' => []], ['CustomerSessionId' => []], ['StaffApiKey' => []]];
        }

        if ($this->containsMiddleware($middleware, 'ResolveCustomerAuthMiddleware')) {
            return [['CustomerAccessToken' => []]];
        }

        return [];
    }

    /**
     * @param  list<string>  $middleware
     */
    private function inferAuthMode(string $uri, array $middleware): string
    {
        if (str_starts_with($uri, 'api/v1/payments/providers/') || str_starts_with($uri, 'api/v1/health')) {
            return 'public';
        }

        if ($this->containsMiddleware($middleware, 'StaffApiKeyMiddleware')) {
            return 'staff_api_key';
        }

        if ($this->containsMiddleware($middleware, 'CustomerOrStaffMiddleware')) {
            return 'customer_or_staff';
        }

        if ($this->containsMiddleware($middleware, 'ResolveCustomerAuthMiddleware')) {
            return 'customer_access_token';
        }

        return 'public';
    }

    /**
     * @param  list<string>  $middleware
     */
    private function containsMiddleware(array $middleware, string $needle): bool
    {
        foreach ($middleware as $item) {
            if (str_contains($item, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function hasIdempotency(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if (str_starts_with($item, 'idempotency:')) {
                return true;
            }
        }

        return false;
    }

    private function defaultSummary(string $method, string $uri): string
    {
        $segments = array_values(array_filter(explode('/', str_replace(['api/v1/', 'api/'], '', $uri))));
        $tail = implode(' ', array_slice($segments, -2));

        return trim($method.' '.Str::headline(str_replace(['{', '}', '-'], ['', '', ' '], $tail)));
    }

    private function defaultTag(string $uri): string
    {
        return match (true) {
            str_starts_with($uri, 'api/v1/auth/') => 'Auth',
            str_starts_with($uri, 'api/v1/waiting-list') => 'Waiting List',
            str_starts_with($uri, 'api/v1/payments/providers/') => 'Payment Webhooks',
            str_starts_with($uri, 'api/v1/staff/cashier/') => 'Staff Cashier',
            str_starts_with($uri, 'api/v1/staff/tables/') => 'Staff Tables',
            str_starts_with($uri, 'api/v1/admin/settings/') => 'Admin Settings',
            str_starts_with($uri, 'api/v1/reservations/') => 'Reservations',
            str_starts_with($uri, 'api/v1/health') => 'Health',
            str_starts_with($uri, 'api/user') => 'Legacy',
            default => 'Legacy',
        };
    }

    private function defaultSuccessStatus(string $method, string $uri): int
    {
        if ($method === 'POST' && (
            str_ends_with($uri, '/open')
            || str_ends_with($uri, '/payment-sessions')
            || $uri === 'api/v1/waiting-list'
            || $uri === 'api/v1/reservations'
            || $uri === 'api/v1/admin/settings/branches'
        )) {
            return 201;
        }

        return 200;
    }

    private function responseDescription(int $status): string
    {
        return match ($status) {
            200 => 'Successful response.',
            201 => 'Resource created successfully.',
            202 => 'Accepted for asynchronous processing.',
            401 => 'Authentication failed or required credentials were missing.',
            403 => 'Authenticated caller is not allowed to perform this operation.',
            404 => 'Requested resource was not found.',
            409 => 'Concurrency or state conflict detected.',
            422 => 'Request validation failed.',
            503 => 'Operational dependency check failed.',
            default => 'HTTP response.',
        };
    }

    /**
     * @param  array<string,mixed>  $paths
     * @return list<array<string,string>>
     */
    private function buildTags(array $paths): array
    {
        $tagDescriptions = $this->metadata->tagDescriptions();
        $used = [];

        foreach ($paths as $operations) {
            foreach ((array) $operations as $operation) {
                foreach ((array) ($operation['tags'] ?? []) as $tag) {
                    $used[(string) $tag] = true;
                }
            }
        }

        $tags = [];
        foreach (array_keys($used) as $tag) {
            $tags[] = [
                'name' => $tag,
                'description' => $tagDescriptions[$tag] ?? $tag,
            ];
        }

        usort($tags, static fn (array $left, array $right): int => strcmp((string) $left['name'], (string) $right['name']));

        return $tags;
    }

    /**
     * @return array<string,array<string,string>>
     */
    private function securitySchemes(): array
    {
        return [
            'CustomerAccessToken' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => (string) config('customer_auth.header', 'X-Customer-Token'),
                'description' => 'Opaque customer access session token. Some deployments also allow Authorization: Bearer as a compatibility mode.',
            ],
            'CustomerSessionId' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-Session-Id',
                'description' => 'Session-scoped customer self-service identifier. The current runtime also accepts session_id in query string or request body on selected routes.',
            ],
            'StaffApiKey' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-Staff-Key',
                'description' => 'Opaque staff API key used for staff and admin operational endpoints.',
            ],
        ];
    }
}

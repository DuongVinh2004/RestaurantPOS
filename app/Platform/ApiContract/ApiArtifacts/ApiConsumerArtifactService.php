<?php

declare(strict_types=1);

namespace App\Platform\ApiContract\ApiArtifacts;

use App\Platform\ApiContract\Services\OpenApiSpecService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ApiConsumerArtifactService
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private array $componentSchemas = [];

    public function __construct(
        private readonly OpenApiSpecService $openApiSpec,
        private readonly ApiEnumStateArtifactService $enumStateArtifacts,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function generate(
        ?string $outputRoot = null,
        ?string $specPath = null,
        bool $refreshOpenApi = false,
        ?string $uatManifestPath = null,
    ): array {
        $outputRoot = $this->normalizeRelativePath($outputRoot ?: (string) config('api_artifacts.output_root', 'build/api-consumer'));
        $specRelativePath = $this->normalizeRelativePath($specPath ?: (string) config('api_artifacts.source_openapi_path', 'storage/app/booking_release/openapi-v1.json'));
        $spec = $this->loadSpec($specRelativePath, $refreshOpenApi);
        $contractReport = $this->openApiSpec->report($spec);
        $this->componentSchemas = (array) ($spec['components']['schemas'] ?? []);
        $operationsBySignature = $this->operationsBySignature($spec);
        $curatedGroups = (array) config('api_artifacts.postman.groups', []);
        $selectedGroupOperations = $this->resolveCuratedGroups($curatedGroups, $operationsBySignature);
        $selectedSignatures = collect($selectedGroupOperations)
            ->flatMap(static fn (array $group): array => array_keys((array) ($group['operations'] ?? [])))
            ->unique()
            ->values()
            ->all();
        $mutationContractGroups = $this->resolveCuratedGroups((array) config('api_artifacts.mutation_contract.groups', []), $operationsBySignature);
        $referenceOperations = $this->resolveReferenceOperations($operationsBySignature, $selectedSignatures);

        $collection = $this->buildPostmanCollection($selectedGroupOperations, $referenceOperations);
        $localEnvironment = $this->buildEnvironmentPayload('local', null, $selectedSignatures, $specRelativePath);
        $stagingEnvironment = $this->buildEnvironmentPayload('staging', null, $selectedSignatures, $specRelativePath);

        $resolvedManifestPath = $this->resolveManifestPath($uatManifestPath);
        $manifest = $resolvedManifestPath !== null ? $this->readManifest($resolvedManifestPath) : null;
        $uatEnvironment = $manifest !== null
            ? $this->buildEnvironmentPayload('uat', $manifest, $selectedSignatures, $specRelativePath)
            : null;

        $sdkOperations = [];
        foreach ($selectedSignatures as $signature) {
            $sdkOperations[$signature] = $operationsBySignature[$signature];
        }

        $sdkSource = $this->buildTypeScriptSdk($sdkOperations, (array) ($spec['components']['schemas'] ?? []), $specRelativePath);
        $sdkReadme = $this->buildSdkReadme($specRelativePath, $selectedGroupOperations);
        $mutationContractReadme = $this->buildMutationContractReadme($specRelativePath, $mutationContractGroups, $selectedSignatures);
        $enumArtifacts = $this->enumStateArtifacts->generate($outputRoot);

        $written = [
            'collection' => $this->writeArtifact($outputRoot, (string) config('api_artifacts.postman.collection'), $collection),
            'local_environment' => $this->writeArtifact($outputRoot, (string) config('api_artifacts.postman.local_template'), $localEnvironment),
            'staging_environment' => $this->writeArtifact($outputRoot, (string) config('api_artifacts.postman.staging_template'), $stagingEnvironment),
            'sdk_typescript' => $this->writeArtifact($outputRoot, (string) config('api_artifacts.sdk.typescript'), $sdkSource),
            'sdk_readme' => $this->writeArtifact($outputRoot, (string) config('api_artifacts.sdk.readme'), $sdkReadme),
            'mutation_contract' => $this->writeArtifact($outputRoot, (string) config('api_artifacts.mutation_contract.readme'), $mutationContractReadme),
        ];
        $written = array_merge($written, (array) ($enumArtifacts['artifacts'] ?? []));

        if ($uatEnvironment !== null) {
            $written['uat_environment'] = $this->writeArtifact($outputRoot, (string) config('api_artifacts.postman.uat_environment'), $uatEnvironment);
        }

        return [
            'ok' => true,
            'spec_path' => $specRelativePath,
            'output_root' => $outputRoot,
            'uat_manifest_path' => $resolvedManifestPath,
            'artifacts' => $written,
            'contract_report_summary' => (array) ($contractReport['summary'] ?? []),
            'summary' => [
                'path_count' => (int) (($contractReport['summary']['path_count'] ?? 0)),
                'full_contract_operation_count' => (int) (($contractReport['summary']['full_contract_operation_count'] ?? 0)),
                'fallback_operation_count' => (int) (($contractReport['summary']['fallback_operation_count'] ?? 0)),
                'curated_group_count' => count($selectedGroupOperations),
                'curated_operation_count' => count($selectedSignatures),
                'reference_operation_count' => count($referenceOperations),
                'sdk_operation_count' => count($sdkOperations),
                'mutation_contract_group_count' => count($mutationContractGroups),
                'enum_group_count' => (int) (($enumArtifacts['summary']['enum_group_count'] ?? 0)),
                'enum_value_count' => (int) (($enumArtifacts['summary']['enum_value_count'] ?? 0)),
                'uat_environment_generated' => $uatEnvironment !== null,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadSpec(string $specRelativePath, bool $refreshOpenApi): array
    {
        $specAbsolutePath = base_path($specRelativePath);

        if ($refreshOpenApi || ! File::exists($specAbsolutePath)) {
            /** @var array{spec:array<string,mixed>} $export */
            $export = $this->openApiSpec->export($specRelativePath);

            return (array) ($export['spec'] ?? []);
        }

        /** @var array<string,mixed> $spec */
        $spec = json_decode((string) File::get($specAbsolutePath), true, 512, JSON_THROW_ON_ERROR);

        return $spec;
    }

    /**
     * @param  array<string,mixed>  $spec
     * @return array<string,array<string,mixed>>
     */
    private function operationsBySignature(array $spec): array
    {
        $operations = [];

        foreach ((array) ($spec['paths'] ?? []) as $path => $pathOperations) {
            foreach ((array) $pathOperations as $method => $operation) {
                $signature = strtoupper((string) $method).' '.ltrim((string) $path, '/');
                $operations[$signature] = [
                    'signature' => $signature,
                    'method' => strtoupper((string) $method),
                    'path' => (string) $path,
                    'operation' => (array) $operation,
                ];
            }
        }

        ksort($operations);

        return $operations;
    }

    /**
     * @param  list<array{name:string,signatures:list<string>}>  $groupDefinitions
     * @param  array<string,array<string,mixed>>  $operationsBySignature
     * @return list<array{name:string,operations:array<string,array<string,mixed>>}>
     */
    private function resolveCuratedGroups(array $groupDefinitions, array $operationsBySignature): array
    {
        $groups = [];

        foreach ($groupDefinitions as $groupDefinition) {
            $resolved = [];

            foreach ((array) ($groupDefinition['signatures'] ?? []) as $signature) {
                if (! array_key_exists($signature, $operationsBySignature)) {
                    throw new RuntimeException(sprintf('Configured API artifact signature [%s] was not found in the frozen OpenAPI artifact.', $signature));
                }

                $resolved[$signature] = $operationsBySignature[$signature];
            }

            $groups[] = [
                'name' => (string) ($groupDefinition['name'] ?? 'Group'),
                'operations' => $resolved,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string,array<string,mixed>>  $operationsBySignature
     * @param  list<string>  $selectedSignatures
     * @return array<string,array<string,mixed>>
     */
    private function resolveReferenceOperations(array $operationsBySignature, array $selectedSignatures): array
    {
        if (! (bool) config('api_artifacts.postman.include_full_contract_reference', true)) {
            return [];
        }

        $selectedLookup = array_fill_keys($selectedSignatures, true);
        $reference = [];

        foreach ($operationsBySignature as $signature => $entry) {
            $operation = (array) ($entry['operation'] ?? []);

            if (($operation['deprecated'] ?? false) === true) {
                continue;
            }

            if (($operation['x-contract-grade'] ?? 'fallback') !== 'full') {
                continue;
            }

            if (array_key_exists($signature, $selectedLookup)) {
                continue;
            }

            $reference[$signature] = $entry;
        }

        uasort($reference, function (array $left, array $right): int {
            $leftTag = (string) (($left['operation']['tags'][0] ?? 'Reference'));
            $rightTag = (string) (($right['operation']['tags'][0] ?? 'Reference'));
            $tagCompare = strcmp($leftTag, $rightTag);

            if ($tagCompare !== 0) {
                return $tagCompare;
            }

            $leftSummary = (string) ($left['operation']['summary'] ?? $left['signature']);
            $rightSummary = (string) ($right['operation']['summary'] ?? $right['signature']);

            return strcmp($leftSummary, $rightSummary);
        });

        return $reference;
    }

    /**
     * @param  list<array{name:string,operations:array<string,array<string,mixed>>}>  $curatedGroups
     * @param  array<string,array<string,mixed>>  $referenceOperations
     * @return array<string,mixed>
     */
    private function buildPostmanCollection(array $curatedGroups, array $referenceOperations): array
    {
        $items = [];

        foreach ($curatedGroups as $group) {
            $groupItems = [];

            foreach ((array) ($group['operations'] ?? []) as $signature => $entry) {
                $groupItems[] = $this->buildPostmanRequestItem((string) $signature, $entry);
            }

            $items[] = [
                'name' => (string) ($group['name'] ?? 'Group'),
                'item' => $groupItems,
            ];
        }

        if ($referenceOperations !== []) {
            $referenceFolders = [];

            foreach ($this->groupReferenceOperationsByTag($referenceOperations) as $tag => $entries) {
                $tagItems = [];
                foreach ($entries as $signature => $entry) {
                    $tagItems[] = $this->buildPostmanRequestItem((string) $signature, $entry);
                }

                $referenceFolders[] = [
                    'name' => (string) $tag,
                    'item' => $tagItems,
                ];
            }

            $items[] = [
                'name' => (string) config('api_artifacts.postman.reference_folder_name', 'Reference'),
                'item' => $referenceFolders,
            ];
        }

        return [
            'info' => [
                'name' => 'RestaurantPOS API Consumer Collection',
                '_postman_id' => $this->deterministicPostmanUuid('collection:restaurantpos-api-consumer'),
                'description' => 'Generated from the frozen OpenAPI artifact. Use the TypeScript SDK for curated priority routes, use the Reference folder or frozen OpenAPI for other full-contract routes, and do not treat controllers/resources as the consumer contract. Regenerate with composer api:artifacts or use php artisan booking:release-build for the full release chain.',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'variable' => [
                [
                    'key' => 'baseUrl',
                    'value' => (string) config('api_artifacts.environments.local.baseUrl', 'http://127.0.0.1:8000'),
                    'type' => 'string',
                ],
            ],
            'item' => $items,
        ];
    }

    /**
     * @param  array<string,array<string,mixed>>  $referenceOperations
     * @return array<string,array<string,array<string,mixed>>>
     */
    private function groupReferenceOperationsByTag(array $referenceOperations): array
    {
        $grouped = [];

        foreach ($referenceOperations as $signature => $entry) {
            $tag = (string) (($entry['operation']['tags'][0] ?? 'Reference'));
            $grouped[$tag][$signature] = $entry;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return array<string,mixed>
     */
    private function buildPostmanRequestItem(string $signature, array $entry): array
    {
        $operation = (array) ($entry['operation'] ?? []);

        $item = [
            'name' => (string) ($operation['summary'] ?? $signature),
            'request' => [
                'method' => (string) ($entry['method'] ?? 'GET'),
                'header' => $this->buildPostmanHeaders($signature, $entry),
                'url' => $this->buildPostmanUrl($signature, $entry),
                'description' => $this->buildRequestDescription($signature, $entry),
            ],
            'response' => [],
        ];

        $body = $this->buildPostmanBody($signature, $entry);
        if ($body !== null) {
            $item['request']['body'] = $body;
        }

        $events = $this->buildPostmanEvents($signature, $entry);
        if ($events !== []) {
            $item['event'] = $events;
        }

        return $item;
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return list<array<string,mixed>>
     */
    private function buildPostmanHeaders(string $signature, array $entry): array
    {
        $headers = [
            [
                'key' => 'Accept',
                'value' => 'application/json',
                'type' => 'text',
            ],
        ];
        $operation = (array) ($entry['operation'] ?? []);
        $path = (string) ($entry['path'] ?? '');
        $authMode = (string) ($operation['x-auth-mode'] ?? 'public');
        $supportsCustomerSession = $this->operationUsesCustomerSession($operation);

        if ($this->operationHasJsonBody($operation)) {
            $headers[] = [
                'key' => 'Content-Type',
                'value' => 'application/json',
                'type' => 'text',
            ];
        }

        if ($authMode === 'customer_access_token') {
            $headers[] = $this->enabledHeader('X-Customer-Token', '{{customerToken}}');
            if ($supportsCustomerSession) {
                $headers[] = $this->enabledHeader('X-Session-Id', '{{customerSessionId}}');
            }
        } elseif ($authMode === 'customer_or_session') {
            $headers[] = $this->enabledHeader('X-Customer-Token', '{{customerToken}}');
            $headers[] = $supportsCustomerSession
                ? $this->enabledHeader('X-Session-Id', '{{customerSessionId}}')
                : $this->disabledHeader('X-Session-Id', '{{customerSessionId}}');
        } elseif ($authMode === 'staff_api_key') {
            $headers[] = $this->enabledHeader('X-Staff-Key', str_starts_with($path, '/api/v1/admin/') ? '{{adminApiKey}}' : '{{staffApiKey}}');
        } elseif ($authMode === 'customer_or_staff') {
            $headers[] = $this->enabledHeader('X-Customer-Token', '{{customerToken}}');
            $headers[] = $supportsCustomerSession
                ? $this->enabledHeader('X-Session-Id', '{{customerSessionId}}')
                : $this->disabledHeader('X-Session-Id', '{{customerSessionId}}');
            $headers[] = $this->disabledHeader('X-Staff-Key', '{{staffApiKey}}');
        }

        foreach ((array) ($operation['parameters'] ?? []) as $parameter) {
            if (($parameter['in'] ?? null) !== 'header') {
                continue;
            }

            $name = (string) ($parameter['name'] ?? '');
            if ($name === 'Idempotency-Key') {
                $headers[] = $this->enabledHeader('Idempotency-Key', 'restaurantpos-{{$guid}}');

                continue;
            }

            if ($name === 'X-Payment-Signature') {
                $headers[] = $this->enabledHeader('X-Payment-Signature', '{{paymentWebhookSignature}}');

                continue;
            }

            if ($name === 'X-Payment-Timestamp') {
                $headers[] = $this->disabledHeader('X-Payment-Timestamp', '{{paymentWebhookOccurredAt}}');
            }
        }

        return $headers;
    }

    /**
     * @return array<string,mixed>
     */
    private function enabledHeader(string $key, string $value): array
    {
        return [
            'key' => $key,
            'value' => $value,
            'type' => 'text',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function disabledHeader(string $key, string $value): array
    {
        return [
            'key' => $key,
            'value' => $value,
            'type' => 'text',
            'disabled' => true,
        ];
    }

    /**
     * @param  array<string,mixed>  $entry
     */
    private function buildPostmanUrl(string $signature, array $entry): string
    {
        $path = (string) ($entry['path'] ?? '/');
        $resolvedPath = preg_replace_callback('/\{([^}]+)\}/', function (array $matches) use ($signature, $path): string {
            $parameterName = (string) ($matches[1] ?? '');

            return '{{'.$this->resolveVariableAlias($signature, $path, $parameterName, 'path').'}}';
        }, $path);

        $queryPairs = [];
        foreach ((array) (($entry['operation']['parameters'] ?? [])) as $parameter) {
            if (($parameter['in'] ?? null) !== 'query') {
                continue;
            }

            $parameterName = (string) ($parameter['name'] ?? '');
            $alias = $this->resolveVariableAlias($signature, $path, $parameterName, 'query');
            $queryPairs[] = rawurlencode($parameterName).'={{'.$alias.'}}';
        }

        if ($queryPairs !== []) {
            return '{{baseUrl}}'.$resolvedPath.'?'.implode('&', $queryPairs);
        }

        return '{{baseUrl}}'.$resolvedPath;
    }

    /**
     * @param  array<string,mixed>  $entry
     */
    private function buildRequestDescription(string $signature, array $entry): string
    {
        $operation = (array) ($entry['operation'] ?? []);
        $lines = [
            (string) ($operation['description'] ?? 'Generated from the frozen OpenAPI artifact.'),
            '',
            sprintf('Signature: `%s`', $signature),
            sprintf('Auth mode: `%s`', (string) ($operation['x-auth-mode'] ?? 'public')),
            sprintf('Contract grade: `%s`', (string) ($operation['x-contract-grade'] ?? 'fallback')),
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return array<string,mixed>|null
     */
    private function buildPostmanBody(string $signature, array $entry): ?array
    {
        $operation = (array) ($entry['operation'] ?? []);
        $schema = $this->requestBodySchema($operation);

        if ($schema === null) {
            return null;
        }

        $context = ['source_spec' => ['components' => ['schemas' => $this->componentSchemas]]];
        $example = $this->requestBodyExample($operation);
        if ($example === null) {
            $example = $this->exampleFromSchema($schema, $context);
        }

        $example = $this->applyBodyOverrides($signature, $example);

        return [
            'mode' => 'raw',
            'raw' => json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'options' => [
                'raw' => [
                    'language' => 'json',
                ],
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $operation
     * @return array<string,mixed>|null
     */
    private function requestBodySchema(array $operation): ?array
    {
        return Arr::get($operation, 'requestBody.content.application/json.schema');
    }

    /**
     * @param  array<string,mixed>  $operation
     * @return array<string,mixed>|null
     */
    private function resolvedRequestBodySchema(array $operation): ?array
    {
        $schema = $this->requestBodySchema($operation);

        if ($schema === null) {
            return null;
        }

        return $this->resolveComponentSchema((array) $schema);
    }

    /**
     * @param  array<string,mixed>  $operation
     * @return array<string,mixed>|list<mixed>|string|int|float|bool|null
     */
    private function requestBodyExample(array $operation): mixed
    {
        return Arr::get($operation, 'requestBody.content.application/json.example');
    }

    /**
     * @param  array<string,mixed>|list<mixed>|string|int|float|bool|null  $example
     * @return array<string,mixed>|list<mixed>|string|int|float|bool|null
     */
    private function applyBodyOverrides(string $signature, mixed $example): mixed
    {
        $overrides = (array) config("api_artifacts.postman.body_overrides.$signature", []);
        if ($overrides === []) {
            return $example;
        }

        $result = $example;
        if (! is_array($result)) {
            $result = [];
        }

        foreach ($overrides as $path => $value) {
            Arr::set($result, (string) $path, $value);
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $schema
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>|list<mixed>|string|int|float|bool|null
     */
    private function exampleFromSchema(array $schema, array $context): mixed
    {
        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }

        if (isset($schema['$ref'])) {
            $refName = $this->refName((string) $schema['$ref']);
            /** @var array<string,mixed> $refSchema */
            $refSchema = (array) data_get($context, 'source_spec.components.schemas.'.$refName, []);
            if ($refSchema !== []) {
                return $this->exampleFromSchema($refSchema, $context);
            }

            return null;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && $schema['enum'] !== []) {
            return $schema['enum'][0];
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            $merged = [];
            foreach ($schema['allOf'] as $child) {
                $childExample = $this->exampleFromSchema((array) $child, $context);
                if (is_array($merged) && is_array($childExample)) {
                    $merged = array_replace_recursive($merged, $childExample);
                }
            }

            return $merged;
        }

        if (isset($schema['oneOf']) && is_array($schema['oneOf']) && $schema['oneOf'] !== []) {
            return $this->exampleFromSchema((array) $schema['oneOf'][0], $context);
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf']) && $schema['anyOf'] !== []) {
            return $this->exampleFromSchema((array) $schema['anyOf'][0], $context);
        }

        $type = (string) ($schema['type'] ?? 'object');

        return match ($type) {
            'object' => $this->objectExampleFromSchema($schema, $context),
            'array' => [$this->exampleFromSchema((array) ($schema['items'] ?? []), $context)],
            'integer' => 1,
            'number' => 1,
            'boolean' => false,
            'string' => $this->stringExampleFromSchema($schema),
            default => null,
        };
    }

    /**
     * @param  array<string,mixed>  $schema
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function objectExampleFromSchema(array $schema, array $context): array
    {
        $properties = (array) ($schema['properties'] ?? []);
        $required = array_map('strval', (array) ($schema['required'] ?? []));
        $result = [];

        foreach ($properties as $name => $propertySchema) {
            if ($required !== [] && ! in_array((string) $name, $required, true) && count($properties) > 5) {
                continue;
            }

            $result[(string) $name] = $this->exampleFromSchema((array) $propertySchema, $context);
        }

        return $result;
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function stringExampleFromSchema(array $schema): string
    {
        $format = (string) ($schema['format'] ?? '');

        return match ($format) {
            'date-time' => '2026-04-05T10:05:00Z',
            'date' => '2026-04-05',
            'email' => 'demo@example.test',
            default => 'example',
        };
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return list<array<string,mixed>>
     */
    private function buildPostmanEvents(string $signature, array $entry): array
    {
        $events = [];

        if ($signature === 'POST api/v1/payments/providers/{provider_code}/webhooks') {
            $events[] = [
                'listen' => 'prerequest',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => $this->webhookPreRequestScript(),
                ],
            ];
        }

        $captures = (array) config("api_artifacts.postman.capture_variables.$signature", []);
        if ($captures !== []) {
            $events[] = [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => $this->captureScript($captures),
                ],
            ];
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    private function webhookPreRequestScript(): array
    {
        return [
            "const secret = pm.environment.get('paymentWebhookSecret');",
            'if (!secret) {',
            "  console.warn('paymentWebhookSecret is empty; signature will not be recomputed.');",
            '} else {',
            "  const resolvedBody = pm.variables.replaceIn(pm.request.body?.raw ?? '');",
            '  const signature = CryptoJS.HmacSHA256(resolvedBody, secret).toString();',
            "  pm.environment.set('paymentWebhookSignature', signature);",
            "  pm.request.headers.upsert({ key: 'X-Payment-Signature', value: signature });",
            '}',
            "const timestamp = pm.environment.get('paymentWebhookOccurredAt');",
            'if (timestamp) {',
            "  pm.request.headers.upsert({ key: 'X-Payment-Timestamp', value: timestamp });",
            '}',
        ];
    }

    /**
     * @param  array<string,string>  $captures
     * @return list<string>
     */
    private function captureScript(array $captures): array
    {
        $payload = json_encode($captures, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'let json = null;',
            'try {',
            '  json = pm.response.json();',
            '} catch (error) {',
            '  console.warn("Response is not JSON; skipping capture.", error);',
            '}',
            'if (!json) {',
            '  return;',
            '}',
            'const readPath = (source, path) => path.split(".").reduce((carry, segment) => carry == null ? undefined : carry[segment], source);',
            'const captures = '.$payload.';',
            'Object.entries(captures).forEach(([key, path]) => {',
            '  const value = readPath(json, path);',
            '  if (value !== undefined && value !== null) {',
            '    pm.environment.set(key, String(value));',
            '  }',
            '});',
        ];
    }

    private function operationHasJsonBody(array $operation): bool
    {
        return Arr::has($operation, 'requestBody.content.application/json');
    }

    private function resolveManifestPath(?string $manifestPath): ?string
    {
        $resolved = trim((string) ($manifestPath ?? ''));
        if ($resolved === '') {
            $default = $this->normalizeRelativePath((string) config('api_artifacts.uat_manifest_path', 'storage/app/uat/scenario-pack.json'));
            $absolute = base_path($default);

            return File::exists($absolute) ? $default : null;
        }

        $relative = $this->normalizeRelativePath($resolved);
        if (! File::exists(base_path($relative))) {
            throw new RuntimeException(sprintf('UAT manifest [%s] was not found.', $relative));
        }

        return $relative;
    }

    /**
     * @return array<string,mixed>
     */
    private function readManifest(string $relativePath): array
    {
        /** @var array<string,mixed> $manifest */
        $manifest = json_decode((string) File::get(base_path($relativePath)), true, 512, JSON_THROW_ON_ERROR);

        return $manifest;
    }

    /**
     * @param  list<string>  $selectedSignatures
     * @return array<string,mixed>
     */
    private function buildEnvironmentPayload(string $environmentName, ?array $manifest, array $selectedSignatures, string $specRelativePath): array
    {
        $defaults = (array) config("api_artifacts.environments.$environmentName", []);
        $variables = $defaults;

        foreach ($this->discoveredEnvironmentKeys($selectedSignatures) as $key) {
            if (! array_key_exists($key, $variables)) {
                $variables[$key] = '';
            }
        }

        if ($manifest !== null) {
            $variables = array_replace($variables, $this->manifestEnvironmentValues($manifest));
        }

        ksort($variables);

        $displayName = $environmentName === 'uat'
            ? 'UAT'
            : Str::headline($environmentName);

        return [
            'id' => $this->deterministicPostmanUuid('environment:'.$environmentName),
            'name' => sprintf('RestaurantPOS %s', $displayName),
            'values' => collect($variables)
                ->map(fn (mixed $value, string $key): array => [
                    'key' => $key,
                    'value' => $value,
                    'type' => 'text',
                    'enabled' => true,
                ])
                ->values()
                ->all(),
            '_postman_variable_scope' => 'environment',
            '_postman_exported_using' => sprintf('RestaurantPOS API artifact generator (%s)', $specRelativePath),
        ];
    }

    private function deterministicPostmanUuid(string $seed): string
    {
        $hex = substr(hash('sha256', $seed), 0, 32);
        $timeHigh = str_pad(dechex((hexdec(substr($hex, 12, 4)) & 0x0FFF) | 0x5000), 4, '0', STR_PAD_LEFT);
        $clockSeq = str_pad(dechex((hexdec(substr($hex, 16, 4)) & 0x3FFF) | 0x8000), 4, '0', STR_PAD_LEFT);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            $timeHigh,
            $clockSeq,
            substr($hex, 20, 12),
        );
    }

    /**
     * @param  list<string>  $selectedSignatures
     * @return list<string>
     */
    private function discoveredEnvironmentKeys(array $selectedSignatures): array
    {
        $keys = [
            'paymentWebhookSignature',
        ];

        foreach ($selectedSignatures as $signature) {
            $aliases = (array) config("api_artifacts.postman.parameter_aliases.$signature", []);

            foreach (['path', 'query'] as $scope) {
                foreach ((array) ($aliases[$scope] ?? []) as $alias) {
                    $keys[] = (string) $alias;
                }
            }

            foreach (array_keys((array) config("api_artifacts.postman.capture_variables.$signature", [])) as $captureKey) {
                $keys[] = (string) $captureKey;
            }

            foreach ((array) config("api_artifacts.postman.body_overrides.$signature", []) as $value) {
                if (is_string($value) && preg_match_all('/\{\{([^}]+)\}\}/', $value, $matches)) {
                    foreach ((array) ($matches[1] ?? []) as $match) {
                        $keys[] = (string) $match;
                    }
                }
            }
        }

        $keys = array_values(array_unique(array_map('strval', $keys)));
        sort($keys);

        return $keys;
    }

    /**
     * @param  array<string,mixed>  $manifest
     * @return array<string,string|int|float|null>
     */
    private function manifestEnvironmentValues(array $manifest): array
    {
        return array_filter([
            'baseUrl' => data_get($manifest, 'pack.base_url'),
            'branchId' => data_get($manifest, 'branch.branch_id'),
            'currency' => data_get($manifest, 'branch.currency'),
            'customerUsername' => data_get($manifest, 'auth.customer_primary.username'),
            'customerPassword' => data_get($manifest, 'auth.customer_primary.password'),
            'staffUsername' => data_get($manifest, 'auth.staff.username'),
            'staffPassword' => data_get($manifest, 'auth.staff.password'),
            'staffApiKey' => data_get($manifest, 'auth.staff.api_key'),
            'adminUsername' => data_get($manifest, 'auth.admin.username'),
            'adminPassword' => data_get($manifest, 'auth.admin.password'),
            'adminApiKey' => data_get($manifest, 'auth.admin.api_key'),
            'customerSessionId' => data_get($manifest, 'scenarios.availability_hold_reservation.session_id'),
            'availabilityFromUtc' => data_get($manifest, 'scenarios.availability_hold_reservation.from_utc'),
            'availabilityToUtc' => data_get($manifest, 'scenarios.availability_hold_reservation.to_utc'),
            'guestCount' => data_get($manifest, 'scenarios.availability_hold_reservation.guest_count'),
            'preferredTableId' => data_get($manifest, 'scenarios.availability_hold_reservation.preferred_table_ids.0'),
            'reservationId' => data_get($manifest, 'scenarios.deposit_self_pay.reservation_id'),
            'reservationIdDeposit' => data_get($manifest, 'scenarios.deposit_self_pay.reservation_id'),
            'reservationIdDineIn' => data_get($manifest, 'scenarios.dine_in_checkout.reservation_id'),
            'reservationIdBenefits' => data_get($manifest, 'scenarios.benefits.reservation_id'),
            'reservationIdRefund' => data_get($manifest, 'scenarios.refund_partial.reservation_id'),
            'reservationIdRefundCancel' => data_get($manifest, 'scenarios.refund_cancel.reservation_id'),
            'reservationRowVersionDeposit' => data_get($manifest, 'reservations.deposit_pending.row_version'),
            'reservationRowVersionDineIn' => data_get($manifest, 'reservations.dine_in_checkin.row_version'),
            'reservationRowVersionBenefits' => data_get($manifest, 'reservations.benefits_pending.row_version'),
            'reservationRowVersionRefund' => data_get($manifest, 'reservations.refund_partial_ready.row_version'),
            'reservationRowVersionRefundCancel' => data_get($manifest, 'reservations.refund_cancel_ready.row_version'),
            'paymentAmount' => data_get($manifest, 'scenarios.deposit_self_pay.payment_amount'),
            'providerCode' => data_get($manifest, 'scenarios.deposit_self_pay.provider_code'),
            'dineInTableId' => data_get($manifest, 'scenarios.dine_in_checkout.table_id'),
            'tableId' => data_get($manifest, 'scenarios.dine_in_checkout.table_id'),
            'menuItemIdPrimary' => data_get($manifest, 'scenarios.dine_in_checkout.menu_item_ids.0'),
            'menuItemIdSecondary' => data_get($manifest, 'scenarios.dine_in_checkout.menu_item_ids.1'),
            'refundAmount' => data_get($manifest, 'scenarios.refund_partial.refund_amount'),
            'refundScope' => data_get($manifest, 'scenarios.refund_partial.refund_scope'),
            'tableTemplateId' => data_get($manifest, 'scenarios.admin_master_data.template_id'),
            'conversationId' => data_get($manifest, 'scenarios.conversation_inbox.conversation_id'),
            'userVoucherId' => data_get($manifest, 'scenarios.benefits.user_voucher_id'),
            'loyaltyPoints' => data_get($manifest, 'scenarios.benefits.loyalty_points'),
            'customerUserIdSecondary' => data_get($manifest, 'scenarios.waiting_list_lifecycle.customer_user_id'),
            'checkedInAt' => now('UTC')->toIso8601String(),
            'paymentWebhookOccurredAt' => now('UTC')->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function resolveVariableAlias(string $signature, string $path, string $parameterName, string $scope): string
    {
        $configured = config("api_artifacts.postman.parameter_aliases.$signature.$scope.$parameterName");
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if ($parameterName === 'id') {
            return $this->implicitIdAlias($path);
        }

        return Str::camel((string) preg_replace('/[^a-z0-9]+/i', '_', $parameterName));
    }

    private function implicitIdAlias(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $previous = 'resource';

        for ($index = count($segments) - 1; $index >= 0; $index--) {
            $segment = (string) ($segments[$index] ?? '');
            if ($segment === '{id}') {
                $previous = (string) ($segments[$index - 1] ?? 'resource');
                break;
            }
        }

        return Str::camel(Str::singular(str_replace('-', ' ', $previous)).' id');
    }

    private function normalizeRelativePath(string $path): string
    {
        return str_replace('\\', '/', ltrim($path, '/\\'));
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function writeArtifact(string $outputRoot, string $relativePath, array|string $payload): string
    {
        $relativeOutputPath = $this->normalizeRelativePath($outputRoot.'/'.ltrim($relativePath, '/\\'));
        $absolutePath = base_path($relativeOutputPath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put(
            $absolutePath,
            is_array($payload)
                ? json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : $payload
        );

        return $relativeOutputPath;
    }

    /**
     * @param  array<string,array<string,mixed>>  $sdkOperations
     * @param  array<string,array<string,mixed>>  $componentSchemas
     */
    private function buildTypeScriptSdk(array $sdkOperations, array $componentSchemas, string $specRelativePath): string
    {
        $inlineDefinitions = [];
        $referencedComponentNames = [];
        $methods = [];

        foreach ($sdkOperations as $entry) {
            $operation = (array) ($entry['operation'] ?? []);
            $operationId = (string) ($operation['operationId'] ?? Str::snake((string) ($entry['signature'] ?? 'operation')));
            $studlyName = Str::studly($operationId);
            $methodName = Str::camel($operationId);
            $path = (string) ($entry['path'] ?? '/');
            $method = (string) ($entry['method'] ?? 'GET');
            $pathParameters = $this->operationParametersByLocation($operation, 'path');
            $queryParameters = $this->operationParametersByLocation($operation, 'query');

            $pathTypeName = null;
            if ($pathParameters !== []) {
                $pathTypeName = $studlyName.'PathParams';
                $inlineDefinitions[$pathTypeName] = $this->parameterObjectSchema($pathParameters);
            }

            $queryTypeName = null;
            if ($queryParameters !== []) {
                $queryTypeName = $studlyName.'QueryParams';
                $inlineDefinitions[$queryTypeName] = $this->parameterObjectSchema($queryParameters);
            }

            $bodyTypeName = null;
            $bodySchema = $this->requestBodySchema($operation);
            if (is_array($bodySchema)) {
                $bodyTypeName = $this->typeNameForSchema($bodySchema, $studlyName.'Body', $inlineDefinitions, $referencedComponentNames);
            }

            $responseTypeName = 'unknown';
            $successSchema = $this->successResponseSchema($operation);
            if (is_array($successSchema)) {
                $responseTypeName = $this->typeNameForSchema($successSchema, $studlyName.'Response', $inlineDefinitions, $referencedComponentNames);
            }

            $methods[] = [
                'method_name' => $methodName,
                'method' => $method,
                'path' => $path,
                'auth_mode' => (string) ($operation['x-auth-mode'] ?? 'public'),
                'supports_customer_session' => $this->operationUsesCustomerSession($operation),
                'requires_idempotency' => $this->operationRequiresIdempotency($operation),
                'path_type' => $pathTypeName,
                'query_type' => $queryTypeName,
                'body_type' => $bodyTypeName,
                'response_type' => $responseTypeName,
            ];
        }

        $definitions = $this->expandDefinitions($inlineDefinitions, $componentSchemas, $referencedComponentNames);
        $typeBlocks = [];
        foreach ($definitions as $name => $schema) {
            $typeBlocks[] = sprintf('export type %s = %s;', $name, $this->tsTypeFromSchema($schema, $componentSchemas));
        }

        $methodBlocks = [];
        foreach ($methods as $methodDefinition) {
            $methodBlocks[] = $this->buildSdkMethod($methodDefinition);
        }

        $typeSection = implode("\n\n", $typeBlocks);
        $methodSection = implode("\n\n", $methodBlocks);

        return <<<TS
/* Generated from {$specRelativePath}. Do not edit by hand. */

export type AuthMode = 'auto' | 'none' | 'customer' | 'staff' | 'session' | 'customerOrSession';

export interface RestaurantPosClientOptions {
  baseUrl: string;
  fetchImpl?: typeof fetch;
  customerToken?: string | (() => string | null | undefined);
  staffApiKey?: string | (() => string | null | undefined);
  customerSessionId?: string | (() => string | null | undefined);
  defaultHeaders?: Record<string, string>;
}

export interface RequestOptions {
  headers?: Record<string, string>;
  signal?: AbortSignal;
  authMode?: AuthMode;
  idempotencyKey?: string;
}

export class RestaurantPosApiError<T = unknown> extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly payload: T,
  ) {
    super(message);
    this.name = 'RestaurantPosApiError';
  }
}

{$typeSection}

export class RestaurantPosClient {
  private readonly fetchImpl: typeof fetch;

  constructor(private readonly options: RestaurantPosClientOptions) {
    if (typeof options.fetchImpl === 'function') {
      const providedFetch = options.fetchImpl;
      this.fetchImpl = providedFetch === globalThis.fetch
        ? globalThis.fetch.bind(globalThis)
        : ((input, init) => providedFetch(input, init)) as typeof fetch;
      return;
    }

    if (typeof globalThis.fetch !== 'function') {
      throw new Error('RestaurantPosClient requires a fetch implementation.');
    }

    this.fetchImpl = globalThis.fetch.bind(globalThis);
  }

{$methodSection}

  private async request<T>(
    method: string,
    path: string,
    authMode: AuthMode,
    routeSupportsCustomerSession: boolean,
    requiresIdempotency: boolean,
    query?: Record<string, unknown>,
    body?: unknown,
    options: RequestOptions = {},
  ): Promise<T> {
    const baseUrl = this.options.baseUrl.replace(/\/+$/, '');
    const url = new URL(baseUrl + path);

    if (query) {
      Object.entries(query).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
          return;
        }
        url.searchParams.set(key, String(value));
      });
    }

    const headers = new Headers(this.options.defaultHeaders ?? {});
    headers.set('Accept', 'application/json');

    if (body !== undefined) {
      headers.set('Content-Type', 'application/json');
    }

    this.applyAuthHeaders(headers, authMode, options.authMode ?? 'auto', routeSupportsCustomerSession);

    if (requiresIdempotency && options.idempotencyKey) {
      headers.set('Idempotency-Key', options.idempotencyKey);
    }

    if (options.headers) {
      Object.entries(options.headers).forEach(([key, value]) => {
        headers.set(key, value);
      });
    }

    const response = await this.fetchImpl(url.toString(), {
      method,
      headers,
      signal: options.signal,
      body: body === undefined ? undefined : JSON.stringify(body),
    });

    const raw = await response.text();
    const payload = raw === '' ? undefined : JSON.parse(raw);

    if (!response.ok) {
      throw new RestaurantPosApiError(
        'RestaurantPOS request failed with status ' + response.status + '.',
        response.status,
        payload,
      );
    }

    return payload as T;
  }

  private interpolatePath(template: string, values: Record<string, string | number>): string {
    return template.replace(/\{([^}]+)\}/g, (_, key) => encodeURIComponent(String(values[key])));
  }

  private applyAuthHeaders(
    headers: Headers,
    routeAuthMode: AuthMode,
    requestedAuthMode: AuthMode,
    routeSupportsCustomerSession: boolean,
  ): void {
    if (routeAuthMode === 'none' || requestedAuthMode === 'none') {
      return;
    }

    const customerToken = this.resolveValue(this.options.customerToken);
    const customerSessionId = this.resolveValue(this.options.customerSessionId);
    const staffApiKey = this.resolveValue(this.options.staffApiKey);

    const selectedMode = requestedAuthMode === 'auto' ? routeAuthMode : requestedAuthMode;

    if (selectedMode === 'customerOrSession') {
      if (customerToken) {
        this.applyCustomerHeaders(headers, customerToken, customerSessionId, routeSupportsCustomerSession);
        return;
      }

      if (routeSupportsCustomerSession && customerSessionId) {
        headers.set('X-Session-Id', customerSessionId);
      }
      return;
    }

    if (selectedMode === 'customer' && customerToken) {
      this.applyCustomerHeaders(headers, customerToken, customerSessionId, routeSupportsCustomerSession);
      return;
    }

    if (selectedMode === 'staff' && staffApiKey) {
      headers.set('X-Staff-Key', staffApiKey);
      return;
    }

    if (selectedMode === 'session' && routeSupportsCustomerSession && customerSessionId) {
      headers.set('X-Session-Id', customerSessionId);
      return;
    }

    if (selectedMode === 'auto') {
      if (customerToken) {
        this.applyCustomerHeaders(headers, customerToken, customerSessionId, routeSupportsCustomerSession);
        return;
      }
      if (routeSupportsCustomerSession && customerSessionId) {
        headers.set('X-Session-Id', customerSessionId);
        return;
      }
      if (staffApiKey) {
        headers.set('X-Staff-Key', staffApiKey);
      }
    }
  }

  private applyCustomerHeaders(
    headers: Headers,
    customerToken: string,
    customerSessionId: string | undefined,
    routeSupportsCustomerSession: boolean,
  ): void {
    headers.set('X-Customer-Token', customerToken);

    if (routeSupportsCustomerSession && customerSessionId) {
      headers.set('X-Session-Id', customerSessionId);
    }
  }

  private resolveValue(value: string | (() => string | null | undefined) | undefined): string | undefined {
    if (typeof value === 'function') {
      return value() ?? undefined;
    }

    return value ?? undefined;
  }
}
TS;
    }

    /**
     * @param  list<array<string,mixed>>  $parameters
     * @return array<string,mixed>
     */
    private function parameterObjectSchema(array $parameters): array
    {
        $properties = [];
        $required = [];

        foreach ($parameters as $parameter) {
            $name = (string) ($parameter['name'] ?? '');
            $properties[$name] = (array) ($parameter['schema'] ?? ['type' => 'string']);

            if ((bool) ($parameter['required'] ?? false)) {
                $required[] = $name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    /**
     * @param  array<string,mixed>  $operation
     * @return list<array<string,mixed>>
     */
    private function operationParametersByLocation(array $operation, string $location): array
    {
        $parameters = [];

        foreach ((array) ($operation['parameters'] ?? []) as $parameter) {
            if (($parameter['in'] ?? null) === $location) {
                $parameters[] = (array) $parameter;
            }
        }

        return $parameters;
    }

    /**
     * @param  array<string,mixed>  $operation
     * @return array<string,mixed>|null
     */
    private function successResponseSchema(array $operation): ?array
    {
        foreach (['200', '201', '202'] as $status) {
            $schema = Arr::get($operation, "responses.$status.content.application/json.schema");
            if (is_array($schema)) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * @param  array<string,array<string,mixed>>  $inlineDefinitions
     * @param  list<string>  $referencedComponentNames
     */
    private function typeNameForSchema(array $schema, string $fallbackName, array &$inlineDefinitions, array &$referencedComponentNames): string
    {
        if (isset($schema['$ref'])) {
            $name = $this->refName((string) $schema['$ref']);
            $referencedComponentNames[] = $name;

            return $name;
        }

        $inlineDefinitions[$fallbackName] = $schema;
        $this->collectComponentRefs($schema, $referencedComponentNames);

        return $fallbackName;
    }

    /**
     * @param  array<string,array<string,mixed>>  $inlineDefinitions
     * @param  array<string,array<string,mixed>>  $componentSchemas
     * @param  list<string>  $referencedComponentNames
     * @return array<string,array<string,mixed>>
     */
    private function expandDefinitions(array $inlineDefinitions, array $componentSchemas, array $referencedComponentNames): array
    {
        $definitions = $inlineDefinitions;
        $pending = array_values(array_unique($referencedComponentNames));

        while ($pending !== []) {
            $name = array_shift($pending);
            if ($name === null || array_key_exists($name, $definitions) || ! array_key_exists($name, $componentSchemas)) {
                continue;
            }

            $definitions[$name] = $componentSchemas[$name];
            $nestedRefs = [];
            $this->collectComponentRefs($componentSchemas[$name], $nestedRefs);
            foreach ($nestedRefs as $nested) {
                if (! array_key_exists($nested, $definitions)) {
                    $pending[] = $nested;
                }
            }
        }

        ksort($definitions);

        return $definitions;
    }

    /**
     * @param  array<string,mixed>  $schema
     * @param  list<string>  $refs
     */
    private function collectComponentRefs(array $schema, array &$refs): void
    {
        foreach ($schema as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                $refs[] = $this->refName($value);

                continue;
            }

            if (is_array($value)) {
                $this->collectComponentRefs($value, $refs);
            }
        }
    }

    private function refName(string $ref): string
    {
        return str_replace('#/components/schemas/', '', $ref);
    }

    /**
     * @param  array<string,mixed>  $schema
     * @param  array<string,array<string,mixed>>  $componentSchemas
     */
    private function tsTypeFromSchema(array $schema, array $componentSchemas): string
    {
        if (isset($schema['$ref'])) {
            $resolved = $this->refName((string) $schema['$ref']);

            return (bool) ($schema['nullable'] ?? false) ? '('.$resolved.') | null' : $resolved;
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && $schema['enum'] !== []) {
            $literals = array_map(static fn (mixed $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $schema['enum']);
            $type = implode(' | ', $literals);

            return (bool) ($schema['nullable'] ?? false) ? '('.$type.') | null' : $type;
        }

        if (isset($schema['allOf']) && is_array($schema['allOf']) && $schema['allOf'] !== []) {
            return implode(' & ', array_map(fn (array $child): string => $this->tsTypeFromSchema($child, $componentSchemas), $schema['allOf']));
        }

        if (isset($schema['oneOf']) && is_array($schema['oneOf']) && $schema['oneOf'] !== []) {
            return implode(' | ', array_map(fn (array $child): string => $this->tsTypeFromSchema($child, $componentSchemas), $schema['oneOf']));
        }

        if (isset($schema['anyOf']) && is_array($schema['anyOf']) && $schema['anyOf'] !== []) {
            return implode(' | ', array_map(fn (array $child): string => $this->tsTypeFromSchema($child, $componentSchemas), $schema['anyOf']));
        }

        $type = (string) ($schema['type'] ?? 'object');

        $resolved = match ($type) {
            'object' => $this->tsObjectType($schema, $componentSchemas),
            'array' => 'Array<'.$this->tsTypeFromSchema((array) ($schema['items'] ?? []), $componentSchemas).'>',
            'integer', 'number' => 'number',
            'boolean' => 'boolean',
            'string' => 'string',
            'null' => 'null',
            default => 'unknown',
        };

        return (bool) ($schema['nullable'] ?? false) ? '('.$resolved.') | null' : $resolved;
    }

    /**
     * @param  array<string,mixed>  $schema
     * @param  array<string,array<string,mixed>>  $componentSchemas
     */
    private function tsObjectType(array $schema, array $componentSchemas): string
    {
        $properties = (array) ($schema['properties'] ?? []);
        $required = array_map('strval', (array) ($schema['required'] ?? []));

        if ($properties === []) {
            if (($schema['additionalProperties'] ?? false) === true) {
                return 'Record<string, unknown>';
            }

            if (is_array($schema['additionalProperties'] ?? null)) {
                return 'Record<string, '.$this->tsTypeFromSchema((array) $schema['additionalProperties'], $componentSchemas).'>';
            }

            return 'Record<string, never>';
        }

        $lines = ['{'];
        foreach ($properties as $name => $propertySchema) {
            $optional = ! in_array((string) $name, $required, true) ? '?' : '';
            $lines[] = sprintf(
                '  %s%s: %s;',
                $this->quotePropertyName((string) $name),
                $optional,
                $this->tsTypeFromSchema((array) $propertySchema, $componentSchemas)
            );
        }

        if (($schema['additionalProperties'] ?? false) === true) {
            $lines[] = '  [key: string]: unknown;';
        } elseif (is_array($schema['additionalProperties'] ?? null)) {
            $lines[] = '  [key: string]: '.$this->tsTypeFromSchema((array) $schema['additionalProperties'], $componentSchemas).';';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function quotePropertyName(string $name): string
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1 ? $name : json_encode($name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string,mixed>  $definition
     */
    private function buildSdkMethod(array $definition): string
    {
        $args = [];
        $pathInterpolation = "'".(string) $definition['path']."'";

        if (is_string($definition['path_type'])) {
            $args[] = 'pathParams: '.$definition['path_type'];
            $pathInterpolation = "this.interpolatePath('{$definition['path']}', pathParams as Record<string, string | number>)";
        }

        if (is_string($definition['query_type'])) {
            $args[] = 'query: '.$definition['query_type'];
        }

        if (is_string($definition['body_type'])) {
            $args[] = 'body: '.$definition['body_type'];
        }

        $args[] = 'options: RequestOptions = {}';
        $queryExpression = is_string($definition['query_type']) ? 'query' : 'undefined';
        $bodyExpression = is_string($definition['body_type']) ? 'body' : 'undefined';

        return sprintf(
            "  async %s(%s): Promise<%s> {\n    return this.request<%s>(\n      '%s',\n      %s,\n      '%s',\n      %s,\n      %s,\n      %s,\n      %s,\n      options,\n    );\n  }",
            $definition['method_name'],
            implode(', ', $args),
            $definition['response_type'],
            $definition['response_type'],
            $definition['method'],
            $pathInterpolation,
            $this->sdkAuthMode((string) $definition['auth_mode']),
            ($definition['supports_customer_session'] ?? false) ? 'true' : 'false',
            $definition['requires_idempotency'] ? 'true' : 'false',
            $queryExpression,
            $bodyExpression,
        );
    }

    private function sdkAuthMode(string $authMode): string
    {
        return match ($authMode) {
            'customer_access_token' => 'customer',
            'customer_or_session' => 'customerOrSession',
            'staff_api_key' => 'staff',
            'customer_or_staff' => 'auto',
            default => 'none',
        };
    }

    /**
     * @param  array<string,mixed>  $operation
     */
    private function operationRequiresIdempotency(array $operation): bool
    {
        foreach ((array) ($operation['parameters'] ?? []) as $parameter) {
            if (($parameter['in'] ?? null) === 'header' && ($parameter['name'] ?? null) === 'Idempotency-Key') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string,mixed>  $operation
     */
    private function operationUsesCustomerSession(array $operation): bool
    {
        return $this->operationSupportsSessionHeader($operation)
            || $this->collectNamedFields($operation, static fn (string $name): bool => $name === 'session_id') !== [];
    }

    /**
     * @param  list<array{name:string,operations:array<string,array<string,mixed>>}>  $mutationGroups
     * @param  list<string>  $selectedSignatures
     */
    private function buildMutationContractReadme(string $specRelativePath, array $mutationGroups, array $selectedSignatures): string
    {
        $sdkLookup = array_fill_keys($selectedSignatures, true);
        $sections = [];

        foreach ($mutationGroups as $group) {
            $sections[] = $this->buildMutationContractGroupSection($group, $sdkLookup);
        }

        $groupSections = $sections === [] ? '_No mutation contract groups are configured._' : implode("\n\n", $sections);

        return <<<MD
# RestaurantPOS Mutation Contract Matrix

Generated from `{$specRelativePath}` by:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate
php artisan booking:release-manifest --write
```

Use this file to answer:

- which mutation requires `row_version`
- which mutation requires `Idempotency-Key`
- which mutation still depends on customer session context / `X-Session-Id`
- when FE should expect `401`, `403`, `409`, or `422`

Deprecated alias routes are intentionally omitted. Use canonical routes only.

## Legend

- `SDK`: route is in the generated TypeScript SDK and the frozen spec marks it as full-contract.
- `SDK (fallback)`: the SDK exposes a method, but the frozen spec still marks the route as fallback. Treat request constraints as useful, but do not assume a fully-endorsed FE response/error contract yet.
- `OpenAPI`: route is full-contract in the frozen spec but not curated into the generated SDK batch.
- `OpenAPI (fallback)`: route is only discoverable through the frozen spec today. Promote it to full-contract before treating it as a stable FE surface.
- When the session column mentions `session_id`, keep sending `X-Session-Id` for session-owned access and also send the documented request field while the current validator still checks it.

{$groupSections}
MD;
    }

    /**
     * @param  array{name:string,operations:array<string,array<string,mixed>>}  $group
     * @param  array<string,bool>  $sdkLookup
     */
    private function buildMutationContractGroupSection(array $group, array $sdkLookup): string
    {
        $lines = [
            '## '.(string) ($group['name'] ?? 'Group'),
            '',
            '| Route | Contract path | Auth | row_version | Idempotency-Key | Session contract | 401 | 403 | 409 | 422 |',
            '|---|---|---|---|---|---|---|---|---|---|',
        ];

        foreach ((array) ($group['operations'] ?? []) as $signature => $entry) {
            $operation = (array) ($entry['operation'] ?? []);
            $contractGrade = (string) ($operation['x-contract-grade'] ?? 'fallback');
            $rowVersionFields = $this->collectNamedFields($operation, static fn (string $name): bool => str_contains($name, 'row_version'));
            $sessionFields = $this->collectNamedFields($operation, static fn (string $name): bool => $name === 'session_id');
            $hasIdempotency = $this->operationRequiresIdempotency($operation);

            $lines[] = sprintf(
                '| `%s` | `%s` | `%s` | %s | `%s` | %s | %s | %s | %s | %s |',
                $signature,
                $this->consumerPathLabel((string) $signature, $operation, $sdkLookup),
                (string) ($operation['x-auth-mode'] ?? 'public'),
                $this->markdownCell($this->fieldSummary($rowVersionFields)),
                $hasIdempotency ? 'Required' : 'No',
                $this->markdownCell($this->sessionContractSummary($operation, $sessionFields)),
                $this->markdownCell($this->statusExpectation(401, $contractGrade, $entry, $rowVersionFields, $sessionFields, $hasIdempotency)),
                $this->markdownCell($this->statusExpectation(403, $contractGrade, $entry, $rowVersionFields, $sessionFields, $hasIdempotency)),
                $this->markdownCell($this->statusExpectation(409, $contractGrade, $entry, $rowVersionFields, $sessionFields, $hasIdempotency)),
                $this->markdownCell($this->statusExpectation(422, $contractGrade, $entry, $rowVersionFields, $sessionFields, $hasIdempotency)),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string,mixed>  $operation
     * @param  callable(string):bool  $filter
     * @return list<array{location:string,name:string,requirement:string}>
     */
    private function collectNamedFields(array $operation, callable $filter): array
    {
        $fields = [];
        $description = (string) ($operation['description'] ?? '');

        foreach ($this->operationParametersByLocation($operation, 'query') as $parameter) {
            $name = (string) ($parameter['name'] ?? '');
            if ($name === '' || ! $filter($name)) {
                continue;
            }

            $fields[] = [
                'location' => 'query',
                'name' => $name,
                'requirement' => $this->fieldRequirementLabel(
                    $name,
                    (bool) ($parameter['required'] ?? false),
                    array_map('strval', (array) ($parameter['x-laravel-rules'] ?? [])),
                    $description,
                ),
            ];
        }

        $bodySchema = $this->resolvedRequestBodySchema($operation);
        if ($bodySchema !== null) {
            $required = array_map('strval', (array) ($bodySchema['required'] ?? []));

            foreach ((array) ($bodySchema['properties'] ?? []) as $name => $propertySchema) {
                $name = (string) $name;
                if ($name === '' || ! $filter($name)) {
                    continue;
                }

                $fields[] = [
                    'location' => 'body',
                    'name' => $name,
                    'requirement' => $this->fieldRequirementLabel(
                        $name,
                        in_array($name, $required, true),
                        array_map('strval', (array) (($propertySchema['x-laravel-rules'] ?? []))),
                        $description,
                    ),
                ];
            }
        }

        return $fields;
    }

    private function fieldSummary(array $fields): string
    {
        if ($fields === []) {
            return 'No';
        }

        $parts = array_map(
            static fn (array $field): string => sprintf('%s.%s %s', $field['location'], $field['name'], $field['requirement']),
            $fields,
        );

        return implode('; ', $parts);
    }

    /**
     * @param  list<string>  $rules
     */
    private function fieldRequirementLabel(string $fieldName, bool $required, array $rules, string $description): string
    {
        if ($required) {
            return 'required';
        }

        foreach ($rules as $rule) {
            $normalized = Str::lower(trim((string) $rule));

            if (str_starts_with($normalized, 'required_with:')) {
                return 'required with '.substr((string) $rule, strlen('required_with:'));
            }

            if (str_starts_with($normalized, 'required_without:')) {
                return 'required without '.substr((string) $rule, strlen('required_without:'));
            }

            if (str_starts_with($normalized, 'required_if:')) {
                return 'required if '.substr((string) $rule, strlen('required_if:'));
            }
        }

        if (
            $fieldName === 'pre_order_row_version'
            && Str::contains(Str::lower($description), 'pre-order row version becomes required')
        ) {
            return 'conditional';
        }

        return 'optional';
    }

    /**
     * @param  list<array{location:string,name:string,requirement:string}>  $sessionFields
     */
    private function sessionContractSummary(array $operation, array $sessionFields): string
    {
        $parts = [];
        if ($sessionFields !== [] || $this->operationSupportsSessionHeader($operation)) {
            $parts[] = 'X-Session-Id accepted';
        }

        if ($sessionFields !== []) {
            $parts[] = $this->fieldSummary($sessionFields);
        }

        return $parts === [] ? 'No' : implode('; ', array_values(array_unique($parts)));
    }

    /**
     * @param  array<string,mixed>  $operation
     */
    private function operationSupportsSessionHeader(array $operation): bool
    {
        foreach ((array) ($operation['security'] ?? []) as $requirement) {
            if (array_key_exists('CustomerSessionId', (array) $requirement)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string,mixed>  $operation
     * @param  array<string,bool>  $sdkLookup
     */
    private function consumerPathLabel(string $signature, array $operation, array $sdkLookup): string
    {
        $isSdkRoute = array_key_exists($signature, $sdkLookup);
        $grade = (string) ($operation['x-contract-grade'] ?? 'fallback');

        if ($isSdkRoute && $grade === 'full') {
            return 'SDK';
        }

        if ($isSdkRoute) {
            return 'SDK (fallback)';
        }

        return $grade === 'full' ? 'OpenAPI' : 'OpenAPI (fallback)';
    }

    /**
     * @param  array<string,mixed>  $entry
     * @param  list<array{location:string,name:string,requirement:string}>  $rowVersionFields
     * @param  list<array{location:string,name:string,requirement:string}>  $sessionFields
     */
    private function statusExpectation(int $status, string $contractGrade, array $entry, array $rowVersionFields, array $sessionFields, bool $hasIdempotency): string
    {
        $operation = (array) ($entry['operation'] ?? []);

        if (! $this->operationHasStatus($operation, $status)) {
            return $contractGrade === 'full' ? 'No' : 'Not formalized (fallback)';
        }

        return match ($status) {
            401 => $this->unauthorizedExpectation($operation, $sessionFields),
            403 => $this->forbiddenExpectation($entry),
            409 => $this->conflictExpectation($hasIdempotency),
            422 => $this->validationExpectation($rowVersionFields, $sessionFields, $hasIdempotency),
            default => 'Handled',
        };
    }

    /**
     * @param  array<string,mixed>  $operation
     */
    private function operationHasStatus(array $operation, int $status): bool
    {
        return array_key_exists((string) $status, (array) ($operation['responses'] ?? []));
    }

    /**
     * @param  array<string,mixed>  $operation
     * @param  list<array{location:string,name:string,requirement:string}>  $sessionFields
     */
    private function unauthorizedExpectation(array $operation, array $sessionFields): string
    {
        $authMode = (string) ($operation['x-auth-mode'] ?? 'public');
        $sessionAware = $sessionFields !== [] || $this->operationSupportsSessionHeader($operation);

        return match ($authMode) {
            'staff_api_key' => 'missing/invalid X-Staff-Key',
            'customer_access_token' => $sessionAware ? 'missing customer auth or session' : 'missing/invalid X-Customer-Token',
            'customer_or_session' => 'missing customer auth or session',
            'customer_or_staff' => $sessionAware ? 'missing customer/staff auth or session' : 'missing customer/staff auth',
            default => 'authentication missing or invalid',
        };
    }

    /**
     * @param  array<string,mixed>  $entry
     */
    private function forbiddenExpectation(array $entry): string
    {
        $path = (string) ($entry['path'] ?? '');

        return match (true) {
            str_starts_with($path, '/api/v1/admin/') => 'staff capability boundary',
            str_starts_with($path, '/api/v1/staff/') => 'capability/branch boundary',
            str_starts_with($path, '/api/v1/reservations'),
            str_starts_with($path, '/api/v1/waiting-list') => 'ownership/session boundary',
            default => 'authorization boundary',
        };
    }

    private function conflictExpectation(bool $hasIdempotency): string
    {
        if ($hasIdempotency) {
            return 'idempotency conflict/replay';
        }

        return 'state conflict';
    }

    private function validationExpectation(array $rowVersionFields, array $sessionFields, bool $hasIdempotency): string
    {
        $reasons = ['validation'];

        if ($hasIdempotency) {
            $reasons[] = 'missing Idempotency-Key';
        }

        if ($this->hasRequiredField($sessionFields)) {
            $reasons[] = 'missing session_id';
        }

        if ($rowVersionFields !== []) {
            $reasons[] = 'stale row_version mismatch';
        }

        if ($this->hasRequiredField($rowVersionFields)) {
            $reasons[] = 'missing row_version';
        }

        return implode(' / ', array_values(array_unique($reasons)));
    }

    private function hasRequiredField(array $fields): bool
    {
        foreach ($fields as $field) {
            if (str_starts_with((string) ($field['requirement'] ?? ''), 'required')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string,mixed>  $schema
     * @return array<string,mixed>
     */
    private function resolveComponentSchema(array $schema): array
    {
        if (isset($schema['$ref'])) {
            return (array) ($this->componentSchemas[$this->refName((string) $schema['$ref'])] ?? $schema);
        }

        return $schema;
    }

    private function markdownCell(string $value): string
    {
        return str_replace(["\r\n", "\n", '|'], ['<br>', '<br>', '\|'], $value);
    }

    /**
     * @param  list<array{name:string,operations:array<string,array<string,mixed>>}>  $curatedGroups
     */
    private function buildSdkReadme(string $specRelativePath, array $curatedGroups): string
    {
        $curatedBatchSection = $this->buildSdkCuratedBatchSection($curatedGroups);

        return <<<MD
# RestaurantPOS TypeScript SDK Foundation

Generated from `{$specRelativePath}` by:

```bash
php artisan booking:api-contract --write
php artisan booking:api-artifacts:generate
php artisan booking:release-manifest --write
```

This is the official TypeScript convenience client for the curated priority frontend batch. The frozen OpenAPI artifact remains the only official schema source for the whole API surface.

## Contract consumption policy

| Need | Official source |
|---|---|
| TypeScript frontend work on the curated priority batch below | `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts` |
| FE enum/state values and semantic aliases such as checked-in reservation state | `build/api-consumer/sdk/typescript/restaurantpos-enums.ts`, `build/api-consumer/enum-state-map.json` |
| Mutation requirements such as `row_version`, `Idempotency-Key`, `X-Session-Id`, and expected `403/409/422` handling | `build/api-consumer/mutation-contracts.md` |
| Typed client generation for another stack or for a full-contract route outside the curated batch | `{$specRelativePath}` |
| Discovery of full-contract routes that are not in the SDK batch | `Reference` folder in `build/api-consumer/postman/RestaurantPOS.postman_collection.json` |
| Runtime or controller behavior investigation | Read backend code as implementation detail only, not as the consumer contract |

Do not treat controllers, resources, or ad-hoc route inspection as contract sources.

The SDK only guarantees method coverage for the curated priority batch listed below. Other full-contract routes stay in the frozen OpenAPI artifact and the generated `Reference` folder until they are curated into a later batch.

## Curated priority batch

{$curatedBatchSection}

Usage sketch:

```ts
import { RestaurantPosClient } from './restaurantpos-sdk';

const client = new RestaurantPosClient({
  baseUrl: 'http://127.0.0.1:8000',
  customerToken: () => localStorage.getItem('customerToken') ?? undefined,
  customerSessionId: () => sessionStorage.getItem('customerSessionId') ?? undefined,
  staffApiKey: () => localStorage.getItem('staffApiKey') ?? undefined,
});

const login = await client.postV1AuthCustomerLogin({
  identifier: 'uat.customer.primary',
  password: 'UatDemo!123',
  session_label: 'web',
});
```

Limitations:

- On curated customer routes whose mutation contract requires session propagation, the generated client keeps `X-Customer-Token` and `X-Session-Id` together when both are configured.
- The SDK is intentionally scoped to the curated priority batch, not every full-contract or fallback endpoint.
- Enum/state exports are generated separately in `restaurantpos-enums.ts` and `enum-state-map.json` so FE can consume stable state values without inferring them from incidental payload strings.
- Response typing follows the frozen OpenAPI artifact. Routes still below contract-grade remain outside the official SDK batch and can stay coarse in the spec.
- No package manifest is emitted in this phase; consumers can vendor the generated file or wrap it in their own workspace.

For the full immutable release path, use `php artisan booking:release-build`.
MD;
    }

    /**
     * @param  list<array{name:string,operations:array<string,array<string,mixed>>}>  $curatedGroups
     */
    private function buildSdkCuratedBatchSection(array $curatedGroups): string
    {
        $sections = [];

        foreach ($curatedGroups as $group) {
            $lines = ['### '.(string) ($group['name'] ?? 'Group'), ''];

            foreach (array_keys((array) ($group['operations'] ?? [])) as $signature) {
                $lines[] = '- '.$signature;
            }

            $sections[] = implode("\n", $lines);
        }

        return implode("\n\n", $sections);
    }
}

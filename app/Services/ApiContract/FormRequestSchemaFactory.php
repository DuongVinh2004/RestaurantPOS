<?php

declare(strict_types=1);

namespace App\Services\ApiContract;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionClass;

class FormRequestSchemaFactory
{
    /**
     * @return array{
     *   schema_name:string,
     *   schema:array<string,mixed>,
     *   query_parameters:list<array<string,mixed>>,
     *   request_example:array<string,mixed>|null
     * }
     */
    public function describe(string $formRequestClass): array
    {
        if (! is_a($formRequestClass, FormRequest::class, true)) {
            throw new \InvalidArgumentException(sprintf('[%s] is not a FormRequest.', $formRequestClass));
        }

        /** @var FormRequest $instance */
        $instance = new $formRequestClass();
        $rules = $instance->rules();

        return [
            'schema_name' => (new ReflectionClass($formRequestClass))->getShortName(),
            'schema' => $this->buildBodySchema($rules),
            'query_parameters' => $this->buildQueryParameters($rules),
            'request_example' => $this->buildExamplePayload($rules),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    private function buildBodySchema(array $rules): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
            'additionalProperties' => false,
            'x-laravel-rules' => [],
        ];

        foreach ($rules as $field => $fieldRules) {
            $segments = explode('.', (string) $field);
            $propertySchema = $this->schemaForRules((array) $fieldRules, (string) $field);
            $this->applyFieldSchema($schema, $segments, $propertySchema, $this->isUnconditionallyRequired((array) $fieldRules));
            $schema['x-laravel-rules'][(string) $field] = $this->rulesForExtension((array) $fieldRules);
        }

        $schema['required'] = array_values(array_unique(array_values(array_filter((array) $schema['required']))));
        sort($schema['required']);

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return list<array<string,mixed>>
     */
    private function buildQueryParameters(array $rules): array
    {
        $parameters = [];

        foreach ($rules as $field => $fieldRules) {
            if (str_contains((string) $field, '.')) {
                continue;
            }

            $schema = $this->schemaForRules((array) $fieldRules, (string) $field);
            unset($schema['x-laravel-rules']);

            $parameters[] = [
                'name' => (string) $field,
                'in' => 'query',
                'required' => $this->isUnconditionallyRequired((array) $fieldRules),
                'schema' => $schema,
                'description' => $this->descriptionForRules((array) $fieldRules),
                'x-laravel-rules' => $this->rulesForExtension((array) $fieldRules),
            ];
        }

        return $parameters;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string,mixed>|null
     */
    private function buildExamplePayload(array $rules): ?array
    {
        $example = [];

        foreach ($rules as $field => $fieldRules) {
            $segments = explode('.', (string) $field);
            if (in_array('*', $segments, true)) {
                continue;
            }

            $value = $this->exampleValueForRules((array) $fieldRules, (string) $field);
            if ($value === null && ! $this->isUnconditionallyRequired((array) $fieldRules)) {
                continue;
            }

            Arr::set($example, (string) $field, $value);
        }

        return $example === [] ? null : $example;
    }

    /**
     * @param  array<string,mixed>  $node
     * @param  list<string>  $segments
     * @param  array<string,mixed>  $schema
     */
    private function applyFieldSchema(array &$node, array $segments, array $schema, bool $required): void
    {
        $segment = array_shift($segments);
        if ($segment === null) {
            return;
        }

        if ($segment === '*') {
            $node['type'] = 'array';
            $node['items'] ??= [];

            if ($segments === []) {
                $node['items'] = $this->mergeSchemas((array) $node['items'], $schema);

                return;
            }

            if ($node['items'] === []) {
                $node['items'] = $segments[0] === '*' ? ['type' => 'array'] : ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false];
            }

            $this->applyFieldSchema($node['items'], $segments, $schema, $required);

            return;
        }

        $node['type'] = 'object';
        $node['properties'] ??= [];
        $node['required'] ??= [];
        $node['additionalProperties'] ??= false;

        if ($segments === []) {
            $node['properties'][$segment] = $this->mergeSchemas((array) ($node['properties'][$segment] ?? []), $schema);
            if ($required) {
                $node['required'][] = $segment;
            }

            return;
        }

        $next = $segments[0];
        $node['properties'][$segment] ??= $next === '*'
            ? ['type' => 'array', 'items' => []]
            : ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false];

        $this->applyFieldSchema($node['properties'][$segment], $segments, $schema, $required);
    }

    /**
     * @param  list<mixed>  $rules
     * @return array<string,mixed>
     */
    private function schemaForRules(array $rules, string $field): array
    {
        $schema = [
            'type' => 'string',
            'nullable' => false,
            'x-laravel-rules' => $this->rulesForExtension($rules),
        ];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                foreach (explode('|', $rule) as $token) {
                    $this->applyRuleToken($schema, trim($token), $field);
                }

                continue;
            }

            $this->applyRuleObject($schema, $rule);
        }

        if (($schema['nullable'] ?? false) !== true) {
            unset($schema['nullable']);
        }

        return $schema;
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function applyRuleToken(array &$schema, string $rule, string $field): void
    {
        if ($rule === '') {
            return;
        }

        [$name, $parameterString] = array_pad(explode(':', $rule, 2), 2, null);
        $name = strtolower(trim($name));
        $parameterString = $parameterString !== null ? trim($parameterString) : null;
        $parameters = $parameterString !== null ? array_map('trim', explode(',', $parameterString)) : [];

        match ($name) {
            'nullable' => $schema['nullable'] = true,
            'string' => $schema['type'] = 'string',
            'integer' => $schema['type'] = 'integer',
            'numeric' => $schema['type'] = 'number',
            'boolean' => $schema['type'] = 'boolean',
            'array' => $schema['type'] = 'array',
            'uuid' => $this->assignStringSchema($schema, 'uuid'),
            'email' => $this->assignStringSchema($schema, 'email'),
            'date' => $this->assignStringSchema($schema, $this->dateFormatForField($field)),
            'regex' => $schema['pattern'] = $this->normalizeRegexPattern($parameterString),
            'min' => $this->applyMinConstraint($schema, $parameters[0] ?? null),
            'max' => $this->applyMaxConstraint($schema, $parameters[0] ?? null),
            'gt' => $this->applyExclusiveMinimum($schema, $parameters[0] ?? null),
            'in' => $schema['enum'] = $parameters,
            default => null,
        };
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function applyRuleObject(array &$schema, mixed $rule): void
    {
        if (! is_object($rule)) {
            return;
        }

        $class = $rule::class;
        if (str_ends_with($class, '\\In')) {
            $values = $this->readObjectProperty($rule, 'values');
            if (is_array($values) && $values !== []) {
                $schema['enum'] = array_values($values);
            }

            return;
        }

        if (method_exists($rule, '__toString')) {
            $this->applyRuleToken($schema, (string) $rule, '');
        }
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function applyMinConstraint(array &$schema, ?string $value): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $number = (float) $value;
        if (($schema['type'] ?? 'string') === 'array') {
            $schema['minItems'] = (int) $number;

            return;
        }

        if (in_array(($schema['type'] ?? 'string'), ['integer', 'number'], true)) {
            $schema['minimum'] = $number;

            return;
        }

        $schema['minLength'] = (int) $number;
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function applyMaxConstraint(array &$schema, ?string $value): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $number = (float) $value;
        if (($schema['type'] ?? 'string') === 'array') {
            $schema['maxItems'] = (int) $number;

            return;
        }

        if (in_array(($schema['type'] ?? 'string'), ['integer', 'number'], true)) {
            $schema['maximum'] = $number;

            return;
        }

        $schema['maxLength'] = (int) $number;
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function applyExclusiveMinimum(array &$schema, ?string $value): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $schema['exclusiveMinimum'] = (float) $value;
    }

    /**
     * @param  array<string,mixed>  $schema
     */
    private function assignStringSchema(array &$schema, string $format): void
    {
        $schema['type'] = 'string';
        $schema['format'] = $format;
    }

    /**
     * @param  array<string,mixed>  $schema
     * @return array<string,mixed>
     */
    private function mergeSchemas(array $existing, array $schema): array
    {
        if ($existing === []) {
            return $schema;
        }

        $merged = array_replace_recursive($existing, $schema);

        if (($existing['required'] ?? null) !== null || ($schema['required'] ?? null) !== null) {
            $merged['required'] = array_values(array_unique(array_merge(
                (array) ($existing['required'] ?? []),
                (array) ($schema['required'] ?? []),
            )));
            sort($merged['required']);
        }

        if (($existing['properties'] ?? null) !== null || ($schema['properties'] ?? null) !== null) {
            $merged['properties'] = array_replace_recursive(
                (array) ($existing['properties'] ?? []),
                (array) ($schema['properties'] ?? []),
            );
        }

        if (($existing['x-laravel-rules'] ?? null) !== null || ($schema['x-laravel-rules'] ?? null) !== null) {
            $merged['x-laravel-rules'] = array_values(array_unique(array_merge(
                (array) ($existing['x-laravel-rules'] ?? []),
                (array) ($schema['x-laravel-rules'] ?? []),
            )));
        }

        return $merged;
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<string>
     */
    private function rulesForExtension(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                $normalized[] = $rule;

                continue;
            }

            if (is_object($rule)) {
                $normalized[] = $this->stringifyRuleObject($rule);
            }
        }

        return array_values(array_filter($normalized, static fn (string $value): bool => trim($value) !== ''));
    }

    private function stringifyRuleObject(object $rule): string
    {
        $class = class_basename($rule);

        if (str_ends_with($rule::class, '\\In')) {
            $values = $this->readObjectProperty($rule, 'values');
            if (is_array($values)) {
                return 'in:' . implode(',', array_map(static fn (mixed $value): string => (string) $value, $values));
            }
        }

        if (method_exists($rule, '__toString')) {
            return (string) $rule;
        }

        return $class;
    }

    /**
     * @param  list<mixed>  $rules
     */
    private function isUnconditionallyRequired(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule)) {
                foreach (explode('|', $rule) as $token) {
                    if (strtolower(trim((string) explode(':', $token, 2)[0])) === 'required') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $rules
     */
    private function descriptionForRules(array $rules): string
    {
        return implode(', ', $this->rulesForExtension($rules));
    }

    private function normalizeRegexPattern(?string $pattern): ?string
    {
        if ($pattern === null || $pattern === '') {
            return null;
        }

        if (Str::startsWith($pattern, '/') && Str::endsWith($pattern, '/')) {
            return substr($pattern, 1, -1);
        }

        return $pattern;
    }

    private function dateFormatForField(string $field): string
    {
        $normalized = Str::lower($field);

        if (Str::contains($normalized, ['time', 'from', 'to', '_at'])) {
            return 'date-time';
        }

        return 'date';
    }

    private function readObjectProperty(object $object, string $property): mixed
    {
        try {
            $reflection = new \ReflectionProperty($object, $property);
            $reflection->setAccessible(true);

            return $reflection->getValue($object);
        } catch (\ReflectionException) {
            return null;
        }
    }

    /**
     * @param  list<mixed>  $rules
     */
    private function exampleValueForRules(array $rules, string $field): mixed
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                return explode(',', substr($rule, 3))[0] ?? null;
            }

            if (is_object($rule) && str_ends_with($rule::class, '\\In')) {
                $values = $this->readObjectProperty($rule, 'values');

                return is_array($values) ? ($values[0] ?? null) : null;
            }
        }

        $ruleText = strtolower(implode('|', $this->rulesForExtension($rules)));
        $field = Str::snake($field);

        if (str_contains($ruleText, 'boolean')) {
            return true;
        }

        if (str_contains($ruleText, 'integer')) {
            return Str::contains($field, ['version', 'count', 'minutes']) ? 1 : 101;
        }

        if (str_contains($ruleText, 'numeric')) {
            return Str::contains($field, ['amount', 'price', 'total']) ? 100000 : 1.5;
        }

        if (str_contains($ruleText, 'array')) {
            return [];
        }

        if (str_contains($ruleText, 'uuid')) {
            return '550e8400-e29b-41d4-a716-446655440000';
        }

        if (str_contains($ruleText, 'date')) {
            return $this->dateFormatForField($field) === 'date-time'
                ? '2026-04-05T10:00:00Z'
                : '2026-04-05';
        }

        return match (true) {
            Str::contains($field, 'identifier') => 'demo-user',
            Str::contains($field, 'password') => 'secret-123',
            Str::contains($field, 'currency') => 'VND',
            Str::contains($field, 'provider_code') => 'simulated',
            Str::contains($field, 'payment_method') => 'Online',
            Str::contains($field, ['phone']) => '0901000000',
            Str::contains($field, ['guest_name', 'full_name', 'name']) => 'Demo Customer',
            Str::contains($field, ['session_id']) => 'sess-demo-001',
            Str::contains($field, ['notes', 'reason']) => 'Contract example payload',
            default => 'example',
        };
    }
}

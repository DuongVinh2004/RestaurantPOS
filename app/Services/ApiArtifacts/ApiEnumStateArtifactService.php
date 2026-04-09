<?php

declare(strict_types=1);

namespace App\Services\ApiArtifacts;

use App\Enums\ReservationStatus;
use BackedEnum;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionEnum;
use RuntimeException;

class ApiEnumStateArtifactService
{
    /**
     * @return array<string,mixed>
     */
    public function generate(?string $outputRoot = null): array
    {
        $outputRoot = $this->normalizeRelativePath($outputRoot ?: (string) config('api_artifacts.output_root', 'build/api-consumer'));
        $payload = $this->buildPayload();
        $typescript = $this->buildTypeScript($payload);

        $artifacts = [
            'enum_state_json' => $this->writeArtifact($outputRoot, (string) config('api_artifacts.enums.json', 'enum-state-map.json'), $payload),
            'enum_state_typescript' => $this->writeArtifact($outputRoot, (string) config('api_artifacts.enums.typescript', 'sdk/typescript/restaurantpos-enums.ts'), $typescript),
        ];

        return [
            'ok' => true,
            'output_root' => $outputRoot,
            'artifacts' => $artifacts,
            'summary' => [
                'enum_group_count' => count((array) ($payload['enums'] ?? [])),
                'enum_value_count' => collect((array) ($payload['enums'] ?? []))
                    ->sum(static fn (array $enum): int => count((array) ($enum['values'] ?? []))),
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function buildPayload(): array
    {
        $enums = [];

        foreach ($this->discoverEnumClasses() as $className) {
            if (! enum_exists($className) || ! is_subclass_of($className, BackedEnum::class)) {
                continue;
            }

            /** @var class-string<BackedEnum> $className */
            $reflection = new ReflectionEnum($className);
            if (! $reflection->isBacked()) {
                continue;
            }

            $cases = [];
            foreach ($className::cases() as $case) {
                $cases[$case->name] = $case->value;
            }

            $enumName = Str::afterLast($className, '\\');
            $specialMetadata = $this->specialMetadata($className);
            $enums[$enumName] = array_filter([
                'php_class' => $className,
                'backing_type' => $reflection->getBackingType()?->getName(),
                'values' => array_values(array_map(static fn (BackedEnum $case): string|int => $case->value, $className::cases())),
                'cases' => $cases,
                'semantic_aliases' => $specialMetadata['semantic_aliases'] ?? [],
                'state_hints' => $specialMetadata['state_hints'] ?? [],
                'notes' => $specialMetadata['notes'] ?? [],
            ], static fn (mixed $value): bool => ! in_array($value, [[], null], true));
        }

        ksort($enums);

        return [
            'source' => [
                'php_enum_root' => 'app/Enums',
                'contract_policy' => 'Generated from PHP backed enums so FE consumers do not infer state values from incidental response strings.',
            ],
            'enums' => $enums,
        ];
    }

    /**
     * @return list<class-string>
     */
    private function discoverEnumClasses(): array
    {
        $enumRoot = app_path('Enums');
        if (! File::isDirectory($enumRoot)) {
            return [];
        }

        $classes = [];
        foreach (File::allFiles($enumRoot) as $file) {
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $relative = str_replace(['/', '\\'], '\\', $file->getRelativePathname());
            $class = 'App\\Enums\\'.str_replace('.php', '', $relative);
            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    /**
     * @return array<string,mixed>
     */
    private function specialMetadata(string $className): array
    {
        return match ($className) {
            ReservationStatus::class => [
                'semantic_aliases' => [
                    'checked_in' => ReservationStatus::checkedInDbValue(),
                ],
                'state_hints' => [
                    'checked_in_db_value' => ReservationStatus::checkedInDbValue(),
                    'active_db_values' => ReservationStatus::activeDbValues(),
                    'cancellable_db_values' => ReservationStatus::cancellableDbValues(),
                ],
                'notes' => [
                    'Historical DB/API value `Reserved` means the guest is already checked in and occupying table(s).',
                ],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function buildTypeScript(array $payload): string
    {
        $lines = [
            '/* eslint-disable */',
            '// Generated from app/Enums. Do not edit by hand.',
            '',
        ];

        foreach ((array) ($payload['enums'] ?? []) as $enumName => $definition) {
            $valuesConst = Str::camel((string) $enumName).'Values';
            $values = (array) ($definition['values'] ?? []);
            $notes = array_map(static fn (string $note): string => '// '.$note, (array) ($definition['notes'] ?? []));
            foreach ($notes as $line) {
                $lines[] = $line;
            }

            $lines[] = sprintf(
                'export const %s = %s as const;',
                $valuesConst,
                json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            $lines[] = sprintf('export type %s = typeof %s[number];', $enumName, $valuesConst);
            $lines[] = '';
        }

        $mapEntries = [];
        foreach ((array) ($payload['enums'] ?? []) as $enumName => $definition) {
            $valuesConst = Str::camel((string) $enumName).'Values';
            $parts = [sprintf('values: %s', $valuesConst)];

            if ((array) ($definition['cases'] ?? []) !== []) {
                $parts[] = 'cases: '.json_encode((array) ($definition['cases'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' as const';
            }

            if ((array) ($definition['semantic_aliases'] ?? []) !== []) {
                $parts[] = 'semanticAliases: '.json_encode((array) ($definition['semantic_aliases'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' as const';
            }

            if ((array) ($definition['state_hints'] ?? []) !== []) {
                $parts[] = 'stateHints: '.json_encode((array) ($definition['state_hints'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' as const';
            }

            if ((array) ($definition['notes'] ?? []) !== []) {
                $parts[] = 'notes: '.json_encode((array) ($definition['notes'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).' as const';
            }

            $mapEntries[] = sprintf("  %s: {\n    %s\n  }", $enumName, implode(",\n    ", $parts));
        }

        $lines[] = 'export const restaurantPosEnumStateMap = {';
        $lines[] = implode(",\n", $mapEntries);
        $lines[] = '} as const;';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string,mixed>|string  $contents
     */
    private function writeArtifact(string $outputRoot, string $relativePath, array|string $contents): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = base_path($outputRoot.DIRECTORY_SEPARATOR.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        if (is_array($contents)) {
            File::put($absolutePath, json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            File::put($absolutePath, $contents);
        }

        return str_replace('\\', '/', $outputRoot.'/'.$relativePath);
    }

    private function normalizeRelativePath(string $path): string
    {
        $normalized = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
        $normalized = trim($normalized, DIRECTORY_SEPARATOR);

        if ($normalized === '') {
            throw new RuntimeException('Artifact path must not be empty.');
        }

        return str_replace('\\', '/', $normalized);
    }
}

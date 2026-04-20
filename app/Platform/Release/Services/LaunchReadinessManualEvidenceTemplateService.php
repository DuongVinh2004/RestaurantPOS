<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class LaunchReadinessManualEvidenceTemplateService
{
    /**
     * @return array{
     *   ok: bool,
     *   status: string,
     *   target: string,
     *   candidate: string,
     *   output_path: string,
     *   resolved_output_path: string,
     *   check_count: int,
     *   check_keys: list<string>,
     *   manual_checks: list<array{
     *     key: string,
     *     label: string,
     *     source: string,
     *     required_for_target: bool,
     *     runbook_path: string,
     *     operator_commands: list<string>,
     *     operator_notes: list<string>
     *   }>,
     *   next_command: string,
     *   issues: list<string>,
     *   warnings: list<string>
     * }
     */
    public function scaffold(
        string $target = 'staging',
        ?string $candidate = null,
        ?string $outputPath = null,
        bool $overwrite = false,
    ): array {
        $normalizedTarget = strtolower(trim($target));
        $targetConfig = (array) config('booking_launch_readiness.targets', []);
        if ($normalizedTarget === '' || ! array_key_exists($normalizedTarget, $targetConfig)) {
            throw new InvalidArgumentException('Unsupported target. Supported values: staging, limited-production.');
        }

        $candidateSlug = $this->normalizeCandidate($candidate);
        $resolvedOutputPath = $this->resolveOutputPath($normalizedTarget, $candidateSlug, $outputPath);
        $displayOutputPath = $this->displayPath($resolvedOutputPath);
        $manualChecks = $this->manualChecksForTarget($normalizedTarget);

        if (File::exists($resolvedOutputPath) && ! $overwrite) {
            return [
                'ok' => false,
                'status' => 'fail',
                'target' => $normalizedTarget,
                'candidate' => $candidateSlug,
                'output_path' => $displayOutputPath,
                'resolved_output_path' => $resolvedOutputPath,
                'check_count' => count($manualChecks),
                'check_keys' => array_values(array_map(static fn (array $row): string => (string) $row['key'], $manualChecks)),
                'manual_checks' => array_values(array_map(
                    static fn (array $row): array => [
                        'key' => (string) $row['key'],
                        'label' => (string) $row['label'],
                        'source' => (string) $row['source'],
                        'required_for_target' => (bool) $row['required_for_target'],
                        'runbook_path' => (string) ($row['runbook_path'] ?? ''),
                        'operator_commands' => array_values((array) ($row['operator_commands'] ?? [])),
                        'operator_notes' => array_values((array) ($row['operator_notes'] ?? [])),
                    ],
                    $manualChecks,
                )),
                'next_command' => sprintf(
                    'php artisan booking:launch-readiness --target=%s --manual-evidence=%s --json',
                    $normalizedTarget,
                    $displayOutputPath
                ),
                'issues' => [sprintf('Manual evidence template [%s] already exists. Re-run with --overwrite to replace it.', $displayOutputPath)],
                'warnings' => [],
            ];
        }

        $now = Carbon::now('UTC');
        $payload = [
            'target' => $normalizedTarget,
            'candidate' => $candidateSlug,
            'generated_at_utc' => $now->toIso8601String(),
            'checks' => [],
            'guidance' => [],
        ];

        foreach ($manualChecks as $manualCheck) {
            $key = (string) $manualCheck['key'];

            $payload['checks'][$key] = [
                'status' => 'missing',
                'performed_by' => '',
                'performed_at_utc' => '',
                'notes' => '',
            ];
            $payload['guidance'][$key] = [
                'label' => (string) $manualCheck['label'],
                'source' => (string) $manualCheck['source'],
                'required_for_target' => (bool) $manualCheck['required_for_target'],
                'pass_criteria' => (string) ($manualCheck['pass_criteria'] ?? ''),
                'failure_meaning' => (string) ($manualCheck['failure_meaning'] ?? ''),
                'remediation_hint' => (string) ($manualCheck['remediation_hint'] ?? ''),
                'runbook_path' => (string) ($manualCheck['runbook_path'] ?? ''),
                'operator_commands' => array_values((array) ($manualCheck['operator_commands'] ?? [])),
                'operator_notes' => array_values((array) ($manualCheck['operator_notes'] ?? [])),
            ];
        }

        $outputDirectory = dirname($resolvedOutputPath);
        if (! File::isDirectory($outputDirectory)) {
            File::makeDirectory($outputDirectory, 0755, true, true);
        }
        File::put(
            $resolvedOutputPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL
        );

        return [
            'ok' => true,
            'status' => 'ok',
            'target' => $normalizedTarget,
            'candidate' => $candidateSlug,
            'output_path' => $displayOutputPath,
            'resolved_output_path' => $resolvedOutputPath,
            'check_count' => count($manualChecks),
            'check_keys' => array_values(array_map(static fn (array $row): string => (string) $row['key'], $manualChecks)),
            'manual_checks' => array_values(array_map(
                static fn (array $row): array => [
                    'key' => (string) $row['key'],
                    'label' => (string) $row['label'],
                    'source' => (string) $row['source'],
                    'required_for_target' => (bool) $row['required_for_target'],
                    'runbook_path' => (string) ($row['runbook_path'] ?? ''),
                    'operator_commands' => array_values((array) ($row['operator_commands'] ?? [])),
                    'operator_notes' => array_values((array) ($row['operator_notes'] ?? [])),
                ],
                $manualChecks,
            )),
            'next_command' => sprintf(
                'php artisan booking:launch-readiness --target=%s --manual-evidence=%s --json',
                $normalizedTarget,
                $displayOutputPath
            ),
            'issues' => [],
            'warnings' => [],
        ];
    }

    private function normalizeCandidate(?string $candidate): string
    {
        $rawCandidate = $candidate !== null && trim($candidate) !== ''
            ? trim($candidate)
            : Carbon::now('UTC')->format('Ymd');
        $normalized = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9._-]+/', '-', $rawCandidate), '-'));

        if ($normalized === '') {
            throw new InvalidArgumentException('Candidate slug cannot be empty.');
        }

        return $normalized;
    }

    private function resolveOutputPath(string $target, string $candidate, ?string $outputPath): string
    {
        $defaultPath = sprintf('storage/app/booking_release/manual_evidence/%s-%s.json', $target, $candidate);
        $path = $outputPath !== null && trim($outputPath) !== '' ? trim($outputPath) : $defaultPath;

        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return $path;
        }

        return base_path(str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manualChecksForTarget(string $target): array
    {
        $checks = [];

        foreach ((array) config('booking_launch_readiness.manual_checks', []) as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $requiredFor = array_values(array_map('strval', (array) ($definition['required_for'] ?? [])));
            $recommendedFor = array_values(array_map('strval', (array) ($definition['recommended_for'] ?? [])));
            $requiredForTarget = in_array($target, $requiredFor, true);

            if (! $requiredForTarget && ! in_array($target, $recommendedFor, true)) {
                continue;
            }

            $checks[] = [
                'key' => (string) ($definition['key'] ?? ''),
                'label' => (string) ($definition['label'] ?? ($definition['key'] ?? 'manual_check')),
                'source' => (string) ($definition['source'] ?? ''),
                'required_for_target' => $requiredForTarget,
                'pass_criteria' => (string) ($definition['pass_criteria'] ?? ''),
                'failure_meaning' => (string) ($definition['failure_meaning'] ?? ''),
                'remediation_hint' => (string) ($definition['remediation_hint'] ?? ''),
                'runbook_path' => (string) ($definition['runbook_path'] ?? ''),
                'operator_commands' => array_values((array) ($definition['operator_commands'] ?? [])),
                'operator_notes' => array_values((array) ($definition['operator_notes'] ?? [])),
            ];
        }

        return array_values(array_filter(
            $checks,
            static fn (array $check): bool => trim((string) ($check['key'] ?? '')) !== ''
        ));
    }

    private function displayPath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedBasePath = rtrim(str_replace('\\', '/', base_path()), '/');

        if (str_starts_with($normalizedPath, $normalizedBasePath.'/')) {
            return ltrim(substr($normalizedPath, strlen($normalizedBasePath)), '/');
        }

        return $normalizedPath;
    }
}

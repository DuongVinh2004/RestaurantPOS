<?php

declare(strict_types=1);

namespace App\Services\Performance;

use App\Services\OpsGateArtifactService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PerformanceVerificationService
{
    public function __construct(
        private readonly OpsGateArtifactService $opsGateArtifactService,
    ) {}

    /**
     * @param  list<string>  $scenarioKeys
     * @return array<string, mixed>
     */
    public function runHarness(
        string $profile,
        string $baseUrl,
        string $manifestPath,
        array $scenarioKeys = [],
    ): array {
        $profileConfig = $this->resolveProfile($profile);
        $selectedScenarios = $this->selectScenarios($profile, $scenarioKeys, automatedOnly: true);
        $rawOutputRelative = $this->buildRawOutputRelativePath($profile);
        $rawOutputAbsolute = base_path($rawOutputRelative);
        File::ensureDirectoryExists($rawOutputAbsolute);

        $arguments = [
            'node',
            base_path('scripts/performance/restaurantpos-performance-pack.mjs'),
            '--base-url='.$baseUrl,
            '--manifest-path='.$this->relativePath($this->resolvePath($manifestPath)),
            '--catalog-path='.$this->relativePath((string) config('booking_performance_verification.catalog_path')),
            '--profile='.$profile,
            '--output-dir='.$this->relativePath($rawOutputAbsolute),
        ];

        foreach ($selectedScenarios as $scenario) {
            $arguments[] = '--scenario='.(string) ($scenario['key'] ?? '');
        }

        $timeoutSeconds = max(
            120,
            array_reduce($selectedScenarios, static function (int $carry, array $scenario) use ($profile): int {
                $settings = (array) (($scenario['profile_settings'] ?? [])[$profile] ?? []);
                $duration = (int) ($settings['duration_seconds'] ?? 0);

                return $carry + max(15, $duration);
            }, 0) + 180
        );

        $process = new Process($arguments, base_path(), null, null, $timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                'Performance harness failed for profile [%s]. stdout=%s stderr=%s',
                $profileConfig['label'] ?? $profile,
                trim($process->getOutput()),
                trim($process->getErrorOutput())
            ));
        }

        $indexPath = $rawOutputAbsolute.DIRECTORY_SEPARATOR.'raw-index.json';
        $index = File::exists($indexPath)
            ? json_decode((string) File::get($indexPath), true, 512, JSON_THROW_ON_ERROR)
            : null;

        return [
            'profile' => $profile,
            'raw_dir' => $rawOutputRelative,
            'raw_dir_absolute' => $rawOutputAbsolute,
            'index' => is_array($index) ? $index : null,
            'runner' => [
                'command' => $arguments,
                'stdout' => trim($process->getOutput()),
            ],
        ];
    }

    /**
     * @param  list<string>  $scenarioKeys
     * @param  array<string, mixed>  $runMeta
     * @return array<string, mixed>
     */
    public function evaluate(
        string $profile,
        string $ingestDir,
        ?string $baselinePath = null,
        bool $promoteBaseline = false,
        array $scenarioKeys = [],
        array $runMeta = [],
    ): array {
        $evaluatedAt = now('UTC');
        $profileConfig = $this->resolveProfile($profile);
        $selectedScenarios = $this->selectScenarios($profile, $scenarioKeys);
        $rawResults = $this->loadRawResults($ingestDir);
        $baseline = $this->loadBaseline($baselinePath);

        $evaluations = [];
        foreach ($selectedScenarios as $scenario) {
            $evaluations[] = $this->evaluateScenario(
                $profile,
                $profileConfig,
                $scenario,
                $rawResults[(string) ($scenario['key'] ?? '')] ?? null,
                $baseline[(string) ($scenario['key'] ?? '')] ?? null,
            );
        }

        $groups = $this->summarizeGroups((array) config('booking_performance_verification.groups', []), $evaluations);
        $blockingFailures = $this->flattenFindings($evaluations, 'blocking');
        $majorWarnings = $this->flattenFindings($evaluations, 'major');
        $informationalFindings = $this->buildInformationalFindings($evaluations);
        $manualScenarios = array_values(array_filter(
            $evaluations,
            static fn (array $scenario): bool => (string) ($scenario['automation'] ?? '') === 'operator_assisted'
        ));

        $decision = 'pass';
        $exitCode = 0;
        if ($blockingFailures !== []) {
            $decision = 'fail';
            $exitCode = 1;
        } elseif ($majorWarnings !== []) {
            $decision = 'pass_with_warnings';
            $exitCode = 2;
        }

        $report = [
            'ok' => $exitCode === 0,
            'decision' => $decision,
            'exit_code' => $exitCode,
            'profile' => [
                'key' => $profile,
                'label' => (string) ($profileConfig['label'] ?? $profile),
                'evidence_level' => (string) ($profileConfig['evidence_level'] ?? 'unknown'),
                'description' => (string) ($profileConfig['description'] ?? ''),
            ],
            'summary' => [
                'scenario_count' => count($evaluations),
                'automated_scenario_count' => count(array_filter($evaluations, static fn (array $scenario): bool => ($scenario['automation'] ?? '') === 'automated')),
                'operator_assisted_scenario_count' => count($manualScenarios),
                'blocking_failure_count' => count($blockingFailures),
                'major_warning_count' => count($majorWarnings),
                'informational_count' => count($informationalFindings),
            ],
            'groups' => $groups,
            'scenarios' => $evaluations,
            'blocking_failures' => $blockingFailures,
            'major_warnings' => $majorWarnings,
            'informational_findings' => $informationalFindings,
            'manual_scenarios' => $manualScenarios,
            'manual_gaps' => array_values(array_filter(
                $manualScenarios,
                static fn (array $scenario): bool => in_array((string) ($scenario['status'] ?? 'pass'), ['warn', 'missing'], true)
            )),
            'metrics_collected' => [
                'operation latency p50/p95/p99/mean/max',
                'operation throughput per second',
                'unexpected error rate',
                'accepted response rate',
                'HTTP status distribution',
                'controlled conflict rate',
                'duplicate rate',
                'delivery status counters (applied/ignored/failed)',
                'cleanup error count',
            ],
            'covered_endpoints' => $this->coveredEndpoints($evaluations),
            'top_bottlenecks' => $this->topBottlenecks($evaluations),
            'baseline' => [
                'requested_path' => $baselinePath !== null ? $this->relativePath($this->resolvePath($baselinePath)) : null,
                'loaded' => $baselinePath !== null && $baseline !== [],
            ],
            'raw_artifacts' => [
                'ingest_dir' => $this->relativePath($this->resolvePath($ingestDir)),
                'scenario_files_found' => count($rawResults),
            ],
            'run_meta' => $runMeta,
            'meta' => [
                'evaluated_at_utc' => $evaluatedAt->toIso8601String(),
                'warning_threshold_ratio' => (float) config('booking_performance_verification.warning_threshold_ratio', 0.9),
            ],
        ];

        $report = $this->writeArtifacts($report, $profile, $evaluatedAt);

        if ($promoteBaseline && $exitCode !== 1) {
            $baselineArtifact = $this->writeBaselineSnapshot($report, $profile, $evaluatedAt);
            $report['baseline']['promoted'] = true;
            $report['baseline']['path'] = $baselineArtifact['path'];
            $report['baseline']['latest_path'] = $baselineArtifact['latest_path'];
            File::put(
                base_path((string) (($report['artifacts'] ?? [])['json_path'] ?? '')),
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            File::put(
                base_path((string) (($report['artifacts'] ?? [])['markdown_path'] ?? '')),
                $this->renderMarkdown($report)
            );
            File::copy(
                base_path((string) (($report['artifacts'] ?? [])['json_path'] ?? '')),
                base_path((string) (($report['artifacts'] ?? [])['latest_json_path'] ?? ''))
            );
            File::copy(
                base_path((string) (($report['artifacts'] ?? [])['markdown_path'] ?? '')),
                base_path((string) (($report['artifacts'] ?? [])['latest_markdown_path'] ?? ''))
            );
        }

        return $report;
    }

    /**
     * @param  list<string>  $scenarioKeys
     * @return list<array<string, mixed>>
     */
    private function selectScenarios(string $profile, array $scenarioKeys, bool $automatedOnly = false): array
    {
        $wanted = array_values(array_filter(array_map(static fn (string $key): string => trim($key), $scenarioKeys)));
        $scenarios = array_values(array_filter((array) config('booking_performance_verification.scenarios', []), static function (array $scenario) use ($profile, $wanted, $automatedOnly): bool {
            $key = (string) ($scenario['key'] ?? '');
            if ($key === '') {
                return false;
            }

            if ($wanted !== [] && ! in_array($key, $wanted, true)) {
                return false;
            }

            if ($automatedOnly && (string) ($scenario['automation'] ?? '') !== 'automated') {
                return false;
            }

            $settings = (array) (($scenario['profile_settings'] ?? [])[$profile] ?? []);
            if ((string) ($scenario['automation'] ?? '') === 'automated') {
                return $settings !== [];
            }

            return true;
        }));

        if ($scenarios === []) {
            throw new \InvalidArgumentException(sprintf('No performance scenarios are configured for profile [%s].', $profile));
        }

        return $scenarios;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProfile(string $profile): array
    {
        $profiles = (array) config('booking_performance_verification.profiles', []);
        if (! isset($profiles[$profile]) || ! is_array($profiles[$profile])) {
            throw new \InvalidArgumentException(sprintf('Unsupported performance profile [%s].', $profile));
        }

        return $profiles[$profile];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadRawResults(string $ingestDir): array
    {
        $resolvedDir = $this->resolvePath($ingestDir);
        if (! File::isDirectory($resolvedDir)) {
            throw new \InvalidArgumentException(sprintf('Raw result directory [%s] does not exist.', $ingestDir));
        }

        $results = [];
        foreach (File::files($resolvedDir) as $file) {
            if (strtolower($file->getExtension()) !== 'json') {
                continue;
            }

            if (strtolower($file->getFilename()) === 'raw-index.json') {
                continue;
            }

            $decoded = json_decode((string) File::get($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                continue;
            }

            $key = trim((string) ($decoded['scenario_key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $results[$key] = $decoded;
        }

        return $results;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadBaseline(?string $baselinePath): array
    {
        if ($baselinePath === null || trim($baselinePath) === '') {
            return [];
        }

        $resolvedPath = $this->resolvePath($baselinePath);
        if (! File::exists($resolvedPath)) {
            return [];
        }

        $decoded = json_decode((string) File::get($resolvedPath), true, 512, JSON_THROW_ON_ERROR);
        $rows = (array) ($decoded['scenario_metrics'] ?? []);
        $baseline = [];

        foreach ($rows as $key => $row) {
            if (is_array($row)) {
                $baseline[(string) $key] = $row;
            }
        }

        return $baseline;
    }

    /**
     * @param  array<string, mixed>  $profileConfig
     * @param  array<string, mixed>  $scenario
     * @param  array<string, mixed>|null  $raw
     * @param  array<string, mixed>|null  $baseline
     * @return array<string, mixed>
     */
    private function evaluateScenario(
        string $profile,
        array $profileConfig,
        array $scenario,
        ?array $raw,
        ?array $baseline,
    ): array {
        $automation = (string) ($scenario['automation'] ?? 'automated');
        $settings = (array) (($scenario['profile_settings'] ?? [])[$profile] ?? []);
        $thresholds = (array) ($settings['thresholds'] ?? []);
        $findings = [];
        $status = 'pass';
        $summary = (string) ($scenario['pass_criteria'] ?? 'Scenario passed.');
        $metrics = $raw !== null ? $this->extractMetrics($raw) : [];

        if ($raw === null) {
            if ($automation === 'automated') {
                $status = 'fail';
                $findings[] = [
                    'severity' => 'blocking',
                    'message' => sprintf('Raw result for automated scenario [%s] is missing.', (string) ($scenario['label'] ?? $scenario['key'] ?? 'unknown')),
                ];
                $summary = 'Automated evidence is missing.';
            } elseif ((bool) ($profileConfig['warn_on_missing_operator_assisted'] ?? false)
                && in_array($profile, array_values((array) ($scenario['required_profiles'] ?? [])), true)
            ) {
                $status = 'warn';
                $findings[] = [
                    'severity' => 'major',
                    'message' => sprintf('Operator-assisted scenario [%s] has not been captured yet.', (string) ($scenario['label'] ?? $scenario['key'] ?? 'unknown')),
                ];
                $summary = 'Operator-assisted staging evidence is still missing.';
            } else {
                $status = 'missing';
                $summary = 'Scenario not executed for this evaluation window.';
            }

            return array_merge($scenario, [
                'status' => $status,
                'summary' => $summary,
                'findings' => $findings,
                'metrics' => [],
                'baseline_delta' => [],
                'raw_status' => null,
                'raw_path' => null,
            ]);
        }

        if ((string) ($raw['status'] ?? 'completed') !== 'completed') {
            $status = 'fail';
            $findings[] = [
                'severity' => $this->scenarioSeverity($scenario) === 'blocking' ? 'blocking' : 'major',
                'message' => sprintf(
                    'Scenario [%s] did not complete cleanly (status=%s).',
                    (string) ($scenario['label'] ?? $scenario['key'] ?? 'unknown'),
                    (string) ($raw['status'] ?? 'unknown')
                ),
            ];
            $summary = 'Scenario runner did not finish cleanly.';
        }

        foreach ($thresholds as $thresholdKey => $thresholdValue) {
            if (! is_numeric($thresholdValue)) {
                continue;
            }

            $actual = $metrics[$this->metricAlias((string) $thresholdKey)] ?? null;
            if (! is_numeric($actual)) {
                $findings[] = [
                    'severity' => $this->scenarioSeverity($scenario) === 'blocking' ? 'blocking' : 'major',
                    'message' => sprintf('Threshold [%s] could not be evaluated because the metric is missing.', $thresholdKey),
                ];
                $status = $this->raiseStatus($status, $this->scenarioSeverity($scenario) === 'blocking' ? 'fail' : 'warn');

                continue;
            }

            $evaluation = $this->evaluateThreshold((string) $thresholdKey, (float) $actual, (float) $thresholdValue);
            if ($evaluation['status'] === 'fail') {
                $findings[] = [
                    'severity' => $this->scenarioSeverity($scenario) === 'blocking' ? 'blocking' : 'major',
                    'message' => sprintf(
                        '%s measured %.4f against threshold %.4f.',
                        $this->humanizeThresholdKey((string) $thresholdKey),
                        (float) $actual,
                        (float) $thresholdValue
                    ),
                ];
                $status = $this->raiseStatus($status, $this->scenarioSeverity($scenario) === 'blocking' ? 'fail' : 'warn');
            } elseif ($evaluation['status'] === 'warn') {
                $findings[] = [
                    'severity' => 'major',
                    'message' => sprintf(
                        '%s is close to the threshold (actual %.4f, threshold %.4f).',
                        $this->humanizeThresholdKey((string) $thresholdKey),
                        (float) $actual,
                        (float) $thresholdValue
                    ),
                ];
                $status = $this->raiseStatus($status, 'warn');
            }
        }

        $baselineDelta = $this->baselineDelta($metrics, $baseline);

        if ($status === 'pass') {
            $summary = 'Scenario met all configured thresholds.';
        } elseif ($status === 'warn') {
            $summary = 'Scenario stayed under blocking thresholds but is close to limits or still requires operator evidence.';
        } elseif ($status === 'fail') {
            $summary = 'Scenario breached one or more required thresholds.';
        }

        return array_merge($scenario, [
            'status' => $status,
            'summary' => $summary,
            'findings' => $findings,
            'metrics' => $metrics,
            'baseline_delta' => $baselineDelta,
            'thresholds' => $thresholds,
            'raw_status' => (string) ($raw['status'] ?? 'completed'),
            'raw_path' => $raw['artifact_path'] ?? null,
            'raw_excerpt' => [
                'requests' => (array) ($raw['requests'] ?? []),
                'operations' => (array) ($raw['operations'] ?? []),
                'rates' => (array) ($raw['rates'] ?? []),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, float|int>
     */
    private function extractMetrics(array $raw): array
    {
        return [
            'latency_p50_ms' => (float) data_get($raw, 'operations.latency_ms.p50', 0.0),
            'latency_p95_ms' => (float) data_get($raw, 'operations.latency_ms.p95', 0.0),
            'latency_p99_ms' => (float) data_get($raw, 'operations.latency_ms.p99', 0.0),
            'latency_mean_ms' => (float) data_get($raw, 'operations.latency_ms.mean', 0.0),
            'latency_max_ms' => (float) data_get($raw, 'operations.latency_ms.max', 0.0),
            'throughput_rps' => (float) data_get($raw, 'operations.throughput_rps', 0.0),
            'operation_count' => (int) data_get($raw, 'operations.count', 0),
            'success_count' => (int) data_get($raw, 'operations.success_count', 0),
            'unexpected_error_rate' => (float) data_get($raw, 'rates.unexpected_error_rate', 0.0),
            'accepted_response_rate' => (float) data_get($raw, 'rates.accepted_response_rate', 0.0),
            'controlled_conflict_rate' => (float) data_get($raw, 'rates.controlled_conflict_rate', 0.0),
            'duplicate_rate' => (float) data_get($raw, 'rates.duplicate_rate', 0.0),
            'failed_delivery_rate' => (float) data_get($raw, 'rates.failed_delivery_rate', 0.0),
            'cleanup_error_count' => (int) data_get($raw, 'operations.cleanup_error_count', 0),
            'request_count' => (int) data_get($raw, 'requests.count', 0),
        ];
    }

    /**
     * @param  array<string, float|int>  $metrics
     * @param  array<string, mixed>|null  $baseline
     * @return array<string, array<string, float|string>>
     */
    private function baselineDelta(array $metrics, ?array $baseline): array
    {
        if ($baseline === null || $baseline === []) {
            return [];
        }

        $interesting = ['latency_p95_ms', 'latency_p99_ms', 'throughput_rps', 'unexpected_error_rate'];
        $deltas = [];

        foreach ($interesting as $key) {
            if (! array_key_exists($key, $baseline) || ! array_key_exists($key, $metrics)) {
                continue;
            }

            $current = (float) $metrics[$key];
            $previous = (float) $baseline[$key];
            $delta = $current - $previous;
            $direction = in_array($key, ['throughput_rps'], true)
                ? ($delta > 0 ? 'improved' : ($delta < 0 ? 'regressed' : 'flat'))
                : ($delta < 0 ? 'improved' : ($delta > 0 ? 'regressed' : 'flat'));

            $deltas[$key] = [
                'baseline' => $previous,
                'current' => $current,
                'delta' => $delta,
                'direction' => $direction,
            ];
        }

        return $deltas;
    }

    /**
     * @return array{status:string}
     */
    private function evaluateThreshold(string $thresholdKey, float $actual, float $threshold): array
    {
        $warningRatio = (float) config('booking_performance_verification.warning_threshold_ratio', 0.9);

        if (str_ends_with($thresholdKey, '_max')) {
            if ($actual > $threshold) {
                return ['status' => 'fail'];
            }

            if ($threshold > 0 && $actual < $threshold && $actual >= $threshold * $warningRatio) {
                return ['status' => 'warn'];
            }

            return ['status' => 'pass'];
        }

        if (str_ends_with($thresholdKey, '_min')) {
            if ($actual < $threshold) {
                return ['status' => 'fail'];
            }

            $warningBoundary = $threshold * (1 + (1 - $warningRatio));
            if ($threshold > 0 && $actual > $threshold && $actual <= $warningBoundary) {
                return ['status' => 'warn'];
            }

            return ['status' => 'pass'];
        }

        return ['status' => 'pass'];
    }

    private function metricAlias(string $thresholdKey): string
    {
        return str_replace(['_max', '_min'], '', $thresholdKey);
    }

    private function humanizeThresholdKey(string $thresholdKey): string
    {
        return str_replace('_', ' ', str_replace(['_max', '_min'], '', $thresholdKey));
    }

    private function raiseStatus(string $current, string $candidate): string
    {
        $weights = ['missing' => 0, 'pass' => 1, 'warn' => 2, 'fail' => 3];

        return ($weights[$candidate] ?? 0) > ($weights[$current] ?? 0)
            ? $candidate
            : $current;
    }

    private function scenarioSeverity(array $scenario): string
    {
        return (string) ($scenario['severity'] ?? 'blocking');
    }

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     * @return array<int, array<string, mixed>>
     */
    private function summarizeGroups(array $labels, array $evaluations): array
    {
        $groups = [];

        foreach ($labels as $key => $label) {
            $groupScenarios = array_values(array_filter($evaluations, static fn (array $scenario): bool => (string) ($scenario['group'] ?? '') === (string) $key));
            $groups[] = [
                'key' => (string) $key,
                'label' => (string) $label,
                'status' => $this->groupStatus($groupScenarios),
                'scenario_count' => count($groupScenarios),
                'blocking_failure_count' => count($this->flattenFindings($groupScenarios, 'blocking')),
                'major_warning_count' => count($this->flattenFindings($groupScenarios, 'major')),
            ];
        }

        return $groups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     */
    private function groupStatus(array $evaluations): string
    {
        if ($this->flattenFindings($evaluations, 'blocking') !== []) {
            return 'fail';
        }

        if ($this->flattenFindings($evaluations, 'major') !== []) {
            return 'warn';
        }

        return 'pass';
    }

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     * @return array<int, array<string, mixed>>
     */
    private function flattenFindings(array $evaluations, string $severity): array
    {
        $findings = [];
        foreach ($evaluations as $scenario) {
            foreach ((array) ($scenario['findings'] ?? []) as $finding) {
                if ((string) ($finding['severity'] ?? '') !== $severity) {
                    continue;
                }

                $findings[] = [
                    'group' => (string) ($scenario['group'] ?? ''),
                    'scenario_key' => (string) ($scenario['key'] ?? ''),
                    'scenario_label' => (string) ($scenario['label'] ?? ''),
                    'type' => (string) ($scenario['type'] ?? ''),
                    'automation' => (string) ($scenario['automation'] ?? ''),
                    'severity' => $severity,
                    'message' => (string) ($finding['message'] ?? ''),
                ];
            }
        }

        return $findings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     * @return array<int, array<string, mixed>>
     */
    private function buildInformationalFindings(array $evaluations): array
    {
        $findings = [];

        foreach ($evaluations as $scenario) {
            if (! in_array((string) ($scenario['status'] ?? ''), ['pass', 'missing'], true)) {
                continue;
            }

            $findings[] = [
                'group' => (string) ($scenario['group'] ?? ''),
                'scenario_key' => (string) ($scenario['key'] ?? ''),
                'scenario_label' => (string) ($scenario['label'] ?? ''),
                'message' => (string) ($scenario['summary'] ?? ''),
            ];
        }

        return $findings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     * @return list<string>
     */
    private function coveredEndpoints(array $evaluations): array
    {
        $endpoints = [];
        foreach ($evaluations as $scenario) {
            foreach ((array) ($scenario['endpoints'] ?? []) as $endpoint) {
                $endpoints[] = (string) $endpoint;
            }
        }

        $endpoints = array_values(array_unique(array_filter($endpoints)));
        sort($endpoints);

        return $endpoints;
    }

    /**
     * @param  array<int, array<string, mixed>>  $evaluations
     * @return array<int, array<string, mixed>>
     */
    private function topBottlenecks(array $evaluations): array
    {
        $ranked = [];

        foreach ($evaluations as $scenario) {
            if (in_array((string) ($scenario['status'] ?? ''), ['missing'], true)) {
                continue;
            }

            $metrics = (array) ($scenario['metrics'] ?? []);
            $thresholds = (array) ($scenario['thresholds'] ?? []);
            $score = 0.0;

            foreach ($thresholds as $key => $threshold) {
                if (! is_numeric($threshold)) {
                    continue;
                }

                if (str_starts_with((string) $key, 'success_count_')) {
                    continue;
                }

                $metricKey = $this->metricAlias((string) $key);
                if (! isset($metrics[$metricKey]) || ! is_numeric($metrics[$metricKey])) {
                    continue;
                }

                $actual = (float) $metrics[$metricKey];
                $thresholdValue = (float) $threshold;
                if ($thresholdValue <= 0.0) {
                    continue;
                }

                $ratio = str_ends_with((string) $key, '_min')
                    ? ($actual > 0.0 ? $thresholdValue / max($actual, 0.0001) : 1000.0)
                    : $actual / $thresholdValue;
                $score = max($score, $ratio);
            }

            if ($score <= 0.0 && isset($metrics['latency_p95_ms'])) {
                $score = (float) $metrics['latency_p95_ms'];
            }

            $ranked[] = [
                'scenario_key' => (string) ($scenario['key'] ?? ''),
                'scenario_label' => (string) ($scenario['label'] ?? ''),
                'status' => (string) ($scenario['status'] ?? ''),
                'score' => round($score, 4),
                'p95_latency_ms' => (float) ($metrics['latency_p95_ms'] ?? 0.0),
                'unexpected_error_rate' => (float) ($metrics['unexpected_error_rate'] ?? 0.0),
                'throughput_rps' => (float) ($metrics['throughput_rps'] ?? 0.0),
            ];
        }

        usort($ranked, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_slice($ranked, 0, 5);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function writeArtifacts(array $report, string $profile, Carbon $evaluatedAt): array
    {
        return $this->opsGateArtifactService->writeReport(
            artifactRoot: trim((string) config('booking_performance_verification.artifact_root', 'storage/app/booking_release/performance_verification')),
            reportPrefix: 'performance-verification',
            scopeKey: $profile,
            payload: $report,
            markdown: $this->renderMarkdown($report),
            evaluatedAt: $evaluatedAt,
        );
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, string>
     */
    private function writeBaselineSnapshot(array $report, string $profile, Carbon $evaluatedAt): array
    {
        $artifactRoot = trim((string) config('booking_performance_verification.artifact_root', 'storage/app/booking_release/performance_verification'));
        $baselineRelative = trim($artifactRoot.'/baselines', '/');
        $baselineAbsolute = base_path($baselineRelative);
        File::ensureDirectoryExists($baselineAbsolute);

        $profileSlug = Str::slug($profile, '-');
        $timestamp = $evaluatedAt->copy()->utc()->format('Ymd\THis\Z');
        $baseName = sprintf('performance-baseline-%s-%s', $profileSlug !== '' ? $profileSlug : 'default', strtolower($timestamp));
        $relativePath = $baselineRelative.'/'.$baseName.'.json';
        $latestRelativePath = $baselineRelative.'/latest-'.($profileSlug !== '' ? $profileSlug : 'default').'.json';

        $payload = [
            'profile' => $profile,
            'captured_at_utc' => $evaluatedAt->toIso8601String(),
            'source_report' => (string) (($report['artifacts'] ?? [])['json_path'] ?? ''),
            'scenario_metrics' => [],
        ];

        foreach ((array) ($report['scenarios'] ?? []) as $scenario) {
            $payload['scenario_metrics'][(string) ($scenario['key'] ?? '')] = array_intersect_key(
                (array) ($scenario['metrics'] ?? []),
                array_flip([
                    'latency_p50_ms',
                    'latency_p95_ms',
                    'latency_p99_ms',
                    'latency_mean_ms',
                    'latency_max_ms',
                    'throughput_rps',
                    'operation_count',
                    'success_count',
                    'unexpected_error_rate',
                    'accepted_response_rate',
                    'controlled_conflict_rate',
                    'duplicate_rate',
                    'failed_delivery_rate',
                    'cleanup_error_count',
                    'request_count',
                ])
            );
        }

        File::put(base_path($relativePath), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        File::copy(base_path($relativePath), base_path($latestRelativePath));

        return [
            'path' => $relativePath,
            'latest_path' => $latestRelativePath,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderMarkdown(array $report): string
    {
        $lines = [];
        $lines[] = '# Booking Performance Verification';
        $lines[] = '';
        $lines[] = sprintf('- Evaluated at: `%s`', (string) (($report['meta'] ?? [])['evaluated_at_utc'] ?? ''));
        $lines[] = sprintf('- Profile: `%s`', (string) (($report['profile'] ?? [])['label'] ?? ($report['profile'] ?? [])['key'] ?? ''));
        $lines[] = sprintf('- Decision: `%s`', strtoupper((string) ($report['decision'] ?? 'unknown')));
        $lines[] = sprintf('- Exit code: `%s`', (string) ($report['exit_code'] ?? '1'));
        $lines[] = '';
        $lines[] = '## Group Summary';
        $lines[] = '';
        $lines[] = '| Group | Status | Blocking | Warnings |';
        $lines[] = '| --- | --- | ---: | ---: |';

        foreach ((array) ($report['groups'] ?? []) as $group) {
            $lines[] = sprintf(
                '| %s | %s | %d | %d |',
                (string) ($group['label'] ?? $group['key'] ?? ''),
                strtoupper((string) ($group['status'] ?? 'unknown')),
                (int) ($group['blocking_failure_count'] ?? 0),
                (int) ($group['major_warning_count'] ?? 0)
            );
        }

        $lines[] = '';
        $lines[] = '## Scenario Summary';
        $lines[] = '';
        $lines[] = '| Scenario | Type | Automation | Status | p95 ms | Error rate | Throughput |';
        $lines[] = '| --- | --- | --- | --- | ---: | ---: | ---: |';

        foreach ((array) ($report['scenarios'] ?? []) as $scenario) {
            $metrics = (array) ($scenario['metrics'] ?? []);
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %.2f | %.4f | %.2f |',
                str_replace('|', '\|', (string) ($scenario['label'] ?? $scenario['key'] ?? '')),
                (string) ($scenario['type'] ?? ''),
                (string) ($scenario['automation'] ?? ''),
                strtoupper((string) ($scenario['status'] ?? 'unknown')),
                (float) ($metrics['latency_p95_ms'] ?? 0.0),
                (float) ($metrics['unexpected_error_rate'] ?? 0.0),
                (float) ($metrics['throughput_rps'] ?? 0.0)
            );
        }

        $lines[] = '';
        $lines[] = '## Blocking Failures';
        $lines[] = '';
        if ((array) ($report['blocking_failures'] ?? []) === []) {
            $lines[] = '- None.';
        } else {
            foreach ((array) ($report['blocking_failures'] ?? []) as $finding) {
                $lines[] = sprintf('- [%s] %s', (string) ($finding['scenario_label'] ?? $finding['scenario_key'] ?? ''), (string) ($finding['message'] ?? ''));
            }
        }

        $lines[] = '';
        $lines[] = '## Major Warnings';
        $lines[] = '';
        if ((array) ($report['major_warnings'] ?? []) === []) {
            $lines[] = '- None.';
        } else {
            foreach ((array) ($report['major_warnings'] ?? []) as $finding) {
                $lines[] = sprintf('- [%s] %s', (string) ($finding['scenario_label'] ?? $finding['scenario_key'] ?? ''), (string) ($finding['message'] ?? ''));
            }
        }

        $lines[] = '';
        $lines[] = '## Top Bottlenecks';
        $lines[] = '';
        if ((array) ($report['top_bottlenecks'] ?? []) === []) {
            $lines[] = '- None.';
        } else {
            foreach ((array) ($report['top_bottlenecks'] ?? []) as $row) {
                $lines[] = sprintf(
                    '- [%s] status=%s score=%.4f p95=%.2fms unexpected_error_rate=%.4f throughput=%.2frps',
                    (string) ($row['scenario_label'] ?? $row['scenario_key'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (float) ($row['score'] ?? 0.0),
                    (float) ($row['p95_latency_ms'] ?? 0.0),
                    (float) ($row['unexpected_error_rate'] ?? 0.0),
                    (float) ($row['throughput_rps'] ?? 0.0)
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Manual / Operator-Assisted';
        $lines[] = '';
        if ((array) ($report['manual_scenarios'] ?? []) === []) {
            $lines[] = '- None.';
        } else {
            foreach ((array) ($report['manual_scenarios'] ?? []) as $scenario) {
                $lines[] = sprintf(
                    '- [%s] %s',
                    strtoupper((string) ($scenario['status'] ?? 'unknown')),
                    (string) ($scenario['label'] ?? $scenario['key'] ?? '')
                );
            }
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function buildRawOutputRelativePath(string $profile): string
    {
        $artifactRoot = trim((string) config('booking_performance_verification.artifact_root', 'storage/app/booking_release/performance_verification'));
        $profileSlug = Str::slug($profile, '-');
        $timestamp = now('UTC')->format('Ymd\THis\Z');

        return trim(sprintf('%s/raw/%s-%s', $artifactRoot, $profileSlug !== '' ? $profileSlug : 'default', strtolower($timestamp)), '/');
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\\\')) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }

    private function relativePath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', base_path());

        if (str_starts_with($normalizedPath, $base.'/')) {
            return ltrim(substr($normalizedPath, strlen($base)), '/');
        }

        if ($normalizedPath === $base) {
            return '.';
        }

        return $normalizedPath;
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\Release\Services;

use App\Services\Concerns\BuildsPhpUnitTestingEnvironment;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class RoundFiveGateService
{
    use BuildsPhpUnitTestingEnvironment;

    /**
     * @return array{
     *   suite: string,
     *   description: string,
     *   definition_path: string,
     *   snapshot_path: string,
     *   definition_sha256: string,
     *   tests: list<array{key: string, path: string, category: string}>
     * }
     */
    public function definition(): array
    {
        $relativeDefinitionPath = trim((string) config('booking_release.round5_gate.definition_path', 'tests/fixtures/round5_gate_suite.json'));
        $definitionAbsolutePath = base_path($relativeDefinitionPath);

        if (! File::exists($definitionAbsolutePath)) {
            throw new \RuntimeException(sprintf('Round 5 gate definition [%s] is missing.', $relativeDefinitionPath));
        }

        $raw = File::get($definitionAbsolutePath);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException(sprintf('Round 5 gate definition [%s] is not valid JSON.', $relativeDefinitionPath));
        }

        $suite = trim((string) ($decoded['suite'] ?? ''));
        $description = trim((string) ($decoded['description'] ?? ''));
        $tests = array_values(array_filter(array_map(function ($entry) {
            if (! is_array($entry)) {
                return null;
            }

            $key = trim((string) ($entry['key'] ?? ''));
            $path = trim((string) ($entry['path'] ?? ''));
            $category = trim((string) ($entry['category'] ?? 'feature'));
            if ($key === '' || $path === '') {
                return null;
            }

            return [
                'key' => $key,
                'path' => $path,
                'category' => $category !== '' ? $category : 'feature',
            ];
        }, (array) ($decoded['tests'] ?? []))));

        if ($suite === '' || $tests === []) {
            throw new \RuntimeException(sprintf('Round 5 gate definition [%s] is missing suite metadata or test entries.', $relativeDefinitionPath));
        }

        return [
            'suite' => $suite,
            'description' => $description !== '' ? $description : 'Canonical Round 5 booking gate.',
            'definition_path' => $relativeDefinitionPath,
            'snapshot_path' => trim((string) config('booking_release.round5_gate.snapshot_path', 'storage/app/booking_release/round5_gate_snapshot.json')),
            'definition_sha256' => hash('sha256', $raw),
            'tests' => $tests,
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   suite: string,
     *   description: string,
     *   definition_path: string,
     *   snapshot_path: string,
     *   tests: list<array{key: string, path: string, category: string, ok: bool, exit_code: int, duration_ms: int, output_tail: string}>,
     *   summary: array{total: int, passed: int, failed: int},
     *   meta: array<string, mixed>
     * }
     */
    public function run(bool $write = false): array
    {
        $definition = $this->definition();
        $results = [];
        $passed = 0;
        $failed = 0;

        foreach ($definition['tests'] as $test) {
            $result = $this->runSingleTest($test);
            $results[] = $result;
            if ($result['ok']) {
                $passed++;
            } else {
                $failed++;
            }
        }

        $snapshot = [
            'ok' => ($failed === 0),
            'suite' => $definition['suite'],
            'description' => $definition['description'],
            'definition_path' => $definition['definition_path'],
            'snapshot_path' => $definition['snapshot_path'],
            'tests' => $results,
            'summary' => [
                'total' => count($results),
                'passed' => $passed,
                'failed' => $failed,
            ],
            'meta' => [
                'generated_at_utc' => now('UTC')->toIso8601String(),
                'environment' => app()->environment(),
                'definition_sha256' => $definition['definition_sha256'],
                'write_requested' => $write,
            ],
        ];

        if ($write) {
            $this->writeSnapshot($snapshot, $definition['snapshot_path']);
        }

        return $snapshot;
    }

    /**
     * @param array{key: string, path: string, category: string} $test
     * @return array{key: string, path: string, category: string, ok: bool, exit_code: int, duration_ms: int, output_tail: string}
     */
    protected function runSingleTest(array $test): array
    {
        $path = (string) $test['path'];
        $start = microtime(true);

        try {
            $artisanPath = base_path('artisan');
            if (! File::exists($artisanPath)) {
                throw new \RuntimeException('Missing artisan bootstrap; cannot execute round 5 gate tests.');
            }

            $process = new Process(
                $this->buildArtisanTestCommand($path),
                base_path(),
                $this->buildPhpUnitTestingEnvironment(),
            );
            $process->setTimeout(null);
            $process->run();

            return [
                'key' => (string) $test['key'],
                'path' => $path,
                'category' => (string) $test['category'],
                'ok' => $process->isSuccessful(),
                'exit_code' => (int) ($process->getExitCode() ?? 1),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'output_tail' => $this->truncateOutput(trim($process->getOutput() . "\n" . $process->getErrorOutput())),
            ];
        } catch (Throwable $e) {
            return [
                'key' => (string) $test['key'],
                'path' => $path,
                'category' => (string) $test['category'],
                'ok' => false,
                'exit_code' => 1,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'output_tail' => $this->truncateOutput($e->getMessage()),
            ];
        }
    }


    /**
     * @return list<string>
     */
    protected function buildArtisanTestCommand(string $path): array
    {
        return [
            PHP_BINARY ?: 'php',
            base_path('artisan'),
            'test',
            $path,
            '--env=testing',
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    protected function writeSnapshot(array $snapshot, string $relativePath): void
    {
        $absolutePath = base_path($relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function truncateOutput(string $output, int $maxLength = 4000): string
    {
        $output = trim($output);
        if ($output === '' || strlen($output) <= $maxLength) {
            return $output;
        }

        return substr($output, -$maxLength);
    }
}

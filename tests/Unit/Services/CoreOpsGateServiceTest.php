<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\Release\Services\CoreOpsGateService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CoreOpsGateServiceTest extends TestCase
{
    public function test_definition_reads_canonical_core_ops_suite(): void
    {
        $service = new CoreOpsGateService();

        $definition = $service->definition();

        $this->assertSame('core_ops', $definition['suite']);
        $this->assertSame('tests/fixtures/core_ops_gate_suite.json', $definition['definition_path']);
        $this->assertSame('storage/app/booking_release/core_ops_gate_snapshot.json', $definition['snapshot_path']);
        $this->assertNotEmpty($definition['definition_sha256']);
        $this->assertContains(
            'tests/Feature/Table/TableHoldHttpFlowTest.php',
            array_column($definition['tests'], 'path')
        );
    }

    public function test_build_artisan_test_command_uses_supported_arguments_only(): void
    {
        $service = new class extends CoreOpsGateService {
            /**
             * @return list<string>
             */
            public function exposeBuildArtisanTestCommand(string $path): array
            {
                return $this->buildArtisanTestCommand($path);
            }
        };

        $command = $service->exposeBuildArtisanTestCommand('tests/Feature/Table/TableHoldHttpFlowTest.php');

        $this->assertSame(PHP_BINARY ?: 'php', $command[0]);
        $this->assertSame(base_path('artisan'), $command[1]);
        $this->assertSame('test', $command[2]);
        $this->assertSame('tests/Feature/Table/TableHoldHttpFlowTest.php', $command[3]);
        $this->assertNotContains('--without-tty', $command);
    }

    public function test_testing_subprocess_environment_uses_phpunit_contract_values(): void
    {
        $service = new class extends CoreOpsGateService {
            /**
             * @return array<string, string>
             */
            public function exposeTestingSubprocessEnvironment(): array
            {
                return $this->buildPhpUnitTestingEnvironment();
            }
        };

        $environment = $service->exposeTestingSubprocessEnvironment();

        $this->assertSame('testing', $environment['APP_ENV'] ?? null);
        $this->assertSame('sqlite', $environment['DB_CONNECTION'] ?? null);
        $this->assertSame(':memory:', $environment['DB_DATABASE'] ?? null);
        $this->assertSame('array', $environment['CACHE_STORE'] ?? null);
        $this->assertSame('sync', $environment['QUEUE_CONNECTION'] ?? null);
    }

    public function test_run_can_write_snapshot_without_shelling_out_when_single_test_runner_is_overridden(): void
    {
        $snapshotPath = 'storage/framework/testing/core_ops_gate/test_snapshot.json';
        config()->set('booking_release.core_ops_gate.snapshot_path', $snapshotPath);

        $service = new class extends CoreOpsGateService {
            protected function runSingleTest(array $test): array
            {
                return array_merge($test, [
                    'ok' => ! str_contains((string) $test['key'], 'staff_waiting_list'),
                    'exit_code' => str_contains((string) $test['key'], 'staff_waiting_list') ? 1 : 0,
                    'duration_ms' => 15,
                    'output_tail' => 'synthetic',
                ]);
            }
        };

        $snapshot = $service->run(write: true);
        $absolutePath = base_path($snapshotPath);

        $this->assertFalse($snapshot['ok']);
        $this->assertSame(count($snapshot['tests']), $snapshot['summary']['total']);
        $this->assertSame(1, $snapshot['summary']['failed']);
        $this->assertTrue(File::exists($absolutePath));

        $stored = json_decode((string) File::get($absolutePath), true);
        $this->assertIsArray($stored);
        $this->assertSame('core_ops', $stored['suite']);
        $this->assertSame($snapshotPath, $stored['snapshot_path']);

        File::delete($absolutePath);
    }
}

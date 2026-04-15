<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Backup\DisasterRecovery\DisasterRecoveryDatabaseProbe;
use App\Platform\Backup\DisasterRecovery\DisasterRecoveryProcessRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class BookingDisasterRecoveryDrillCommandTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/disaster_recovery_command';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_disaster_recovery.artifact_root', $this->artifactRoot);
        Carbon::setTestNow(Carbon::parse('2026-04-05 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory(base_path($this->artifactRoot));
        File::deleteDirectory(base_path('storage/framework/testing/disaster_recovery_bad_hash'));

        parent::tearDown();
    }

    #[Group('booking-smoke')]
    public function test_booking_dr_drill_can_pass_metadata_verify_with_valid_manifest_fixture(): void
    {
        $runner = new FakeDisasterRecoveryProcessRunner;
        $probe = new FakeDisasterRecoveryDatabaseProbe([]);
        $this->app->instance(DisasterRecoveryProcessRunner::class, $runner);
        $this->app->instance(DisasterRecoveryDatabaseProbe::class, $probe);

        $exitCode = Artisan::call('booking:dr-drill', [
            '--mode' => 'metadata-verify',
            '--manifest' => 'tests/fixtures/disaster_recovery/backup_bundle/manifest.json',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('pass', $payload['decision'] ?? null);
        $this->assertSame($this->artifactRoot.'/reports', $payload['artifacts']['reports_root'] ?? null);
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['json_path'] ?? '')));
        $this->assertFileExists(base_path((string) (($payload['artifacts'] ?? [])['markdown_path'] ?? '')));
        $this->assertCount(0, $runner->phpToolCalls);
        $this->assertCount(0, $runner->artisanCalls);
        $this->assertSame(0, $probe->calls);
    }

    #[Group('booking-smoke')]
    public function test_booking_dr_drill_blocks_metadata_when_manifest_hash_mismatches(): void
    {
        $runner = new FakeDisasterRecoveryProcessRunner;
        $probe = new FakeDisasterRecoveryDatabaseProbe([]);
        $this->app->instance(DisasterRecoveryProcessRunner::class, $runner);
        $this->app->instance(DisasterRecoveryDatabaseProbe::class, $probe);

        $fixtureRoot = base_path('storage/framework/testing/disaster_recovery_bad_hash');
        File::copyDirectory(base_path('tests/fixtures/disaster_recovery/backup_bundle'), $fixtureRoot);

        $manifestPath = $fixtureRoot.'/manifest.json';
        $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifest['artifacts']['full']['sha256'] = str_repeat('a', 64);
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $exitCode = Artisan::call('booking:dr-drill', [
            '--mode' => 'metadata-verify',
            '--manifest' => $manifestPath,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('fail', $payload['decision'] ?? null);
        $this->assertTrue(
            collect((array) ($payload['blocking_failures'] ?? []))
                ->contains(static fn (array $finding): bool => str_contains((string) ($finding['message'] ?? ''), 'full artifact'))
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_dr_drill_blocks_dry_restore_when_target_database_is_not_isolated(): void
    {
        $runner = new FakeDisasterRecoveryProcessRunner;
        $probe = new FakeDisasterRecoveryDatabaseProbe([]);
        $this->app->instance(DisasterRecoveryProcessRunner::class, $runner);
        $this->app->instance(DisasterRecoveryDatabaseProbe::class, $probe);

        $exitCode = Artisan::call('booking:dr-drill', [
            '--mode' => 'dry-restore',
            '--manifest' => 'tests/fixtures/disaster_recovery/backup_bundle/manifest.json',
            '--target-db' => 'restaurant_pos',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('fail', $payload['decision'] ?? null);
        $this->assertCount(0, $runner->phpToolCalls);
        $this->assertTrue(
            collect((array) ($payload['blocking_failures'] ?? []))
                ->contains(static fn (array $finding): bool => str_contains((string) ($finding['message'] ?? ''), 'does not look isolated'))
        );
    }

    #[Group('booking-smoke')]
    public function test_booking_dr_drill_can_pass_full_isolated_restore_with_fake_restore_and_probe_evidence(): void
    {
        $runner = new FakeDisasterRecoveryProcessRunner(
            phpToolQueue: [
                [
                    'exit_code' => 0,
                    'stdout' => json_encode($this->successfulRestorePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'stderr' => '',
                    'command' => ['php', 'tools/mysql/restore_release.php'],
                ],
            ],
            artisanQueue: [
                [
                    'exit_code' => 0,
                    'stdout' => json_encode($this->successfulReleaseManifestPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'stderr' => '',
                    'command' => ['php', 'artisan', 'booking:release-manifest'],
                ],
                [
                    'exit_code' => 0,
                    'stdout' => json_encode($this->successfulCoreOpsPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    'stderr' => '',
                    'command' => ['php', 'artisan', 'booking:core-ops-gate'],
                ],
            ],
        );
        $probe = new FakeDisasterRecoveryDatabaseProbe($this->successfulProbePayload());

        $this->app->instance(DisasterRecoveryProcessRunner::class, $runner);
        $this->app->instance(DisasterRecoveryDatabaseProbe::class, $probe);

        $exitCode = Artisan::call('booking:dr-drill', [
            '--mode' => 'full-isolated-restore',
            '--manifest' => 'tests/fixtures/disaster_recovery/backup_bundle/manifest.json',
            '--target-db' => 'restaurant_pos_restore_drill',
            '--drop-target-first' => true,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('pass', $payload['decision'] ?? null);
        $this->assertCount(1, $runner->phpToolCalls);
        $this->assertCount(2, $runner->artisanCalls);
        $this->assertSame(1, $probe->calls);
        $this->assertSame('pass', data_get($payload, 'checks.10.status'));
        $this->assertSame('pass', data_get($payload, 'checks.11.status'));
        $this->assertSame('pass', data_get($payload, 'recovery_objectives.rto_status'));
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulRestorePayload(): array
    {
        return [
            'ok' => true,
            'timestamp_utc' => '2026-04-05T10:00:00Z',
            'selected_artifact' => [
                'name' => 'full',
                'path' => base_path('tests/fixtures/disaster_recovery/backup_bundle/full.sql'),
            ],
            'steps' => [
                'target_database.ensure_exists' => [
                    'status' => 'ok',
                    'message' => 'Target database is ready.',
                    'details' => ['exit_code' => 0],
                ],
                'target_database.inspect' => [
                    'status' => 'ok',
                    'message' => 'Target database is empty.',
                    'details' => ['table_count' => 0],
                ],
                'restore.import' => [
                    'status' => 'ok',
                    'message' => 'Full artifact imported.',
                    'details' => ['exit_code' => 0],
                ],
                'verify.release_contract' => [
                    'status' => 'ok',
                    'message' => 'Release contract SQL verification passed.',
                    'details' => ['exit_code' => 0],
                ],
                'verify.booking_doctor' => [
                    'status' => 'ok',
                    'message' => 'booking:doctor passed against restored target.',
                    'details' => [
                        'exit_code' => 0,
                        'stdout' => json_encode([
                            'ok' => true,
                            'validation' => [
                                'ok' => true,
                                'errors' => [],
                                'warnings' => [],
                            ],
                            'runtime' => [
                                'db' => ['ok' => true, 'message' => 'ok'],
                                'scheduler' => ['ok' => true, 'message' => 'ok'],
                            ],
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'verify.booking_deploy_check' => [
                    'status' => 'ok',
                    'message' => 'booking:deploy-check postflight passed against restored target.',
                    'details' => [
                        'exit_code' => 0,
                        'stdout' => json_encode([
                            'ok' => true,
                            'report' => [
                                'errors' => [],
                                'warnings' => [],
                                'summary' => [
                                    'data_guard_error_count' => 0,
                                ],
                            ],
                        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ],
                ],
            ],
            'warnings' => [],
            'errors' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulProbePayload(): array
    {
        return [
            'ok' => true,
            'schema_summary' => [
                'table_count' => 42,
                'view_count' => 0,
                'trigger_count' => 2,
                'procedure_count' => 1,
                'function_count' => 0,
                'event_count' => 0,
            ],
            'required_tables' => [
                'users' => ['exists' => true],
                'reservations' => ['exists' => true],
                'restaurant_tables' => ['exists' => true],
                'waiting_list' => ['exists' => true],
                'payments' => ['exists' => true],
                'notification_outbox' => ['exists' => true],
            ],
            'samples' => [
                'users' => ['exists' => true, 'row_count' => 1, 'rows' => [['user_id' => 1, 'role_id' => 10]]],
                'restaurant_tables' => ['exists' => true, 'row_count' => 1, 'rows' => [['table_id' => 1, 'capacity' => 4]]],
            ],
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulReleaseManifestPayload(): array
    {
        return [
            'ok' => true,
            'status' => 'ok',
            'issues' => [],
            'path' => 'storage/app/booking_release/release_manifest_snapshot.json',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulCoreOpsPayload(): array
    {
        return [
            'ok' => true,
            'suite' => 'core_ops',
            'summary' => [
                'total' => 2,
                'passed' => 2,
                'failed' => 0,
            ],
            'tests' => [
                [
                    'key' => 'table_hold_http_flow',
                    'ok' => true,
                    'output_tail' => '',
                ],
                [
                    'key' => 'reservation_http_flow',
                    'ok' => true,
                    'output_tail' => '',
                ],
            ],
        ];
    }
}

class FakeDisasterRecoveryProcessRunner extends DisasterRecoveryProcessRunner
{
    /**
     * @param  list<array{exit_code: int, stdout: string, stderr: string, command: list<string>}>  $phpToolQueue
     * @param  list<array{exit_code: int, stdout: string, stderr: string, command: list<string>}>  $artisanQueue
     */
    public function __construct(
        public array $phpToolQueue = [],
        public array $artisanQueue = [],
    ) {}

    /** @var list<array<string, mixed>> */
    public array $phpToolCalls = [];

    /** @var list<array<string, mixed>> */
    public array $artisanCalls = [];

    public function runPhpTool(string $relativeScriptPath, array $arguments = [], array $environment = []): array
    {
        $this->phpToolCalls[] = [
            'script' => $relativeScriptPath,
            'arguments' => $arguments,
            'environment' => $environment,
        ];

        if ($this->phpToolQueue === []) {
            throw new \RuntimeException(sprintf('Unexpected php tool call for [%s].', $relativeScriptPath));
        }

        return array_shift($this->phpToolQueue);
    }

    public function runArtisan(array $arguments, array $environment = []): array
    {
        $this->artisanCalls[] = [
            'arguments' => $arguments,
            'environment' => $environment,
        ];

        if ($this->artisanQueue === []) {
            throw new \RuntimeException('Unexpected artisan call in fake runner.');
        }

        return array_shift($this->artisanQueue);
    }
}

class FakeDisasterRecoveryDatabaseProbe extends DisasterRecoveryDatabaseProbe
{
    public int $calls = 0;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function inspect(array $targetConnection): array
    {
        $this->calls++;

        return $this->payload;
    }
}

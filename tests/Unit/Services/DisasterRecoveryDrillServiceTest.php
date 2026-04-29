<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\ApiContract\Services\OpsGateArtifactService;
use App\Platform\Backup\DisasterRecovery\DisasterRecoveryDatabaseProbe;
use App\Platform\Backup\DisasterRecovery\DisasterRecoveryDrillService;
use App\Platform\Backup\DisasterRecovery\DisasterRecoveryProcessRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class DisasterRecoveryDrillServiceTest extends TestCase
{
    private string $artifactRoot = 'storage/framework/testing/disaster_recovery_unit';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking_disaster_recovery.artifact_root', $this->artifactRoot);
        config()->set('booking_disaster_recovery.rpo_target_minutes', 60 * 24 * 60);
        Carbon::setTestNow(Carbon::parse('2026-04-28T10:00:00Z'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory(base_path($this->artifactRoot));

        parent::tearDown();
    }

    public function test_full_isolated_restore_report_exposes_safe_launch_evidence_fields(): void
    {
        $runner = new UnitFakeDisasterRecoveryProcessRunner(
            phpToolQueue: [
                [
                    'exit_code' => 0,
                    'stdout' => json_encode($this->successfulRestorePayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'stderr' => '',
                    'command' => ['php', 'tools/mysql/restore_release.php'],
                ],
            ],
            artisanQueue: [
                [
                    'exit_code' => 0,
                    'stdout' => json_encode(['ok' => true, 'status' => 'ok', 'issues' => []], JSON_THROW_ON_ERROR),
                    'stderr' => '',
                    'command' => ['php', 'artisan', 'booking:release-manifest'],
                ],
                [
                    'exit_code' => 0,
                    'stdout' => json_encode(['ok' => true, 'suite' => 'core_ops', 'summary' => ['failed' => 0], 'tests' => []], JSON_THROW_ON_ERROR),
                    'stderr' => '',
                    'command' => ['php', 'artisan', 'booking:core-ops-gate'],
                ],
            ],
        );

        $report = $this->service($runner)->run(
            mode: 'full-isolated-restore',
            manifestPath: 'tests/fixtures/disaster_recovery/backup_bundle/manifest.json',
            targetOverrides: ['database' => 'restaurant_pos_restore_drill'],
            dropTargetFirst: true,
        );

        $this->assertTrue((bool) ($report['ok'] ?? false));
        $this->assertSame('pass', $report['decision'] ?? null);
        $this->assertSame('restaurant_pos_restore_drill', data_get($report, 'launch_evidence.restore_target'));
        $this->assertStringContainsString('full tests/fixtures/disaster_recovery/backup_bundle/full.sql', (string) data_get($report, 'launch_evidence.restored_dump_identifier'));
        $this->assertStringContainsString('booking:dr-drill --mode=full-isolated-restore', (string) data_get($report, 'launch_evidence.verification_command'));
        $this->assertSame('pass', data_get($report, 'launch_evidence.verification_result'));
        $this->assertTrue((bool) data_get($report, 'launch_evidence.safe_to_commit'));
        $this->assertFalse((bool) data_get($report, 'launch_evidence.secret_fields_included'));
        $this->assertCount(1, $runner->phpToolCalls);
    }

    private function service(UnitFakeDisasterRecoveryProcessRunner $runner): DisasterRecoveryDrillService
    {
        return new DisasterRecoveryDrillService(
            $runner,
            new UnitFakeDisasterRecoveryDatabaseProbe([
                'ok' => true,
                'schema_summary' => ['table_count' => 10],
                'required_tables' => [
                    'users' => ['exists' => true],
                    'reservations' => ['exists' => true],
                    'restaurant_tables' => ['exists' => true],
                    'waiting_list' => ['exists' => true],
                    'payments' => ['exists' => true],
                    'notification_outbox' => ['exists' => true],
                ],
                'samples' => [
                    'users' => ['exists' => true, 'row_count' => 1, 'rows' => [['user_id' => 1]]],
                    'restaurant_tables' => ['exists' => true, 'row_count' => 1, 'rows' => [['table_id' => 1]]],
                ],
                'errors' => [],
                'warnings' => [],
            ]),
            new OpsGateArtifactService,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulRestorePayload(): array
    {
        return [
            'ok' => true,
            'selected_artifact' => [
                'name' => 'full',
                'path' => 'tests/fixtures/disaster_recovery/backup_bundle/full.sql',
            ],
            'steps' => [
                'target_database.ensure_exists' => ['status' => 'ok', 'message' => 'Target ready.', 'details' => ['exit_code' => 0]],
                'target_database.inspect' => ['status' => 'ok', 'message' => 'Target empty.', 'details' => ['table_count' => 0]],
                'restore.import' => ['status' => 'ok', 'message' => 'Imported.', 'details' => ['exit_code' => 0]],
                'verify.release_contract' => ['status' => 'ok', 'message' => 'SQL verification passed.', 'details' => ['exit_code' => 0]],
                'verify.booking_doctor' => [
                    'status' => 'ok',
                    'message' => 'doctor passed.',
                    'details' => [
                        'exit_code' => 0,
                        'stdout' => json_encode(['ok' => true, 'validation' => ['ok' => true, 'errors' => [], 'warnings' => []], 'runtime' => []], JSON_THROW_ON_ERROR),
                    ],
                ],
                'verify.booking_deploy_check' => [
                    'status' => 'ok',
                    'message' => 'deploy passed.',
                    'details' => [
                        'exit_code' => 0,
                        'stdout' => json_encode(['ok' => true, 'report' => ['errors' => [], 'warnings' => [], 'summary' => []]], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
            'warnings' => [],
            'errors' => [],
        ];
    }
}

final class UnitFakeDisasterRecoveryProcessRunner extends DisasterRecoveryProcessRunner
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
        $this->phpToolCalls[] = compact('relativeScriptPath', 'arguments', 'environment');

        if ($this->phpToolQueue === []) {
            throw new \RuntimeException('Unexpected php tool call.');
        }

        return array_shift($this->phpToolQueue);
    }

    public function runArtisan(array $arguments, array $environment = []): array
    {
        $this->artisanCalls[] = compact('arguments', 'environment');

        if ($this->artisanQueue === []) {
            throw new \RuntimeException('Unexpected artisan call.');
        }

        return array_shift($this->artisanQueue);
    }
}

final class UnitFakeDisasterRecoveryDatabaseProbe extends DisasterRecoveryDatabaseProbe
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function inspect(array $targetConnection): array
    {
        return $this->payload;
    }
}

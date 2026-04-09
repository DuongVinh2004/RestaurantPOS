<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Services\Reporting\ReportingSnapshotService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class ReportingSnapshotCommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Group('booking-ops')]
    public function test_reporting_snapshot_rebuild_command_passes_compact_filters_to_service(): void
    {
        $mock = Mockery::mock(ReportingSnapshotService::class);
        $mock->shouldReceive('rebuild')
            ->once()
            ->with([
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-04',
                'include_sales' => true,
                'include_operations' => false,
                'include_inventory' => false,
                'branch_id' => 2,
            ])
            ->andReturn([
                'date_range' => [
                    'start_date' => '2026-04-01',
                    'end_date' => '2026-04-04',
                ],
                'branch_id' => 2,
                'rebuild' => [
                    'sales' => ['row_count' => 4],
                ],
                'warnings' => [],
            ]);
        $this->app->instance(ReportingSnapshotService::class, $mock);

        $exitCode = Artisan::call('booking:reporting-snapshots:rebuild', [
            '--branch-id' => 2,
            '--start-date' => '2026-04-01',
            '--end-date' => '2026-04-04',
            '--sales' => true,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $payload['data']['branch_id'] ?? null);
        $this->assertSame(4, $payload['data']['rebuild']['sales']['row_count'] ?? null);
    }
}

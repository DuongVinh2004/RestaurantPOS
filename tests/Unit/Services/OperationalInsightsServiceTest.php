<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseContractInspector;
use App\Services\OperationalInsightsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class OperationalInsightsServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('booking.ops.reporting_snapshot_stale_hours', 48);
        $this->requireBookingSchema();
        $this->clearReportingSnapshotTables();
    }

    private function clearReportingSnapshotTables(): void
    {
        foreach ([
            'reporting_daily_sales_snapshots',
            'reporting_daily_operation_snapshots',
            'reporting_daily_inventory_movement_snapshots',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    #[Group('booking-ops')]
    public function test_snapshot_exposes_reporting_and_branch_sections_when_live_foundations_are_seeded(): void
    {
        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        $ingredientId = $this->createIngredient();

        DB::table('reporting_daily_sales_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-04-01',
            'currency' => 'VND',
            'refreshed_at' => $now->copy()->subHour(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('reporting_daily_operation_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-04-01',
            'refreshed_at' => $now->copy()->subHours(2),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('reporting_daily_inventory_movement_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-04-01',
            'ingredient_id' => $ingredientId,
            'unit_code' => 'g',
            'refreshed_at' => $now->copy()->subHours(3),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $snapshot = app(OperationalInsightsService::class)->snapshot($now);

        $this->assertArrayHasKey('reporting_snapshots', $snapshot);
        $this->assertArrayHasKey('branch_defaults', $snapshot);
        $this->assertSame('ok', $snapshot['reporting_snapshots']['status']);
        $this->assertSame(3, $snapshot['reporting_snapshots']['populated_family_count']);
        $this->assertSame(3, $snapshot['reporting_snapshots']['healthy_family_count']);
        $this->assertSame(0, $snapshot['reporting_snapshots']['stale_scope_count_total']);
        $this->assertSame(3, $snapshot['reporting_snapshots']['family_count']);
        $this->assertGreaterThanOrEqual(3 * 3600, (int) $snapshot['reporting_snapshots']['latest_refresh_age_seconds_max']);
        $this->assertSame('ok', $snapshot['branch_defaults']['status']);
        $this->assertSame(1, $snapshot['branch_defaults']['default_count']);
        $this->assertSame('MAIN', $snapshot['branch_defaults']['default_branch_code']);
    }

    #[Group('booking-ops')]
    public function test_database_contract_snapshot_reads_live_metadata_without_inspector_exception(): void
    {
        $snapshot = app(DatabaseContractInspector::class)->snapshot();
        $issues = (array) ($snapshot['issues'] ?? []);

        $this->assertFalse(
            collect($issues)->contains(static fn ($issue) => is_string($issue) && str_contains($issue, 'Failed to inspect database contract metadata:'))
        );
    }

    #[Group('booking-ops')]
    public function test_snapshot_returns_fail_section_when_a_dependency_throws(): void
    {
        $this->app->instance(DatabaseContractInspector::class, new class extends DatabaseContractInspector
        {
            public function __construct() {}

            public function snapshot(): array
            {
                throw new \RuntimeException('database contract inspector unavailable');
            }
        });

        $snapshot = app(OperationalInsightsService::class)->snapshot(Carbon::parse('2026-04-02T09:00:00Z')->utc());

        $this->assertSame('fail', $snapshot['database_contract']['status']);
        $this->assertContains('runtime_dependency_unavailable', $snapshot['database_contract']['reasons']);
        $this->assertStringContainsString('database contract inspector unavailable', (string) ($snapshot['database_contract']['error'] ?? ''));
    }

    #[Group('booking-ops')]
    public function test_reporting_snapshot_health_degrades_when_latest_refresh_is_stale(): void
    {
        config()->set('booking.ops.reporting_snapshot_stale_hours', 24);

        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();

        DB::table('reporting_daily_sales_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-03-29',
            'currency' => 'VND',
            'refreshed_at' => $now->copy()->subHours(72),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $snapshot = app(OperationalInsightsService::class)->reportingSnapshotsSnapshot($now);

        $this->assertSame('degraded', $snapshot['status']);
        $this->assertContains('reporting_snapshot_stale', $snapshot['reasons']);
        $this->assertSame(0, $snapshot['healthy_family_count']);
        $this->assertSame(1, $snapshot['stale_scope_count_total']);
        $this->assertGreaterThanOrEqual(72 * 3600, (int) $snapshot['latest_refresh_age_seconds_max']);
        $this->assertSame(1, $snapshot['families']['sales']['stale_scope_count']);
        $this->assertGreaterThanOrEqual(72 * 3600, (int) $snapshot['families']['sales']['latest_refresh_age_seconds']);
    }

    #[Group('booking-ops')]
    public function test_reporting_snapshot_health_stays_ok_when_snapshot_tables_are_empty_and_source_domains_are_empty(): void
    {
        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();

        $snapshot = app(OperationalInsightsService::class)->reportingSnapshotsSnapshot($now);

        $this->assertSame('ok', $snapshot['status']);
        $this->assertSame([], $snapshot['reasons']);
        $this->assertSame(0, $snapshot['populated_family_count']);
        $this->assertSame(0, $snapshot['source_activity_count_total']);
        $this->assertSame(0, $snapshot['families']['sales']['source_activity_count']);
        $this->assertSame(0, $snapshot['families']['operations']['source_activity_count']);
        $this->assertSame(0, $snapshot['families']['inventory']['source_activity_count']);
    }

    #[Group('booking-ops')]
    public function test_reporting_snapshot_health_degrades_when_snapshot_family_is_missing_and_foundation_activity_exists(): void
    {
        $now = Carbon::parse('2026-04-02T09:00:00Z')->utc();
        $customerId = $this->createUser(['role_name' => 'Customer']);
        $ingredientId = $this->createIngredient();

        $this->createReservation([
            'branch_id' => 1,
            'user_id' => $customerId,
            'status' => 'Completed',
            'guest_count' => 2,
            'start_time' => $now->copy()->subDay()->setTime(12, 0),
            'end_time' => $now->copy()->subDay()->setTime(13, 0),
            'checked_in_at' => $now->copy()->subDay()->setTime(12, 5),
            'checked_out_at' => $now->copy()->subDay()->setTime(12, 55),
            'billed_at' => $now->copy()->subDay()->setTime(13, 0),
            'bill_currency' => 'VND',
            'final_bill_amount' => '90000.00',
            'discount_amount' => '10000.00',
        ]);

        $this->createWaitingListEntry([
            'branch_id' => 1,
            'requested_at' => $now->copy()->subDay()->setTime(18, 0),
            'status' => 'Waiting',
        ]);

        $this->createIngredientStockMovement([
            'branch_id' => 1,
            'ingredient_id' => $ingredientId,
            'movement_type' => 'StockIn',
            'quantity_delta' => '5.000',
            'unit_code' => 'g',
            'created_at' => $now->copy()->subDay()->setTime(6, 0),
        ]);

        DB::table('reporting_daily_sales_snapshots')->insert([
            'branch_id' => 1,
            'business_date' => '2026-04-01',
            'currency' => 'VND',
            'refreshed_at' => $now->copy()->subHour(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $snapshot = app(OperationalInsightsService::class)->reportingSnapshotsSnapshot($now);

        $this->assertSame('degraded', $snapshot['status']);
        $this->assertContains('reporting_snapshot_incomplete', $snapshot['reasons']);
        $this->assertSame(1, $snapshot['populated_family_count']);
        $this->assertSame(2, $snapshot['empty_family_count']);
        $this->assertSame(['operations', 'inventory'], $snapshot['empty_families']);
        $this->assertGreaterThan(0, $snapshot['families']['operations']['source_activity_count']);
        $this->assertGreaterThan(0, $snapshot['families']['inventory']['source_activity_count']);
    }

    #[Group('booking-ops')]
    public function test_branch_defaults_health_fails_when_multiple_default_branches_exist(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'NORTH',
            'branch_name' => 'North Branch',
        ]);

        DB::table('branches')->where('branch_id', $branchId)->update([
            'is_default' => true,
            'updated_at' => now('UTC'),
        ]);

        $snapshot = app(OperationalInsightsService::class)->branchDefaultsSnapshot();

        $this->assertSame('fail', $snapshot['status']);
        $this->assertContains('branch_default_ambiguous', $snapshot['reasons']);
        $this->assertSame(2, $snapshot['default_count']);
    }
}

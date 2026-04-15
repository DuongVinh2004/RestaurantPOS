<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\FeatureFlags\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
    }

    public function test_exact_environment_and_branch_override_wins_over_broader_scopes(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'FLAG-A',
            'branch_name' => 'Flag Branch A',
        ]);

        $this->upsertFeatureFlagOverride('customer.bill_self_payment', true, '*', null, ['reason' => 'wildcard global on']);
        $this->upsertFeatureFlagOverride('customer.bill_self_payment', true, 'testing', null, ['reason' => 'testing global on']);
        $this->upsertFeatureFlagOverride('customer.bill_self_payment', true, '*', $branchId, ['reason' => 'wildcard branch on']);
        $this->upsertFeatureFlagOverride('customer.bill_self_payment', false, 'testing', $branchId, ['reason' => 'testing branch off']);

        $resolved = app(FeatureFlagService::class)->resolve('customer.bill_self_payment', $branchId, 'testing');

        self::assertFalse((bool) $resolved['enabled']);
        self::assertSame('database_override', $resolved['source']);
        self::assertSame('testing', $resolved['matched_environment']);
        self::assertSame($branchId, $resolved['matched_branch_id']);
        self::assertSame('testing branch off', $resolved['override_reason']);
    }

    public function test_exact_environment_global_beats_wildcard_branch_override(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'FLAG-B',
            'branch_name' => 'Flag Branch B',
        ]);

        $this->upsertFeatureFlagOverride('staff.kitchen_dispatch', false, '*', $branchId, ['reason' => 'branch wildcard off']);
        $this->upsertFeatureFlagOverride('staff.kitchen_dispatch', true, 'testing', null, ['reason' => 'testing global on']);

        $resolved = app(FeatureFlagService::class)->resolve('staff.kitchen_dispatch', $branchId, 'testing');

        self::assertTrue((bool) $resolved['enabled']);
        self::assertSame('database_override', $resolved['source']);
        self::assertSame('testing', $resolved['matched_environment']);
        self::assertNull($resolved['matched_branch_id']);
    }

    public function test_config_defaults_and_unknown_features_resolve_safely(): void
    {
        $service = app(FeatureFlagService::class);

        $testingDefault = $service->resolve('staff.conversation_inbox', null, 'testing');
        self::assertTrue((bool) $testingDefault['enabled']);
        self::assertSame('config_default', $testingDefault['source']);
        self::assertSame('testing', $testingDefault['matched_environment']);

        $productionDefault = $service->resolve('staff.conversation_inbox', null, 'production');
        self::assertFalse((bool) $productionDefault['enabled']);
        self::assertSame('config_default', $productionDefault['source']);
        self::assertSame('*', $productionDefault['matched_environment']);

        $aiAssistTestingDefault = $service->resolve('staff.conversation_ai_assist', null, 'testing');
        self::assertTrue((bool) $aiAssistTestingDefault['enabled']);
        self::assertSame('config_default', $aiAssistTestingDefault['source']);
        self::assertSame('testing', $aiAssistTestingDefault['matched_environment']);

        $aiAssistProductionDefault = $service->resolve('staff.conversation_ai_assist', null, 'production');
        self::assertFalse((bool) $aiAssistProductionDefault['enabled']);
        self::assertSame('config_default', $aiAssistProductionDefault['source']);
        self::assertSame('*', $aiAssistProductionDefault['matched_environment']);

        $unknown = $service->resolve('unknown.feature', null, 'testing');
        self::assertFalse((bool) $unknown['enabled']);
        self::assertSame('unknown_feature', $unknown['source']);
    }

    public function test_missing_feature_flags_table_falls_back_to_config_defaults(): void
    {
        Schema::drop('feature_flags');

        $resolved = app(FeatureFlagService::class)->resolve('inventory.uplift', null, 'testing');

        self::assertTrue((bool) $resolved['enabled']);
        self::assertSame('config_default', $resolved['source']);

        $this->ensurePortableBookingSchema();
    }
}

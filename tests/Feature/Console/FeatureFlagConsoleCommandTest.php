<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AssertsAuditTrail;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class FeatureFlagConsoleCommandTest extends TestCase
{
    use AssertsAuditTrail;
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
    }

    public function test_console_commands_can_set_list_and_clear_feature_flags_with_audit_trail(): void
    {
        $branchId = $this->createBranch([
            'branch_code' => 'CMDOFF',
            'branch_name' => 'Command Branch',
        ]);
        $staffId = $this->createUser(['role_name' => 'Staff']);

        $setExit = Artisan::call('booking:feature-flags:set', [
            'feature' => 'customer.bill_self_payment',
            'state' => 'off',
            '--branch-id' => $branchId,
            '--environment' => 'production',
            '--reason' => 'canary paused',
            '--actor-user-id' => $staffId,
            '--json' => true,
        ]);

        self::assertSame(0, $setExit);
        $setPayload = $this->decodeArtisanOutput();
        self::assertSame('created', $setPayload['data']['action']);
        self::assertFalse((bool) $setPayload['data']['feature']['enabled']);
        self::assertSame('database_override', $setPayload['data']['feature']['source']);
        self::assertSame($branchId, $setPayload['data']['feature']['matched_branch_id']);

        $listExit = Artisan::call('booking:feature-flags:list', [
            '--feature' => 'customer.bill_self_payment',
            '--branch-id' => $branchId,
            '--environment' => 'production',
            '--json' => true,
        ]);

        self::assertSame(0, $listExit);
        $listPayload = $this->decodeArtisanOutput();
        self::assertSame(1, (int) $listPayload['meta']['count']);
        self::assertFalse((bool) $listPayload['data'][0]['enabled']);
        self::assertSame('production', $listPayload['data'][0]['matched_environment']);
        self::assertSame($branchId, $listPayload['data'][0]['matched_branch_id']);

        $entityId = 'customer.bill_self_payment|production|' . $branchId;
        $updatedAudit = $this->assertAuditLogRecorded('feature_flag.updated', 'feature_flag', $entityId);
        self::assertSame($staffId, $updatedAudit->actor_user_id);
        self::assertSame('console', $updatedAudit->actor_type);
        $this->assertAuditSubjectRecorded($updatedAudit, 'branch', $branchId, 'branch');

        $clearExit = Artisan::call('booking:feature-flags:clear', [
            'feature' => 'customer.bill_self_payment',
            '--branch-id' => $branchId,
            '--environment' => 'production',
            '--actor-user-id' => $staffId,
            '--json' => true,
        ]);

        self::assertSame(0, $clearExit);
        $clearPayload = $this->decodeArtisanOutput();
        self::assertSame('cleared', $clearPayload['data']['action']);
        self::assertTrue((bool) $clearPayload['data']['had_override']);
        self::assertFalse((bool) $clearPayload['data']['feature']['enabled']);
        self::assertSame('config_default', $clearPayload['data']['feature']['source']);

        $clearedAudit = $this->assertAuditLogRecorded('feature_flag.cleared', 'feature_flag', $entityId);
        self::assertSame($staffId, $clearedAudit->actor_user_id);
        self::assertSame('console', $clearedAudit->actor_type);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeArtisanOutput(): array
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use App\Support\AuditEvent;
use App\Support\AuditTrail\AuditDurabilityPolicy;
use App\Support\AuditTrail\CriticalAuditPersistenceException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class CriticalAuditDurabilityTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    private string $alertLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->alertLogPath = storage_path('logs/b14-audit-alert-'.bin2hex(random_bytes(6)).'.log');
        config()->set('audit.failure_alert_channel', 'audit_alert');
        config()->set('logging.channels.audit_alert', [
            'driver' => 'single',
            'path' => $this->alertLogPath,
            'level' => 'warning',
            'replace_placeholders' => true,
        ]);
        Log::forgetChannel('audit_alert');
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('audit_alert');

        if (is_file($this->alertLogPath)) {
            unlink($this->alertLogPath);
        }

        parent::tearDown();
    }

    public function test_critical_audit_failure_rolls_back_api_key_mutation_and_emits_safe_alert(): void
    {
        $staffUserId = $this->createUser(['role_name' => 'Staff']);
        $roleId = (int) DB::table('users')->where('user_id', $staffUserId)->value('role_id');
        $rawLabel = 'B14 raw key label must not reach alert';

        config()->set('staff_auth.allowed_role_ids', [$roleId]);
        config()->set('audit.tables.logs', 'audit_logs_b14_forced_unavailable');

        try {
            app(StaffApiKeyStore::class)->issueKey($staffUserId, $rawLabel);
            self::fail('Critical audit persistence failure must abort the mutation.');
        } catch (CriticalAuditPersistenceException $exception) {
            self::assertSame('staff_api_key_issued', $exception->eventName());
        }

        self::assertSame(
            0,
            (int) DB::table('staff_api_keys')->where('user_id', $staffUserId)->count(),
            'The staff API key write must roll back with its critical audit write.',
        );

        $alertPayload = (string) file_get_contents($this->alertLogPath);
        self::assertStringContainsString('critical_audit_persistence_failed', $alertPayload);
        self::assertStringContainsString('staff_api_key_issued', $alertPayload);
        self::assertStringNotContainsString($rawLabel, $alertPayload);
        self::assertStringNotContainsString('audit_logs_b14_forced_unavailable', $alertPayload);
    }

    public function test_finance_inventory_and_api_key_actions_are_classified_as_critical(): void
    {
        $policy = app(AuditDurabilityPolicy::class);

        self::assertTrue($policy->isCritical('staff_order_payment_recorded', null));
        self::assertTrue($policy->isCritical('staff_order_payment_recorded', ['action' => 'payment.final_captured']));
        self::assertTrue($policy->isCritical('admin.ingredient.created', ['action' => 'inventory.ingredient.created']));
        self::assertTrue($policy->isCritical('staff_api_key_rotated', ['action' => 'identity.staff_api_key.rotated']));
        self::assertFalse($policy->isCritical('http_request', null));
    }

    public function test_best_effort_audit_failure_warns_without_throwing(): void
    {
        config()->set('audit.tables.logs', 'audit_logs_b14_best_effort_unavailable');

        AuditEvent::info('staff.waiting_list.created', [
            '_audit' => [
                'action' => 'waiting_list.created',
                'entity_type' => 'waiting_list',
                'entity_id' => '98001',
            ],
        ]);

        $alertPayload = (string) file_get_contents($this->alertLogPath);
        self::assertStringContainsString('best_effort_audit_failed', $alertPayload);
        self::assertStringContainsString('staff.waiting_list.created', $alertPayload);
        self::assertStringNotContainsString('audit_logs_b14_best_effort_unavailable', $alertPayload);
    }
}

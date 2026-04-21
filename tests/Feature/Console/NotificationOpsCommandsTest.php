<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Modules\Notifications\Application\Services\NotificationOutboxHealthService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Platform\Metrics\Services\OperationalInsightsService;
use App\Modules\Waitlist\Application\Services\StaffWaitingListService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class NotificationOpsCommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Group('booking-ops')]
    public function test_process_outbox_command_passes_limit_and_worker_id_to_service(): void
    {
        $mock = Mockery::mock(NotificationOutboxService::class);
        $mock->shouldReceive('processDueMessages')
            ->once()
            ->with(5, 'worker-a')
            ->andReturn(3);
        $this->app->instance(NotificationOutboxService::class, $mock);

        $exitCode = Artisan::call('notifications:process-outbox', [
            '--limit' => 5,
            '--worker-id' => 'worker-a',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Processed 3 outbox message(s).', Artisan::output());
    }

    #[Group('booking-ops')]
    public function test_enqueue_reminders_command_supports_custom_timestamp(): void
    {
        $expected = Carbon::parse('2026-03-14T12:30:00Z')->utc();

        $mock = Mockery::mock(NotificationOutboxService::class);
        $mock->shouldReceive('enqueueDueReservationReminders')
            ->once()
            ->withArgs(function (?Carbon $now) use ($expected) {
                return $now instanceof Carbon && $now->equalTo($expected);
            })
            ->andReturn(2);
        $this->app->instance(NotificationOutboxService::class, $mock);

        $exitCode = Artisan::call('notifications:enqueue-reminders', [
            '--at' => '2026-03-14T12:30:00Z',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Enqueued 2 reservation reminder message(s).', Artisan::output());
    }

    #[Group('booking-ops')]
    public function test_outbox_health_command_supports_json_output_and_exit_code(): void
    {
        $mock = Mockery::mock(NotificationOutboxHealthService::class);
        $mock->shouldReceive('snapshot')
            ->once()
            ->andReturn([
                'ok' => false,
                'enabled' => true,
                'pending_count' => 4,
                'processing_count' => 1,
                'failed_count' => 2,
                'cancelled_count' => 0,
                'due_now_count' => 3,
                'stale_processing_count' => 1,
                'oldest_pending_age_seconds' => 900,
                'dead_letter_count' => 1,
                'recent_failure_attempt_count' => 4,
                'recent_failure_attempt_window_hours' => 24,
                'channel_breakdown' => [
                    'Email' => [
                        'channel' => 'Email',
                        'enabled' => true,
                        'readiness' => 'production_lean',
                        'driver' => 'mail',
                        'provider_key' => 'mail',
                        'delivery_mode' => 'real',
                        'pending_count' => 4,
                        'failed_count' => 2,
                        'cancelled_count' => 0,
                        'recent_failure_attempt_count' => 4,
                    ],
                ],
                'error' => null,
            ]);
        $this->app->instance(NotificationOutboxHealthService::class, $mock);

        $exitCode = Artisan::call('notifications:outbox-health', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"failed_count": 2', $output);
        $this->assertStringContainsString('"stale_processing_count": 1', $output);
        $this->assertStringContainsString('"readiness": "production_lean"', $output);
    }

    #[Group('booking-ops')]
    public function test_outbox_dead_letter_command_supports_json_output(): void
    {
        $mock = Mockery::mock(NotificationOutboxHealthService::class);
        $mock->shouldReceive('deadLetterSnapshot')
            ->once()
            ->with('SMS', 5)
            ->andReturn([
                'ok' => true,
                'channel' => 'SMS',
                'limit' => 5,
                'count' => 1,
                'rows' => [
                    [
                        'outbox_id' => 99,
                        'channel' => 'SMS',
                        'readiness' => 'provider_ready',
                        'delivery_mode' => 'stub',
                        'status' => 'Cancelled',
                        'template_key' => 'reservation.reminder',
                        'attempt_count' => 2,
                        'recipient_masked' => '84******99',
                        'latest_error_code' => 'channel_disabled',
                        'last_error' => 'Notification channel [SMS] is not enabled.',
                    ],
                ],
                'error' => null,
            ]);
        $this->app->instance(NotificationOutboxHealthService::class, $mock);

        $exitCode = Artisan::call('notifications:outbox-dead-letter', [
            '--channel' => 'SMS',
            '--limit' => 5,
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame(99, $payload['rows'][0]['outbox_id'] ?? null);
        $this->assertSame('channel_disabled', $payload['rows'][0]['latest_error_code'] ?? null);
    }


    #[Group('booking-ops')]
    public function test_booking_ops_snapshot_command_supports_json_output_and_fail_exit_code(): void
    {
        $mock = Mockery::mock(OperationalInsightsService::class);
        $mock->shouldReceive('snapshot')
            ->once()
            ->andReturn([
                'notification_outbox' => ['status' => 'ok', 'reasons' => [], 'failed_count' => 0],
                'payment_integrity' => [
                    'status' => 'fail',
                    'reasons' => ['payment_over_refund_detected'],
                    'over_refunded_source_count' => 1,
                    'refund_without_source_count' => 0,
                    'cross_reservation_refund_count' => 0,
                    'currency_mismatch_refund_count' => 0,
                    'invalid_refund_target_count' => 0,
                ],
                'voucher_locks' => ['status' => 'ok', 'reasons' => [], 'stale_lock_count' => 0],
                'session_linkage' => ['status' => 'degraded', 'reasons' => ['session_hold_linkage_backfill_needed'], 'active_unlinked_session_hold_count' => 6],
                'reporting_snapshots' => ['status' => 'ok', 'reasons' => [], 'total_row_count' => 12, 'populated_family_count' => 3],
                'kitchen_kds' => ['status' => 'degraded', 'reasons' => ['kitchen_ticket_ready_backlog_stale'], 'active_ticket_count' => 2, 'drift_count' => 0],
                'inventory_purchasing' => ['status' => 'ok', 'reasons' => [], 'issue_order_count' => 0, 'duplicate_purchase_receipt_reference_count' => 0, 'duplicate_purchase_receipt_movement_count' => 0, 'overdue_open_order_count' => 0],
                'conversation_inbox' => ['status' => 'ok', 'reasons' => [], 'unassigned_count' => 0, 'overdue_count' => 0],
                'branch_defaults' => ['status' => 'ok', 'reasons' => [], 'total_count' => 2, 'default_count' => 1],
                'database_contract' => ['status' => 'ok', 'reasons' => []],
            ]);
        $this->app->instance(OperationalInsightsService::class, $mock);

        $exitCode = Artisan::call('booking:ops-snapshot', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"payment_integrity"', $output);
        $this->assertStringContainsString('"over_refunded_source_count": 1', $output);
        $this->assertStringContainsString('"active_unlinked_session_hold_count": 6', $output);
        $this->assertStringContainsString('"kitchen_kds"', $output);
    }

    #[Group('booking-ops')]
    public function test_booking_ops_snapshot_table_output_includes_reporting_and_branch_metrics(): void
    {
        $mock = Mockery::mock(OperationalInsightsService::class);
        $mock->shouldReceive('snapshot')
            ->once()
            ->andReturn([
                'notification_outbox' => ['status' => 'ok', 'reasons' => [], 'failed_count' => 0],
                'payment_integrity' => [
                    'status' => 'ok',
                    'reasons' => [],
                    'over_refunded_source_count' => 0,
                    'refund_without_source_count' => 0,
                    'cross_reservation_refund_count' => 0,
                    'currency_mismatch_refund_count' => 0,
                    'invalid_refund_target_count' => 0,
                ],
                'voucher_locks' => ['status' => 'ok', 'reasons' => [], 'stale_lock_count' => 0],
                'session_linkage' => ['status' => 'ok', 'reasons' => [], 'active_unlinked_session_hold_count' => 0],
                'reporting_snapshots' => ['status' => 'ok', 'reasons' => [], 'total_row_count' => 7, 'populated_family_count' => 2],
                'kitchen_kds' => ['status' => 'ok', 'reasons' => [], 'active_ticket_count' => 1, 'drift_count' => 0],
                'inventory_purchasing' => ['status' => 'ok', 'reasons' => [], 'issue_order_count' => 0, 'duplicate_purchase_receipt_reference_count' => 1, 'duplicate_purchase_receipt_movement_count' => 2, 'overdue_open_order_count' => 1],
                'conversation_inbox' => ['status' => 'ok', 'reasons' => [], 'unassigned_count' => 2, 'overdue_count' => 1],
                'branch_defaults' => ['status' => 'ok', 'reasons' => [], 'total_count' => 3, 'default_count' => 1],
                'database_contract' => ['status' => 'ok', 'reasons' => []],
            ]);
        $this->app->instance(OperationalInsightsService::class, $mock);

        $exitCode = Artisan::call('booking:ops-snapshot');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('reporting_snapshot_total_row_count', $output);
        $this->assertStringContainsString('kitchen_active_ticket_count', $output);
        $this->assertStringContainsString('inventory_duplicate_purchase_receipt_reference_count', $output);
        $this->assertStringContainsString('inventory_overdue_open_order_count', $output);
        $this->assertStringContainsString('conversation_overdue_count', $output);
        $this->assertStringContainsString('branch_default_count', $output);
    }

    #[Group('booking-ops')]
    public function test_expire_notified_command_supports_custom_timestamp(): void
    {
        $expected = Carbon::parse('2026-03-14T13:00:00Z')->utc();

        $mock = Mockery::mock(StaffWaitingListService::class);
        $mock->shouldReceive('expireNotifiedEntries')
            ->once()
            ->withArgs(function (?Carbon $now) use ($expected) {
                return $now instanceof Carbon && $now->equalTo($expected);
            })
            ->andReturn(1);
        $this->app->instance(StaffWaitingListService::class, $mock);

        $exitCode = Artisan::call('waiting-list:expire-notified', [
            '--at' => '2026-03-14T13:00:00Z',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Expired 1 waiting-list notified entry(s).', Artisan::output());
    }
}

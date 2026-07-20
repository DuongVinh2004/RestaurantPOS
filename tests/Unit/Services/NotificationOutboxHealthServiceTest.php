<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Notifications\Application\Services\NotificationOutboxHealthService;
use App\Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use App\Modules\Notifications\Domain\Models\NotificationOutbox;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class NotificationOutboxHealthServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notifications.outbox.enabled', true);
        config()->set('notifications.outbox.lock_seconds', 90);
        config()->set('notifications.outbox.health_failed_threshold', 0);
        config()->set('notifications.outbox.health_oldest_pending_seconds', 300);
        config()->set('notifications.channels.email.enabled', true);
        config()->set('notifications.channels.email.driver', 'mail');
        config()->set('notifications.channels.sms.enabled', false);
        config()->set('notifications.channels.sms.driver', 'stub');
        config()->set('notifications.channels.zalo.enabled', false);
        config()->set('notifications.channels.zalo.driver', 'stub');
    }

    private function requireOutboxSchema(): void
    {
        $this->ensureNotificationOutboxSchema();

        if (! Schema::hasTable('notification_outbox')) {
            $this->failOrSkipBookingSchemaContract('Required table [notification_outbox] is missing. Run booking migrations on the test database first.');
        }
    }

    private function resetOutboxRowsForSnapshot(): void
    {
        if (Schema::hasTable('notification_delivery_attempts')) {
            NotificationDeliveryAttempt::query()->delete();
        }

        NotificationOutbox::query()->delete();
    }

    #[Group('booking-ops')]
    public function test_snapshot_marks_outbox_degraded_when_failed_or_stale_processing_rows_exist(): void
    {
        $this->requireOutboxSchema();
        $this->resetOutboxRowsForSnapshot();

        $now = Carbon::parse('2026-03-14T12:00:00Z')->utc();

        NotificationOutbox::query()->create([
            'channel' => 'Email',
            'recipient' => 'pending@example.test',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'health:pending:'.uniqid('', true),
            'payload_json' => ['x' => 1],
            'status' => 'Pending',
            'processing_token' => null,
            'locked_until' => null,
            'locked_by' => null,
            'attempt_count' => 0,
            'next_retry_at' => null,
            'last_error' => null,
            'related_reservation_id' => null,
            'created_at' => $now->copy()->subMinutes(20),
            'sent_at' => null,
        ]);

        NotificationOutbox::query()->create([
            'channel' => 'Email',
            'recipient' => 'processing@example.test',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'health:processing:'.uniqid('', true),
            'payload_json' => ['x' => 2],
            'status' => 'Processing',
            'processing_token' => 'token-a',
            'locked_until' => $now->copy()->subMinutes(3),
            'locked_by' => 'worker-a',
            'attempt_count' => 1,
            'next_retry_at' => null,
            'last_error' => null,
            'related_reservation_id' => null,
            'created_at' => $now->copy()->subMinutes(5),
            'sent_at' => null,
        ]);

        NotificationOutbox::query()->create([
            'channel' => 'Email',
            'recipient' => 'failed@example.test',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'health:failed:'.uniqid('', true),
            'payload_json' => ['x' => 3],
            'status' => 'Failed',
            'processing_token' => null,
            'locked_until' => null,
            'locked_by' => null,
            'attempt_count' => 2,
            'next_retry_at' => $now->copy()->addMinute(),
            'last_error' => 'boom',
            'related_reservation_id' => null,
            'created_at' => $now->copy()->subMinutes(2),
            'sent_at' => null,
        ]);

        $snapshot = app(NotificationOutboxHealthService::class)->snapshot($now);

        $this->assertFalse($snapshot['ok']);
        $this->assertSame(1, $snapshot['pending_count']);
        $this->assertSame(1, $snapshot['processing_count']);
        $this->assertSame(1, $snapshot['failed_count']);
        $this->assertSame(1, $snapshot['stale_processing_count']);
        $this->assertSame(1, $snapshot['due_now_count']);
        $this->assertGreaterThanOrEqual(1200, (int) $snapshot['oldest_pending_age_seconds']);
        $this->assertSame(0, $snapshot['dead_letter_count']);
        $this->assertArrayHasKey('Email', $snapshot['channel_breakdown']);
        $this->assertSame(3, $snapshot['channel_breakdown']['Email']['total_count']);
        $this->assertSame('production_lean', $snapshot['channel_breakdown']['Email']['readiness']);
    }

    #[Group('booking-ops')]
    public function test_snapshot_reports_clean_state_when_outbox_is_disabled(): void
    {
        config()->set('notifications.outbox.enabled', false);

        $snapshot = app(NotificationOutboxHealthService::class)->snapshot(Carbon::parse('2026-03-14T12:00:00Z')->utc());

        $this->assertTrue($snapshot['ok']);
        $this->assertFalse($snapshot['enabled']);
        $this->assertSame(0, $snapshot['pending_count']);
        $this->assertSame(0, $snapshot['failed_count']);
        $this->assertArrayHasKey('Email', $snapshot['channel_breakdown']);
    }

    #[Group('booking-ops')]
    public function test_dead_letter_snapshot_includes_latest_attempt_evidence(): void
    {
        $this->requireOutboxSchema();
        $this->resetOutboxRowsForSnapshot();

        $message = NotificationOutbox::query()->create([
            'channel' => 'Email',
            'recipient' => 'failed@example.test',
            'template_key' => 'reservation.cancelled',
            'idempotency_key' => 'health:dead-letter:'.uniqid('', true),
            'payload_json' => ['x' => 1],
            'status' => 'Cancelled',
            'attempt_count' => 2,
            'last_error' => 'boom',
            'last_attempted_at' => Carbon::parse('2026-03-14T12:10:00Z')->utc(),
            'created_at' => Carbon::parse('2026-03-14T12:00:00Z')->utc(),
        ]);

        NotificationDeliveryAttempt::query()->create([
            'outbox_id' => (int) $message->outbox_id,
            'channel' => 'Email',
            'provider_key' => 'mail',
            'attempt_number' => 2,
            'status' => 'Failed',
            'recipient' => 'failed@example.test',
            'provider_status' => 'rejected',
            'error_code' => 'mail_failed',
            'error_message' => 'boom',
            'attempted_at' => Carbon::parse('2026-03-14T12:10:00Z')->utc(),
            'created_at' => Carbon::parse('2026-03-14T12:10:00Z')->utc(),
        ]);

        $snapshot = app(NotificationOutboxHealthService::class)->deadLetterSnapshot('Email', 5);

        $this->assertTrue($snapshot['ok']);
        $this->assertSame('Email', $snapshot['channel']);
        $this->assertSame(1, $snapshot['count']);
        $this->assertSame((int) $message->outbox_id, $snapshot['rows'][0]['outbox_id']);
        $this->assertSame('production_lean', $snapshot['rows'][0]['readiness']);
        $this->assertSame('mail', $snapshot['rows'][0]['latest_attempt']['provider_key']);
        $this->assertSame('mail_failed', $snapshot['rows'][0]['latest_attempt']['error_code']);
    }

    #[Group('booking-ops')]
    public function test_snapshot_exposes_bounded_reminder_lag_and_unknown_delivery_outcomes(): void
    {
        $this->requireBookingSchema();
        $this->resetOutboxRowsForSnapshot();
        config()->set('notifications.outbox.reminder_enabled', true);
        config()->set('notifications.outbox.reminder_lead_minutes', 60);
        config()->set('notifications.outbox.reminder_catch_up_minutes', 20);
        config()->set('notifications.outbox.health.reminder_lag_warn_seconds', 120);

        $now = Carbon::parse('2026-07-20T01:00:00Z')->utc();
        $withinUserId = $this->createUser(['email' => 'health-reminder-within@example.test']);
        $withinId = $this->createReservation([
            'user_id' => $withinUserId,
            'status' => 'Confirmed',
            'start_time' => $now->copy()->addMinutes(45),
            'end_time' => $now->copy()->addMinutes(105),
        ]);
        $this->attachReservationTable($withinId);

        $expiredUserId = $this->createUser(['email' => 'health-reminder-expired@example.test']);
        $expiredId = $this->createReservation([
            'user_id' => $expiredUserId,
            'status' => 'Confirmed',
            'start_time' => $now->copy()->addMinutes(5),
            'end_time' => $now->copy()->addMinutes(65),
        ]);
        $this->attachReservationTable($expiredId);

        $unknown = NotificationOutbox::query()->create([
            'channel' => 'Email',
            'recipient' => 'health-unknown@example.test',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'health:unknown:'.uniqid('', true),
            'payload_json' => ['x' => 1],
            'status' => 'Cancelled',
            'attempt_count' => 1,
            'last_error' => 'Provider delivery outcome is unknown after worker interruption.',
            'created_at' => $now,
        ]);
        NotificationDeliveryAttempt::query()->create([
            'outbox_id' => (int) $unknown->outbox_id,
            'channel' => 'Email',
            'provider_key' => 'mail',
            'attempt_number' => 1,
            'status' => 'Unknown',
            'recipient' => 'health-unknown@example.test',
            'error_code' => 'delivery_outcome_unknown',
            'error_message' => 'Provider delivery outcome is unknown after worker interruption.',
            'attempted_at' => $now,
            'created_at' => $now,
        ]);

        $snapshot = app(NotificationOutboxHealthService::class)->snapshot($now);

        $this->assertFalse($snapshot['ok']);
        $this->assertSame(1, $snapshot['reminder_catch_up_due_count']);
        $this->assertSame(1, $snapshot['reminder_catch_up_expired_count']);
        $this->assertSame(3300, $snapshot['oldest_reminder_lag_seconds']);
        $this->assertSame(20, $snapshot['reminder_catch_up_window_minutes']);
        $this->assertSame(1, $snapshot['unknown_delivery_outcome_count']);
    }
}

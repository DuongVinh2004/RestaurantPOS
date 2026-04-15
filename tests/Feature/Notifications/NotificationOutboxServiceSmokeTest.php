<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use App\Modules\Notifications\Domain\Models\NotificationOutbox;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class NotificationOutboxServiceSmokeTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('notifications.outbox.enabled', true);
        config()->set('mail.default', 'log');
        config()->set('notifications.outbox.mailer', 'log');
        config()->set('notifications.outbox.max_attempts', 3);
        config()->set('notifications.outbox.lock_seconds', 90);
        config()->set('notifications.outbox.reminder_enabled', true);
        config()->set('notifications.outbox.reminder_lead_minutes', 60);
        config()->set('notifications.outbox.reminder_window_minutes', 10);
        config()->set('notifications.channels.email.enabled', true);
        config()->set('notifications.channels.email.driver', 'mail');
        config()->set('notifications.channels.sms.enabled', false);
        config()->set('notifications.channels.sms.driver', 'stub');
        config()->set('notifications.channels.zalo.enabled', false);
        config()->set('notifications.channels.zalo.driver', 'stub');
    }

    private function requireOutboxSchema(): void
    {
        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();

        foreach (['notification_outbox'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->failOrSkipBookingSchemaContract(sprintf('Required table [%s] is missing. Run booking migrations on the test database first.', $table));
            }
        }
    }

    #[Group('booking-smoke')]
    public function test_enqueue_due_reservation_reminders_counts_only_new_messages(): void
    {
        $this->requireOutboxSchema();

        Mail::fake();

        $userId = $this->createUser(['email' => 'customer.reminder@example.test']);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-14T13:05:00Z')->utc(),
            'end_time' => Carbon::parse('2026-03-14T15:05:00Z')->utc(),
        ]);
        $this->attachReservationTable($reservationId);

        $service = app(NotificationOutboxService::class);
        $now = Carbon::parse('2026-03-14T12:00:00Z')->utc();

        $first = $service->enqueueDueReservationReminders($now);
        $second = $service->enqueueDueReservationReminders($now);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second, 'Re-running reminder enqueue in the same window must not over-count existing idempotent rows.');
        $this->assertSame(1, NotificationOutbox::query()->where('template_key', 'reservation.reminder')->count());
    }

    #[Group('booking-smoke')]
    public function test_reservation_notifications_keep_utc_contract_fields_and_add_branch_local_display_times(): void
    {
        $this->requireOutboxSchema();
        config()->set('booking.multi_branch.default_branch_timezone', 'Asia/Ho_Chi_Minh');

        $branchId = $this->createBranch([
            'branch_code' => 'HCM',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $userId = $this->createUser(['email' => 'customer.localtime@example.test']);
        $reservationId = $this->createReservation([
            'branch_id' => $branchId,
            'user_id' => $userId,
            'status' => 'Confirmed',
            'start_time' => Carbon::parse('2026-03-14T13:05:00Z')->utc(),
            'end_time' => Carbon::parse('2026-03-14T15:05:00Z')->utc(),
        ]);
        $this->attachReservationTable($reservationId);

        $reservation = Reservation::query()->findOrFail($reservationId);
        $service = app(NotificationOutboxService::class);
        $message = $service->enqueueReservationReminder($reservation, 60);

        self::assertNotNull($message);

        $payload = (array) $message->payload_json;
        self::assertSame('2026-03-14 13:05:00 UTC', $payload['start_time_utc'] ?? null);
        self::assertSame('2026-03-14 15:05:00 UTC', $payload['end_time_utc'] ?? null);
        self::assertSame('20:05 14/03/2026', $payload['start_time_local'] ?? null);
        self::assertSame('22:05 14/03/2026', $payload['end_time_local'] ?? null);
        self::assertSame('production_lean', data_get($payload, '_notification.readiness'));
        self::assertSame('real', data_get($payload, '_notification.delivery_mode'));

        $renderEmailBody = new \ReflectionMethod($service, 'renderEmailBody');
        $renderEmailBody->setAccessible(true);
        $body = $renderEmailBody->invoke($service, 'reservation.reminder', $payload);

        self::assertIsString($body);
        self::assertStringContainsString('20:05 14/03/2026', $body);
        self::assertStringNotContainsString('UTC', $body);
    }

    #[Group('booking-smoke')]
    public function test_process_due_messages_marks_email_rows_as_sent(): void
    {
        $this->requireOutboxSchema();

        Mail::fake();

        $message = NotificationOutbox::query()->create([
            'channel' => 'Email',
            'recipient' => 'ops@example.test',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'test:outbox:sent:'.uniqid('', true),
            'payload_json' => [
                'reservation_code' => 'RSV-TEST-001',
                'customer_name' => 'Test User',
                'restaurant_name' => 'RestaurantPOS',
            ],
            'status' => 'Pending',
            'processing_token' => null,
            'locked_until' => null,
            'locked_by' => null,
            'attempt_count' => 0,
            'next_retry_at' => null,
            'last_error' => null,
            'related_reservation_id' => null,
            'created_at' => Carbon::parse('2026-03-14T11:00:00Z')->utc(),
            'sent_at' => null,
        ]);

        $processed = app(NotificationOutboxService::class)->processDueMessages(10, 'test-worker');

        $this->assertSame(1, $processed);
        $message->refresh();
        $this->assertSame('Sent', $message->status);
        $this->assertNull($message->processing_token);
        $this->assertNotNull($message->sent_at);
        $this->assertSame(1, NotificationDeliveryAttempt::query()->where('outbox_id', (int) $message->outbox_id)->count());
    }

    #[Group('booking-smoke')]
    public function test_process_due_messages_cancels_non_retryable_channel_rows_without_retry_loop(): void
    {
        $this->requireOutboxSchema();

        $message = NotificationOutbox::query()->create([
            'channel' => 'SMS',
            'recipient' => '84901234567',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'test:outbox:failed:'.uniqid('', true),
            'payload_json' => [
                'reservation_code' => 'RSV-TEST-002',
            ],
            'status' => 'Pending',
            'processing_token' => null,
            'locked_until' => null,
            'locked_by' => null,
            'attempt_count' => 0,
            'next_retry_at' => null,
            'last_error' => null,
            'related_reservation_id' => null,
            'created_at' => Carbon::parse('2026-03-14T11:30:00Z')->utc(),
            'sent_at' => null,
        ]);

        $processed = app(NotificationOutboxService::class)->processDueMessages(10, 'test-worker');

        $this->assertSame(1, $processed);
        $message->refresh();
        $this->assertSame('Cancelled', $message->status);
        $this->assertNull($message->next_retry_at);
        $this->assertStringContainsString('not enabled', (string) $message->last_error);
        $attempt = NotificationDeliveryAttempt::query()->firstOrFail();
        $this->assertSame('Failed', $attempt->status);
        $this->assertSame('channel_disabled', $attempt->error_code);
    }
}

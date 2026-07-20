<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Modules\Notifications\Application\Services\NotificationChannelManager;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Notifications\Application\Services\NotificationPreferenceService;
use App\Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use App\Modules\Notifications\Domain\Models\NotificationOutbox;
use App\Modules\Notifications\Infrastructure\Contracts\NotificationChannelDriver;
use App\Modules\Reservations\Domain\Models\Reservation;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class NotificationReminderDurabilityTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        $this->ensureNotificationOutboxSchema();
        config()->set('notifications.outbox.enabled', true);
        config()->set('notifications.outbox.reminder_enabled', true);
        config()->set('notifications.outbox.reminder_lead_minutes', 60);
        config()->set('notifications.outbox.reminder_window_minutes', 10);
        config()->set('notifications.outbox.reminder_catch_up_minutes', 120);
        config()->set('notifications.channels.email.enabled', true);
        config()->set('notifications.channels.email.driver', 'mail');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sent_reminder_does_not_dedupe_the_new_schedule_version(): void
    {
        $reservation = $this->createConfirmedReservation('sent-reschedule@example.test');
        $service = app(NotificationOutboxService::class);

        $oldReminder = $service->enqueueReservationReminder($reservation, 60);
        self::assertNotNull($oldReminder);
        $oldReminder->forceFill(['status' => 'Sent', 'sent_at' => now('UTC')])->save();

        $newStart = $reservation->start_time->copy()->addDay();
        $this->moveReservationSchedule($reservation, $newStart, 2);

        $newReminder = $service->enqueueReservationReminder($reservation->fresh(), 60);

        self::assertNotNull($newReminder);
        self::assertNotSame((int) $oldReminder->outbox_id, (int) $newReminder->outbox_id);
        self::assertNotSame((string) $oldReminder->idempotency_key, (string) $newReminder->idempotency_key);
        self::assertSame(2, (int) data_get($newReminder->payload_json, '_notification.reminder_schedule.row_version'));
        self::assertSame($newStart->utc()->format('Y-m-d\TH:i:s.u\Z'), data_get($newReminder->payload_json, '_notification.reminder_schedule.start_at_utc'));
    }

    /** @return array<string,array{string}> */
    public static function supersedableReminderStates(): array
    {
        return [
            'pending reminder' => ['Pending'],
            'failed reminder' => ['Failed'],
        ];
    }

    #[DataProvider('supersedableReminderStates')]
    public function test_reschedule_transaction_supersedes_old_pending_or_failed_reminder(string $oldStatus): void
    {
        $reservation = $this->createConfirmedReservation(strtolower($oldStatus).'-reschedule@example.test');
        $service = app(NotificationOutboxService::class);
        $oldReminder = $service->enqueueReservationReminder($reservation, 60);
        self::assertNotNull($oldReminder);
        $oldReminder->forceFill([
            'status' => $oldStatus,
            'attempt_count' => $oldStatus === 'Failed' ? 1 : 0,
            'next_retry_at' => $oldStatus === 'Failed' ? now('UTC')->addMinute() : null,
        ])->save();

        $newStart = $reservation->start_time->copy()->addDay();
        DB::transaction(function () use ($reservation, $newStart, $service): void {
            $this->moveReservationSchedule($reservation, $newStart, 2);
            $service->enqueueReservationRescheduled($reservation->fresh(), [
                'previous_start_time_utc' => $reservation->start_time->toIso8601String(),
                'new_start_time_utc' => $newStart->toIso8601String(),
            ]);
        });

        $oldReminder->refresh();
        self::assertSame('Cancelled', $oldReminder->status);
        self::assertNull($oldReminder->next_retry_at);
        self::assertSame('reminder_schedule_superseded', NotificationDeliveryAttempt::query()
            ->where('outbox_id', (int) $oldReminder->outbox_id)
            ->latest('attempt_id')
            ->value('error_code'));

        $newReminder = $service->enqueueReservationReminder($reservation->fresh(), 60);
        self::assertNotNull($newReminder);
        self::assertNotSame((int) $oldReminder->outbox_id, (int) $newReminder->outbox_id);
    }

    public function test_supersession_rolls_back_with_the_reschedule_transaction(): void
    {
        $reservation = $this->createConfirmedReservation('rollback-reschedule@example.test');
        $service = app(NotificationOutboxService::class);
        $oldReminder = $service->enqueueReservationReminder($reservation, 60);
        self::assertNotNull($oldReminder);

        try {
            DB::transaction(function () use ($reservation, $service): void {
                $newStart = $reservation->start_time->copy()->addDay();
                $this->moveReservationSchedule($reservation, $newStart, 2);
                $service->enqueueReservationRescheduled($reservation->fresh());

                self::assertSame('Cancelled', NotificationOutbox::query()->findOrFail($oldReminderId = (int) NotificationOutbox::query()
                    ->where('template_key', 'reservation.reminder')
                    ->value('outbox_id'))->status);
                self::assertGreaterThan(0, $oldReminderId);

                throw new RuntimeException('force reschedule rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('force reschedule rollback', $exception->getMessage());
        }

        $oldReminder->refresh();
        self::assertSame('Pending', $oldReminder->status);
        self::assertSame(0, NotificationDeliveryAttempt::query()
            ->where('outbox_id', (int) $oldReminder->outbox_id)
            ->where('error_code', 'reminder_schedule_superseded')
            ->count());
    }

    public function test_schedule_change_after_claim_suppresses_stale_reminder_before_mail_side_effect(): void
    {
        Mail::fake();
        $reservation = $this->createConfirmedReservation('stale-worker@example.test');
        $enqueueService = app(NotificationOutboxService::class);
        $oldReminder = $enqueueService->enqueueReservationReminder($reservation, 60);
        self::assertNotNull($oldReminder);

        $realChannelManager = app(NotificationChannelManager::class);
        $beforeResolve = function () use ($reservation): void {
            // Driver resolution happens after claim and before the locked
            // pre-side-effect schedule check.
            $this->moveReservationSchedule($reservation, $reservation->start_time->copy()->addDay(), 2);
        };
        $channelManager = new class($realChannelManager, $beforeResolve) extends NotificationChannelManager
        {
            public function __construct(
                private readonly NotificationChannelManager $delegate,
                private readonly Closure $beforeResolve,
            ) {
            }

            public function resolve(string $channel): NotificationChannelDriver
            {
                ($this->beforeResolve)();

                return $this->delegate->resolve($channel);
            }
        };
        $workerService = new NotificationOutboxService(
            $channelManager,
            app(NotificationPreferenceService::class),
        );

        self::assertSame(1, $workerService->processDueMessages(10, 'stale-worker'));
        $oldReminder->refresh();
        self::assertSame('Cancelled', $oldReminder->status);
        self::assertSame('reminder_schedule_stale', NotificationDeliveryAttempt::query()
            ->where('outbox_id', (int) $oldReminder->outbox_id)
            ->latest('attempt_id')
            ->value('error_code'));
        Mail::assertNothingSent();
    }

    public function test_scheduler_outage_beyond_forward_window_has_bounded_idempotent_catch_up(): void
    {
        $now = Carbon::parse('2026-07-20T01:00:00Z')->utc();
        $reservation = $this->createConfirmedReservation(
            'catch-up@example.test',
            $now->copy()->addMinutes(30),
        );

        $service = app(NotificationOutboxService::class);
        self::assertSame(1, $service->enqueueDueReservationReminders($now));
        self::assertSame(0, $service->enqueueDueReservationReminders($now));

        $message = NotificationOutbox::query()
            ->where('related_reservation_id', (int) $reservation->reservation_id)
            ->where('template_key', 'reservation.reminder')
            ->firstOrFail();
        self::assertSame('2026-07-20T00:30:00.000000Z', data_get($message->payload_json, '_notification.reminder_schedule.due_at_utc'));
        self::assertTrue((bool) data_get($message->payload_json, '_notification.reminder_schedule.caught_up'));
    }

    public function test_reminder_outside_bounded_catch_up_window_is_not_enqueued(): void
    {
        $now = Carbon::parse('2026-07-20T01:00:00Z')->utc();
        config()->set('notifications.outbox.reminder_catch_up_minutes', 20);
        $reservation = $this->createConfirmedReservation(
            'catch-up-expired@example.test',
            $now->copy()->addMinutes(5),
        );

        self::assertSame(0, app(NotificationOutboxService::class)->enqueueDueReservationReminders($now));
        self::assertFalse(NotificationOutbox::query()
            ->where('related_reservation_id', (int) $reservation->reservation_id)
            ->where('template_key', 'reservation.reminder')
            ->exists());
    }

    private function createConfirmedReservation(string $email, ?Carbon $start = null): Reservation
    {
        $start ??= Carbon::parse('2026-07-21T12:00:00Z')->utc();
        $userId = $this->createUser(['email' => $email]);
        $reservationId = $this->createReservation([
            'user_id' => $userId,
            'status' => 'Confirmed',
            'row_version' => 1,
            'start_time' => $start,
            'end_time' => $start->copy()->addHours(2),
        ]);
        $this->attachReservationTable($reservationId);

        return Reservation::query()->findOrFail($reservationId);
    }

    private function moveReservationSchedule(Reservation $reservation, CarbonInterface $newStart, int $rowVersion): void
    {
        Reservation::query()
            ->whereKey((int) $reservation->reservation_id)
            ->update([
                'start_time' => $newStart->copy()->utc(),
                'end_time' => $newStart->copy()->addHours(2)->utc(),
                'row_version' => $rowVersion,
            ]);
    }
}

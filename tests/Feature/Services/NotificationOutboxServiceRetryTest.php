<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\NotificationDeliveryAttempt;
use App\Models\NotificationOutbox;
use App\Models\NotificationPreference;
use App\Services\NotificationOutboxService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NotificationOutboxServiceRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('notifications.outbox.enabled', true);
        config()->set('notifications.outbox.max_attempts', 2);
        config()->set('notifications.outbox.retry_backoff_minutes', [1, 1]);
        config()->set('notifications.channels.email.enabled', true);
        config()->set('notifications.channels.email.driver', 'mail');
        config()->set('notifications.channels.sms.enabled', false);
        config()->set('notifications.channels.sms.driver', 'stub');
        config()->set('notifications.channels.zalo.enabled', false);
        config()->set('notifications.channels.zalo.driver', 'stub');
        config()->set('notifications.preferences.enabled', true);
        config()->set('notifications.preferences.timezone', 'UTC');
        config()->set('notifications.preferences.default_opt_in_channels', ['Email']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropIfExists('notification_delivery_attempts');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_outbox');
        Schema::create('notification_outbox', function (Blueprint $table): void {
            $table->increments('outbox_id');
            $table->string('channel');
            $table->string('recipient');
            $table->unsignedInteger('recipient_user_id')->nullable();
            $table->string('template_key');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('dedupe_key')->nullable();
            $table->text('payload_json');
            $table->string('status')->default('Pending');
            $table->string('processing_token')->nullable();
            $table->dateTime('locked_until')->nullable();
            $table->string('locked_by')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->dateTime('last_attempted_at')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedInteger('related_reservation_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('sent_at')->nullable();
        });

        Schema::create('notification_delivery_attempts', function (Blueprint $table): void {
            $table->increments('attempt_id');
            $table->unsignedInteger('outbox_id');
            $table->string('channel');
            $table->string('provider_key')->nullable();
            $table->unsignedInteger('attempt_number');
            $table->string('status');
            $table->string('recipient');
            $table->string('provider_message_id')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->text('request_payload_json')->nullable();
            $table->text('response_payload_json')->nullable();
            $table->dateTime('attempted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->increments('notification_preference_id');
            $table->unsignedInteger('user_id');
            $table->string('channel');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('quiet_hours_start_minute')->nullable();
            $table->unsignedSmallInteger('quiet_hours_end_minute')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unique(['user_id', 'channel'], 'uq_notification_preferences__user_id__channel');
        });
    }

    public function test_it_marks_email_notifications_as_sent(): void
    {
        Mail::fake();

        NotificationOutbox::query()->create([
            'channel' => 'Email',
            'recipient' => 'guest@example.com',
            'template_key' => 'payment.refunded',
            'idempotency_key' => 'outbox:sent:1',
            'payload_json' => [
                'restaurant_name' => 'RestaurantPOS',
                'customer_name' => 'Guest',
                'reservation_code' => 'RSV-100',
                'refund_amount' => 50.00,
                'refund_currency' => 'VND',
            ],
            'status' => 'Pending',
            'created_at' => Carbon::now('UTC'),
        ]);

        $processed = app(NotificationOutboxService::class)->processDueMessages(10, 'test-worker');

        self::assertSame(1, $processed);
        $message = NotificationOutbox::query()->firstOrFail();
        self::assertSame('Sent', $message->status);
        self::assertNotNull($message->sent_at);
        self::assertSame(1, NotificationDeliveryAttempt::query()->count());
        $attempt = NotificationDeliveryAttempt::query()->firstOrFail();
        self::assertSame('Succeeded', $attempt->status);
        self::assertSame('mail', $attempt->provider_key);
    }


    public function test_claiming_messages_does_not_increment_attempt_count_before_delivery(): void
    {
        NotificationOutbox::query()->create([
            'channel' => 'Email',
            'recipient' => 'guest@example.com',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'outbox:claim-only:1',
            'payload_json' => ['restaurant_name' => 'RestaurantPOS'],
            'status' => 'Pending',
            'created_at' => Carbon::now('UTC'),
        ]);

        $service = app(NotificationOutboxService::class);
        $method = new \ReflectionMethod($service, 'claimDueMessages');
        $method->setAccessible(true);

        $claimed = $method->invoke($service, 10, 'claim-only-worker');

        self::assertCount(1, $claimed);
        $message = NotificationOutbox::query()->firstOrFail();
        self::assertSame('Processing', $message->status);
        self::assertSame(0, (int) $message->attempt_count);
        self::assertNotNull($message->processing_token);
    }

    public function test_it_retries_then_cancels_messages_after_max_attempts(): void
    {
        NotificationOutbox::query()->create([
            'channel' => 'SMS',
            'recipient' => '84999999999',
            'template_key' => 'unsupported.channel',
            'idempotency_key' => 'outbox:fail:1',
            'payload_json' => ['restaurant_name' => 'RestaurantPOS'],
            'status' => 'Pending',
            'created_at' => Carbon::now('UTC'),
        ]);

        $service = app(NotificationOutboxService::class);
        $service->processDueMessages(10, 'test-worker');

        $message = NotificationOutbox::query()->firstOrFail();
        self::assertSame('Failed', $message->status);
        self::assertSame(1, (int) $message->attempt_count);
        self::assertNotNull($message->next_retry_at);
        self::assertStringContainsString('not enabled', (string) $message->last_error);

        $message->next_retry_at = Carbon::now('UTC')->subMinute();
        $message->save();

        $service->processDueMessages(10, 'test-worker');

        $message->refresh();
        self::assertSame('Cancelled', $message->status);
        self::assertSame(2, (int) $message->attempt_count);
        self::assertNull($message->next_retry_at);
        self::assertSame(2, NotificationDeliveryAttempt::query()->count());
        self::assertSame(2, NotificationDeliveryAttempt::query()->where('status', 'Failed')->count());
    }

    public function test_it_processes_stub_sms_channel_when_enabled(): void
    {
        config()->set('notifications.channels.sms.enabled', true);

        NotificationOutbox::query()->create([
            'channel' => 'SMS',
            'recipient' => '84999999999',
            'template_key' => 'reservation.reminder',
            'idempotency_key' => 'outbox:sms:stub:1',
            'payload_json' => ['restaurant_name' => 'RestaurantPOS'],
            'status' => 'Pending',
            'created_at' => Carbon::now('UTC'),
        ]);

        $processed = app(NotificationOutboxService::class)->processDueMessages(10, 'test-worker');

        self::assertSame(1, $processed);
        $message = NotificationOutbox::query()->firstOrFail();
        self::assertSame('Sent', $message->status);
        $attempt = NotificationDeliveryAttempt::query()->firstOrFail();
        self::assertSame('Succeeded', $attempt->status);
        self::assertSame('sms.stub', $attempt->provider_key);
    }

    public function test_enqueue_message_suppresses_duplicate_within_cooldown_window(): void
    {
        $service = app(NotificationOutboxService::class);

        $first = $service->enqueueMessage([
            'channel' => 'Email',
            'recipient' => 'guest@example.com',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'dedupe:first',
            'dedupe_key' => 'reservation-created:guest@example.com',
            'cooldown_seconds' => 300,
            'payload' => ['restaurant_name' => 'RestaurantPOS'],
            'created_at' => Carbon::parse('2026-04-05T01:00:00Z')->utc(),
        ]);

        $second = $service->enqueueMessage([
            'channel' => 'Email',
            'recipient' => 'guest@example.com',
            'template_key' => 'reservation.created',
            'idempotency_key' => 'dedupe:second',
            'dedupe_key' => 'reservation-created:guest@example.com',
            'cooldown_seconds' => 300,
            'payload' => ['restaurant_name' => 'RestaurantPOS'],
            'created_at' => Carbon::parse('2026-04-05T01:02:00Z')->utc(),
        ]);

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame((int) $first->outbox_id, (int) $second->outbox_id);
        self::assertSame(1, NotificationOutbox::query()->count());
    }

    public function test_enqueue_message_respects_disabled_channel_preference(): void
    {
        NotificationPreference::query()->create([
            'user_id' => 7,
            'channel' => 'Email',
            'is_enabled' => false,
        ]);

        $message = app(NotificationOutboxService::class)->enqueueMessage([
            'channel' => 'Email',
            'recipient' => 'guest@example.com',
            'recipient_user_id' => 7,
            'template_key' => 'reservation.created',
            'idempotency_key' => 'pref:disabled:1',
            'payload' => ['restaurant_name' => 'RestaurantPOS'],
        ]);

        self::assertNull($message);
        self::assertSame(0, NotificationOutbox::query()->count());
    }

    public function test_enqueue_message_delays_delivery_during_quiet_hours(): void
    {
        NotificationPreference::query()->create([
            'user_id' => 8,
            'channel' => 'Email',
            'is_enabled' => true,
            'quiet_hours_start_minute' => 120,
            'quiet_hours_end_minute' => 240,
        ]);

        $message = app(NotificationOutboxService::class)->enqueueMessage([
            'channel' => 'Email',
            'recipient' => 'guest@example.com',
            'recipient_user_id' => 8,
            'template_key' => 'reservation.reminder',
            'idempotency_key' => 'pref:quiet:1',
            'payload' => ['restaurant_name' => 'RestaurantPOS'],
            'created_at' => Carbon::parse('2026-04-05T02:30:00Z')->utc(),
        ]);

        self::assertNotNull($message);
        self::assertSame('Pending', $message->status);
        self::assertSame('2026-04-05T04:00:00+00:00', $message->next_retry_at?->utc()->toIso8601String());
    }
}

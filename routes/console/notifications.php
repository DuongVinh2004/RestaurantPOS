<?php

use App\Modules\Notifications\Application\Services\NotificationOutboxHealthService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Waitlist\Application\Services\StaffWaitingListService;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

Artisan::command('notifications:process-outbox {--limit= : Max number of due messages to claim} {--worker-id= : Override worker identifier}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $limit = $command->option('limit');
    $workerId = $command->option('worker-id');

    $processed = app(NotificationOutboxService::class)->processDueMessages(
        $limit !== null && $limit !== '' ? (int) $limit : null,
        $workerId !== null && $workerId !== '' ? (string) $workerId : null,
    );

    $command->info(sprintf('Processed %d outbox message(s).', $processed));
})->purpose('Process pending notification outbox messages');

Artisan::command('notifications:enqueue-reminders {--at= : Evaluate reminder window using the supplied UTC timestamp}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $at = $command->option('at');
    $now = ($at !== null && $at !== '') ? Carbon::parse((string) $at)->utc() : null;

    $enqueued = app(NotificationOutboxService::class)->enqueueDueReservationReminders($now);
    $command->info(sprintf('Enqueued %d reservation reminder message(s).', $enqueued));
})->purpose('Enqueue upcoming reservation reminder notifications');

Artisan::command('notifications:outbox-health {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $snapshot = app(NotificationOutboxHealthService::class)->snapshot();

    if ($command->option('json')) {
        $command->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $snapshot['ok'] ? 0 : 1;
    }

    $command->table(['Metric', 'Value'], [
        ['enabled', $snapshot['enabled'] ? 'true' : 'false'],
        ['ok', $snapshot['ok'] ? 'true' : 'false'],
        ['pending_count', (string) $snapshot['pending_count']],
        ['processing_count', (string) $snapshot['processing_count']],
        ['failed_count', (string) $snapshot['failed_count']],
        ['cancelled_count', (string) $snapshot['cancelled_count']],
        ['dead_letter_count', (string) ($snapshot['dead_letter_count'] ?? 0)],
        ['due_now_count', (string) $snapshot['due_now_count']],
        ['stale_processing_count', (string) $snapshot['stale_processing_count']],
        ['recent_failure_attempt_count', (string) ($snapshot['recent_failure_attempt_count'] ?? 0)],
        ['oldest_pending_age_seconds', $snapshot['oldest_pending_age_seconds'] === null ? 'null' : (string) $snapshot['oldest_pending_age_seconds']],
        ['error', (string) ($snapshot['error'] ?? '')],
    ]);

    $channelBreakdown = (array) ($snapshot['channel_breakdown'] ?? []);
    if ($channelBreakdown !== []) {
        $command->newLine();
        $command->table(
            ['Channel', 'Enabled', 'Readiness', 'Mode', 'Driver', 'Provider', 'Pending', 'Failed', 'Cancelled', 'Recent failures'],
            collect($channelBreakdown)->map(static function (array $row, string $channel) {
                return [
                    $channel,
                    ($row['enabled'] ?? false) ? 'yes' : 'no',
                    (string) ($row['readiness'] ?? ''),
                    (string) ($row['delivery_mode'] ?? ''),
                    (string) ($row['driver'] ?? ''),
                    (string) ($row['provider_key'] ?? ''),
                    (string) ($row['pending_count'] ?? 0),
                    (string) ($row['failed_count'] ?? 0),
                    (string) ($row['cancelled_count'] ?? 0),
                    (string) ($row['recent_failure_attempt_count'] ?? 0),
                ];
            })->values()->all()
        );
    }

    return $snapshot['ok'] ? 0 : 1;
})->purpose('Show current notification outbox health snapshot');

Artisan::command('notifications:outbox-dead-letter {--channel= : Filter a single notification channel} {--limit=20 : Maximum failed/cancelled rows to inspect} {--json : Output machine-readable JSON}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $snapshot = app(NotificationOutboxHealthService::class)->deadLetterSnapshot(
        $command->option('channel') !== null && $command->option('channel') !== '' ? (string) $command->option('channel') : null,
        (int) $command->option('limit'),
    );

    if ($command->option('json')) {
        $command->line(json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($snapshot['ok'] ?? false) ? 0 : 1;
    }

    if (! ($snapshot['ok'] ?? false)) {
        $command->error((string) ($snapshot['error'] ?? 'Notification dead-letter snapshot failed.'));

        return 1;
    }

    $command->info('Notification outbox dead-letter snapshot');
    $command->table(['Field', 'Value'], [
        ['channel', (string) ($snapshot['channel'] ?? 'all')],
        ['limit', (string) ($snapshot['limit'] ?? 0)],
        ['count', (string) ($snapshot['count'] ?? 0)],
    ]);

    $rows = (array) ($snapshot['rows'] ?? []);
    if ($rows !== []) {
        $command->table(
            ['Outbox', 'Channel', 'Readiness', 'Mode', 'Status', 'Template', 'Attempts', 'Recipient', 'Error code', 'Error'],
            collect($rows)->map(static function (array $row) {
                return [
                    (string) ($row['outbox_id'] ?? 0),
                    (string) ($row['channel'] ?? ''),
                    (string) ($row['readiness'] ?? ''),
                    (string) ($row['delivery_mode'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['template_key'] ?? ''),
                    (string) ($row['attempt_count'] ?? 0),
                    (string) ($row['recipient_masked'] ?? ''),
                    (string) ($row['latest_error_code'] ?? ''),
                    (string) ($row['last_error'] ?? ''),
                ];
            })->values()->all()
        );
    }

    return 0;
})->purpose('Inspect failed and dead-letter notification outbox rows with latest attempt evidence');

Artisan::command('waiting-list:expire-notified {--at= : Expire entries using the supplied UTC timestamp}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $at = $command->option('at');
    $now = ($at !== null && $at !== '') ? Carbon::parse((string) $at)->utc() : null;

    $count = app(StaffWaitingListService::class)->expireNotifiedEntries($now);
    $command->info(sprintf('Expired %d waiting-list notified entry(s).', $count));
})->purpose('Return expired notified waiting-list entries back to waiting state');

Artisan::command('notifications:delivery-smoke {--recipient= : Safe smoke email recipient} {--channel=Email : Rehearsal channel} {--force : Force rehearsal in production}', function () {
    /** @var ConsoleCommand $command */
    // @phpstan-ignore-next-line Laravel binds the console command instance to the closure.
    $command = $this;

    $channel = (string) $command->option('channel');
    $recipient = (string) $command->option('recipient');
    $force = (bool) $command->option('force');

    $env = app()->environment();
    $isProd = in_array(strtolower($env), ['production', 'staging', 'limited-production'], true);

    if ($isProd && ! $force) {
        $command->error(sprintf('Delivery smoke rehearsal is blocked in production-like environments [%s] unless --force is specified.', $env));

        return 1;
    }

    if ($recipient === '') {
        $recipient = (string) env('NOTIFICATION_SMOKE_EMAIL', '');
    }

    if ($recipient === '') {
        $command->error('No recipient provided. Pass --recipient=<email> or set NOTIFICATION_SMOKE_EMAIL in your environment.');

        return 1;
    }

    if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        $command->error(sprintf('Invalid smoke email recipient [%s].', $recipient));

        return 1;
    }

    $command->info('Starting notification delivery smoke rehearsal...');
    $command->info(sprintf('Environment: %s', $env));
    $command->info(sprintf('Channel:     %s', $channel));
    $command->info(sprintf('Recipient:   %s', $recipient));

    $outboxService = app(NotificationOutboxService::class);
    $idempotencyKey = 'smoke:delivery:'.uniqid('', true);

    $message = $outboxService->enqueueMessage([
        'channel' => $channel,
        'recipient' => $recipient,
        'template_key' => 'reservation.created',
        'idempotency_key' => $idempotencyKey,
        'payload' => [
            'reservation_code' => 'RSV-SMOKE-999',
            'customer_name' => 'Smoke Test Recipient',
            'restaurant_name' => 'Mộc Sen Bistro (Smoke Rehearsal)',
            'start_time_local' => '12:00 01/01/2027',
            'end_time_local' => '14:00 01/01/2027',
            'start_time_utc' => '2027-01-01 05:00:00 UTC',
            'end_time_utc' => '2027-01-01 07:00:00 UTC',
            'tables' => [
                ['table_code' => 'T-SMOKE', 'guest_count' => 2],
            ],
        ],
        'missing_recipient_audit_context' => [
            'smoke' => true,
        ],
    ]);

    if ($message === null) {
        $command->error('Failed to enqueue smoke message. Check if notifications or outbox are disabled in configuration.');

        return 1;
    }

    $command->info(sprintf('Enqueued smoke message ID: %d', $message->outbox_id));

    $processed = $outboxService->processDueMessages(10, 'smoke-worker');
    $message->refresh();

    $command->info(sprintf('Processed messages count: %d', $processed));
    $command->info(sprintf('Smoke message status:     %s', $message->status));

    if ($message->status !== 'Sent') {
        $command->error(sprintf('Delivery rehearsal failed. Status: %s. Last error: %s', $message->status, $message->last_error ?? 'None'));

        return 1;
    }

    $evidenceDir = base_path('storage/app/booking_release/manual_evidence');
    if (! is_dir($evidenceDir)) {
        mkdir($evidenceDir, 0755, true);
    }

    $timestamp = date('Ymd_His');
    $evidenceFilename = sprintf('notification-delivery-%s.json', $timestamp);
    $evidencePath = $evidenceDir.'/'.$evidenceFilename;

    $emailParts = explode('@', $recipient);
    $maskedEmail = substr($emailParts[0], 0, 3).'***@'.$emailParts[1];

    $evidence = [
        'rehearsal_type' => 'notification_delivery_rehearsal',
        'channel' => $channel,
        'recipient_masked' => $maskedEmail,
        'environment' => $env,
        'outbox_id' => $message->outbox_id,
        'status' => $message->status,
        'attempt_count' => $message->attempt_count,
        'processed_at_utc' => now('UTC')->toIso8601String(),
        'mailer' => config('notifications.outbox.mailer', config('mail.default')),
        'success' => true,
    ];

    file_put_contents($evidencePath, json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $command->info(sprintf('Captured delivery rehearsal evidence at: %s', 'storage/app/booking_release/manual_evidence/'.$evidenceFilename));

    return 0;
})->purpose('Run a safe staging/local notification delivery rehearsal and write evidence JSON');

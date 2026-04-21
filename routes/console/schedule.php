<?php

use App\Platform\FeatureFlags\Services\RuntimeSettingService;
use App\Platform\Health\Services\BookingMaintenanceService;
use App\Platform\Health\Services\OpsHeartbeatService;
use App\Support\AuditEvent;
use Illuminate\Console\Scheduling\Schedule;

app()->booted(function () {
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);

    $schedule->call(function () {
        $ttl = (int) config('booking.scheduler_heartbeat_ttl_seconds', 300);
        app(OpsHeartbeatService::class)->touch('scheduler', $ttl);
        AuditEvent::info('scheduler_heartbeat', ['ttl_seconds' => $ttl]);
    })
        ->name('scheduler-heartbeat')
        ->everyMinute()
        ->withoutOverlapping(5)->onOneServer();

    $schedule->call(function () {
        $maintenance = app(BookingMaintenanceService::class);
        $count = $maintenance->expireHolds();
        if ($count > 0) {
            AuditEvent::info('expire_table_holds', ['count' => $count]);
        }
    })
        ->name('expire-table-holds')
        ->everyMinute()
        ->withoutOverlapping(5)->onOneServer();

    $schedule->call(function () {
        $maintenance = app(BookingMaintenanceService::class);
        $graceNoShow = max(0, app(RuntimeSettingService::class)->int('noshow.grace_minutes', (int) config('booking.no_show_grace_minutes', 15)));
        $count = $maintenance->markNoShows($graceNoShow);

        if ($count > 0) {
            AuditEvent::info('mark_no_shows', ['count' => $count, 'grace_minutes' => $graceNoShow]);
        }
    })
        ->name('mark-no-shows')
        ->everyMinute()
        ->withoutOverlapping(5)->onOneServer();

    $schedule->command('waiting-list:expire-notified')
        ->name('waiting-list-expire-notified')
        ->everyMinute()
        ->withoutOverlapping(5)->onOneServer();

    if ((bool) config('notifications.outbox.enabled', true)) {
        if ((bool) config('notifications.outbox.reminder_enabled', true)) {
            $schedule->command('notifications:enqueue-reminders')
                ->name('notifications-enqueue-reminders')
                ->everyMinute()
                ->withoutOverlapping(5)->onOneServer();
        }

        $schedule->command('notifications:process-outbox')
            ->name('notifications-process-outbox')
            ->everyMinute()
            ->withoutOverlapping(5)->onOneServer();
    }

    if ((bool) config('booking.reporting_snapshot_auto_rebuild_enabled', true)) {
        $hours = max(1, min(23, (int) config('booking.reporting_snapshot_auto_rebuild_hours', 2)));
        $lookbackDays = max(1, min(
            (int) config('booking.reporting_snapshot_auto_rebuild_lookback_days', 7),
            (int) config('booking.reporting_snapshot_rebuild_max_days', 90)
        ));

        $schedule->command(sprintf('booking:reporting-snapshots:rebuild --days=%d', $lookbackDays))
            ->name('reporting-snapshots-rebuild')
            ->cron(sprintf('0 */%d * * *', $hours))
            ->withoutOverlapping(max(10, $hours * 60))
            ->onOneServer();
    }

    $schedule->call(function () {
        $maintenance = app(BookingMaintenanceService::class);
        $grace = (int) config('booking.expire_reservation_grace_minutes', 0);
        $count = $maintenance->expireReservations($grace);
        if ($count > 0) {
            AuditEvent::info('expire_reservations', ['count' => $count, 'grace_minutes' => $grace]);
        }
    })
        ->name('expire-reservations')
        ->everyMinute()
        ->withoutOverlapping(5)->onOneServer();
});

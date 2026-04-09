<?php

use App\Models\CustomerAccessSession;
use App\Models\StaffApiKey;
use App\Models\User;
use App\Services\ApiArtifacts\ApiConsumerArtifactService;
use App\Services\ApiContract\OpenApiSpecService;
use App\Services\BookingDeploySafetyService;
use App\Services\BookingDoctorService;
use App\Services\BookingMaintenanceService;
use App\Services\CoreOpsGateService;
use App\Services\CustomerAccessSessionService;
use App\Services\DataLifecycle\DataRetentionService;
use App\Services\DisasterRecovery\DisasterRecoveryDrillService;
use App\Services\FeatureFlagManagementService;
use App\Services\LaunchReadinessService;
use App\Services\NotificationOutboxHealthService;
use App\Services\NotificationOutboxService;
use App\Services\OperationalAlertService;
use App\Services\OperationalInsightsService;
use App\Services\OpsGateArtifactService;
use App\Services\OpsHeartbeatService;
use App\Services\Performance\PerformanceVerificationService;
use App\Services\ReleaseArtifactManifestService;
use App\Services\ReleaseArtifactNormalizerService;
use App\Services\ReleasePackageService;
use App\Services\Reporting\ReportingSnapshotService;
use App\Services\RoundFiveGateService;
use App\Services\RouteInventoryGateService;
use App\Services\RuntimeSettingService;
use App\Services\SiteBootstrapService;
use App\Services\Staff\StaffWaitingListService;
use App\Services\StaffApiKeyGovernanceService;
use App\Services\Uat\UatScenarioPackService;
use App\Support\AuditEvent;
use Illuminate\Console\Command as ConsoleCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

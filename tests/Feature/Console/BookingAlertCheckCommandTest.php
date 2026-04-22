<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Platform\Metrics\Services\OperationalAlertService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BookingAlertCheckCommandTest extends TestCase
{
    public function test_booking_alert_check_supports_json_output_in_dry_run_mode(): void
    {
        $service = new class extends OperationalAlertService
        {
            public function __construct() {}

            public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
            {
                return [
                    'payment_integrity' => [
                        'status' => 'fail',
                        'reasons' => ['payment_over_refund_detected'],
                        'over_refunded_source_count' => 1,
                    ],
                ];
            }

            public function buildAlerts(array $snapshot, ?Carbon $now = null): array
            {
                return [[
                    'fingerprint' => 'abc123',
                    'dedupe_key' => 'ops-alert:abc123',
                    'section' => 'payment_integrity',
                    'status' => 'fail',
                    'severity' => 'critical',
                    'reasons' => ['payment_over_refund_detected'],
                    'message' => '[RestaurantPOS] payment_integrity is fail (payment_over_refund_detected)',
                    'context' => ['over_refunded_source_count' => 1],
                    'generated_at_utc' => '2026-03-20T08:00:00Z',
                ]];
            }

            public function dispatchAlerts(array $alerts, bool $dryRun = false, ?Carbon $now = null): array
            {
                return [
                    'enabled' => true,
                    'dry_run' => $dryRun,
                    'cooldown_seconds' => 1800,
                    'triggered_count' => 1,
                    'sent_count' => 0,
                    'suppressed_count' => 1,
                    'transport_failure_count' => 0,
                    'results' => [[
                        'fingerprint' => 'abc123',
                        'section' => 'payment_integrity',
                        'severity' => 'critical',
                        'suppressed' => true,
                        'suppression_reason' => 'dry_run',
                        'sent' => false,
                        'transport_results' => [],
                    ]],
                ];
            }
        };

        $this->app->instance(OperationalAlertService::class, $service);

        $exitCode = Artisan::call('booking:alert-check', [
            '--json' => true,
            '--dry-run' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"payment_integrity"', $output);
        $this->assertStringContainsString('"suppression_reason": "dry_run"', $output);
        $this->assertStringContainsString('"fail_on_alert": false', $output);
    }

    public function test_booking_alert_check_fails_when_fail_on_alert_is_requested(): void
    {
        $service = new class extends OperationalAlertService
        {
            public function __construct() {}

            public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
            {
                return [
                    'notification_outbox' => [
                        'status' => 'degraded',
                        'reasons' => ['notification_outbox_pending_backlog'],
                        'pending_count' => 120,
                    ],
                ];
            }

            public function buildAlerts(array $snapshot, ?Carbon $now = null): array
            {
                return [[
                    'fingerprint' => 'warn123',
                    'dedupe_key' => 'ops-alert:warn123',
                    'section' => 'notification_outbox',
                    'status' => 'degraded',
                    'severity' => 'warning',
                    'reasons' => ['notification_outbox_pending_backlog'],
                    'message' => '[RestaurantPOS] notification_outbox is degraded (notification_outbox_pending_backlog)',
                    'context' => ['pending_count' => 120],
                    'generated_at_utc' => '2026-03-20T08:00:00Z',
                ]];
            }

            public function dispatchAlerts(array $alerts, bool $dryRun = false, ?Carbon $now = null): array
            {
                return [
                    'enabled' => true,
                    'dry_run' => $dryRun,
                    'cooldown_seconds' => 1800,
                    'triggered_count' => 1,
                    'sent_count' => 1,
                    'suppressed_count' => 0,
                    'transport_failure_count' => 0,
                    'results' => [[
                        'fingerprint' => 'warn123',
                        'section' => 'notification_outbox',
                        'severity' => 'warning',
                        'suppressed' => false,
                        'suppression_reason' => null,
                        'sent' => true,
                        'transport_results' => [['channel' => 'ops_log', 'ok' => true]],
                    ]],
                ];
            }
        };

        $this->app->instance(OperationalAlertService::class, $service);

        $exitCode = Artisan::call('booking:alert-check', [
            '--fail-on-alert' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('booking:alert-check detected actionable issues.', Artisan::output());
    }
}

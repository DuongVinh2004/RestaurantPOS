<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Platform\Metrics\Services\OperationalAlertService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class OperationalAlertServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_alerts_maps_fail_and_degraded_sections_into_prioritized_alerts(): void
    {
        $service = new OperationalAlertService();

        config(['ops_alerts.max_alerts_per_run' => 10]);

        $alerts = $service->buildAlerts([
            'payment_integrity' => [
                'status' => 'fail',
                'reasons' => ['payment_over_refund_detected'],
                'over_refunded_source_count' => 1,
            ],
            'notification_outbox' => [
                'status' => 'degraded',
                'reasons' => ['notification_outbox_pending_backlog'],
                'pending_count' => 150,
            ],
            'database_contract' => [
                'status' => 'ok',
                'reasons' => [],
            ],
        ], Carbon::parse('2026-03-20T08:00:00Z')->utc());

        $this->assertCount(2, $alerts);
        $this->assertSame('payment_integrity', $alerts[0]['section']);
        $this->assertSame('critical', $alerts[0]['severity']);
        $this->assertSame('notification_outbox', $alerts[1]['section']);
        $this->assertSame('warning', $alerts[1]['severity']);
        $this->assertArrayHasKey('fingerprint', $alerts[0]);
        $this->assertSame(1, $alerts[0]['context']['over_refunded_source_count']);
    }

    public function test_dispatch_alerts_sends_once_then_suppresses_duplicate_alert_during_cooldown(): void
    {
        Cache::flush();
        Http::fake([
            'https://alerts.example.test/slack' => Http::response(['ok' => true], 200),
        ]);

        config([
            'ops_alerts.enabled' => true,
            'ops_alerts.cooldown_seconds' => 600,
            'ops_alerts.channels.ops_log.enabled' => false,
            'ops_alerts.channels.audit.enabled' => false,
            'ops_alerts.channels.slack.enabled' => true,
            'ops_alerts.channels.slack.webhook_url' => 'https://alerts.example.test/slack',
            'ops_alerts.channels.webhook.enabled' => false,
        ]);

        $service = new OperationalAlertService();

        $alert = [
            'fingerprint' => 'abc123',
            'dedupe_key' => 'ops-alert:abc123',
            'section' => 'payment_integrity',
            'status' => 'fail',
            'severity' => 'critical',
            'reasons' => ['payment_over_refund_detected'],
            'message' => '[RestaurantPOS] payment_integrity is fail (payment_over_refund_detected)',
            'context' => ['over_refunded_source_count' => 1],
            'generated_at_utc' => '2026-03-20T08:00:00Z',
        ];

        $first = $service->dispatchAlerts([$alert], false, Carbon::parse('2026-03-20T08:00:00Z')->utc());
        $second = $service->dispatchAlerts([$alert], false, Carbon::parse('2026-03-20T08:05:00Z')->utc());

        $this->assertSame(1, $first['sent_count']);
        $this->assertSame(0, $first['suppressed_count']);
        $this->assertSame(1, $second['suppressed_count']);
        $this->assertSame('cooldown_active', $second['results'][0]['suppression_reason']);
        Http::assertSentCount(1);
    }
}

<?php

namespace App\Platform\Metrics\Services;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Log;

class MetricsService
{
    public function __construct(
        private readonly RedisFactory $redis
    ) {}

    private function labelsToKey(array $labels): string
    {
        ksort($labels);

        $parts = [];
        foreach ($labels as $k => $v) {
            $k = (string) $k;
            $v = (string) $v;

            // escape separators
            $v = str_replace(['\\', '|', ','], ['\\\\', '\|', '\,'], $v);

            $parts[] = $k.'='.$v;
        }

        return implode(',', $parts);
    }

    public function inc(string $metric, array $labels, int $by = 1): void
    {
        $field = $this->labelsToKey($labels);

        try {
            $this->redis->connection()->hincrby('m:'.$metric, $field, $by);
        } catch (\Throwable $e) {
            Log::debug('metrics_inc_failed', ['metric' => $metric, 'error' => $e->getMessage()]);
        }
    }

    public function addFloat(string $metric, array $labels, float $by): void
    {
        $field = $this->labelsToKey($labels);

        try {
            $this->redis->connection()->hincrbyfloat('m:'.$metric, $field, $by);
        } catch (\Throwable $e) {
            Log::debug('metrics_add_float_failed', ['metric' => $metric, 'error' => $e->getMessage()]);
        }
    }

    public function recordHttp(string $method, string $route, int $status, int $durationMs): void
    {
        $labels = [
            'method' => strtoupper($method),
            'route' => $route,
            'status' => (string) $status,
        ];

        $this->inc('http_requests_total', $labels, 1);
        $this->addFloat('http_request_duration_ms_sum', $labels, (float) $durationMs);
        $this->inc('http_request_duration_ms_count', $labels, 1);
    }

    /**
     * @return array<string, array<string,int|float|string>>
     */
    public function dumpAll(): array
    {
        $keys = [
            'm:http_requests_total',
            'm:http_request_duration_ms_sum',
            'm:http_request_duration_ms_count',
            'm:booking_cache_hit_total',
            'm:booking_cache_miss_total',
            'm:notification_outbox_enqueued_total',
            'm:notification_outbox_claimed_total',
            'm:notification_outbox_sent_total',
            'm:notification_outbox_failed_total',
            'm:notification_outbox_cancelled_total',
            'm:voucher_usage_rejected_total',
            'm:loyalty_clawback_shortfall_events_total',
            'm:loyalty_clawback_shortfall_points_total',
        ];

        $out = [];
        foreach ($keys as $key) {
            try {
                $out[$key] = $this->redis->connection()->hgetall($key) ?: [];
            } catch (\Throwable $e) {
                Log::debug('metrics_dump_failed', ['key' => $key, 'error' => $e->getMessage()]);
                $out[$key] = [];
            }
        }

        return $out;
    }
}

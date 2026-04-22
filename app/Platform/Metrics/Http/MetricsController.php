<?php

namespace App\Platform\Metrics\Http;

use App\Http\Controllers\Controller;
use App\Platform\Metrics\Services\MetricsService;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function __construct(
        private readonly MetricsService $metrics
    ) {}

    public function index(): Response
    {
        $all = $this->metrics->dumpAll();

        $lines = [];
        $lines[] = '# HELP http_requests_total Total HTTP requests';
        $lines[] = '# TYPE http_requests_total counter';
        $this->emitHash($lines, 'http_requests_total', $all['m:http_requests_total'] ?? []);

        $lines[] = '# HELP http_request_duration_ms_sum Sum of request durations in ms';
        $lines[] = '# TYPE http_request_duration_ms_sum counter';
        $this->emitHash($lines, 'http_request_duration_ms_sum', $all['m:http_request_duration_ms_sum'] ?? []);

        $lines[] = '# HELP http_request_duration_ms_count Count of request durations';
        $lines[] = '# TYPE http_request_duration_ms_count counter';
        $this->emitHash($lines, 'http_request_duration_ms_count', $all['m:http_request_duration_ms_count'] ?? []);

        $lines[] = '# HELP booking_cache_hit_total Cache hits for booking availability';
        $lines[] = '# TYPE booking_cache_hit_total counter';
        $this->emitHash($lines, 'booking_cache_hit_total', $all['m:booking_cache_hit_total'] ?? []);

        $lines[] = '# HELP booking_cache_miss_total Cache misses for booking availability';
        $lines[] = '# TYPE booking_cache_miss_total counter';
        $this->emitHash($lines, 'booking_cache_miss_total', $all['m:booking_cache_miss_total'] ?? []);

        $lines[] = '# HELP notification_outbox_enqueued_total Notification outbox messages enqueued';
        $lines[] = '# TYPE notification_outbox_enqueued_total counter';
        $this->emitHash($lines, 'notification_outbox_enqueued_total', $all['m:notification_outbox_enqueued_total'] ?? []);

        $lines[] = '# HELP notification_outbox_claimed_total Notification outbox messages claimed for processing';
        $lines[] = '# TYPE notification_outbox_claimed_total counter';
        $this->emitHash($lines, 'notification_outbox_claimed_total', $all['m:notification_outbox_claimed_total'] ?? []);

        $lines[] = '# HELP notification_outbox_sent_total Notification outbox messages sent successfully';
        $lines[] = '# TYPE notification_outbox_sent_total counter';
        $this->emitHash($lines, 'notification_outbox_sent_total', $all['m:notification_outbox_sent_total'] ?? []);

        $lines[] = '# HELP notification_outbox_failed_total Notification outbox delivery failures';
        $lines[] = '# TYPE notification_outbox_failed_total counter';
        $this->emitHash($lines, 'notification_outbox_failed_total', $all['m:notification_outbox_failed_total'] ?? []);

        $lines[] = '# HELP notification_outbox_cancelled_total Notification outbox messages cancelled after retries exhausted';
        $lines[] = '# TYPE notification_outbox_cancelled_total counter';
        $this->emitHash($lines, 'notification_outbox_cancelled_total', $all['m:notification_outbox_cancelled_total'] ?? []);

        $lines[] = '# HELP voucher_usage_rejected_total Voucher usage attempts rejected by usage-cap guards';
        $lines[] = '# TYPE voucher_usage_rejected_total counter';
        $this->emitHash($lines, 'voucher_usage_rejected_total', $all['m:voucher_usage_rejected_total'] ?? []);

        $lines[] = '# HELP loyalty_clawback_shortfall_events_total Loyalty clawback events that could not fully reverse earned points';
        $lines[] = '# TYPE loyalty_clawback_shortfall_events_total counter';
        $this->emitHash($lines, 'loyalty_clawback_shortfall_events_total', $all['m:loyalty_clawback_shortfall_events_total'] ?? []);

        $lines[] = '# HELP loyalty_clawback_shortfall_points_total Loyalty points that could not be clawed back after refunds';
        $lines[] = '# TYPE loyalty_clawback_shortfall_points_total counter';
        $this->emitHash($lines, 'loyalty_clawback_shortfall_points_total', $all['m:loyalty_clawback_shortfall_points_total'] ?? []);

        $body = implode("\n", $lines)."\n";

        return new Response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    /**
     * @param  array<string, int|float|string>  $hash
     */
    private function emitHash(array &$lines, string $metric, array $hash): void
    {
        foreach ($hash as $labelKey => $val) {
            $labels = $this->parseLabels((string) $labelKey);
            $lines[] = $metric.$this->formatLabels($labels).' '.$val;
        }
    }

    /**
     * @return array<string,string>
     */
    private function parseLabels(string $key): array
    {
        if ($key === '') {
            return [];
        }

        $labels = [];
        foreach ($this->splitEscaped($key, ',') as $pair) {
            $parts = $this->splitEscaped($pair, '=', 2);
            if (count($parts) !== 2 || $parts[0] === '') {
                continue;
            }

            $labels[$parts[0]] = $parts[1];
        }

        return $labels;
    }

    /**
     * @param  array<string,string>  $labels
     */
    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        $parts = [];
        foreach ($labels as $k => $v) {
            $v = str_replace(['\\', '"', "\n"], ['\\\\', '\\"', ''], $v);
            $parts[] = $k.'="'.$v.'"';
        }

        return '{'.implode(',', $parts).'}';
    }

    /**
     * @return list<string>
     */
    private function splitEscaped(string $value, string $delimiter, ?int $limit = null): array
    {
        $parts = [];
        $current = '';
        $escaped = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $escaped = true;

                continue;
            }

            if ($char === $delimiter && ($limit === null || count($parts) < $limit - 1)) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($escaped) {
            $current .= '\\';
        }

        $parts[] = $current;

        return $parts;
    }
}

<?php

return [
    'enabled' => (bool) env('OPS_ALERTS_ENABLED', true),
    'cooldown_seconds' => max(60, (int) env('OPS_ALERTS_COOLDOWN_SECONDS', 1800)),
    'max_alerts_per_run' => max(1, (int) env('OPS_ALERTS_MAX_ALERTS_PER_RUN', 25)),
    'scheduler' => [
        'enabled' => (bool) env('OPS_ALERTS_SCHEDULER_ENABLED', true),
    ],
    'channels' => [
        'ops_log' => [
            'enabled' => (bool) env('OPS_ALERTS_LOG_ENABLED', true),
            'channel' => env('OPS_ALERTS_LOG_CHANNEL', 'ops_alerts'),
        ],
        'audit' => [
            'enabled' => (bool) env('OPS_ALERTS_AUDIT_ENABLED', true),
        ],
        'slack' => [
            'enabled' => (bool) env('OPS_ALERTS_SLACK_ENABLED', false),
            'webhook_url' => env('OPS_ALERTS_SLACK_WEBHOOK_URL', env('LOG_SLACK_WEBHOOK_URL')),
            'timeout_seconds' => max(1, (int) env('OPS_ALERTS_SLACK_TIMEOUT_SECONDS', 5)),
        ],
        'webhook' => [
            'enabled' => (bool) env('OPS_ALERTS_WEBHOOK_ENABLED', false),
            'url' => env('OPS_ALERTS_WEBHOOK_URL'),
            'timeout_seconds' => max(1, (int) env('OPS_ALERTS_WEBHOOK_TIMEOUT_SECONDS', 5)),
            'auth_token' => env('OPS_ALERTS_WEBHOOK_AUTH_TOKEN'),
        ],
    ],
];

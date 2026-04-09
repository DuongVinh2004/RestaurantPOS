<?php

return [
    'outbox' => [
        'enabled' => env('NOTIFICATIONS_OUTBOX_ENABLED', true),
        'mailer' => env('NOTIFICATIONS_OUTBOX_MAILER', env('MAIL_MAILER', 'log')),
        'batch_size' => (int) env('NOTIFICATIONS_OUTBOX_BATCH_SIZE', 20),
        'lock_seconds' => (int) env('NOTIFICATIONS_OUTBOX_LOCK_SECONDS', 90),
        'max_attempts' => (int) env('NOTIFICATIONS_OUTBOX_MAX_ATTEMPTS', 5),
        'retry_backoff_minutes' => [1, 5, 15, 60],
        'reminder_enabled' => env('NOTIFICATIONS_REMINDER_ENABLED', true),
        'reminder_lead_minutes' => (int) env('NOTIFICATIONS_REMINDER_LEAD_MINUTES', 60),
        'reminder_window_minutes' => (int) env('NOTIFICATIONS_REMINDER_WINDOW_MINUTES', 10),
        'cooldowns' => [
            'reservation.created' => (int) env('NOTIFICATIONS_COOLDOWN_RESERVATION_CREATED_SECONDS', 60),
            'reservation.cancelled' => (int) env('NOTIFICATIONS_COOLDOWN_RESERVATION_CANCELLED_SECONDS', 60),
            'reservation.reminder' => (int) env('NOTIFICATIONS_COOLDOWN_RESERVATION_REMINDER_SECONDS', 1800),
            'waiting_list.notified' => (int) env('NOTIFICATIONS_COOLDOWN_WAITING_LIST_NOTIFIED_SECONDS', 120),
            'payment.refunded' => (int) env('NOTIFICATIONS_COOLDOWN_PAYMENT_REFUNDED_SECONDS', 60),
        ],
        'health' => [
            'pending_warn_count' => (int) env('NOTIFICATIONS_OUTBOX_PENDING_WARN_COUNT', 100),
            'failed_warn_count' => (int) env('NOTIFICATIONS_OUTBOX_FAILED_WARN_COUNT', 10),
            'retry_due_warn_count' => (int) env('NOTIFICATIONS_OUTBOX_RETRY_DUE_WARN_COUNT', 20),
            'stale_processing_warn_count' => (int) env('NOTIFICATIONS_OUTBOX_STALE_PROCESSING_WARN_COUNT', 1),
            'oldest_pending_warn_seconds' => (int) env('NOTIFICATIONS_OUTBOX_OLDEST_PENDING_WARN_SECONDS', 900),
            'recent_failure_attempt_window_hours' => (int) env('NOTIFICATIONS_OUTBOX_RECENT_FAILURE_WINDOW_HOURS', 24),
        ],
    ],
    'channels' => [
        'email' => [
            'enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', true),
            'driver' => env('NOTIFICATIONS_EMAIL_DRIVER', 'mail'),
            'provider_key' => env('NOTIFICATIONS_EMAIL_PROVIDER_KEY', 'mail'),
            'delivery_mode' => 'real',
        ],
        'sms' => [
            'enabled' => env('NOTIFICATIONS_SMS_ENABLED', false),
            'driver' => env('NOTIFICATIONS_SMS_DRIVER', 'stub'),
            'provider_key' => env('NOTIFICATIONS_SMS_PROVIDER_KEY', 'sms.stub'),
            'delivery_mode' => 'stub',
        ],
        'zalo' => [
            'enabled' => env('NOTIFICATIONS_ZALO_ENABLED', false),
            'driver' => env('NOTIFICATIONS_ZALO_DRIVER', 'stub'),
            'provider_key' => env('NOTIFICATIONS_ZALO_PROVIDER_KEY', 'zalo.stub'),
            'delivery_mode' => 'stub',
        ],
    ],
    'preferences' => [
        'enabled' => env('NOTIFICATIONS_PREFERENCES_ENABLED', true),
        'timezone' => env('NOTIFICATIONS_PREFERENCES_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
        'default_opt_in_channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('NOTIFICATIONS_DEFAULT_OPT_IN_CHANNELS', 'Email'))
        ))),
    ],
];

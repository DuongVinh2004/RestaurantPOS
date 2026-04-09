<?php

declare(strict_types=1);

return [
    'anonymization' => [
        'display_name_template' => 'Deleted Customer #%d',
        'username_prefix' => 'anon_user_',
        'redacted_text' => '[redacted after privacy anonymization]',
        'redacted_recipient' => 'redacted://privacy/recipient',
        'redacted_file_url' => 'redacted://privacy/file',
        'redacted_json_reason' => 'privacy_anonymized',
    ],

    'retention' => [
        'customer_access_sessions_days' => 30,
        'user_auth_tokens_days' => 14,
        'notification_outbox_days' => 90,
        'notification_delivery_attempts_days' => 90,
        'conversation_analyses_days' => 30,
        'message_entities_days' => 30,
        'audit_logs_days' => 365,
        'payments_days' => 365,
        'payment_webhook_receipts_days' => 365,
    ],
];

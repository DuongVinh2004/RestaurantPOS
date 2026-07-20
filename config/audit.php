<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit privacy boundary
    |--------------------------------------------------------------------------
    |
    | A dedicated key is preferred so audit correlation identifiers can be
    | rotated independently. The application key remains a safe keyed fallback.
    |
    */
    'hash_key' => env('AUDIT_HASH_KEY', env('APP_KEY')),

    'failure_alert_channel' => env('AUDIT_FAILURE_ALERT_CHANNEL', 'audit_alert'),

    'tables' => [
        'logs' => 'audit_logs',
        'subjects' => 'audit_log_subjects',
    ],

    /*
    | Critical events must be recorded inside the active business transaction.
    | Patterns use Illuminate\Support\Str::is semantics.
    */
    'critical_event_patterns' => [
        'staff_api_key_*',
        'staff_order_payment_recorded',
        'staff.reservation.payment_refunded',
        'staff.reservation.refund_cancelled',
        'staff.cashier_shift.*',
        'admin.ingredient.*',
    ],

    'critical_action_patterns' => [
        'checkout.finalized',
        'payment.final_captured',
        'payment.refunded',
        'reservation.refund_cancelled',
        'cashier_shift.*',
        'inventory.*',
        'identity.staff_api_key.*',
    ],
];

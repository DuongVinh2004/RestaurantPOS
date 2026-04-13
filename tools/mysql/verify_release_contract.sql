SELECT 'verify_release_contract:start' AS checkpoint;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'),
    'SELECT "users:ok"',
    'SELECT * FROM __missing_restore_contract_users__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'reservations'),
    'SELECT "reservations:ok"',
    'SELECT * FROM __missing_restore_contract_reservations__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'restaurant_tables'),
    'SELECT "restaurant_tables:ok"',
    'SELECT * FROM __missing_restore_contract_restaurant_tables__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'notification_outbox'),
    'SELECT "notification_outbox:ok"',
    'SELECT * FROM __missing_restore_contract_notification_outbox__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notification_outbox' AND column_name = 'recipient_user_id'),
    'SELECT "notification_outbox.recipient_user_id:ok"',
    'SELECT * FROM __missing_restore_contract_notification_outbox_recipient_user_id__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'notification_outbox' AND column_name = 'dedupe_key'),
    'SELECT "notification_outbox.dedupe_key:ok"',
    'SELECT * FROM __missing_restore_contract_notification_outbox_dedupe_key__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'notification_delivery_attempts'),
    'SELECT "notification_delivery_attempts:ok"',
    'SELECT * FROM __missing_restore_contract_notification_delivery_attempts__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'notification_preferences'),
    'SELECT "notification_preferences:ok"',
    'SELECT * FROM __missing_restore_contract_notification_preferences__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'audit_logs' AND column_name = 'actor_type'),
    'SELECT "audit_logs.actor_type:ok"',
    'SELECT * FROM __missing_restore_contract_audit_logs_actor_type__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'audit_logs' AND column_name = 'summary_json'),
    'SELECT "audit_logs.summary_json:ok"',
    'SELECT * FROM __missing_restore_contract_audit_logs_summary_json__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'audit_logs' AND column_name = 'request_id'),
    'SELECT "audit_logs.request_id:ok"',
    'SELECT * FROM __missing_restore_contract_audit_logs_request_id__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'audit_log_subjects'),
    'SELECT "audit_log_subjects:ok"',
    'SELECT * FROM __missing_restore_contract_audit_log_subjects__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'branches' AND column_name = 'business_hours'),
    'SELECT "branches.business_hours:ok"',
    'SELECT * FROM __missing_restore_contract_branches_business_hours__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'branches' AND column_name = 'closure_windows'),
    'SELECT "branches.closure_windows:ok"',
    'SELECT * FROM __missing_restore_contract_branches_closure_windows__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'branches' AND column_name = 'booking_policy'),
    'SELECT "branches.booking_policy:ok"',
    'SELECT * FROM __missing_restore_contract_branches_booking_policy__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'customer_privacy_requests'),
    'SELECT "customer_privacy_requests:ok"',
    'SELECT * FROM __missing_restore_contract_customer_privacy_requests__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'privacy_anonymized_at'),
    'SELECT "users.privacy_anonymized_at:ok"',
    'SELECT * FROM __missing_restore_contract_users_privacy_anonymized_at__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'feature_flags'),
    'SELECT "feature_flags:ok"',
    'SELECT * FROM __missing_restore_contract_feature_flags__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'staff_api_keys'),
    'SELECT "staff_api_keys:ok"',
    'SELECT * FROM __missing_restore_contract_staff_api_keys__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.triggers
        WHERE trigger_schema = DATABASE()
          AND event_object_table = 'payments'
          AND trigger_name IN (
              'trg_payments__bi_refund_cap',
              'trg_payments__bu_refund_cap',
              'trg_payments__bi_refund_lineage_guard',
              'trg_payments__bu_refund_lineage_guard'
          )
    ),
    'SELECT * FROM __runtime_incompatible_payment_refund_triggers_present__',
    'SELECT "payments.refund_trigger_contract:ok"'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SELECT 'verify_release_contract:done' AS checkpoint;

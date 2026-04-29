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
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'reservation_order_items'),
    'SELECT "reservation_order_items:ok"',
    'SELECT * FROM __missing_restore_contract_reservation_order_items__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'reservation_order_items'
          AND constraint_name = 'chk_reservation_order_items__line_total_matches'
          AND constraint_type = 'CHECK'
    ),
    'SELECT "reservation_order_items.line_total_matches:ok"',
    'SELECT * FROM __missing_restore_contract_reservation_order_items_line_total_matches__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    NOT EXISTS (
        SELECT 1
        FROM reservation_order_items
        WHERE line_total <> ROUND(unit_price * quantity, 2)
    ),
    'SELECT "reservation_order_items.line_total_data:ok"',
    'SELECT * FROM __drifted_reservation_order_items_line_total__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND column_name = 'row_version'
          AND table_name IN ('ingredients', 'menu_item_recipes', 'suppliers', 'purchase_orders')
    ) = 4,
    'SELECT "inventory_master_data.row_version_columns:ok"',
    'SELECT * FROM __missing_restore_contract_inventory_master_data_row_version_columns__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    (
        SELECT COUNT(*)
        FROM information_schema.triggers
        WHERE trigger_schema = DATABASE()
          AND (
            (event_object_table = 'ingredients' AND trigger_name IN ('trg_ingredients__bi_row_version', 'trg_ingredients__bu_row_version'))
            OR (event_object_table = 'menu_item_recipes' AND trigger_name IN ('trg_menu_item_recipes__bi_row_version', 'trg_menu_item_recipes__bu_row_version'))
            OR (event_object_table = 'suppliers' AND trigger_name IN ('trg_suppliers__bi_row_version', 'trg_suppliers__bu_row_version'))
            OR (event_object_table = 'purchase_orders' AND trigger_name IN ('trg_purchase_orders__bi_row_version', 'trg_purchase_orders__bu_row_version'))
          )
    ) = 8,
    'SELECT "inventory_master_data.row_version_triggers:ok"',
    'SELECT * FROM __missing_restore_contract_inventory_master_data_row_version_triggers__'
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
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'reservations'
          AND constraint_name = 'fk_reservations__branch_id__branches'
          AND column_name = 'branch_id'
          AND referenced_table_name = 'branches'
          AND referenced_column_name = 'branch_id'
    ),
    'SELECT "reservations.branch_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_reservations_branch_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'kitchen_stations' AND column_name = 'branch_id'),
    'SELECT "kitchen_stations.branch_id:ok"',
    'SELECT * FROM __missing_restore_contract_kitchen_stations_branch_id__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'kitchen_stations'
          AND constraint_name = 'fk_kitchen_stations__branch_id__branches'
          AND column_name = 'branch_id'
          AND referenced_table_name = 'branches'
          AND referenced_column_name = 'branch_id'
    ),
    'SELECT "kitchen_stations.branch_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_kitchen_stations_branch_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'kitchen_station_category_routes' AND column_name = 'branch_id'),
    'SELECT "kitchen_station_category_routes.branch_id:ok"',
    'SELECT * FROM __missing_restore_contract_kitchen_station_category_routes_branch_id__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'kitchen_station_category_routes'
          AND index_name = 'uq_kitchen_station_category_routes__branch_id__category_id'
          AND non_unique = 0
    ),
    'SELECT "kitchen_station_category_routes.branch_category_unique:ok"',
    'SELECT * FROM __missing_restore_contract_kitchen_routes_branch_category_unique__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'kitchen_station_category_routes'
          AND constraint_name = 'fk_kitchen_station_category_routes__branch_id__branches'
          AND column_name = 'branch_id'
          AND referenced_table_name = 'branches'
          AND referenced_column_name = 'branch_id'
    ),
    'SELECT "kitchen_station_category_routes.branch_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_kitchen_station_category_routes_branch_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'table_holds'
          AND constraint_name = 'fk_table_holds__branch_id__branches'
          AND column_name = 'branch_id'
          AND referenced_table_name = 'branches'
          AND referenced_column_name = 'branch_id'
    ),
    'SELECT "table_holds.branch_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_table_holds_branch_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'cashier_shifts'
          AND constraint_name = 'fk_cashier_shifts__branch_id__branches'
          AND column_name = 'branch_id'
          AND referenced_table_name = 'branches'
          AND referenced_column_name = 'branch_id'
    ),
    'SELECT "cashier_shifts.branch_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_cashier_shifts_branch_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'cashier_shifts'
          AND constraint_name = 'fk_cashier_shifts__cashier_user_id__users'
          AND column_name = 'cashier_user_id'
          AND referenced_table_name = 'users'
          AND referenced_column_name = 'user_id'
    ),
    'SELECT "cashier_shifts.cashier_user_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_cashier_shifts_cashier_user_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'cashier_shifts'
          AND constraint_name = 'fk_cashier_shifts__opened_by__users'
          AND column_name = 'opened_by'
          AND referenced_table_name = 'users'
          AND referenced_column_name = 'user_id'
    ),
    'SELECT "cashier_shifts.opened_by_fk:ok"',
    'SELECT * FROM __missing_restore_contract_cashier_shifts_opened_by_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'cashier_shifts'
          AND constraint_name = 'fk_cashier_shifts__closed_by__users'
          AND column_name = 'closed_by'
          AND referenced_table_name = 'users'
          AND referenced_column_name = 'user_id'
    ),
    'SELECT "cashier_shifts.closed_by_fk:ok"',
    'SELECT * FROM __missing_restore_contract_cashier_shifts_closed_by_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = 'payments'
          AND column_name = 'cashier_shift_id'
          AND column_type = 'bigint unsigned'
          AND is_nullable = 'YES'
    ),
    'SELECT "payments.cashier_shift_id:ok"',
    'SELECT * FROM __missing_restore_contract_payments_cashier_shift_id__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'payments'
          AND index_name = 'idx_payments__cashier_shift_id'
          AND column_name = 'cashier_shift_id'
          AND seq_in_index = 1
    ),
    'SELECT "payments.cashier_shift_id_index:ok"',
    'SELECT * FROM __missing_restore_contract_payments_cashier_shift_index__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'payments'
          AND constraint_name = 'fk_payments__cashier_shift_id__cashier_shifts'
          AND column_name = 'cashier_shift_id'
          AND referenced_table_name = 'cashier_shifts'
          AND referenced_column_name = 'cashier_shift_id'
    ),
    'SELECT "payments.cashier_shift_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_payments_cashier_shift_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    NOT EXISTS (
        SELECT 1
        FROM reservations r
        WHERE r.status = 'Completed'
          AND (
              r.final_bill_amount IS NULL
              OR r.billed_at IS NULL
              OR TRIM(COALESCE(r.bill_currency, '')) = ''
          )
          AND EXISTS (
              SELECT 1
              FROM reservation_orders ro
              WHERE ro.reservation_id = r.reservation_id
                AND ro.order_type = 'OnSpot'
                AND ro.status = 'Completed'
          )
          AND EXISTS (
              SELECT 1
              FROM payments p
              WHERE p.reservation_id = r.reservation_id
                AND p.payment_type IN ('Deposit', 'Final')
                AND p.status IN ('Success', 'Partial')
          )
    ),
    'SELECT "reservations.completed_paid_bill_snapshot:ok"',
    'SELECT * FROM __drifted_completed_paid_reservations_missing_bill_snapshot__'
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
    EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'staff_branch_assignments'),
    'SELECT "staff_branch_assignments:ok"',
    'SELECT * FROM __missing_restore_contract_staff_branch_assignments__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'staff_branch_assignments'
          AND constraint_name = 'fk_staff_branch_assignments__user_id__users'
          AND column_name = 'user_id'
          AND referenced_table_name = 'users'
          AND referenced_column_name = 'user_id'
    ),
    'SELECT "staff_branch_assignments.user_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_staff_branch_assignments_user_fk__'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE()
          AND table_name = 'staff_branch_assignments'
          AND constraint_name = 'fk_staff_branch_assignments__branch_id__branches'
          AND column_name = 'branch_id'
          AND referenced_table_name = 'branches'
          AND referenced_column_name = 'branch_id'
    ),
    'SELECT "staff_branch_assignments.branch_id_fk:ok"',
    'SELECT * FROM __missing_restore_contract_staff_branch_assignments_branch_fk__'
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

SET @stmt := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.triggers
        WHERE trigger_schema = DATABASE()
          AND trigger_name IN (
              'trg_reservation_tables__bi_prevent_overlap',
              'trg_reservation_tables__bu_prevent_overlap',
              'trg_table_hold_details__bi_prevent_overlap',
              'trg_table_hold_details__bu_prevent_overlap'
          )
          AND (
              action_statement LIKE '%hold_status` IN (''Holding'', ''Pending'', ''Confirmed'')%'
              OR action_statement LIKE '%v_status IN (''Holding'', ''Pending'', ''Confirmed'')%'
          )
    ),
    'SELECT * FROM __stale_confirmed_hold_conflict_triggers__',
    'SELECT "table_hold_conflict_scope.confirmed_linkage:ok"'
);
PREPARE verify_stmt FROM @stmt;
EXECUTE verify_stmt;
DEALLOCATE PREPARE verify_stmt;

SELECT 'verify_release_contract:done' AS checkpoint;

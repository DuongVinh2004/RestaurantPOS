<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseContractInspector
{
    /**
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        $defaultConnection = (string) config('database.default', '');
        $configuredDriver = (string) config("database.connections.{$defaultConnection}.driver", $defaultConnection);
        $supportedDrivers = array_values(array_filter((array) config('booking.database_contract.supported_drivers', ['mysql'])));
        $enforceSupportedDriver = (bool) config('booking.database_contract.enforce_supported_driver', false);

        $driverName = $configuredDriver;
        try {
            $driverName = (string) DB::connection()->getDriverName();
        } catch (Throwable) {
            // fall back to configured driver if the database is unavailable.
        }

        $snapshot = [
            'default_connection' => $defaultConnection,
            'driver' => $driverName,
            'supported_drivers' => $supportedDrivers,
            'enforce_supported_driver' => $enforceSupportedDriver,
            'driver_supported' => in_array($driverName, $supportedDrivers, true),
            'status' => 'ok',
            'issues' => [],
            'checks' => [
                'refund_lineage_trigger_insert' => null,
                'refund_lineage_trigger_update' => null,
                'payment_refund_trigger_compatibility' => null,
                'menu_price_overlap_trigger_insert' => null,
                'menu_price_overlap_trigger_update' => null,
                'voucher_usage_cap_index' => null,
                'session_reservation_lookup_index' => null,
                'agent_assignment_active_unique' => null,
                'bank_account_default_unique' => null,
                'reservation_money_check' => null,
                'reservation_reserved_checked_in_check' => null,
                'payment_provider_transaction_unique' => null,
                'reservation_active_voucher_column' => null,
                'reservation_active_voucher_unique' => null,
                'payment_webhook_receipts_table' => null,
                'payment_webhook_receipts_unique' => null,
                'payment_webhook_receipts_delivery_status_check' => null,
                'reservation_deposit_payment_sessions_table' => null,
                'reservation_deposit_linked_payment_unique' => null,
                'reservation_deposit_session_status_check' => null,
                'reservation_bill_payment_sessions_table' => null,
                'reservation_bill_linked_payment_unique' => null,
                'reservation_bill_session_status_check' => null,
                'customer_access_sessions_table' => null,
                'customer_access_sessions_token_hash_unique' => null,
                'customer_access_sessions_expires_check' => null,
                'table_hold_time_range_check' => null,
                'notification_outbox_channel_enum' => null,
                'notification_outbox_recipient_user_column' => null,
                'notification_outbox_dedupe_key_column' => null,
                'notification_outbox_operational_indexes' => null,
                'ingredient_stock_movement_reference_unique' => null,
                'notification_delivery_attempts_table' => null,
                'notification_delivery_attempts_operational_indexes' => null,
                'notification_preferences_table' => null,
                'notification_preferences_unique' => null,
                'notification_preferences_quiet_window_check' => null,
                'audit_logs_context_columns' => null,
                'audit_logs_request_id_index' => null,
                'audit_log_subjects_table' => null,
                'audit_log_subjects_lookup_index' => null,
                'branches_policy_columns' => null,
                'customer_privacy_requests_table' => null,
                'customer_privacy_requests_indexes' => null,
                'users_privacy_anonymized_at_column' => null,
                'feature_flags_table' => null,
                'feature_flags_unique' => null,
                'users_role_no_default' => null,
                'staff_api_keys_table' => null,
                'staff_api_keys_hash_unique' => null,
                'staff_api_keys_label_check' => null,
            ],
        ];

        if (! $snapshot['driver_supported']) {
            $snapshot['status'] = $enforceSupportedDriver ? 'fail' : 'degraded';
            $snapshot['issues'][] = sprintf(
                'Configured database driver "%s" is outside the supported production contract (%s).',
                $driverName,
                implode(', ', $supportedDrivers) ?: 'none'
            );

            return $snapshot;
        }

        if (! in_array($driverName, ['mysql', 'mariadb'], true)) {
            return $snapshot;
        }

        try {
            $triggerNames = DB::table('information_schema.triggers')
                ->selectRaw('TRIGGER_NAME as trigger_name')
                ->whereRaw('TRIGGER_SCHEMA = DATABASE()')
                ->whereIn('TRIGGER_NAME', [
                    'trg_payments__bi_refund_cap',
                    'trg_payments__bu_refund_cap',
                    'trg_payments__bi_refund_lineage_guard',
                    'trg_payments__bu_refund_lineage_guard',
                    'trg_menu_item_prices__bi_overlap_guard',
                    'trg_menu_item_prices__bu_overlap_guard',
                ])
                ->pluck('trigger_name')
                ->map(static fn ($value) => (string) $value)
                ->all();

            $indexNames = DB::table('information_schema.statistics')
                ->selectRaw('TABLE_NAME as table_name, INDEX_NAME as index_name')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where(function ($query): void {
                    $query->where(function ($inner): void {
                        $inner->where('TABLE_NAME', 'user_vouchers')
                            ->where('INDEX_NAME', 'idx_user_vouchers__voucher_id__is_used__user_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'table_holds')
                            ->where('INDEX_NAME', 'idx_table_holds__session_id__confirmed_reservation_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'table_holds')
                            ->where('INDEX_NAME', 'idx_table_holds__session_id__confirmed_reservation_id__user_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'agent_assignments')
                            ->where('INDEX_NAME', 'uq_agent_assignments__active_conversation_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'bank_accounts')
                            ->where('INDEX_NAME', 'uq_bank_accounts__default_user_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'payments')
                            ->where('INDEX_NAME', 'uq_payments__payment_provider__transaction_code');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservations')
                            ->where('INDEX_NAME', 'uq_reservations__active_applied_user_voucher_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'payment_provider_webhook_receipts')
                            ->where('INDEX_NAME', 'uq_payment_provider_webhook_receipts__provider_code__pr_122db085');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservation_deposit_payment_sessions')
                            ->where('INDEX_NAME', 'uq_reservation_deposit_payment_sessions__linked_payment_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservation_bill_payment_sessions')
                            ->where('INDEX_NAME', 'uq_reservation_bill_payment_sessions__linked_payment_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'customer_access_sessions')
                            ->where('INDEX_NAME', 'uq_customer_access_sessions__token_hash');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'staff_api_keys')
                            ->where('INDEX_NAME', 'uq_staff_api_keys__key_hash');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'notification_outbox')
                            ->whereIn('INDEX_NAME', [
                                'idx_notification_outbox__dedupe_key__created_at',
                                'idx_notification_outbox__recipient_user_id__status__created_at',
                            ]);
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'ingredient_stock_movements')
                            ->where('INDEX_NAME', 'uq_ingredient_stock_movements__reference');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'notification_delivery_attempts')
                            ->whereIn('INDEX_NAME', [
                                'idx_notif_delivery_attempts__status__attempted_at',
                                'idx_notif_delivery_attempts__channel__status__attempted_at',
                                'idx_notif_delivery_attempts__provider_key__attempted_at',
                            ]);
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'notification_preferences')
                            ->where('INDEX_NAME', 'uq_notification_preferences__user_id__channel');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'audit_logs')
                            ->where('INDEX_NAME', 'idx_audit_logs__request_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'audit_log_subjects')
                            ->where('INDEX_NAME', 'idx_audit_log_subjects__subject_type__subject_id__audit_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'customer_privacy_requests')
                            ->whereIn('INDEX_NAME', [
                                'idx_customer_privacy_requests__user_id__status__created_at',
                                'idx_customer_privacy_requests__status__created_at',
                            ]);
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'feature_flags')
                            ->where('INDEX_NAME', 'uq_feature_flags__feature_key__environment__branch_id');
                    });
                })
                ->get(['table_name', 'index_name'])
                ->map(static fn ($row) => sprintf('%s:%s', (string) $row->table_name, (string) $row->index_name))
                ->all();

            $constraintNames = DB::table('information_schema.table_constraints')
                ->selectRaw('TABLE_NAME as table_name, CONSTRAINT_NAME as constraint_name')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where('CONSTRAINT_TYPE', 'CHECK')
                ->where(function ($query): void {
                    $query->where(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservations')
                            ->whereIn('CONSTRAINT_NAME', [
                                'chk_reservations__money_nonneg',
                                'chk_reservations__reserved_requires_checked_in_at',
                            ]);
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'table_holds')
                            ->where('CONSTRAINT_NAME', 'chk_table_holds__time_range');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'payment_provider_webhook_receipts')
                            ->where('CONSTRAINT_NAME', 'chk_payment_provider_webhook_receipts__delivery_status');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservation_deposit_payment_sessions')
                            ->where('CONSTRAINT_NAME', 'chk_reservation_deposit_payment_sessions__session_status');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservation_bill_payment_sessions')
                            ->where('CONSTRAINT_NAME', 'chk_reservation_bill_payment_sessions__session_status');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'customer_access_sessions')
                            ->where('CONSTRAINT_NAME', 'chk_customer_access_sessions__expires_future');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'staff_api_keys')
                            ->where('CONSTRAINT_NAME', 'chk_staff_api_keys__label_nonempty');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'notification_preferences')
                            ->where('CONSTRAINT_NAME', 'chk_notification_preferences__quiet_window');
                    });
                })
                ->get(['table_name', 'constraint_name'])
                ->map(static fn ($row) => sprintf('%s:%s', (string) $row->table_name, (string) $row->constraint_name))
                ->all();

            $columnMetadata = DB::table('information_schema.columns')
                ->selectRaw('TABLE_NAME as table_name, COLUMN_NAME as column_name, COLUMN_TYPE as column_type, IS_NULLABLE as is_nullable, COLUMN_DEFAULT as column_default')
                ->whereRaw('TABLE_SCHEMA = DATABASE()')
                ->where(function ($query): void {
                    $query->where(function ($inner): void {
                        $inner->where('TABLE_NAME', 'notification_outbox')
                            ->where('COLUMN_NAME', 'channel');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'users')
                            ->where('COLUMN_NAME', 'role_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'table_holds')
                            ->where('COLUMN_NAME', 'end_time');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservations')
                            ->where('COLUMN_NAME', 'active_applied_user_voucher_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'payment_provider_webhook_receipts')
                            ->where('COLUMN_NAME', 'payment_provider_webhook_receipt_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservation_deposit_payment_sessions')
                            ->where('COLUMN_NAME', 'deposit_payment_session_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'reservation_bill_payment_sessions')
                            ->where('COLUMN_NAME', 'bill_payment_session_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'customer_access_sessions')
                            ->where('COLUMN_NAME', 'access_session_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'staff_api_keys')
                            ->where('COLUMN_NAME', 'staff_api_key_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'notification_outbox')
                            ->whereIn('COLUMN_NAME', [
                                'recipient_user_id',
                                'dedupe_key',
                            ]);
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'notification_delivery_attempts')
                            ->where('COLUMN_NAME', 'attempt_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'notification_preferences')
                            ->where('COLUMN_NAME', 'notification_preference_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'audit_logs')
                            ->whereIn('COLUMN_NAME', [
                                'actor_type',
                                'summary_json',
                                'request_id',
                            ]);
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'audit_log_subjects')
                            ->where('COLUMN_NAME', 'audit_subject_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'branches')
                            ->whereIn('COLUMN_NAME', [
                                'business_hours',
                                'closure_windows',
                                'booking_policy',
                            ]);
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'customer_privacy_requests')
                            ->where('COLUMN_NAME', 'customer_privacy_request_id');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'users')
                            ->where('COLUMN_NAME', 'privacy_anonymized_at');
                    })->orWhere(function ($inner): void {
                        $inner->where('TABLE_NAME', 'feature_flags')
                            ->where('COLUMN_NAME', 'feature_flag_id');
                    });
                })
                ->get(['table_name', 'column_name', 'column_type', 'is_nullable', 'column_default'])
                ->mapWithKeys(static fn ($row) => [sprintf('%s:%s', (string) $row->table_name, (string) $row->column_name) => [
                    'column_type' => (string) ($row->column_type ?? ''),
                    'is_nullable' => (string) ($row->is_nullable ?? ''),
                    'column_default' => $row->column_default,
                ]])
                ->all();

            $snapshot['checks']['refund_lineage_trigger_insert'] = in_array('trg_payments__bi_refund_lineage_guard', $triggerNames, true);
            $snapshot['checks']['refund_lineage_trigger_update'] = in_array('trg_payments__bu_refund_lineage_guard', $triggerNames, true);
            $snapshot['checks']['payment_refund_trigger_compatibility'] = ! self::hasRuntimeIncompatiblePaymentRefundTriggers($triggerNames);
            $snapshot['checks']['menu_price_overlap_trigger_insert'] = in_array('trg_menu_item_prices__bi_overlap_guard', $triggerNames, true);
            $snapshot['checks']['menu_price_overlap_trigger_update'] = in_array('trg_menu_item_prices__bu_overlap_guard', $triggerNames, true);
            $snapshot['checks']['voucher_usage_cap_index'] = self::hasVoucherUsageCapIndex($indexNames);
            $snapshot['checks']['session_reservation_lookup_index'] = self::hasSessionReservationLookupIndex($indexNames);
            $snapshot['checks']['agent_assignment_active_unique'] = self::hasAgentAssignmentActiveUniqueIndex($indexNames);
            $snapshot['checks']['bank_account_default_unique'] = self::hasBankAccountDefaultUniqueIndex($indexNames);
            $snapshot['checks']['reservation_money_check'] = in_array('reservations:chk_reservations__money_nonneg', $constraintNames, true);
            $snapshot['checks']['reservation_reserved_checked_in_check'] = in_array('reservations:chk_reservations__reserved_requires_checked_in_at', $constraintNames, true);
            $snapshot['checks']['payment_provider_transaction_unique'] = in_array('payments:uq_payments__payment_provider__transaction_code', $indexNames, true);
            $snapshot['checks']['reservation_active_voucher_column'] = array_key_exists('reservations:active_applied_user_voucher_id', $columnMetadata);
            $snapshot['checks']['reservation_active_voucher_unique'] = in_array('reservations:uq_reservations__active_applied_user_voucher_id', $indexNames, true);
            $snapshot['checks']['payment_webhook_receipts_table'] = array_key_exists('payment_provider_webhook_receipts:payment_provider_webhook_receipt_id', $columnMetadata);
            $snapshot['checks']['payment_webhook_receipts_unique'] = self::hasPaymentWebhookReceiptsUniqueIndex($indexNames);
            $snapshot['checks']['payment_webhook_receipts_delivery_status_check'] = in_array('payment_provider_webhook_receipts:chk_payment_provider_webhook_receipts__delivery_status', $constraintNames, true);
            $snapshot['checks']['reservation_deposit_payment_sessions_table'] = array_key_exists('reservation_deposit_payment_sessions:deposit_payment_session_id', $columnMetadata);
            $snapshot['checks']['reservation_deposit_linked_payment_unique'] = self::hasReservationDepositLinkedPaymentUniqueIndex($indexNames);
            $snapshot['checks']['reservation_deposit_session_status_check'] = in_array('reservation_deposit_payment_sessions:chk_reservation_deposit_payment_sessions__session_status', $constraintNames, true);
            $snapshot['checks']['reservation_bill_payment_sessions_table'] = array_key_exists('reservation_bill_payment_sessions:bill_payment_session_id', $columnMetadata);
            $snapshot['checks']['reservation_bill_linked_payment_unique'] = self::hasReservationBillLinkedPaymentUniqueIndex($indexNames);
            $snapshot['checks']['reservation_bill_session_status_check'] = in_array('reservation_bill_payment_sessions:chk_reservation_bill_payment_sessions__session_status', $constraintNames, true);
            $snapshot['checks']['customer_access_sessions_table'] = array_key_exists('customer_access_sessions:access_session_id', $columnMetadata);
            $snapshot['checks']['customer_access_sessions_token_hash_unique'] = self::hasCustomerAccessSessionTokenHashUniqueIndex($indexNames);
            $snapshot['checks']['customer_access_sessions_expires_check'] = in_array('customer_access_sessions:chk_customer_access_sessions__expires_future', $constraintNames, true);
            $snapshot['checks']['table_hold_time_range_check'] = in_array('table_holds:chk_table_holds__time_range', $constraintNames, true)
                && (($columnMetadata['table_holds:end_time']['is_nullable'] ?? '') === 'NO');
            $snapshot['checks']['notification_outbox_channel_enum'] = str_starts_with(strtolower((string) ($columnMetadata['notification_outbox:channel']['column_type'] ?? '')), 'enum(');
            $snapshot['checks']['notification_outbox_recipient_user_column'] = array_key_exists('notification_outbox:recipient_user_id', $columnMetadata);
            $snapshot['checks']['notification_outbox_dedupe_key_column'] = array_key_exists('notification_outbox:dedupe_key', $columnMetadata);
            $snapshot['checks']['notification_outbox_operational_indexes'] = self::hasNotificationOutboxOperationalIndexes($indexNames);
            $snapshot['checks']['ingredient_stock_movement_reference_unique'] = self::hasIngredientStockMovementReferenceUniqueIndex($indexNames);
            $snapshot['checks']['notification_delivery_attempts_table'] = array_key_exists('notification_delivery_attempts:attempt_id', $columnMetadata);
            $snapshot['checks']['notification_delivery_attempts_operational_indexes'] = self::hasNotificationDeliveryAttemptsOperationalIndexes($indexNames);
            $snapshot['checks']['notification_preferences_table'] = array_key_exists('notification_preferences:notification_preference_id', $columnMetadata);
            $snapshot['checks']['notification_preferences_unique'] = self::hasNotificationPreferencesUniqueIndex($indexNames);
            $snapshot['checks']['notification_preferences_quiet_window_check'] = in_array('notification_preferences:chk_notification_preferences__quiet_window', $constraintNames, true);
            $snapshot['checks']['audit_logs_context_columns'] = self::hasAuditLogContextColumns($columnMetadata);
            $snapshot['checks']['audit_logs_request_id_index'] = in_array('audit_logs:idx_audit_logs__request_id', $indexNames, true);
            $snapshot['checks']['audit_log_subjects_table'] = array_key_exists('audit_log_subjects:audit_subject_id', $columnMetadata);
            $snapshot['checks']['audit_log_subjects_lookup_index'] = self::hasAuditLogSubjectsLookupIndex($indexNames);
            $snapshot['checks']['branches_policy_columns'] = self::hasBranchPolicyColumns($columnMetadata);
            $snapshot['checks']['customer_privacy_requests_table'] = array_key_exists('customer_privacy_requests:customer_privacy_request_id', $columnMetadata);
            $snapshot['checks']['customer_privacy_requests_indexes'] = self::hasCustomerPrivacyRequestIndexes($indexNames);
            $snapshot['checks']['users_privacy_anonymized_at_column'] = array_key_exists('users:privacy_anonymized_at', $columnMetadata);
            $snapshot['checks']['feature_flags_table'] = array_key_exists('feature_flags:feature_flag_id', $columnMetadata);
            $snapshot['checks']['feature_flags_unique'] = self::hasFeatureFlagUniqueIndex($indexNames);
            $snapshot['checks']['users_role_no_default'] = array_key_exists('users:role_id', $columnMetadata)
                && self::isUsersRoleDefaultRemoved($columnMetadata['users:role_id']['column_default'] ?? null);
            $snapshot['checks']['staff_api_keys_table'] = array_key_exists('staff_api_keys:staff_api_key_id', $columnMetadata);
            $snapshot['checks']['staff_api_keys_hash_unique'] = in_array('staff_api_keys:uq_staff_api_keys__key_hash', $indexNames, true);
            $snapshot['checks']['staff_api_keys_label_check'] = in_array('staff_api_keys:chk_staff_api_keys__label_nonempty', $constraintNames, true);
        } catch (Throwable $e) {
            $snapshot['status'] = 'degraded';
            $snapshot['issues'][] = 'Failed to inspect database contract metadata: ' . $e->getMessage();

            return $snapshot;
        }

        if ($snapshot['checks']['payment_refund_trigger_compatibility'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Runtime-incompatible payments refund triggers are still installed; refund execution can fail with MySQL ERROR 1442.';
        }

        if ($snapshot['checks']['menu_price_overlap_trigger_insert'] !== true || $snapshot['checks']['menu_price_overlap_trigger_update'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Menu price overlap guard triggers are missing from the current database.';
        }

        if ($snapshot['checks']['voucher_usage_cap_index'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Voucher usage-cap support index is missing.';
        }

        if ($snapshot['checks']['session_reservation_lookup_index'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Session-to-reservation lookup index is missing.';
        }

        if ($snapshot['checks']['agent_assignment_active_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Active agent-assignment uniqueness guard is missing.';
        }

        if ($snapshot['checks']['bank_account_default_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Default bank-account uniqueness guard is missing.';
        }

        if ($snapshot['checks']['reservation_money_check'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Reservation money non-negative check constraint is missing.';
        }

        if ($snapshot['checks']['reservation_reserved_checked_in_check'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Reservation checked-in timestamp guard for Reserved status is missing.';
        }

        if ($snapshot['checks']['payment_provider_transaction_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Payment provider transaction uniqueness guard is missing.';
        }

        if ($snapshot['checks']['reservation_active_voucher_column'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Active reservation voucher generated column is missing.';
        }

        if ($snapshot['checks']['reservation_active_voucher_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Active reservation voucher uniqueness guard is missing.';
        }

        if ($snapshot['checks']['payment_webhook_receipts_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Payment provider webhook receipt table is missing from the current database contract.';
        }

        if ($snapshot['checks']['payment_webhook_receipts_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Payment provider webhook receipt uniqueness guard is missing.';
        }

        if ($snapshot['checks']['payment_webhook_receipts_delivery_status_check'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Payment provider webhook receipt delivery-status check is missing.';
        }

        if ($snapshot['checks']['reservation_deposit_payment_sessions_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Reservation deposit payment session table is missing from the current database contract.';
        }

        if ($snapshot['checks']['reservation_deposit_linked_payment_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Reservation deposit payment session linked-payment uniqueness guard is missing.';
        }

        if ($snapshot['checks']['reservation_deposit_session_status_check'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Reservation deposit payment session status check is missing.';
        }

        if ($snapshot['checks']['reservation_bill_payment_sessions_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Reservation bill payment session table is missing from the current database contract.';
        }

        if ($snapshot['checks']['reservation_bill_linked_payment_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Reservation bill payment session linked-payment uniqueness guard is missing.';
        }

        if ($snapshot['checks']['reservation_bill_session_status_check'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Reservation bill payment session status check is missing.';
        }

        if ($snapshot['checks']['customer_access_sessions_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Customer access session table is missing from the current database contract.';
        }

        if ($snapshot['checks']['customer_access_sessions_token_hash_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Customer access session token-hash uniqueness guard is missing.';
        }

        if ($snapshot['checks']['customer_access_sessions_expires_check'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Customer access session expiry check is missing.';
        }

        if ($snapshot['checks']['table_hold_time_range_check'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Table-hold time-range contract is missing or end_time remains nullable.';
        }

        if ($snapshot['checks']['notification_outbox_channel_enum'] !== true) {
            $snapshot['status'] = 'degraded';
            $snapshot['issues'][] = 'Notification outbox channel column is broader than the supported enum contract.';
        }

        if ($snapshot['checks']['notification_outbox_recipient_user_column'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Notification outbox recipient_user_id linkage column is missing.';
        }

        if ($snapshot['checks']['notification_outbox_dedupe_key_column'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Notification outbox dedupe_key column is missing.';
        }

        if ($snapshot['checks']['notification_outbox_operational_indexes'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Notification outbox operational indexes for dedupe and recipient scans are incomplete.';
        }

        if ($snapshot['checks']['ingredient_stock_movement_reference_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Ingredient stock movement reference uniqueness guard is missing.';
        }

        if ($snapshot['checks']['notification_delivery_attempts_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Notification delivery attempt table is missing from the current database contract.';
        }

        if ($snapshot['checks']['notification_delivery_attempts_operational_indexes'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Notification delivery attempt operational indexes are incomplete.';
        }

        if ($snapshot['checks']['notification_preferences_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Notification preferences table is missing from the current database contract.';
        }

        if ($snapshot['checks']['notification_preferences_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Notification preferences uniqueness guard is missing.';
        }

        if ($snapshot['checks']['notification_preferences_quiet_window_check'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Notification preferences quiet-window check is missing.';
        }

        if ($snapshot['checks']['audit_logs_context_columns'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Unified audit trail context columns are missing from audit_logs.';
        }

        if ($snapshot['checks']['audit_logs_request_id_index'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Audit log request_id lookup index is missing.';
        }

        if ($snapshot['checks']['audit_log_subjects_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Audit log subjects table is missing from the current database contract.';
        }

        if ($snapshot['checks']['audit_log_subjects_lookup_index'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Audit log subjects lookup index is missing.';
        }

        if ($snapshot['checks']['branches_policy_columns'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Branch scheduling policy columns are missing from branches.';
        }

        if ($snapshot['checks']['customer_privacy_requests_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Customer privacy requests table is missing from the current database contract.';
        }

        if ($snapshot['checks']['customer_privacy_requests_indexes'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Customer privacy request operational indexes are incomplete.';
        }

        if ($snapshot['checks']['users_privacy_anonymized_at_column'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Users.privacy_anonymized_at column is missing.';
        }

        if ($snapshot['checks']['feature_flags_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Feature flags table is missing from the current database contract.';
        }

        if ($snapshot['checks']['feature_flags_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Feature flag scope uniqueness guard is missing.';
        }

        if ($snapshot['checks']['users_role_no_default'] !== true) {
            $snapshot['status'] = 'degraded';
            $snapshot['issues'][] = 'Users.role_id still has an implicit default; callers must bind roles explicitly.';
        }

        if ($snapshot['checks']['staff_api_keys_table'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Database-backed staff_api_keys table is missing from the current database contract.';
        }

        if ($snapshot['checks']['staff_api_keys_hash_unique'] !== true) {
            $snapshot['status'] = 'fail';
            $snapshot['issues'][] = 'Staff API key hash uniqueness guard is missing.';
        }

        if ($snapshot['checks']['staff_api_keys_label_check'] !== true) {
            $snapshot['status'] = $snapshot['status'] === 'fail' ? 'fail' : 'degraded';
            $snapshot['issues'][] = 'Staff API key label integrity check is missing.';
        }

        return $snapshot;
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasVoucherUsageCapIndex(array $indexNames): bool
    {
        return in_array('user_vouchers:idx_user_vouchers__voucher_id__is_used__user_id', $indexNames, true);
    }

    /**
     * @param list<string> $triggerNames
     */
    public static function hasRuntimeIncompatiblePaymentRefundTriggers(array $triggerNames): bool
    {
        foreach ([
            'trg_payments__bi_refund_cap',
            'trg_payments__bu_refund_cap',
            'trg_payments__bi_refund_lineage_guard',
            'trg_payments__bu_refund_lineage_guard',
        ] as $candidate) {
            if (in_array($candidate, $triggerNames, true)) {
                return true;
            }
        }

        return false;
    }


    /**
     * @param list<string> $indexNames
     */
    public static function hasAgentAssignmentActiveUniqueIndex(array $indexNames): bool
    {
        return in_array('agent_assignments:uq_agent_assignments__active_conversation_id', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasBankAccountDefaultUniqueIndex(array $indexNames): bool
    {
        return in_array('bank_accounts:uq_bank_accounts__default_user_id', $indexNames, true);
    }


    /**
     * @param list<string> $indexNames
     */
    public static function hasPaymentProviderTransactionUniqueIndex(array $indexNames): bool
    {
        return in_array('payments:uq_payments__payment_provider__transaction_code', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasActiveReservationVoucherUniqueIndex(array $indexNames): bool
    {
        return in_array('reservations:uq_reservations__active_applied_user_voucher_id', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasPaymentWebhookReceiptsUniqueIndex(array $indexNames): bool
    {
        return in_array('payment_provider_webhook_receipts:uq_payment_provider_webhook_receipts__provider_code__pr_122db085', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasReservationDepositLinkedPaymentUniqueIndex(array $indexNames): bool
    {
        return in_array('reservation_deposit_payment_sessions:uq_reservation_deposit_payment_sessions__linked_payment_id', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasReservationBillLinkedPaymentUniqueIndex(array $indexNames): bool
    {
        return in_array('reservation_bill_payment_sessions:uq_reservation_bill_payment_sessions__linked_payment_id', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasCustomerAccessSessionTokenHashUniqueIndex(array $indexNames): bool
    {
        return in_array('customer_access_sessions:uq_customer_access_sessions__token_hash', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasNotificationOutboxOperationalIndexes(array $indexNames): bool
    {
        foreach ([
            'notification_outbox:idx_notification_outbox__dedupe_key__created_at',
            'notification_outbox:idx_notification_outbox__recipient_user_id__status__created_at',
        ] as $candidate) {
            if (! in_array($candidate, $indexNames, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasIngredientStockMovementReferenceUniqueIndex(array $indexNames): bool
    {
        return in_array('ingredient_stock_movements:uq_ingredient_stock_movements__reference', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasNotificationDeliveryAttemptsOperationalIndexes(array $indexNames): bool
    {
        foreach ([
            'notification_delivery_attempts:idx_notif_delivery_attempts__status__attempted_at',
            'notification_delivery_attempts:idx_notif_delivery_attempts__channel__status__attempted_at',
            'notification_delivery_attempts:idx_notif_delivery_attempts__provider_key__attempted_at',
        ] as $candidate) {
            if (! in_array($candidate, $indexNames, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasNotificationPreferencesUniqueIndex(array $indexNames): bool
    {
        return in_array('notification_preferences:uq_notification_preferences__user_id__channel', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasAuditLogSubjectsLookupIndex(array $indexNames): bool
    {
        return in_array('audit_log_subjects:idx_audit_log_subjects__subject_type__subject_id__audit_id', $indexNames, true);
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasCustomerPrivacyRequestIndexes(array $indexNames): bool
    {
        foreach ([
            'customer_privacy_requests:idx_customer_privacy_requests__user_id__status__created_at',
            'customer_privacy_requests:idx_customer_privacy_requests__status__created_at',
        ] as $candidate) {
            if (! in_array($candidate, $indexNames, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasFeatureFlagUniqueIndex(array $indexNames): bool
    {
        return in_array('feature_flags:uq_feature_flags__feature_key__environment__branch_id', $indexNames, true);
    }

    public static function isNotificationOutboxChannelEnum(string $columnType): bool
    {
        return str_starts_with(strtolower(trim($columnType)), 'enum');
    }

    public static function isUsersRoleDefaultRemoved(mixed $columnDefault): bool
    {
        return $columnDefault === null;
    }

    /**
     * @param list<string> $indexNames
     */
    public static function hasSessionReservationLookupIndex(array $indexNames): bool
    {
        foreach ([
            'table_holds:idx_table_holds__session_id__confirmed_reservation_id',
            'table_holds:idx_table_holds__session_id__confirmed_reservation_id__user_id',
        ] as $candidate) {
            if (in_array($candidate, $indexNames, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{column_type: string, is_nullable: string, column_default: mixed}> $columnMetadata
     */
    public static function hasAuditLogContextColumns(array $columnMetadata): bool
    {
        foreach ([
            'audit_logs:actor_type',
            'audit_logs:summary_json',
            'audit_logs:request_id',
        ] as $candidate) {
            if (! array_key_exists($candidate, $columnMetadata)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, array{column_type: string, is_nullable: string, column_default: mixed}> $columnMetadata
     */
    public static function hasBranchPolicyColumns(array $columnMetadata): bool
    {
        foreach ([
            'branches:business_hours',
            'branches:closure_windows',
            'branches:booking_policy',
        ] as $candidate) {
            if (! array_key_exists($candidate, $columnMetadata)) {
                return false;
            }
        }

        return true;
    }
}

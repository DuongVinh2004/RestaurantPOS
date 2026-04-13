<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseContractInspector;
use Tests\TestCase;

class DatabaseContractInspectorTest extends TestCase
{
    public function test_it_accepts_exact_session_lookup_index(): void
    {
        $this->assertTrue(DatabaseContractInspector::hasSessionReservationLookupIndex([
            'table_holds:idx_table_holds__session_id__confirmed_reservation_id',
        ]));
    }

    public function test_it_accepts_wider_session_lookup_index_with_matching_left_prefix(): void
    {
        $this->assertTrue(DatabaseContractInspector::hasSessionReservationLookupIndex([
            'table_holds:idx_table_holds__session_id__confirmed_reservation_id__user_id',
        ]));
    }

    public function test_it_requires_active_agent_assignment_unique_index(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasAgentAssignmentActiveUniqueIndex([
            'agent_assignments:idx_agent_assignments__conversation_id__is_active',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasAgentAssignmentActiveUniqueIndex([
            'agent_assignments:uq_agent_assignments__active_conversation_id',
        ]));
    }

    public function test_it_requires_default_bank_account_unique_index(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasBankAccountDefaultUniqueIndex([
            'bank_accounts:idx_bank_accounts__user_id__is_default',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasBankAccountDefaultUniqueIndex([
            'bank_accounts:uq_bank_accounts__default_user_id',
        ]));
    }

    public function test_it_requires_explicit_voucher_usage_cap_index(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasVoucherUsageCapIndex([
            'user_vouchers:idx_user_vouchers__user_id__is_used',
            'user_vouchers:fk_user_vouchers__voucher_id__vouchers',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasVoucherUsageCapIndex([
            'user_vouchers:idx_user_vouchers__voucher_id__is_used__user_id',
        ]));
    }

    public function test_it_detects_runtime_incompatible_payment_refund_triggers(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasRuntimeIncompatiblePaymentRefundTriggers([
            'trg_menu_item_prices__bi_overlap_guard',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasRuntimeIncompatiblePaymentRefundTriggers([
            'trg_payments__bi_refund_cap',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasRuntimeIncompatiblePaymentRefundTriggers([
            'trg_payments__bu_refund_lineage_guard',
        ]));
    }

    public function test_it_requires_payment_provider_transaction_uniqueness_guard(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasPaymentProviderTransactionUniqueIndex([
            'payments:idx_payments__reservation_id__payment_type__status',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasPaymentProviderTransactionUniqueIndex([
            'payments:uq_payments__payment_provider__transaction_code',
        ]));
    }

    public function test_it_requires_active_reservation_voucher_uniqueness_guard(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasActiveReservationVoucherUniqueIndex([
            'reservations:idx_reservations__applied_user_voucher_id',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasActiveReservationVoucherUniqueIndex([
            'reservations:uq_reservations__active_applied_user_voucher_id',
        ]));
    }

    public function test_it_requires_payment_webhook_receipt_uniqueness_guard(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasPaymentWebhookReceiptsUniqueIndex([
            'payment_provider_webhook_receipts:idx_payment_provider_webhook_receipts__provider_code__p_6117b764',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasPaymentWebhookReceiptsUniqueIndex([
            'payment_provider_webhook_receipts:uq_payment_provider_webhook_receipts__provider_code__pr_122db085',
        ]));
    }

    public function test_it_requires_reservation_payment_session_linked_payment_uniqueness_guards(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasReservationDepositLinkedPaymentUniqueIndex([
            'reservation_deposit_payment_sessions:idx_reservation_deposit_payment_sessions__reservation_i_5cc2e4f1',
        ]));
        $this->assertTrue(DatabaseContractInspector::hasReservationDepositLinkedPaymentUniqueIndex([
            'reservation_deposit_payment_sessions:uq_reservation_deposit_payment_sessions__linked_payment_id',
        ]));

        $this->assertFalse(DatabaseContractInspector::hasReservationBillLinkedPaymentUniqueIndex([
            'reservation_bill_payment_sessions:idx_reservation_bill_payment_sessions__reservation_id___b47ad219',
        ]));
        $this->assertTrue(DatabaseContractInspector::hasReservationBillLinkedPaymentUniqueIndex([
            'reservation_bill_payment_sessions:uq_reservation_bill_payment_sessions__linked_payment_id',
        ]));
    }

    public function test_it_requires_customer_access_session_token_hash_uniqueness_guard(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasCustomerAccessSessionTokenHashUniqueIndex([
            'customer_access_sessions:idx_customer_access_sessions__user_id__expires_at',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasCustomerAccessSessionTokenHashUniqueIndex([
            'customer_access_sessions:uq_customer_access_sessions__token_hash',
        ]));
    }

    public function test_it_recognizes_notification_outbox_enum_contract(): void
    {
        $this->assertFalse(DatabaseContractInspector::isNotificationOutboxChannelEnum('varchar'));
        $this->assertTrue(DatabaseContractInspector::isNotificationOutboxChannelEnum('enum'));
    }

    public function test_it_requires_notification_platform_operational_indexes(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasNotificationOutboxOperationalIndexes([
            'notification_outbox:idx_notification_outbox__dedupe_key__created_at',
        ]));
        $this->assertTrue(DatabaseContractInspector::hasNotificationOutboxOperationalIndexes([
            'notification_outbox:idx_notification_outbox__dedupe_key__created_at',
            'notification_outbox:idx_notification_outbox__recipient_user_id__status__created_at',
        ]));

        $this->assertFalse(DatabaseContractInspector::hasNotificationDeliveryAttemptsOperationalIndexes([
            'notification_delivery_attempts:idx_notif_delivery_attempts__status__attempted_at',
            'notification_delivery_attempts:idx_notif_delivery_attempts__channel__status__attempted_at',
        ]));
        $this->assertTrue(DatabaseContractInspector::hasNotificationDeliveryAttemptsOperationalIndexes([
            'notification_delivery_attempts:idx_notif_delivery_attempts__status__attempted_at',
            'notification_delivery_attempts:idx_notif_delivery_attempts__channel__status__attempted_at',
            'notification_delivery_attempts:idx_notif_delivery_attempts__provider_key__attempted_at',
        ]));

        $this->assertFalse(DatabaseContractInspector::hasNotificationPreferencesUniqueIndex([
            'notification_preferences:idx_notification_preferences__user_id',
        ]));
        $this->assertTrue(DatabaseContractInspector::hasNotificationPreferencesUniqueIndex([
            'notification_preferences:uq_notification_preferences__user_id__channel',
        ]));
    }

    public function test_it_requires_ingredient_stock_movement_reference_uniqueness_guard(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasIngredientStockMovementReferenceUniqueIndex([
            'ingredient_stock_movements:idx_ingredient_stock_movements__reference',
        ]));

        $this->assertTrue(DatabaseContractInspector::hasIngredientStockMovementReferenceUniqueIndex([
            'ingredient_stock_movements:uq_ingredient_stock_movements__reference',
        ]));
    }

    public function test_it_requires_audit_privacy_and_feature_flag_contract_helpers(): void
    {
        $this->assertFalse(DatabaseContractInspector::hasAuditLogContextColumns([
            'audit_logs:actor_type' => ['column_type' => 'varchar(40)', 'is_nullable' => 'YES', 'column_default' => null],
            'audit_logs:summary_json' => ['column_type' => 'json', 'is_nullable' => 'YES', 'column_default' => null],
        ]));
        $this->assertTrue(DatabaseContractInspector::hasAuditLogContextColumns([
            'audit_logs:actor_type' => ['column_type' => 'varchar(40)', 'is_nullable' => 'YES', 'column_default' => null],
            'audit_logs:summary_json' => ['column_type' => 'json', 'is_nullable' => 'YES', 'column_default' => null],
            'audit_logs:request_id' => ['column_type' => 'varchar(64)', 'is_nullable' => 'YES', 'column_default' => null],
        ]));

        $this->assertFalse(DatabaseContractInspector::hasBranchPolicyColumns([
            'branches:business_hours' => ['column_type' => 'json', 'is_nullable' => 'YES', 'column_default' => null],
            'branches:closure_windows' => ['column_type' => 'json', 'is_nullable' => 'YES', 'column_default' => null],
        ]));
        $this->assertTrue(DatabaseContractInspector::hasBranchPolicyColumns([
            'branches:business_hours' => ['column_type' => 'json', 'is_nullable' => 'YES', 'column_default' => null],
            'branches:closure_windows' => ['column_type' => 'json', 'is_nullable' => 'YES', 'column_default' => null],
            'branches:booking_policy' => ['column_type' => 'json', 'is_nullable' => 'YES', 'column_default' => null],
        ]));

        $this->assertFalse(DatabaseContractInspector::hasAuditLogSubjectsLookupIndex([
            'audit_log_subjects:idx_audit_log_subjects__audit_id',
        ]));
        $this->assertTrue(DatabaseContractInspector::hasAuditLogSubjectsLookupIndex([
            'audit_log_subjects:idx_audit_log_subjects__subject_type__subject_id__audit_id',
        ]));

        $this->assertFalse(DatabaseContractInspector::hasCustomerPrivacyRequestIndexes([
            'customer_privacy_requests:idx_customer_privacy_requests__status__created_at',
        ]));
        $this->assertTrue(DatabaseContractInspector::hasCustomerPrivacyRequestIndexes([
            'customer_privacy_requests:idx_customer_privacy_requests__user_id__status__created_at',
            'customer_privacy_requests:idx_customer_privacy_requests__status__created_at',
        ]));

        $this->assertFalse(DatabaseContractInspector::hasFeatureFlagUniqueIndex([
            'feature_flags:idx_feature_flags__feature_key',
        ]));
        $this->assertTrue(DatabaseContractInspector::hasFeatureFlagUniqueIndex([
            'feature_flags:uq_feature_flags__feature_key__environment__branch_id',
        ]));
    }

    public function test_it_requires_users_role_default_to_be_removed(): void
    {
        $this->assertFalse(DatabaseContractInspector::isUsersRoleDefaultRemoved('1'));
        $this->assertTrue(DatabaseContractInspector::isUsersRoleDefaultRemoved(null));
    }

    public function test_snapshot_initializes_april_five_foundation_checks_even_on_unsupported_driver(): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.driver', 'sqlite');
        config()->set('booking.database_contract.supported_drivers', ['mysql']);
        config()->set('booking.database_contract.enforce_supported_driver', false);

        $snapshot = app(DatabaseContractInspector::class)->snapshot();

        foreach ([
            'notification_outbox_recipient_user_column',
            'notification_outbox_dedupe_key_column',
            'notification_outbox_operational_indexes',
            'ingredient_stock_movement_reference_unique',
            'notification_delivery_attempts_table',
            'notification_delivery_attempts_operational_indexes',
            'notification_preferences_table',
            'notification_preferences_unique',
            'notification_preferences_quiet_window_check',
            'audit_logs_context_columns',
            'audit_logs_request_id_index',
            'audit_log_subjects_table',
            'audit_log_subjects_lookup_index',
            'branches_policy_columns',
            'customer_privacy_requests_table',
            'customer_privacy_requests_indexes',
            'users_privacy_anonymized_at_column',
            'feature_flags_table',
            'feature_flags_unique',
            'payment_refund_trigger_compatibility',
        ] as $checkKey) {
            $this->assertArrayHasKey($checkKey, $snapshot['checks']);
            $this->assertNull($snapshot['checks'][$checkKey]);
        }
    }
}

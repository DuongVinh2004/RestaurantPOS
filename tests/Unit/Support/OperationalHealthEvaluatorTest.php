<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Platform\Health\Support\OperationalHealthEvaluator;
use PHPUnit\Framework\TestCase;

class OperationalHealthEvaluatorTest extends TestCase
{
    public function test_notification_outbox_snapshot_becomes_degraded_when_failed_backlog_crosses_threshold(): void
    {
        $result = OperationalHealthEvaluator::forNotificationOutbox([
            'pending_count' => 5,
            'failed_count' => 12,
            'retry_due_count' => 0,
            'stale_processing_count' => 0,
            'oldest_pending_age_seconds' => 60,
        ], [
            'pending_warn_count' => 100,
            'failed_warn_count' => 10,
            'retry_due_warn_count' => 20,
            'stale_processing_warn_count' => 1,
            'oldest_pending_warn_seconds' => 900,
        ]);

        $this->assertSame('degraded', $result['status']);
        $this->assertContains('notification_outbox_failed_backlog', $result['reasons']);
    }

    public function test_payment_integrity_snapshot_becomes_fail_when_over_refund_exists(): void
    {
        $result = OperationalHealthEvaluator::forPaymentIntegrity([
            'over_refunded_source_count' => 1,
            'refund_without_source_count' => 0,
        ], [
            'over_refund_fail_count' => 1,
            'refund_without_source_fail_count' => 1,
        ]);

        $this->assertSame('fail', $result['status']);
        $this->assertContains('payment_over_refund_detected', $result['reasons']);
    }


    public function test_payment_integrity_snapshot_becomes_fail_when_currency_or_scope_mismatch_exists(): void
    {
        $result = OperationalHealthEvaluator::forPaymentIntegrity([
            'over_refunded_source_count' => 0,
            'refund_without_source_count' => 0,
            'cross_reservation_refund_count' => 1,
            'currency_mismatch_refund_count' => 1,
            'invalid_refund_target_count' => 1,
        ], [
            'over_refund_fail_count' => 1,
            'refund_without_source_fail_count' => 1,
        ]);

        $this->assertSame('fail', $result['status']);
        $this->assertContains('refund_cross_reservation_detected', $result['reasons']);
        $this->assertContains('refund_currency_mismatch_detected', $result['reasons']);
        $this->assertContains('refund_invalid_source_payment_detected', $result['reasons']);
    }

    public function test_session_linkage_snapshot_becomes_degraded_when_backfill_is_needed_or_legacy_fallback_is_enabled(): void
    {
        $result = OperationalHealthEvaluator::forSessionLinkage([
            'active_unlinked_session_hold_count' => 6,
            'legacy_fallback_enabled' => true,
        ], [
            'unlinked_session_hold_warn_count' => 5,
        ]);

        $this->assertSame('degraded', $result['status']);
        $this->assertContains('session_hold_linkage_backfill_needed', $result['reasons']);
        $this->assertContains('session_legacy_fallback_enabled', $result['reasons']);
    }

    public function test_voucher_lock_snapshot_stays_ok_below_threshold(): void
    {
        $result = OperationalHealthEvaluator::forVoucherLocks([
            'stale_lock_count' => 2,
        ], [
            'stale_lock_warn_count' => 5,
        ]);

        $this->assertSame('ok', $result['status']);
        $this->assertSame([], $result['reasons']);
    }


    public function test_staff_api_key_snapshot_fails_when_database_store_has_no_active_keys(): void
    {
        $result = OperationalHealthEvaluator::forStaffApiKeys([
            'database_store_enabled' => true,
            'env_fallback_enabled' => false,
            'active_count' => 0,
            'never_used_active_count' => 0,
            'expiring_soon_count' => 0,
        ], [
            'missing_active_fail_count' => 1,
            'never_used_warn_count' => 5,
        ]);

        $this->assertSame('fail', $result['status']);
        $this->assertContains('staff_api_keys_missing_active_keys', $result['reasons']);
    }

    public function test_table_state_audit_snapshot_degrades_when_recent_audit_rows_miss_actor_and_context(): void
    {
        $result = OperationalHealthEvaluator::forTableStateAudit([
            'recent_transition_count' => 5,
            'recent_missing_actor_count' => 1,
            'recent_missing_context_count' => 4,
        ], [
            'missing_actor_warn_count' => 1,
            'missing_context_warn_count' => 3,
        ]);

        $this->assertSame('degraded', $result['status']);
        $this->assertContains('table_state_audit_missing_actor', $result['reasons']);
        $this->assertContains('table_state_audit_missing_context', $result['reasons']);
    }

    public function test_row_version_contract_snapshot_fails_when_required_requests_are_missing(): void
    {
        $result = OperationalHealthEvaluator::forRowVersionContract([
            'missing_required_count' => 1,
        ], [
            'missing_required_fail_count' => 1,
        ]);

        $this->assertSame('fail', $result['status']);
        $this->assertContains('staff_mutation_row_version_contract_missing', $result['reasons']);
    }

}

<?php

declare(strict_types=1);

namespace App\Platform\Health\Support;

final class OperationalHealthEvaluator
{
    /**
     * @param  array<string,int|float|string|null>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forNotificationOutbox(array $snapshot, array $thresholds): array
    {
        $reasons = [];
        $status = 'ok';

        $failedCount = (int) ($snapshot['failed_count'] ?? 0);
        $retryDueCount = (int) ($snapshot['retry_due_count'] ?? 0);
        $staleProcessingCount = (int) ($snapshot['stale_processing_count'] ?? 0);
        $pendingCount = (int) ($snapshot['pending_count'] ?? 0);
        $oldestPendingAge = (int) ($snapshot['oldest_pending_age_seconds'] ?? 0);

        if ($failedCount >= max(1, (int) ($thresholds['failed_warn_count'] ?? 10))) {
            $status = 'degraded';
            $reasons[] = 'notification_outbox_failed_backlog';
        }

        if ($retryDueCount >= max(1, (int) ($thresholds['retry_due_warn_count'] ?? 20))) {
            $status = 'degraded';
            $reasons[] = 'notification_outbox_retry_due_backlog';
        }

        if ($staleProcessingCount >= max(1, (int) ($thresholds['stale_processing_warn_count'] ?? 1))) {
            $status = 'degraded';
            $reasons[] = 'notification_outbox_stale_processing';
        }

        if ($pendingCount >= max(1, (int) ($thresholds['pending_warn_count'] ?? 100))) {
            $status = 'degraded';
            $reasons[] = 'notification_outbox_pending_backlog';
        }

        if ($oldestPendingAge >= max(60, (int) ($thresholds['oldest_pending_warn_seconds'] ?? 900))) {
            $status = 'degraded';
            $reasons[] = 'notification_outbox_oldest_pending_stale';
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,int|float|string|null|array<int,array<string,mixed>>>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forPaymentIntegrity(array $snapshot, array $thresholds): array
    {
        $reasons = [];

        $overRefundCount = (int) ($snapshot['over_refunded_source_count'] ?? 0);
        $refundWithoutSourceCount = (int) ($snapshot['refund_without_source_count'] ?? 0);
        $crossReservationRefundCount = (int) ($snapshot['cross_reservation_refund_count'] ?? 0);
        $currencyMismatchRefundCount = (int) ($snapshot['currency_mismatch_refund_count'] ?? 0);
        $invalidRefundTargetCount = (int) ($snapshot['invalid_refund_target_count'] ?? 0);
        $overRefundFailCount = max(1, (int) ($thresholds['over_refund_fail_count'] ?? 1));
        $refundWithoutSourceFailCount = max(1, (int) ($thresholds['refund_without_source_fail_count'] ?? 1));

        if ($overRefundCount >= $overRefundFailCount) {
            $reasons[] = 'payment_over_refund_detected';
        }

        if ($refundWithoutSourceCount >= $refundWithoutSourceFailCount) {
            $reasons[] = 'refund_without_source_detected';
        }

        if ($crossReservationRefundCount > 0) {
            $reasons[] = 'refund_cross_reservation_detected';
        }

        if ($currencyMismatchRefundCount > 0) {
            $reasons[] = 'refund_currency_mismatch_detected';
        }

        if ($invalidRefundTargetCount > 0) {
            $reasons[] = 'refund_invalid_source_payment_detected';
        }

        return [
            'status' => $reasons === [] ? 'ok' : 'fail',
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,int|float|string|bool|null>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forSessionLinkage(array $snapshot, array $thresholds): array
    {
        $reasons = [];
        $status = 'ok';

        $unlinkedCount = (int) ($snapshot['active_unlinked_session_hold_count'] ?? 0);
        $warnCount = max(1, (int) ($thresholds['unlinked_session_hold_warn_count'] ?? 5));
        $legacyFallbackEnabled = (bool) ($snapshot['legacy_fallback_enabled'] ?? false);

        if ($unlinkedCount >= $warnCount) {
            $status = 'degraded';
            $reasons[] = 'session_hold_linkage_backfill_needed';
        }

        if ($legacyFallbackEnabled) {
            $status = 'degraded';
            $reasons[] = 'session_legacy_fallback_enabled';
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,int|float|string|null>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */

    /**
     * @param  array<string,int|float|string|bool|null>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forStaffApiKeys(array $snapshot, array $thresholds): array
    {
        $status = 'ok';
        $reasons = [];

        $databaseStoreEnabled = (bool) ($snapshot['database_store_enabled'] ?? false);
        $activeCount = (int) ($snapshot['active_count'] ?? 0);
        $neverUsedActiveCount = (int) ($snapshot['never_used_active_count'] ?? 0);
        $expiringSoonCount = (int) ($snapshot['expiring_soon_count'] ?? 0);
        $envFallbackEnabled = (bool) ($snapshot['env_fallback_enabled'] ?? false);

        $missingActiveFailCount = max(1, (int) ($thresholds['missing_active_fail_count'] ?? 1));
        $neverUsedWarnCount = max(1, (int) ($thresholds['never_used_warn_count'] ?? 5));

        if ($databaseStoreEnabled && $activeCount < $missingActiveFailCount) {
            $status = 'fail';
            $reasons[] = 'staff_api_keys_missing_active_keys';
        }

        if ($envFallbackEnabled) {
            $status = $status === 'fail' ? 'fail' : 'degraded';
            $reasons[] = 'staff_api_keys_env_fallback_enabled';
        }

        if ($neverUsedActiveCount >= $neverUsedWarnCount) {
            $status = $status === 'fail' ? 'fail' : 'degraded';
            $reasons[] = 'staff_api_keys_never_used_backlog';
        }

        if ($expiringSoonCount > 0) {
            $status = $status === 'fail' ? 'fail' : 'degraded';
            $reasons[] = 'staff_api_keys_expiring_soon';
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,int|float|string|bool|null>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forTableStateAudit(array $snapshot, array $thresholds): array
    {
        $status = 'ok';
        $reasons = [];

        $recentTransitionCount = (int) ($snapshot['recent_transition_count'] ?? 0);
        $missingActorCount = (int) ($snapshot['recent_missing_actor_count'] ?? 0);
        $missingContextCount = (int) ($snapshot['recent_missing_context_count'] ?? 0);
        $missingActorWarnCount = max(1, (int) ($thresholds['missing_actor_warn_count'] ?? 1));
        $missingContextWarnCount = max(1, (int) ($thresholds['missing_context_warn_count'] ?? 3));

        if ($recentTransitionCount > 0 && $missingActorCount >= $missingActorWarnCount) {
            $status = 'degraded';
            $reasons[] = 'table_state_audit_missing_actor';
        }

        if ($recentTransitionCount > 0 && $missingContextCount >= $missingContextWarnCount) {
            $status = 'degraded';
            $reasons[] = 'table_state_audit_missing_context';
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,int|float|string|bool|null|array<int,array<string,mixed>>>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forRowVersionContract(array $snapshot, array $thresholds): array
    {
        $missingRequiredCount = (int) ($snapshot['missing_required_count'] ?? 0);
        $failCount = max(1, (int) ($thresholds['missing_required_fail_count'] ?? 1));

        if ($missingRequiredCount >= $failCount) {
            return [
                'status' => 'fail',
                'reasons' => ['staff_mutation_row_version_contract_missing'],
            ];
        }

        return [
            'status' => 'ok',
            'reasons' => [],
        ];
    }

    public static function forVoucherLocks(array $snapshot, array $thresholds): array
    {
        $staleLockCount = (int) ($snapshot['stale_lock_count'] ?? 0);
        $warnCount = max(1, (int) ($thresholds['stale_lock_warn_count'] ?? 10));

        if ($staleLockCount >= $warnCount) {
            return [
                'status' => 'degraded',
                'reasons' => ['voucher_stale_locks_detected'],
            ];
        }

        return [
            'status' => 'ok',
            'reasons' => [],
        ];
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forReportingSnapshots(array $snapshot, array $thresholds): array
    {
        $missingTables = array_values((array) ($snapshot['missing_tables'] ?? []));
        if ($missingTables !== []) {
            return [
                'status' => 'fail',
                'reasons' => ['reporting_snapshot_tables_missing'],
            ];
        }

        $reasons = [];
        $status = 'ok';
        $familyCount = (int) ($snapshot['family_count'] ?? 0);
        $populatedFamilyCount = (int) ($snapshot['populated_family_count'] ?? 0);
        $staleHours = max(1, (int) ($thresholds['stale_hours'] ?? 48));
        $staleSeconds = $staleHours * 3600;
        $actionableEmptyFamilyCount = 0;

        foreach ((array) ($snapshot['families'] ?? []) as $familySnapshot) {
            if (! is_array($familySnapshot)) {
                continue;
            }

            $rowCount = (int) ($familySnapshot['row_count'] ?? 0);
            $sourceActivityCount = (int) ($familySnapshot['source_activity_count'] ?? 0);
            if ($rowCount <= 0 && $sourceActivityCount > 0) {
                $actionableEmptyFamilyCount++;
            }
        }

        if ($populatedFamilyCount === 0 && $actionableEmptyFamilyCount > 0) {
            $status = 'degraded';
            $reasons[] = 'reporting_snapshot_seed_missing';
        } elseif ($familyCount > 0 && $actionableEmptyFamilyCount > 0) {
            $status = 'degraded';
            $reasons[] = 'reporting_snapshot_incomplete';
        }

        foreach ((array) ($snapshot['families'] ?? []) as $familySnapshot) {
            if (! is_array($familySnapshot)) {
                continue;
            }

            $rowCount = (int) ($familySnapshot['row_count'] ?? 0);
            $ageSeconds = $familySnapshot['latest_refresh_age_seconds'] ?? null;
            $staleScopeCount = (int) ($familySnapshot['stale_scope_count'] ?? 0);

            if ($staleScopeCount > 0) {
                $status = 'degraded';
                $reasons[] = 'reporting_snapshot_stale';
                break;
            }

            if ($rowCount <= 0 || $ageSeconds === null) {
                continue;
            }

            if ((int) $ageSeconds >= $staleSeconds) {
                $status = 'degraded';
                $reasons[] = 'reporting_snapshot_stale';
                break;
            }
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forBranchDefaults(array $snapshot, array $thresholds): array
    {
        unset($thresholds);

        if (! (($snapshot['table_present'] ?? true) === true)) {
            return [
                'status' => 'fail',
                'reasons' => ['branches_table_missing'],
            ];
        }

        $reasons = [];
        $status = 'ok';
        $totalCount = (int) ($snapshot['total_count'] ?? 0);
        $activeCount = (int) ($snapshot['active_count'] ?? 0);
        $defaultCount = (int) ($snapshot['default_count'] ?? 0);
        $inactiveDefaultCount = (int) ($snapshot['inactive_default_count'] ?? 0);
        $duplicateCodeCount = (int) ($snapshot['duplicate_code_count'] ?? 0);
        $activeIncompleteSchedulingCount = (int) ($snapshot['active_incomplete_scheduling_count'] ?? 0);

        $compatibilityBootstrapAvailable = (bool) ($snapshot['compatibility_bootstrap_available'] ?? false);

        if ($totalCount <= 0) {
            if (! $compatibilityBootstrapAvailable) {
                $reasons[] = 'branch_catalog_empty';
            }
        } elseif ($activeCount <= 0) {
            $reasons[] = 'branch_catalog_no_active_branch';
        }

        if ($defaultCount <= 0) {
            if ($totalCount > 0 && ! $compatibilityBootstrapAvailable) {
                $reasons[] = 'branch_default_missing';
            }
        } elseif ($defaultCount > 1) {
            $reasons[] = 'branch_default_ambiguous';
        }

        if ($inactiveDefaultCount > 0) {
            $reasons[] = 'branch_default_inactive';
        }

        if ($duplicateCodeCount > 0) {
            $reasons[] = 'branch_code_duplicate';
        }

        if ($reasons !== []) {
            $status = 'fail';
        }

        if ($activeIncompleteSchedulingCount > 0) {
            $status = $status === 'fail' ? 'fail' : 'degraded';
            $reasons[] = 'branch_scheduling_incomplete';
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forKitchenKds(array $snapshot, array $thresholds): array
    {
        $reasons = [];
        $status = 'ok';

        $statusDriftCount = (int) ($snapshot['status_drift_count'] ?? 0);
        $routingDriftCount = (int) ($snapshot['routing_drift_count'] ?? 0);
        $stuckTicketCount = (int) ($snapshot['stuck_ticket_count'] ?? 0);
        $oldestFiredAgeSeconds = (int) ($snapshot['oldest_fired_age_seconds'] ?? 0);
        $oldestReadyAgeSeconds = (int) ($snapshot['oldest_ready_age_seconds'] ?? 0);

        if ($statusDriftCount > 0) {
            $status = 'fail';
            $reasons[] = 'kitchen_ticket_status_drift_detected';
        }

        if ($routingDriftCount > 0) {
            $status = 'fail';
            $reasons[] = 'kitchen_ticket_routing_drift_detected';
        }

        if ($stuckTicketCount > 0) {
            $status = $status === 'fail' ? 'fail' : 'degraded';
            $reasons[] = 'kitchen_ticket_stuck_detected';
        }

        if ($status !== 'fail') {
            if ($oldestFiredAgeSeconds >= max(60, (int) ($thresholds['fired_backlog_warn_seconds'] ?? 900))) {
                $status = 'degraded';
                $reasons[] = 'kitchen_ticket_fire_backlog_stale';
            }

            if ($oldestReadyAgeSeconds >= max(60, (int) ($thresholds['ready_backlog_warn_seconds'] ?? 600))) {
                $status = 'degraded';
                $reasons[] = 'kitchen_ticket_ready_backlog_stale';
            }
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forInventoryPurchasing(array $snapshot, array $thresholds): array
    {
        $reasons = [];
        $status = 'ok';

        $issueOrderCount = (int) ($snapshot['issue_order_count'] ?? 0);
        $movementIssueCount = (int) ($snapshot['movement_issue_count'] ?? 0);
        $duplicatePurchaseReceiptReferenceCount = (int) ($snapshot['duplicate_purchase_receipt_reference_count'] ?? 0);
        $overdueOpenOrderCount = (int) ($snapshot['overdue_open_order_count'] ?? 0);
        $oldestOverdueOpenAgeSeconds = (int) ($snapshot['oldest_overdue_open_age_seconds'] ?? 0);

        if ($duplicatePurchaseReceiptReferenceCount > 0) {
            $status = 'fail';
            $reasons[] = 'inventory_purchase_receipt_lineage_duplicate_detected';
        }

        if ($movementIssueCount > 0) {
            $status = 'fail';
            $reasons[] = 'inventory_stock_movement_lineage_drift_detected';
        }

        if ($issueOrderCount > 0) {
            $status = 'fail';
            $reasons[] = 'inventory_purchase_receiving_drift_detected';
        }

        if ($status !== 'fail') {
            if ($overdueOpenOrderCount >= max(1, (int) ($thresholds['overdue_open_order_warn_count'] ?? 1))) {
                $status = 'degraded';
                $reasons[] = 'inventory_purchase_order_overdue_backlog';
            }

            if ($oldestOverdueOpenAgeSeconds >= max(60, (int) ($thresholds['overdue_open_order_warn_seconds'] ?? 86400))) {
                $status = 'degraded';
                $reasons[] = 'inventory_purchase_order_overdue_stale';
            }
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @param  array<string,mixed>  $snapshot
     * @param  array<string,int>  $thresholds
     * @return array{status:string,reasons:array<int,string>}
     */
    public static function forConversationInbox(array $snapshot, array $thresholds): array
    {
        $reasons = [];
        $status = 'ok';

        $terminalWithActiveAssignmentCount = (int) ($snapshot['terminal_with_active_assignment_count'] ?? 0);
        $unassignedCount = (int) ($snapshot['unassigned_count'] ?? 0);
        $overdueCount = (int) ($snapshot['overdue_count'] ?? 0);
        $oldestOverdueAgeSeconds = (int) ($snapshot['oldest_overdue_age_seconds'] ?? 0);

        if ($terminalWithActiveAssignmentCount > 0) {
            $status = 'fail';
            $reasons[] = 'conversation_terminal_assignment_drift';
        }

        if ($status !== 'fail') {
            if ($unassignedCount >= max(1, (int) ($thresholds['unassigned_warn_count'] ?? 5))) {
                $status = 'degraded';
                $reasons[] = 'conversation_unassigned_backlog';
            }

            if ($overdueCount >= max(1, (int) ($thresholds['overdue_warn_count'] ?? 5))) {
                $status = 'degraded';
                $reasons[] = 'conversation_overdue_backlog';
            }

            if ($oldestOverdueAgeSeconds >= max(60, (int) ($thresholds['oldest_overdue_warn_seconds'] ?? 3600))) {
                $status = 'degraded';
                $reasons[] = 'conversation_backlog_stale';
            }
        }

        return [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}

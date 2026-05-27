<?php

declare(strict_types=1);

namespace App\Platform\Metrics\Services;

use App\Enums\KitchenTicketStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StaffConversationWorkflowState;
use App\Modules\BranchScheduling\Application\Services\BranchSchedulingPolicyService;
use App\Modules\Conversations\Application\Services\StaffConversationInboxService;
use App\Modules\InventoryProcurement\Application\Workflows\InventoryStockReconciliationService;
use App\Modules\InventoryProcurement\Application\Workflows\PurchaseOrderReconciliationService;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenTicketReconciliationService;
use App\Modules\Notifications\Application\Services\NotificationOutboxHealthService;
use App\Platform\ApiContract\Services\DatabaseContractInspector;
use App\Platform\Health\Services\OpsHeartbeatService;
use App\Platform\Health\Support\OperationalHealthEvaluator;
use App\Platform\QualityAssurance\Verification\Application\Verifiers\StaffMutationRowVersionContract;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\SharedKernel\Money\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationalInsightsService
{
    public function __construct(
        private readonly DatabaseContractInspector $databaseContractInspector,
        private readonly KitchenTicketReconciliationService $kitchenTicketReconciliationService,
        private readonly PurchaseOrderReconciliationService $purchaseOrderReconciliationService,
        private readonly OperationalRealtimeService $staffOperationalRealtimeService,
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
    {
        $now ??= Carbon::now('UTC');
        $mysqlRuntime = $this->safeSectionSnapshot('mysql_runtime', fn () => $this->mysqlRuntimeSnapshot());
        $redisRuntime = $this->safeSectionSnapshot('redis_runtime', fn () => $this->redisRuntimeSnapshot($now));
        $schedulerHeartbeat = $this->safeSectionSnapshot('scheduler_heartbeat', fn () => $this->schedulerHeartbeatSnapshot($now));

        if (($mysqlRuntime['status'] ?? 'fail') !== 'ok') {
            return [
                'mysql_runtime' => $mysqlRuntime,
                'redis_runtime' => $redisRuntime,
                'scheduler_heartbeat' => $schedulerHeartbeat,
                'notification_outbox' => $this->dependencyBlockedSection('notification_outbox', 'mysql_runtime', $mysqlRuntime),
                'payment_integrity' => $this->dependencyBlockedSection('payment_integrity', 'mysql_runtime', $mysqlRuntime),
                'voucher_locks' => $this->dependencyBlockedSection('voucher_locks', 'mysql_runtime', $mysqlRuntime),
                'session_linkage' => $this->dependencyBlockedSection('session_linkage', 'mysql_runtime', $mysqlRuntime),
                'staff_api_keys' => $this->dependencyBlockedSection('staff_api_keys', 'mysql_runtime', $mysqlRuntime),
                'table_state_audit' => $this->dependencyBlockedSection('table_state_audit', 'mysql_runtime', $mysqlRuntime),
                'row_version_contract' => $this->safeSectionSnapshot('row_version_contract', fn () => $this->rowVersionContractSnapshot()),
                'reporting_snapshots' => $this->dependencyBlockedSection('reporting_snapshots', 'mysql_runtime', $mysqlRuntime),
                'kitchen_kds' => $this->dependencyBlockedSection('kitchen_kds', 'mysql_runtime', $mysqlRuntime),
                'inventory_purchasing' => $this->dependencyBlockedSection('inventory_purchasing', 'mysql_runtime', $mysqlRuntime),
                'conversation_inbox' => $this->dependencyBlockedSection('conversation_inbox', 'mysql_runtime', $mysqlRuntime),
                'staff_operational_realtime' => $this->safeSectionSnapshot('staff_operational_realtime', fn () => $this->staffOperationalRealtimeSnapshot()),
                'branch_defaults' => $this->dependencyBlockedSection('branch_defaults', 'mysql_runtime', $mysqlRuntime),
                'database_contract' => $this->dependencyBlockedSection('database_contract', 'mysql_runtime', $mysqlRuntime),
            ];
        }

        return [
            'mysql_runtime' => $mysqlRuntime,
            'redis_runtime' => $redisRuntime,
            'scheduler_heartbeat' => $schedulerHeartbeat,
            'notification_outbox' => $this->safeSectionSnapshot('notification_outbox', fn () => $this->notificationOutboxSnapshot($now)),
            'payment_integrity' => $this->safeSectionSnapshot('payment_integrity', fn () => $this->paymentIntegritySnapshot($paymentSampleLimit)),
            'voucher_locks' => $this->safeSectionSnapshot('voucher_locks', fn () => $this->voucherLockSnapshot($now)),
            'session_linkage' => $this->safeSectionSnapshot('session_linkage', fn () => $this->sessionLinkageSnapshot($now)),
            'staff_api_keys' => $this->safeSectionSnapshot('staff_api_keys', fn () => $this->staffApiKeySnapshot($now)),
            'table_state_audit' => $this->safeSectionSnapshot('table_state_audit', fn () => $this->tableStateAuditSnapshot($now)),
            'row_version_contract' => $this->safeSectionSnapshot('row_version_contract', fn () => $this->rowVersionContractSnapshot()),
            'reporting_snapshots' => $this->safeSectionSnapshot('reporting_snapshots', fn () => $this->reportingSnapshotsSnapshot($now)),
            'kitchen_kds' => $this->safeSectionSnapshot('kitchen_kds', fn () => $this->kitchenKdsSnapshot($now)),
            'inventory_purchasing' => $this->safeSectionSnapshot('inventory_purchasing', fn () => $this->inventoryPurchasingSnapshot($now)),
            'conversation_inbox' => $this->safeSectionSnapshot('conversation_inbox', fn () => $this->conversationInboxSnapshot($now)),
            'staff_operational_realtime' => $this->safeSectionSnapshot('staff_operational_realtime', fn () => $this->staffOperationalRealtimeSnapshot()),
            'branch_defaults' => $this->safeSectionSnapshot('branch_defaults', fn () => $this->branchDefaultsSnapshot()),
            'database_contract' => $this->safeSectionSnapshot('database_contract', fn () => $this->databaseContractInspector->snapshot()),
        ];
    }

    /**
     * @param  array<string,mixed>  $upstream
     * @return array<string,mixed>
     */
    private function dependencyBlockedSection(string $section, string $dependency, array $upstream): array
    {
        return [
            'status' => 'fail',
            'reasons' => ['dependency_blocked'],
            'dependency' => $dependency,
            'message' => sprintf('%s inspection skipped because %s is unavailable.', $section, $dependency),
            'upstream_reasons' => array_values(array_map('strval', (array) ($upstream['reasons'] ?? []))),
            'upstream_error' => $upstream['error'] ?? null,
        ];
    }

    /**
     * @param  callable(): array<string,mixed>  $resolver
     * @return array<string,mixed>
     */
    private function safeSectionSnapshot(string $section, callable $resolver): array
    {
        try {
            return $resolver();
        } catch (Throwable $exception) {
            return [
                'status' => 'fail',
                'reasons' => ['runtime_dependency_unavailable'],
                'error' => sprintf('%s inspection failed: %s', $section, trim($exception->getMessage())),
                'exception_class' => $exception::class,
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function mysqlRuntimeSnapshot(): array
    {
        DB::selectOne('SELECT 1');

        return [
            'status' => 'ok',
            'reasons' => [],
            'connection' => (string) config('database.default'),
            'driver' => (string) DB::connection()->getDriverName(),
            'probe' => 'select_1',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function redisRuntimeSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $key = 'ops:insights:redis:'.$now->format('YmdHis').':'.random_int(1000, 9999);
        $redis = Cache::store('redis');
        $redis->put($key, 'pong', 10);
        $valueOk = $redis->get($key) === 'pong';
        $lock = $redis->lock('ops:insights:redis-lock:'.$key, 3);
        $lockOk = (bool) $lock->get();

        if ($lockOk) {
            $lock->release();
        }

        $ok = $valueOk && $lockOk;

        return [
            'status' => $ok ? 'ok' : 'fail',
            'reasons' => $ok ? [] : ['redis_probe_failed'],
            'probe' => 'cache_store_redis_set_get_lock',
            'set_get_ok' => $valueOk,
            'lock_ok' => $lockOk,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function schedulerHeartbeatSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $lastRun = app(OpsHeartbeatService::class)->getLastRun('scheduler');
        $staleThresholdSeconds = (int) config('booking.scheduler_heartbeat_stale_seconds', 180);

        if (! $lastRun) {
            return [
                'status' => 'fail',
                'reasons' => ['scheduler_heartbeat_missing'],
                'last_heartbeat_at_utc' => null,
                'age_seconds' => null,
                'stale_threshold_seconds' => $staleThresholdSeconds,
                'remediation' => 'Start the scheduler worker and confirm schedule:work touches ops:heartbeat:scheduler.',
            ];
        }

        $ageSeconds = max(0, $now->getTimestamp() - $lastRun->getTimestamp());
        $isStale = $ageSeconds > $staleThresholdSeconds;

        return [
            'status' => $isStale ? 'fail' : 'ok',
            'reasons' => $isStale ? ['scheduler_heartbeat_stale'] : [],
            'last_heartbeat_at_utc' => $lastRun->copy()->utc()->toIso8601String(),
            'age_seconds' => $ageSeconds,
            'stale_threshold_seconds' => $staleThresholdSeconds,
            'remediation' => $isStale
                ? 'Restart the scheduler worker and verify queue/schedule logs for stuck tasks.'
                : null,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function notificationOutboxSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');

        $enabled = (bool) config('notifications.outbox.enabled', true);
        $healthSnapshot = app(NotificationOutboxHealthService::class)->snapshot($now);
        if (! $enabled) {
            return [
                'enabled' => false,
                'table_present' => Schema::hasTable('notification_outbox'),
                'pending_count' => 0,
                'processing_count' => 0,
                'failed_count' => 0,
                'cancelled_count' => 0,
                'retry_due_count' => 0,
                'stale_processing_count' => 0,
                'oldest_pending_age_seconds' => null,
                'oldest_retry_due_age_seconds' => null,
                'dead_letter_count' => 0,
                'recent_failure_attempt_count' => 0,
                'channel_breakdown' => (array) ($healthSnapshot['channel_breakdown'] ?? []),
                'status' => 'ok',
                'reasons' => [],
            ];
        }

        if (! Schema::hasTable('notification_outbox')) {
            return [
                'enabled' => true,
                'table_present' => false,
                'pending_count' => 0,
                'processing_count' => 0,
                'failed_count' => 0,
                'cancelled_count' => 0,
                'retry_due_count' => 0,
                'stale_processing_count' => 0,
                'oldest_pending_age_seconds' => null,
                'oldest_retry_due_age_seconds' => null,
                'dead_letter_count' => 0,
                'recent_failure_attempt_count' => 0,
                'channel_breakdown' => (array) ($healthSnapshot['channel_breakdown'] ?? []),
                'status' => 'fail',
                'reasons' => ['notification_outbox_table_missing'],
            ];
        }

        $pendingCount = (int) DB::table('notification_outbox')->where('status', 'Pending')->count();
        $processingCount = (int) DB::table('notification_outbox')->where('status', 'Processing')->count();
        $failedCount = (int) DB::table('notification_outbox')->where('status', 'Failed')->count();
        $cancelledCount = (int) DB::table('notification_outbox')->where('status', 'Cancelled')->count();
        $retryDueCount = (int) DB::table('notification_outbox')
            ->whereIn('status', ['Pending', 'Failed', 'Processing'])
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', $now)
            ->count();
        $staleProcessingCount = (int) DB::table('notification_outbox')
            ->where('status', 'Processing')
            ->whereNotNull('locked_until')
            ->where('locked_until', '<=', $now)
            ->count();

        $oldestPendingAt = DB::table('notification_outbox')
            ->where('status', 'Pending')
            ->min('created_at');
        $oldestRetryDueAt = DB::table('notification_outbox')
            ->whereIn('status', ['Pending', 'Failed', 'Processing'])
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', $now)
            ->min('next_retry_at');

        $snapshot = [
            'enabled' => true,
            'table_present' => true,
            'pending_count' => $pendingCount,
            'processing_count' => $processingCount,
            'failed_count' => $failedCount,
            'cancelled_count' => $cancelledCount,
            'retry_due_count' => $retryDueCount,
            'stale_processing_count' => $staleProcessingCount,
            'oldest_pending_age_seconds' => $this->ageSeconds($oldestPendingAt, $now),
            'oldest_retry_due_age_seconds' => $this->ageSeconds($oldestRetryDueAt, $now),
            'dead_letter_count' => (int) ($healthSnapshot['dead_letter_count'] ?? $cancelledCount),
            'recent_failure_attempt_count' => (int) ($healthSnapshot['recent_failure_attempt_count'] ?? 0),
            'channel_breakdown' => (array) ($healthSnapshot['channel_breakdown'] ?? []),
        ];

        $evaluation = OperationalHealthEvaluator::forNotificationOutbox($snapshot, [
            'pending_warn_count' => (int) config('notifications.outbox.health.pending_warn_count', 100),
            'failed_warn_count' => (int) config('notifications.outbox.health.failed_warn_count', 10),
            'retry_due_warn_count' => (int) config('notifications.outbox.health.retry_due_warn_count', 20),
            'stale_processing_warn_count' => (int) config('notifications.outbox.health.stale_processing_warn_count', 1),
            'oldest_pending_warn_seconds' => (int) config('notifications.outbox.health.oldest_pending_warn_seconds', 900),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array<string,mixed>
     */
    public function paymentIntegritySnapshot(int $sampleLimit = 10): array
    {
        $sampleLimit = max(1, min(50, $sampleLimit));

        $refundAgg = DB::table('payments as refund')
            ->select('refund.refund_of_payment_id')
            ->selectRaw('SUM(refund.amount) AS refunded_amount')
            ->where('refund.payment_type', 'Refund')
            ->where('refund.status', 'Refunded')
            ->whereNotNull('refund.refund_of_payment_id')
            ->groupBy('refund.refund_of_payment_id');

        $baseQuery = DB::table('payments as source')
            ->leftJoinSub($refundAgg, 'refunds', function ($join) {
                $join->on('refunds.refund_of_payment_id', '=', 'source.payment_id');
            })
            ->whereIn('source.payment_type', ['Deposit', 'Final'])
            ->whereIn('source.status', ['Success', 'Partial'])
            ->selectRaw('source.payment_id')
            ->selectRaw('source.reservation_id')
            ->selectRaw('source.payment_type')
            ->selectRaw('source.amount AS captured_amount')
            ->selectRaw('COALESCE(refunds.refunded_amount, 0) AS refunded_amount')
            ->selectRaw('COALESCE(refunds.refunded_amount, 0) - source.amount AS over_refunded_amount')
            ->whereRaw('COALESCE(refunds.refunded_amount, 0) > source.amount');

        $sampleRows = (clone $baseQuery)
            ->orderByRaw('COALESCE(refunds.refunded_amount, 0) - source.amount DESC')
            ->orderBy('source.payment_id')
            ->limit($sampleLimit)
            ->get()
            ->map(static function ($row) {
                return [
                    'payment_id' => (int) $row->payment_id,
                    'reservation_id' => (int) $row->reservation_id,
                    'payment_type' => (string) $row->payment_type,
                    'captured_amount' => Money::toFloat($row->captured_amount ?? 0, true),
                    'refunded_amount' => Money::toFloat($row->refunded_amount ?? 0, true),
                    'over_refunded_amount' => Money::toFloat($row->over_refunded_amount ?? 0, true),
                ];
            })
            ->values()
            ->all();

        $overRefundCount = DB::query()
            ->fromSub($baseQuery, 'payment_integrity_scan')
            ->count();

        $refundWithoutSourceCount = (int) DB::table('payments')
            ->where('payment_type', 'Refund')
            ->where('status', 'Refunded')
            ->whereNull('refund_of_payment_id')
            ->count();

        $crossReservationRefundCount = (int) DB::table('payments as refund')
            ->join('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
            ->where('refund.payment_type', 'Refund')
            ->where('refund.status', 'Refunded')
            ->whereColumn('refund.reservation_id', '!=', 'source.reservation_id')
            ->count();

        $currencyMismatchRefundCount = (int) DB::table('payments as refund')
            ->join('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
            ->where('refund.payment_type', 'Refund')
            ->where('refund.status', 'Refunded')
            ->whereColumn('refund.currency', '!=', 'source.currency')
            ->count();

        $invalidRefundTargetCount = (int) DB::table('payments as refund')
            ->leftJoin('payments as source', 'source.payment_id', '=', 'refund.refund_of_payment_id')
            ->where('refund.payment_type', 'Refund')
            ->where('refund.status', 'Refunded')
            ->whereNotNull('refund.refund_of_payment_id')
            ->where(function ($query): void {
                $query->whereNull('source.payment_id')
                    ->orWhere('source.payment_type', 'Refund');
            })
            ->count();

        $maxOverRefundAmount = 0.0;
        foreach ($sampleRows as $row) {
            $maxOverRefundAmount = max($maxOverRefundAmount, (float) ($row['over_refunded_amount'] ?? 0.0));
        }

        $snapshot = [
            'over_refunded_source_count' => (int) $overRefundCount,
            'refund_without_source_count' => $refundWithoutSourceCount,
            'cross_reservation_refund_count' => $crossReservationRefundCount,
            'currency_mismatch_refund_count' => $currencyMismatchRefundCount,
            'invalid_refund_target_count' => $invalidRefundTargetCount,
            'max_over_refunded_amount' => Money::toFloat($maxOverRefundAmount, true),
            'samples' => $sampleRows,
        ];

        $evaluation = OperationalHealthEvaluator::forPaymentIntegrity($snapshot, [
            'over_refund_fail_count' => (int) config('booking.ops.payment_over_refund_fail_count', 1),
            'refund_without_source_fail_count' => (int) config('booking.ops.refund_without_source_fail_count', 1),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array<string,mixed>
     */
    public function sessionLinkageSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');

        if (! Schema::hasTable('table_holds')) {
            return [
                'active_unlinked_session_hold_count' => 0,
                'oldest_unlinked_session_hold_age_seconds' => null,
                'legacy_fallback_enabled' => ((int) config('booking.customer_session_legacy_access_hours', 0) > 0),
                'status' => 'ok',
                'reasons' => [],
            ];
        }

        $baseQuery = DB::table('table_holds')
            ->whereNull('confirmed_reservation_id')
            ->whereNotNull('session_id')
            ->where('session_id', '<>', '')
            ->whereIn('hold_status', ['Confirmed', 'Holding', 'Pending']);

        $activeUnlinkedCount = (int) (clone $baseQuery)->count();
        $oldestCreatedAt = (clone $baseQuery)->min('created_at');

        $snapshot = [
            'active_unlinked_session_hold_count' => $activeUnlinkedCount,
            'oldest_unlinked_session_hold_age_seconds' => $this->ageSeconds($oldestCreatedAt, $now),
            'legacy_fallback_enabled' => ((int) config('booking.customer_session_legacy_access_hours', 0) > 0),
        ];

        $evaluation = OperationalHealthEvaluator::forSessionLinkage($snapshot, [
            'unlinked_session_hold_warn_count' => (int) config('booking.ops.unlinked_session_hold_warn_count', 5),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array<string,mixed>
     */
    public function voucherLockSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');

        $staleLockCount = (int) DB::table('user_vouchers')
            ->where('is_used', 0)
            ->whereNotNull('lock_token')
            ->whereNotNull('locked_until')
            ->where('locked_until', '<=', $now)
            ->count();

        $snapshot = [
            'stale_lock_count' => $staleLockCount,
        ];

        $evaluation = OperationalHealthEvaluator::forVoucherLocks($snapshot, [
            'stale_lock_warn_count' => (int) config('booking.ops.stale_voucher_lock_warn_count', 10),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array<string,mixed>
     */
    public function staffApiKeySnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');

        $databaseStoreEnabled = (bool) config('staff_auth.database_store_enabled', false);
        $envFallbackEnabled = (bool) config('staff_auth.allow_env_fallback', false);

        if (! Schema::hasTable('staff_api_keys')) {
            return [
                'database_store_enabled' => $databaseStoreEnabled,
                'env_fallback_enabled' => $envFallbackEnabled,
                'active_count' => 0,
                'revoked_count' => 0,
                'expired_count' => 0,
                'expiring_soon_count' => 0,
                'never_used_active_count' => 0,
                'status' => $databaseStoreEnabled ? 'fail' : 'ok',
                'reasons' => $databaseStoreEnabled ? ['staff_api_keys_table_missing'] : [],
            ];
        }

        $expiringSoonDays = max(1, (int) config('booking.ops.staff_api_keys_expiring_soon_days', 14));
        $expiringSoonAt = $now->copy()->addDays($expiringSoonDays);

        $activeQuery = DB::table('staff_api_keys')
            ->whereNull('revoked_at')
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            });

        $activeCount = (int) (clone $activeQuery)->count();
        $activeSessionCount = (int) $this->applyStaffSessionKeyScope(clone $activeQuery)->count();
        $activeGovernanceCount = max(0, $activeCount - $activeSessionCount);
        $expiringSoonCount = (int) DB::table('staff_api_keys')
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->where('expires_at', '<=', $expiringSoonAt)
            ->count();
        $expiringSoonSessionCount = (int) $this->applyStaffSessionKeyScope(
            DB::table('staff_api_keys')
                ->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<=', $expiringSoonAt)
        )->count();
        $neverUsedActiveCount = (int) (clone $activeQuery)->whereNull('last_used_at')->count();
        $neverUsedActiveSessionCount = (int) $this->applyStaffSessionKeyScope(
            (clone $activeQuery)->whereNull('last_used_at')
        )->count();

        $snapshot = [
            'database_store_enabled' => $databaseStoreEnabled,
            'env_fallback_enabled' => $envFallbackEnabled,
            'active_count' => $activeCount,
            'active_governance_count' => $activeGovernanceCount,
            'active_session_count' => $activeSessionCount,
            'revoked_count' => (int) DB::table('staff_api_keys')->whereNotNull('revoked_at')->count(),
            'expired_count' => (int) DB::table('staff_api_keys')
                ->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->count(),
            'expiring_soon_count' => $expiringSoonCount,
            'expiring_soon_governance_count' => max(0, $expiringSoonCount - $expiringSoonSessionCount),
            'expiring_soon_session_count' => $expiringSoonSessionCount,
            'never_used_active_count' => $neverUsedActiveCount,
            'never_used_active_governance_count' => max(0, $neverUsedActiveCount - $neverUsedActiveSessionCount),
            'never_used_active_session_count' => $neverUsedActiveSessionCount,
            'session_key_label_prefix' => 'Auth Session',
        ];

        $status = 'ok';
        $reasons = [];
        if ($databaseStoreEnabled && $activeGovernanceCount < max(1, (int) config('booking.ops.staff_api_keys_missing_active_fail_count', 1))) {
            $status = 'fail';
            $reasons[] = 'staff_api_keys_missing_active_keys';
        } elseif ($envFallbackEnabled) {
            $status = 'degraded';
            $reasons[] = 'staff_api_keys_env_fallback_enabled';
        } elseif ((int) $snapshot['never_used_active_governance_count'] >= max(1, (int) config('booking.ops.staff_api_keys_never_used_warn_count', 5))) {
            $status = 'degraded';
            $reasons[] = 'staff_api_keys_never_used_backlog';
        } elseif ((int) $snapshot['expiring_soon_governance_count'] > 0) {
            $status = 'degraded';
            $reasons[] = 'staff_api_keys_expiring_soon';
        }

        return array_merge($snapshot, [
            'status' => $status,
            'reasons' => $reasons,
        ]);
    }

    private function applyStaffSessionKeyScope(Builder $query): Builder
    {
        return $query->whereRaw("LOWER(COALESCE(label, '')) LIKE ?", ['auth session%']);
    }

    /**
     * @return array<string,mixed>
     */
    public function tableStateAuditSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');
        $windowHours = max(1, (int) config('booking.ops.table_state_audit_recent_window_hours', 24));
        $windowStart = $now->copy()->subHours($windowHours);

        if (! Schema::hasTable('audit_logs')) {
            return [
                'recent_transition_count' => 0,
                'recent_missing_actor_count' => 0,
                'recent_missing_context_count' => 0,
                'window_hours' => $windowHours,
                'status' => 'fail',
                'reasons' => ['table_state_audit_table_missing'],
            ];
        }

        $baseQuery = DB::table('audit_logs')
            ->where('entity_type', 'restaurant_table')
            ->where('action', 'like', 'table_state_%')
            ->whereNotNull('created_at')
            ->where('created_at', '>=', $windowStart);

        $recentTransitionCount = (int) (clone $baseQuery)->count();
        $recentMissingActorCount = (int) (clone $baseQuery)
            ->whereNull('actor_user_id')
            ->where(function ($query): void {
                $query->whereNull('actor_key')
                    ->orWhere('actor_key', '');
            })
            ->count();

        $driver = (string) DB::connection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $recentMissingContextCount = (int) (clone $baseQuery)
                ->where(function ($query): void {
                    $query->whereNull('after_json')
                        ->orWhereRaw("JSON_EXTRACT(after_json, '$.context') IS NULL");
                })
                ->count();
        } else {
            $rows = (clone $baseQuery)->select(['after_json'])->get();
            $recentMissingContextCount = 0;
            foreach ($rows as $row) {
                $payload = json_decode((string) ($row->after_json ?? ''), true);
                if (! is_array($payload) || ! array_key_exists('context', $payload) || $payload['context'] === null) {
                    $recentMissingContextCount++;
                }
            }
        }

        $snapshot = [
            'recent_transition_count' => $recentTransitionCount,
            'recent_missing_actor_count' => $recentMissingActorCount,
            'recent_missing_context_count' => $recentMissingContextCount,
            'window_hours' => $windowHours,
        ];

        $status = 'ok';
        $reasons = [];
        if ($recentTransitionCount > 0 && $recentMissingActorCount >= max(1, (int) config('booking.ops.table_state_audit_missing_actor_warn_count', 1))) {
            $status = 'degraded';
            $reasons[] = 'table_state_audit_missing_actor';
        }
        if ($recentTransitionCount > 0 && $recentMissingContextCount >= max(1, (int) config('booking.ops.table_state_audit_missing_context_warn_count', 3))) {
            $status = 'degraded';
            $reasons[] = 'table_state_audit_missing_context';
        }

        return array_merge($snapshot, [
            'status' => $status,
            'reasons' => array_values(array_unique($reasons)),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function rowVersionContractSnapshot(): array
    {
        $snapshot = StaffMutationRowVersionContract::snapshot();

        $evaluation = OperationalHealthEvaluator::forRowVersionContract($snapshot, [
            'missing_required_fail_count' => (int) config('booking.ops.row_version_contract_missing_required_fail_count', 1),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array<string,mixed>
     */
    public function reportingSnapshotsSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');

        $families = $this->reportingFamilies();
        $sourceActivityCounts = $this->reportingSnapshotSourceActivityCounts();

        $missingTables = [];
        $familySnapshots = [];
        $totalRowCount = 0;
        $populatedFamilyCount = 0;
        $emptyFamilies = [];
        $staleScopeCountTotal = 0;
        $healthyFamilyCount = 0;
        $latestRefreshAgeSecondsMax = null;

        foreach ($families as $family => $definition) {
            $table = (string) $definition['table'];
            $scopeColumns = array_values((array) ($definition['scope_columns'] ?? ['branch_id']));
            $tier = (string) ($definition['tier'] ?? 'experimental');
            $warnings = array_values((array) ($definition['warnings'] ?? []));
            $sourceActivityCount = (int) ($sourceActivityCounts[$family] ?? 0);

            if (! Schema::hasTable($table)) {
                $missingTables[] = $table;
                $familySnapshots[$family] = [
                    'table' => $table,
                    'table_present' => false,
                    'tier' => $tier,
                    'launch_critical' => $tier === 'launch_critical',
                    'certified_accounting' => (bool) ($definition['certified_accounting'] ?? false),
                    'warnings' => $warnings,
                    'scope_columns' => $scopeColumns,
                    'source_activity_count' => $sourceActivityCount,
                    'scope_count' => 0,
                    'stale_scope_count' => 0,
                    'stale_scope_examples' => [],
                    'row_count' => 0,
                    'latest_business_date' => null,
                    'latest_refreshed_at_utc' => null,
                    'latest_refresh_age_seconds' => null,
                ];

                continue;
            }

            $rowCount = (int) DB::table($table)->count();
            $latestBusinessDate = DB::table($table)->max('business_date');
            $latestRefreshedAt = DB::table($table)->max('refreshed_at');
            [$scopeCount, $staleScopeCount, $staleScopeExamples, $healthReferenceRefreshAgeSeconds] = $this->reportingScopeFreshnessSummary(
                $table,
                $scopeColumns,
                $now,
            );

            if ($rowCount > 0) {
                $totalRowCount += $rowCount;
                $populatedFamilyCount++;
            } else {
                $emptyFamilies[] = $family;
            }

            $staleScopeCountTotal += $staleScopeCount;
            if ($rowCount > 0 && $staleScopeCount === 0) {
                $healthyFamilyCount++;
            }
            if ($healthReferenceRefreshAgeSeconds !== null) {
                $latestRefreshAgeSecondsMax = $latestRefreshAgeSecondsMax === null
                    ? $healthReferenceRefreshAgeSeconds
                    : max($latestRefreshAgeSecondsMax, $healthReferenceRefreshAgeSeconds);
            }

            $familySnapshots[$family] = [
                'table' => $table,
                'table_present' => true,
                'tier' => $tier,
                'launch_critical' => $tier === 'launch_critical',
                'certified_accounting' => (bool) ($definition['certified_accounting'] ?? false),
                'warnings' => $warnings,
                'scope_columns' => $scopeColumns,
                'source_activity_count' => $sourceActivityCount,
                'scope_count' => $scopeCount,
                'stale_scope_count' => $staleScopeCount,
                'stale_scope_examples' => $staleScopeExamples,
                'row_count' => $rowCount,
                'latest_business_date' => $latestBusinessDate !== null ? (string) $latestBusinessDate : null,
                'latest_refreshed_at_utc' => $latestRefreshedAt !== null ? Carbon::parse((string) $latestRefreshedAt)->utc()->toIso8601String() : null,
                'latest_refresh_age_seconds' => $healthReferenceRefreshAgeSeconds,
            ];
        }

        $launchCriticalReconciliation = $this->launchCriticalReportingReconciliation($now);
        $launchCriticalFamilies = array_values(array_keys(array_filter(
            $families,
            static fn (array $definition): bool => (string) ($definition['tier'] ?? '') === 'launch_critical',
        )));
        $experimentalFamilies = array_values(array_keys(array_filter(
            $families,
            static fn (array $definition): bool => (string) ($definition['tier'] ?? '') === 'experimental',
        )));

        $snapshot = [
            'family_count' => count($families),
            'launch_critical_families' => $launchCriticalFamilies,
            'experimental_families' => $experimentalFamilies,
            'experimental_reporting_warnings' => array_values(array_unique(array_merge(...array_map(
                static fn (string $family): array => array_values((array) ($families[$family]['warnings'] ?? [])),
                $experimentalFamilies,
            )))),
            'populated_family_count' => $populatedFamilyCount,
            'empty_family_count' => count($emptyFamilies),
            'empty_families' => array_values($emptyFamilies),
            'healthy_family_count' => $healthyFamilyCount,
            'stale_scope_count_total' => $staleScopeCountTotal,
            'latest_refresh_age_seconds_max' => $latestRefreshAgeSecondsMax,
            'total_row_count' => $totalRowCount,
            'source_activity_count_total' => array_sum($sourceActivityCounts),
            'missing_tables' => array_values($missingTables),
            'launch_critical_reconciliation_drift_count' => (int) ($launchCriticalReconciliation['drift_count'] ?? 0),
            'launch_critical_reconciliation_examples' => array_values((array) ($launchCriticalReconciliation['examples'] ?? [])),
            'launch_critical_reconciliation_checked_families' => array_values((array) ($launchCriticalReconciliation['checked_families'] ?? [])),
            'launch_critical_reconciliation_lookback_days' => (int) ($launchCriticalReconciliation['lookback_days'] ?? 0),
            'families' => $familySnapshots,
        ];

        $evaluation = OperationalHealthEvaluator::forReportingSnapshots($snapshot, [
            'stale_hours' => (int) config('booking.ops.reporting_snapshot_stale_hours', 48),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function reportingFamilies(): array
    {
        return [
            'sales' => [
                'table' => 'reporting_daily_sales_snapshots',
                'scope_columns' => ['branch_id', 'currency'],
                'tier' => 'launch_critical',
                'certified_accounting' => false,
                'warnings' => ['reporting_read_model_not_certified_accounting'],
            ],
            'operations' => [
                'table' => 'reporting_daily_operation_snapshots',
                'scope_columns' => ['branch_id'],
                'tier' => 'launch_critical',
                'certified_accounting' => false,
                'warnings' => [],
            ],
            'inventory' => [
                'table' => 'reporting_daily_inventory_movement_snapshots',
                'scope_columns' => ['branch_id', 'ingredient_id', 'unit_code'],
                'tier' => 'experimental',
                'certified_accounting' => false,
                'warnings' => ['experimental_reporting_not_certified_accounting'],
            ],
        ];
    }

    /**
     * @return array{drift_count:int,examples:list<array<string,mixed>>,checked_families:list<string>,lookback_days:int}
     */
    private function launchCriticalReportingReconciliation(Carbon $now): array
    {
        $lookbackDays = max(1, (int) config('booking.ops.reporting_reconciliation_lookback_days', 14));
        $startAt = $now->copy()->subDays($lookbackDays - 1)->startOfDay();
        $endAt = $now->copy()->endOfDay();
        $startDate = $startAt->toDateString();
        $endDate = $endAt->toDateString();
        $examples = [];
        $driftCount = 0;
        $checkedFamilies = [];
        $sampleLimit = 5;

        if (Schema::hasTable('reservations') && Schema::hasTable('reporting_daily_sales_snapshots')) {
            $checkedFamilies[] = 'sales';
            $this->reconcileSalesSnapshots($startAt, $endAt, $examples, $driftCount, $sampleLimit);
        }

        if (
            Schema::hasTable('reservations')
            && Schema::hasTable('waiting_list')
            && Schema::hasTable('reporting_daily_operation_snapshots')
        ) {
            $checkedFamilies[] = 'operations';
            $this->reconcileOperationSnapshots($startAt, $endAt, $startDate, $endDate, $examples, $driftCount, $sampleLimit);
        }

        return [
            'drift_count' => $driftCount,
            'examples' => array_slice($examples, 0, $sampleLimit),
            'checked_families' => $checkedFamilies,
            'lookback_days' => $lookbackDays,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $examples
     */
    private function reconcileSalesSnapshots(Carbon $startAt, Carbon $endAt, array &$examples, int &$driftCount, int $sampleLimit): void
    {
        DB::table('reservations')
            ->select([
                'branch_id',
                DB::raw('DATE(billed_at) AS business_date'),
                DB::raw("COALESCE(bill_currency, 'VND') AS currency"),
            ])
            ->selectRaw('COUNT(*) AS billed_reservation_count')
            ->selectRaw('SUM(COALESCE(guest_count, 0)) AS billed_guest_count')
            ->selectRaw('ROUND(SUM(COALESCE(final_bill_amount, 0) + COALESCE(discount_amount, 0)), 2) AS gross_bill_amount')
            ->selectRaw('ROUND(SUM(COALESCE(discount_amount, 0)), 2) AS discount_amount')
            ->selectRaw('ROUND(SUM(COALESCE(final_bill_amount, 0)), 2) AS billed_total_amount')
            ->whereNotNull('billed_at')
            ->whereBetween('billed_at', [$startAt, $endAt])
            ->groupBy('branch_id', DB::raw('DATE(billed_at)'), DB::raw("COALESCE(bill_currency, 'VND')"))
            ->orderBy('branch_id')
            ->orderBy('business_date')
            ->get()
            ->each(function (object $source) use (&$examples, &$driftCount, $sampleLimit): void {
                $context = [
                    'branch_id' => (int) ($source->branch_id ?? 0),
                    'business_date' => (string) ($source->business_date ?? ''),
                    'currency' => strtoupper((string) ($source->currency ?? 'VND')),
                ];
                $snapshot = DB::table('reporting_daily_sales_snapshots')
                    ->where('branch_id', $context['branch_id'])
                    ->where('business_date', $context['business_date'])
                    ->where('currency', $context['currency'])
                    ->first();

                foreach ([
                    'billed_reservation_count',
                    'billed_guest_count',
                    'gross_bill_amount',
                    'discount_amount',
                    'billed_total_amount',
                ] as $metric) {
                    $this->recordReportingDriftIfNeeded(
                        $examples,
                        $driftCount,
                        $sampleLimit,
                        'sales',
                        $context,
                        $metric,
                        $source->{$metric} ?? 0,
                        $snapshot->{$metric} ?? null,
                    );
                }
            });
    }

    /**
     * @param  list<array<string,mixed>>  $examples
     */
    private function reconcileOperationSnapshots(Carbon $startAt, Carbon $endAt, string $startDate, string $endDate, array &$examples, int &$driftCount, int $sampleLimit): void
    {
        $sourceRows = [];

        DB::table('reservations')
            ->select([
                'branch_id',
                'start_time',
                'checked_in_at',
                'checked_out_at',
                'cancelled_at',
                'no_show_at',
                'guest_count',
                'status',
            ])
            ->where(function (Builder $query) use ($startAt, $endAt): void {
                $query->whereBetween('start_time', [$startAt, $endAt])
                    ->orWhereBetween('checked_in_at', [$startAt, $endAt])
                    ->orWhereBetween('checked_out_at', [$startAt, $endAt])
                    ->orWhereBetween('cancelled_at', [$startAt, $endAt])
                    ->orWhereBetween('no_show_at', [$startAt, $endAt]);
            })
            ->orderBy('reservation_id')
            ->get()
            ->each(function (object $reservation) use (&$sourceRows, $startDate, $endDate): void {
                $branchId = (int) ($reservation->branch_id ?? 1);
                $guestCount = (int) ($reservation->guest_count ?? 0);

                $scheduledDate = $this->dateStringInRange($reservation->start_time ?? null, $startDate, $endDate);
                if ($scheduledDate !== null) {
                    $row = $this->operationSourceRow($sourceRows, $branchId, $scheduledDate);
                    $row['scheduled_reservation_count']++;
                    $row['scheduled_guest_count'] += $guestCount;
                    $sourceRows[$this->operationSourceKey($branchId, $scheduledDate)] = $row;
                }

                $checkedInDate = $this->dateStringInRange($reservation->checked_in_at ?? null, $startDate, $endDate);
                if ($checkedInDate !== null) {
                    $row = $this->operationSourceRow($sourceRows, $branchId, $checkedInDate);
                    $row['checked_in_count']++;
                    $sourceRows[$this->operationSourceKey($branchId, $checkedInDate)] = $row;
                }

                $completedDate = $this->dateStringInRange($reservation->checked_out_at ?? null, $startDate, $endDate);
                if ($completedDate !== null || ((string) ($reservation->status ?? '') === 'Completed' && $this->dateStringInRange($reservation->start_time ?? null, $startDate, $endDate) !== null)) {
                    $completedDate ??= $this->dateStringInRange($reservation->start_time ?? null, $startDate, $endDate);
                    if ($completedDate !== null) {
                        $row = $this->operationSourceRow($sourceRows, $branchId, $completedDate);
                        $row['completed_count']++;
                        $sourceRows[$this->operationSourceKey($branchId, $completedDate)] = $row;
                    }
                }

                foreach ([
                    'cancelled_at' => 'cancelled_count',
                    'no_show_at' => 'no_show_count',
                ] as $field => $metric) {
                    $date = $this->dateStringInRange($reservation->{$field} ?? null, $startDate, $endDate);
                    if ($date === null) {
                        continue;
                    }

                    $row = $this->operationSourceRow($sourceRows, $branchId, $date);
                    $row[$metric]++;
                    $sourceRows[$this->operationSourceKey($branchId, $date)] = $row;
                }
            });

        DB::table('waiting_list')
            ->select([
                'branch_id',
                'requested_at',
                'seated_at',
                'cancelled_at',
            ])
            ->where(function (Builder $query) use ($startAt, $endAt): void {
                $query->whereBetween('requested_at', [$startAt, $endAt])
                    ->orWhereBetween('seated_at', [$startAt, $endAt])
                    ->orWhereBetween('cancelled_at', [$startAt, $endAt]);
            })
            ->orderBy('waiting_id')
            ->get()
            ->each(function (object $entry) use (&$sourceRows, $startDate, $endDate): void {
                $branchId = (int) ($entry->branch_id ?? 1);
                foreach ([
                    'requested_at' => 'waiting_list_created_count',
                    'seated_at' => 'waiting_list_seated_count',
                    'cancelled_at' => 'waiting_list_cancelled_count',
                ] as $field => $metric) {
                    $date = $this->dateStringInRange($entry->{$field} ?? null, $startDate, $endDate);
                    if ($date === null) {
                        continue;
                    }

                    $row = $this->operationSourceRow($sourceRows, $branchId, $date);
                    $row[$metric]++;
                    $sourceRows[$this->operationSourceKey($branchId, $date)] = $row;
                }
            });

        ksort($sourceRows);

        foreach ($sourceRows as $source) {
            $context = [
                'branch_id' => (int) $source['branch_id'],
                'business_date' => (string) $source['business_date'],
            ];
            $snapshot = DB::table('reporting_daily_operation_snapshots')
                ->where('branch_id', $context['branch_id'])
                ->where('business_date', $context['business_date'])
                ->first();

            foreach ([
                'scheduled_reservation_count',
                'scheduled_guest_count',
                'checked_in_count',
                'completed_count',
                'cancelled_count',
                'no_show_count',
                'waiting_list_created_count',
                'waiting_list_seated_count',
                'waiting_list_cancelled_count',
            ] as $metric) {
                $this->recordReportingDriftIfNeeded(
                    $examples,
                    $driftCount,
                    $sampleLimit,
                    'operations',
                    $context,
                    $metric,
                    $source[$metric] ?? 0,
                    $snapshot->{$metric} ?? null,
                );
            }
        }
    }

    /**
     * @param  list<array<string,mixed>>  $examples
     * @param  array<string,mixed>  $context
     */
    private function recordReportingDriftIfNeeded(array &$examples, int &$driftCount, int $sampleLimit, string $family, array $context, string $metric, mixed $sourceValue, mixed $snapshotValue): void
    {
        if ($snapshotValue !== null && ! $this->reportingValuesDiffer($sourceValue, $snapshotValue)) {
            return;
        }

        $driftCount++;
        if (count($examples) >= $sampleLimit) {
            return;
        }

        $examples[] = array_merge($context, [
            'family' => $family,
            'metric' => $metric,
            'source_value' => $this->normalizeReportingValue($sourceValue),
            'snapshot_value' => $snapshotValue === null ? null : $this->normalizeReportingValue($snapshotValue),
        ]);
    }

    private function reportingValuesDiffer(mixed $sourceValue, mixed $snapshotValue): bool
    {
        return abs((float) $sourceValue - (float) $snapshotValue) > 0.01;
    }

    private function normalizeReportingValue(mixed $value): int|float
    {
        $number = (float) $value;

        return abs($number - round($number)) <= 0.0001
            ? (int) round($number)
            : round($number, 2);
    }

    /**
     * @param  array<string,array<string,mixed>>  $sourceRows
     * @return array<string,mixed>
     */
    private function operationSourceRow(array $sourceRows, int $branchId, string $date): array
    {
        return $sourceRows[$this->operationSourceKey($branchId, $date)] ?? [
            'branch_id' => $branchId,
            'business_date' => $date,
            'scheduled_reservation_count' => 0,
            'scheduled_guest_count' => 0,
            'checked_in_count' => 0,
            'completed_count' => 0,
            'cancelled_count' => 0,
            'no_show_count' => 0,
            'waiting_list_created_count' => 0,
            'waiting_list_seated_count' => 0,
            'waiting_list_cancelled_count' => 0,
        ];
    }

    private function operationSourceKey(int $branchId, string $date): string
    {
        return $branchId.'|'.$date;
    }

    private function dateStringInRange(mixed $value, string $startDate, string $endDate): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse((string) $value)->utc()->toDateString();
        } catch (Throwable) {
            return null;
        }

        return $date >= $startDate && $date <= $endDate ? $date : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function kitchenKdsSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');

        $requiredTables = [
            'kitchen_order_item_tickets',
            'reservation_order_items',
            'reservations',
            'kitchen_station_category_routes',
            'kitchen_stations',
        ];
        $missingTables = array_values(array_filter(
            $requiredTables,
            static fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missingTables !== []) {
            return [
                'table_present' => false,
                'missing_tables' => $missingTables,
                'active_ticket_count' => 0,
                'queued_count' => 0,
                'fired_count' => 0,
                'ready_count' => 0,
                'stuck_ticket_count' => 0,
                'stuck_status_counts' => [
                    'queued' => 0,
                    'fired' => 0,
                    'ready' => 0,
                ],
                'stuck_thresholds_seconds' => [
                    'queued' => max(60, (int) config('booking.ops.kitchen_queued_backlog_warn_seconds', 900)),
                    'fired' => max(60, (int) config('booking.ops.kitchen_fired_backlog_warn_seconds', 900)),
                    'ready' => max(60, (int) config('booking.ops.kitchen_ready_backlog_warn_seconds', 600)),
                ],
                'checked_ticket_count' => 0,
                'drift_count' => 0,
                'status_drift_count' => 0,
                'routing_drift_count' => 0,
                'duplicate_order_item_ticket_group_count' => 0,
                'duplicate_order_item_ticket_examples' => [],
                'branch_scope_mismatch_count' => 0,
                'branch_scope_mismatch_examples' => [],
                'station_scope_mismatch_count' => 0,
                'station_scope_mismatch_examples' => [],
                'realtime_cache_status' => null,
                'realtime_cache_store' => null,
                'realtime_cache_driver' => null,
                'realtime_cache_trusted' => false,
                'realtime_strict_required' => $this->kitchenRealtimeStrictRequired(),
                'realtime_cache_reasons' => [],
                'oldest_fired_age_seconds' => null,
                'oldest_ready_age_seconds' => null,
                'drift_examples' => [],
                'backlog_examples' => [],
                'stuck_examples' => [],
                'status' => 'fail',
                'reasons' => ['kitchen_kds_tables_missing'],
            ];
        }

        $activeBaseQuery = DB::table('kitchen_order_item_tickets')
            ->whereNotIn('ticket_status', [
                KitchenTicketStatus::Completed->value,
                KitchenTicketStatus::Cancelled->value,
            ]);

        $reconciliation = $this->kitchenTicketReconciliationService->scan([
            'include_terminal' => false,
        ]);
        $duplicateTicketSummary = $this->kitchenDuplicateTicketSummary();
        $scopeSummary = $this->kitchenScopeIntegritySummary();
        $realtimeSnapshot = $this->staffOperationalRealtimeSnapshot();
        $realtimeStrictRequired = $this->kitchenRealtimeStrictRequired();
        $stuckThresholds = [
            KitchenTicketStatus::Queued->value => max(60, (int) config('booking.ops.kitchen_queued_backlog_warn_seconds', 900)),
            KitchenTicketStatus::Fired->value => max(60, (int) config('booking.ops.kitchen_fired_backlog_warn_seconds', 900)),
            KitchenTicketStatus::Ready->value => max(60, (int) config('booking.ops.kitchen_ready_backlog_warn_seconds', 600)),
        ];

        $backlogTimestampSql = 'COALESCE(ready_at, fired_at, first_dispatched_at, created_at)';

        $backlogExamples = DB::table('kitchen_order_item_tickets')
            ->select(['ticket_id', 'station_id', 'ticket_status'])
            ->selectRaw($backlogTimestampSql.' AS backlog_started_at')
            ->whereIn('ticket_status', [
                KitchenTicketStatus::Fired->value,
                KitchenTicketStatus::Ready->value,
            ])
            ->orderByRaw($backlogTimestampSql.' ASC')
            ->limit(3)
            ->get()
            ->map(function (object $row) use ($now): array {
                return [
                    'ticket_id' => (int) ($row->ticket_id ?? 0),
                    'station_id' => (int) ($row->station_id ?? 0),
                    'ticket_status' => (string) ($row->ticket_status ?? ''),
                    'backlog_started_at_utc' => $row->backlog_started_at !== null
                        ? Carbon::parse((string) $row->backlog_started_at)->utc()->toIso8601String()
                        : null,
                    'backlog_age_seconds' => $this->ageSeconds($row->backlog_started_at, $now),
                ];
            })
            ->values()
            ->all();
        $stuckCounts = [
            'queued' => 0,
            'fired' => 0,
            'ready' => 0,
        ];
        $stuckExamples = $activeBaseQuery
            ->select([
                'ticket_id',
                'station_id',
                'ticket_status',
                'dispatch_count',
                'created_at',
                'first_dispatched_at',
                'fired_at',
                'ready_at',
            ])
            ->whereIn('ticket_status', [
                KitchenTicketStatus::Queued->value,
                KitchenTicketStatus::Fired->value,
                KitchenTicketStatus::Ready->value,
            ])
            ->get()
            ->map(function (object $row) use ($now, $stuckThresholds, &$stuckCounts): ?array {
                $status = (string) ($row->ticket_status ?? '');
                $startedAt = $this->kitchenTicketStartedAt($row, $status);
                $ageSeconds = $this->ageSeconds($startedAt, $now);
                $thresholdSeconds = $stuckThresholds[$status] ?? null;
                if ($thresholdSeconds === null || $ageSeconds === null || $ageSeconds < $thresholdSeconds) {
                    return null;
                }

                $statusKey = strtolower($status);
                if (array_key_exists($statusKey, $stuckCounts)) {
                    $stuckCounts[$statusKey]++;
                }

                return [
                    'ticket_id' => (int) ($row->ticket_id ?? 0),
                    'station_id' => (int) ($row->station_id ?? 0),
                    'ticket_status' => $status,
                    'dispatch_count' => (int) ($row->dispatch_count ?? 0),
                    'stuck_started_at_utc' => $startedAt !== null
                        ? Carbon::parse((string) $startedAt)->utc()->toIso8601String()
                        : null,
                    'stuck_age_seconds' => $ageSeconds,
                    'stuck_threshold_seconds' => $thresholdSeconds,
                ];
            })
            ->filter()
            ->sortByDesc('stuck_age_seconds')
            ->take(3)
            ->values()
            ->all();

        $snapshot = [
            'table_present' => true,
            'missing_tables' => [],
            'active_ticket_count' => (int) (clone $activeBaseQuery)->count(),
            'queued_count' => (int) (clone $activeBaseQuery)
                ->where('ticket_status', KitchenTicketStatus::Queued->value)
                ->count(),
            'fired_count' => (int) (clone $activeBaseQuery)
                ->where('ticket_status', KitchenTicketStatus::Fired->value)
                ->count(),
            'ready_count' => (int) (clone $activeBaseQuery)
                ->where('ticket_status', KitchenTicketStatus::Ready->value)
                ->count(),
            'stuck_ticket_count' => array_sum($stuckCounts),
            'stuck_status_counts' => $stuckCounts,
            'stuck_thresholds_seconds' => [
                'queued' => $stuckThresholds[KitchenTicketStatus::Queued->value],
                'fired' => $stuckThresholds[KitchenTicketStatus::Fired->value],
                'ready' => $stuckThresholds[KitchenTicketStatus::Ready->value],
            ],
            'checked_ticket_count' => (int) ($reconciliation['checked_count'] ?? 0),
            'drift_count' => (int) ($reconciliation['drift_count'] ?? 0),
            'status_drift_count' => (int) ($reconciliation['status_drift_count'] ?? 0),
            'routing_drift_count' => (int) ($reconciliation['routing_drift_count'] ?? 0),
            'duplicate_order_item_ticket_group_count' => (int) ($duplicateTicketSummary['duplicate_group_count'] ?? 0),
            'duplicate_order_item_ticket_examples' => array_values((array) ($duplicateTicketSummary['examples'] ?? [])),
            'branch_scope_mismatch_count' => (int) ($scopeSummary['branch_scope_mismatch_count'] ?? 0),
            'branch_scope_mismatch_examples' => array_values((array) ($scopeSummary['branch_scope_mismatch_examples'] ?? [])),
            'station_scope_mismatch_count' => (int) ($scopeSummary['station_scope_mismatch_count'] ?? 0),
            'station_scope_mismatch_examples' => array_values((array) ($scopeSummary['station_scope_mismatch_examples'] ?? [])),
            'realtime_cache_status' => (string) ($realtimeSnapshot['status'] ?? 'degraded'),
            'realtime_cache_store' => $realtimeSnapshot['store'] ?? null,
            'realtime_cache_driver' => $realtimeSnapshot['driver'] ?? null,
            'realtime_cache_trusted' => (bool) ($realtimeSnapshot['trusted'] ?? false),
            'realtime_strict_required' => $realtimeStrictRequired,
            'realtime_cache_reasons' => array_values((array) ($realtimeSnapshot['reasons'] ?? [])),
            'oldest_fired_age_seconds' => $this->ageSeconds(
                DB::table('kitchen_order_item_tickets')
                    ->where('ticket_status', KitchenTicketStatus::Fired->value)
                    ->min(DB::raw('COALESCE(fired_at, first_dispatched_at, created_at)')),
                $now,
            ),
            'oldest_ready_age_seconds' => $this->ageSeconds(
                DB::table('kitchen_order_item_tickets')
                    ->where('ticket_status', KitchenTicketStatus::Ready->value)
                    ->min(DB::raw('COALESCE(ready_at, fired_at, first_dispatched_at, created_at)')),
                $now,
            ),
            'drift_examples' => array_values(array_slice((array) ($reconciliation['tickets'] ?? []), 0, 3)),
            'backlog_examples' => $backlogExamples,
            'stuck_examples' => $stuckExamples,
        ];

        $evaluation = OperationalHealthEvaluator::forKitchenKds($snapshot, [
            'queued_backlog_warn_seconds' => (int) config('booking.ops.kitchen_queued_backlog_warn_seconds', 900),
            'fired_backlog_warn_seconds' => (int) config('booking.ops.kitchen_fired_backlog_warn_seconds', 900),
            'ready_backlog_warn_seconds' => (int) config('booking.ops.kitchen_ready_backlog_warn_seconds', 600),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array{duplicate_group_count:int,examples:list<array<string,mixed>>}
     */
    private function kitchenDuplicateTicketSummary(): array
    {
        $duplicates = DB::table('kitchen_order_item_tickets')
            ->select('order_item_id')
            ->selectRaw('COUNT(*) AS duplicate_count')
            ->selectRaw('MIN(ticket_id) AS first_ticket_id')
            ->selectRaw('MAX(ticket_id) AS latest_ticket_id')
            ->whereNotNull('order_item_id')
            ->groupBy('order_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('duplicate_count')
            ->orderBy('order_item_id')
            ->limit(3)
            ->get();

        $examples = $duplicates
            ->map(function (object $row): array {
                $ticketIds = DB::table('kitchen_order_item_tickets')
                    ->where('order_item_id', $row->order_item_id)
                    ->orderBy('ticket_id')
                    ->limit(5)
                    ->pluck('ticket_id')
                    ->map(static fn (mixed $ticketId): int => (int) $ticketId)
                    ->values()
                    ->all();

                return [
                    'order_item_id' => (int) ($row->order_item_id ?? 0),
                    'duplicate_count' => (int) ($row->duplicate_count ?? 0),
                    'ticket_sample_ids' => $ticketIds,
                    'first_ticket_id' => (int) ($row->first_ticket_id ?? 0),
                    'latest_ticket_id' => (int) ($row->latest_ticket_id ?? 0),
                ];
            })
            ->values()
            ->all();

        return [
            'duplicate_group_count' => (int) DB::query()
                ->fromSub(
                    DB::table('kitchen_order_item_tickets')
                        ->select('order_item_id')
                        ->whereNotNull('order_item_id')
                        ->groupBy('order_item_id')
                        ->havingRaw('COUNT(*) > 1'),
                    'duplicate_ticket_groups',
                )
                ->count(),
            'examples' => $examples,
        ];
    }

    /**
     * @return array{branch_scope_mismatch_count:int,branch_scope_mismatch_examples:list<array<string,mixed>>,station_scope_mismatch_count:int,station_scope_mismatch_examples:list<array<string,mixed>>}
     */
    private function kitchenScopeIntegritySummary(): array
    {
        $branchMismatchQuery = $this->kitchenScopeBaseQuery()
            ->where(function (Builder $query): void {
                $query->whereColumn('reservations.branch_id', '<>', 'kitchen_stations.branch_id')
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('tickets.route_id')
                            ->whereColumn('routes.branch_id', '<>', 'reservations.branch_id');
                    })
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('tickets.route_id')
                            ->whereColumn('routes.branch_id', '<>', 'kitchen_stations.branch_id');
                    });
            });

        $stationMismatchQuery = $this->kitchenScopeBaseQuery()
            ->where(function (Builder $query): void {
                $query->where('kitchen_stations.is_active', '<>', 1)
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('tickets.route_id')
                            ->whereNull('routes.route_id');
                    })
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('tickets.route_id')
                            ->whereColumn('routes.station_id', '<>', 'tickets.station_id');
                    })
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('routes.route_id')
                            ->where('routes.is_active', '<>', 1);
                    });
            });

        return [
            'branch_scope_mismatch_count' => (int) (clone $branchMismatchQuery)->count(),
            'branch_scope_mismatch_examples' => $this->kitchenScopeExamples($branchMismatchQuery),
            'station_scope_mismatch_count' => (int) (clone $stationMismatchQuery)->count(),
            'station_scope_mismatch_examples' => $this->kitchenScopeExamples($stationMismatchQuery),
        ];
    }

    private function kitchenScopeBaseQuery(): Builder
    {
        return DB::table('kitchen_order_item_tickets as tickets')
            ->join('reservations', 'reservations.reservation_id', '=', 'tickets.reservation_id')
            ->join('kitchen_stations', 'kitchen_stations.station_id', '=', 'tickets.station_id')
            ->leftJoin('kitchen_station_category_routes as routes', 'routes.route_id', '=', 'tickets.route_id')
            ->select([
                'tickets.ticket_id',
                'tickets.order_id',
                'tickets.order_item_id',
                'tickets.reservation_id',
                'tickets.station_id',
                'tickets.route_id',
                'tickets.ticket_status',
                'reservations.branch_id as reservation_branch_id',
                'kitchen_stations.branch_id as station_branch_id',
                'kitchen_stations.is_active as station_is_active',
                'routes.branch_id as route_branch_id',
                'routes.station_id as route_station_id',
                'routes.is_active as route_is_active',
            ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function kitchenScopeExamples(Builder $query): array
    {
        return (clone $query)
            ->orderBy('tickets.ticket_id')
            ->limit(3)
            ->get()
            ->map(static fn (object $row): array => [
                'ticket_id' => (int) ($row->ticket_id ?? 0),
                'order_id' => (int) ($row->order_id ?? 0),
                'order_item_id' => (int) ($row->order_item_id ?? 0),
                'reservation_id' => (int) ($row->reservation_id ?? 0),
                'ticket_status' => (string) ($row->ticket_status ?? ''),
                'reservation_branch_id' => isset($row->reservation_branch_id) ? (int) $row->reservation_branch_id : null,
                'station_id' => isset($row->station_id) ? (int) $row->station_id : null,
                'station_branch_id' => isset($row->station_branch_id) ? (int) $row->station_branch_id : null,
                'station_is_active' => isset($row->station_is_active) ? (bool) $row->station_is_active : null,
                'route_id' => isset($row->route_id) ? (int) $row->route_id : null,
                'route_branch_id' => isset($row->route_branch_id) ? (int) $row->route_branch_id : null,
                'route_station_id' => isset($row->route_station_id) ? (int) $row->route_station_id : null,
                'route_is_active' => isset($row->route_is_active) ? (bool) $row->route_is_active : null,
            ])
            ->values()
            ->all();
    }

    private function kitchenRealtimeStrictRequired(): bool
    {
        $environment = trim((string) config('app.env', app()->environment()));
        if ($environment === '') {
            return false;
        }

        return (bool) config('booking.realtime.enabled', true)
            && in_array($environment, array_values(array_filter(array_map(
                'strval',
                (array) config('booking.realtime.production_like_environments', ['production', 'staging']),
            ))), true);
    }

    /**
     * @return array<string,mixed>
     */
    public function inventoryPurchasingSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');

        $requiredTables = [
            'purchase_orders',
            'purchase_order_lines',
            'purchase_receipts',
            'purchase_receipt_lines',
            'ingredient_stock_movements',
        ];
        $missingTables = array_values(array_filter(
            $requiredTables,
            static fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missingTables !== []) {
            return [
                'table_present' => false,
                'missing_tables' => $missingTables,
                'checked_order_count' => 0,
                'issue_order_count' => 0,
                'line_issue_count' => 0,
                'receipt_issue_count' => 0,
                'movement_issue_count' => 0,
                'duplicate_purchase_receipt_reference_count' => 0,
                'duplicate_purchase_receipt_movement_count' => 0,
                'stock_reconciliation_dimensions' => [],
                'stock_on_hand_group_count' => 0,
                'negative_stock_group_count' => 0,
                'impossible_stock_movement_count' => 0,
                'open_purchase_order_count' => 0,
                'overdue_open_order_count' => 0,
                'oldest_overdue_open_age_seconds' => null,
                'status_counts' => [],
                'issue_examples' => [],
                'overdue_examples' => [],
                'duplicate_purchase_receipt_reference_examples' => [],
                'negative_stock_examples' => [],
                'impossible_stock_movement_examples' => [],
                'status' => 'fail',
                'reasons' => ['inventory_purchasing_tables_missing'],
            ];
        }

        $scan = $this->purchaseOrderReconciliationService->scan([
            'limit' => (int) config('booking.ops.inventory_purchase_scan_limit', 50),
        ]);
        $duplicateReferenceSummary = $this->purchaseOrderReconciliationService->duplicatePurchaseReceiptReferenceSummary();
        $stockReconciliation = app(InventoryStockReconciliationService::class)->summary();

        $openStatuses = [
            PurchaseOrderStatus::Ordered->value,
            PurchaseOrderStatus::PartiallyReceived->value,
        ];
        $openOrdersQuery = DB::table('purchase_orders')
            ->whereIn('purchase_order_status', $openStatuses);
        $overdueOpenOrdersQuery = (clone $openOrdersQuery)
            ->whereNotNull('expected_at')
            ->where('expected_at', '<=', $now);

        $statusCounts = DB::table('purchase_orders')
            ->select('purchase_order_status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('purchase_order_status')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [
                (string) ($row->purchase_order_status ?? 'unknown') => (int) ($row->aggregate ?? 0),
            ])
            ->all();

        $overdueExamples = (clone $overdueOpenOrdersQuery)
            ->select(['purchase_order_id', 'order_code', 'branch_id', 'purchase_order_status', 'expected_at'])
            ->orderBy('expected_at')
            ->limit(3)
            ->get()
            ->map(function (object $row) use ($now): array {
                return [
                    'purchase_order_id' => (int) ($row->purchase_order_id ?? 0),
                    'order_code' => (string) ($row->order_code ?? ''),
                    'branch_id' => (int) ($row->branch_id ?? 0),
                    'purchase_order_status' => (string) ($row->purchase_order_status ?? ''),
                    'expected_at_utc' => $row->expected_at !== null
                        ? Carbon::parse((string) $row->expected_at)->utc()->toIso8601String()
                        : null,
                    'overdue_age_seconds' => $this->ageSeconds($row->expected_at, $now),
                ];
            })
            ->values()
            ->all();

        $issueExamples = array_values(array_slice(array_values(array_filter(
            (array) ($scan['orders'] ?? []),
            static fn (array $row): bool => (int) ($row['issue_count'] ?? 0) > 0,
        )), 0, 3));

        $snapshot = [
            'table_present' => true,
            'missing_tables' => [],
            'checked_order_count' => (int) ($scan['checked_order_count'] ?? 0),
            'issue_order_count' => (int) ($scan['issue_order_count'] ?? 0),
            'line_issue_count' => (int) ($scan['line_issue_count'] ?? 0),
            'receipt_issue_count' => (int) ($scan['receipt_issue_count'] ?? 0),
            'movement_issue_count' => (int) ($scan['movement_issue_count'] ?? 0),
            'duplicate_purchase_receipt_reference_count' => (int) ($duplicateReferenceSummary['duplicate_reference_count'] ?? 0),
            'duplicate_purchase_receipt_movement_count' => (int) ($duplicateReferenceSummary['duplicate_movement_count'] ?? 0),
            'stock_reconciliation_dimensions' => array_values((array) ($stockReconciliation['dimensions'] ?? [])),
            'stock_on_hand_group_count' => (int) ($stockReconciliation['stock_on_hand_group_count'] ?? 0),
            'negative_stock_group_count' => (int) ($stockReconciliation['negative_group_count'] ?? 0),
            'impossible_stock_movement_count' => (int) ($stockReconciliation['impossible_movement_count'] ?? 0),
            'open_purchase_order_count' => (int) (clone $openOrdersQuery)->count(),
            'overdue_open_order_count' => (int) (clone $overdueOpenOrdersQuery)->count(),
            'oldest_overdue_open_age_seconds' => $this->ageSeconds(
                (clone $overdueOpenOrdersQuery)->min('expected_at'),
                $now,
            ),
            'status_counts' => $statusCounts,
            'issue_examples' => $issueExamples,
            'overdue_examples' => $overdueExamples,
            'duplicate_purchase_receipt_reference_examples' => array_values((array) ($duplicateReferenceSummary['examples'] ?? [])),
            'negative_stock_examples' => array_values((array) ($stockReconciliation['negative_examples'] ?? [])),
            'impossible_stock_movement_examples' => array_values((array) ($stockReconciliation['impossible_examples'] ?? [])),
        ];

        $evaluation = OperationalHealthEvaluator::forInventoryPurchasing($snapshot, [
            'overdue_open_order_warn_count' => (int) config('booking.ops.inventory_purchase_overdue_warn_count', 1),
            'overdue_open_order_warn_seconds' => (int) config('booking.ops.inventory_purchase_overdue_warn_seconds', 86400),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array<string,mixed>
     */
    public function conversationInboxSnapshot(?Carbon $now = null): array
    {
        $now ??= Carbon::now('UTC');

        $requiredTables = [
            'conversations',
            'agent_assignments',
            'conversation_messages',
        ];
        $missingTables = array_values(array_filter(
            $requiredTables,
            static fn (string $table): bool => ! Schema::hasTable($table),
        ));

        if ($missingTables !== []) {
            return [
                'table_present' => false,
                'missing_tables' => $missingTables,
                'active_conversation_count' => 0,
                'unassigned_count' => 0,
                'overdue_count' => 0,
                'waiting_on_customer_count' => 0,
                'resolved_today_count' => 0,
                'terminal_with_active_assignment_count' => 0,
                'oldest_overdue_age_seconds' => null,
                'sla_minutes' => StaffConversationInboxService::OVERDUE_AFTER_MINUTES,
                'workflow_state_counts' => [],
                'overdue_examples' => [],
                'status' => 'fail',
                'reasons' => ['conversation_inbox_tables_missing'],
            ];
        }

        $terminalWorkflowStates = [
            StaffConversationWorkflowState::Resolved->value,
            StaffConversationWorkflowState::Closed->value,
        ];
        $overdueThreshold = $now->copy()
            ->subMinutes(StaffConversationInboxService::OVERDUE_AFTER_MINUTES)
            ->toDateTimeString();
        $latestActivitySql = $this->conversationLatestActivitySql();
        $conversationMetricsQuery = $this->conversationInboxMetricsQuery();

        $activeConversationsQuery = (clone $conversationMetricsQuery)
            ->whereNotIn('workflow_state', $terminalWorkflowStates);

        $unassignedConversationsQuery = (clone $activeConversationsQuery)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('agent_assignments as active_assignments')
                    ->whereColumn('active_assignments.conversation_id', 'conversations.conversation_id')
                    ->where('active_assignments.is_active', 1);
            });

        $overdueConversationsQuery = (clone $activeConversationsQuery)
            ->whereRaw($latestActivitySql.' <= ?', [$overdueThreshold]);

        $terminalAssignedQuery = (clone $conversationMetricsQuery)
            ->whereIn('workflow_state', $terminalWorkflowStates)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('agent_assignments as active_assignments')
                    ->whereColumn('active_assignments.conversation_id', 'conversations.conversation_id')
                    ->where('active_assignments.is_active', 1);
            });

        $workflowStateCounts = (clone $conversationMetricsQuery)
            ->select('workflow_state')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('workflow_state')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [
                (string) ($row->workflow_state ?? 'unknown') => (int) ($row->aggregate ?? 0),
            ])
            ->all();

        $overdueExamples = (clone $overdueConversationsQuery)
            ->select(['conversations.conversation_id', 'conversations.branch_id', 'conversations.workflow_state'])
            ->selectRaw($latestActivitySql.' AS latest_activity_at')
            ->orderByRaw($latestActivitySql.' ASC')
            ->limit(3)
            ->get()
            ->map(function (object $row) use ($now): array {
                return [
                    'conversation_id' => (string) ($row->conversation_id ?? ''),
                    'branch_id' => (int) ($row->branch_id ?? 0),
                    'workflow_state' => (string) ($row->workflow_state ?? ''),
                    'latest_activity_at_utc' => $row->latest_activity_at !== null
                        ? Carbon::parse((string) $row->latest_activity_at)->utc()->toIso8601String()
                        : null,
                    'overdue_age_seconds' => $this->ageSeconds($row->latest_activity_at, $now),
                ];
            })
            ->values()
            ->all();

        $snapshot = [
            'table_present' => true,
            'missing_tables' => [],
            'active_conversation_count' => (int) (clone $activeConversationsQuery)->count(),
            'unassigned_count' => (int) (clone $unassignedConversationsQuery)->count(),
            'overdue_count' => (int) (clone $overdueConversationsQuery)->count(),
            'waiting_on_customer_count' => (int) (clone $activeConversationsQuery)
                ->where('workflow_state', StaffConversationWorkflowState::PendingCustomer->value)
                ->count(),
            'resolved_today_count' => (int) (clone $conversationMetricsQuery)
                ->where('workflow_state', StaffConversationWorkflowState::Resolved->value)
                ->whereBetween('resolved_at', [
                    $now->copy()->startOfDay(),
                    $now->copy()->endOfDay(),
                ])
                ->count(),
            'terminal_with_active_assignment_count' => (int) (clone $terminalAssignedQuery)->count(),
            'oldest_overdue_age_seconds' => $this->ageSeconds(
                (clone $overdueConversationsQuery)->selectRaw('MIN('.$latestActivitySql.') AS oldest_activity_at')->value('oldest_activity_at'),
                $now,
            ),
            'sla_minutes' => StaffConversationInboxService::OVERDUE_AFTER_MINUTES,
            'workflow_state_counts' => $workflowStateCounts,
            'overdue_examples' => $overdueExamples,
        ];

        $evaluation = OperationalHealthEvaluator::forConversationInbox($snapshot, [
            'unassigned_warn_count' => (int) config('booking.ops.conversation_unassigned_warn_count', 5),
            'overdue_warn_count' => (int) config('booking.ops.conversation_overdue_warn_count', 5),
            'oldest_overdue_warn_seconds' => (int) config('booking.ops.conversation_oldest_overdue_warn_seconds', 3600),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    private function conversationInboxMetricsQuery(): Builder
    {
        $query = DB::table('conversations');

        if (! Schema::hasTable('conversation_analyses')) {
            return $query;
        }

        return $query->whereNotExists(function (Builder $subquery): void {
            $subquery->selectRaw('1')
                ->from('conversation_analyses as fixture_analyses')
                ->whereColumn('fixture_analyses.conversation_id', 'conversations.conversation_id')
                ->where('fixture_analyses.analyzer_name', 'uat_demo_pack');
        });
    }

    /**
     * @return array{sales:int,operations:int,inventory:int}
     */
    protected function reportingSnapshotSourceActivityCounts(): array
    {
        return [
            'sales' => $this->countRowsIfTablePresent('reservations', static fn ($query) => $query->whereNotNull('billed_at'))
                + $this->countRowsIfTablePresent('billing_invoices', static fn ($query) => $query->where('invoice_status', 'Issued'))
                + $this->countRowsIfTablePresent('payments')
                + $this->countRowsIfTablePresent('cashier_shifts', static fn ($query) => $query->where('status', 'Closed')),
            'operations' => $this->countRowsIfTablePresent('reservations')
                + $this->countRowsIfTablePresent('waiting_list'),
            'inventory' => $this->countRowsIfTablePresent('ingredient_stock_movements'),
        ];
    }

    /**
     * @param  list<string>  $scopeColumns
     * @return array{0:int,1:int,2:list<array<string,mixed>>,3:?int}
     */
    private function reportingScopeFreshnessSummary(string $table, array $scopeColumns, Carbon $now): array
    {
        $staleHours = max(1, (int) config('booking.ops.reporting_snapshot_stale_hours', 48));
        $staleThreshold = $now->copy()->subHours($staleHours);

        $groupedQuery = DB::table($table)
            ->select($scopeColumns)
            ->selectRaw('MAX(refreshed_at) AS latest_refreshed_at')
            ->groupBy($scopeColumns);

        $groupedSubquery = DB::query()->fromSub($groupedQuery, 'reporting_scope_freshness');
        $scopeCount = (int) (clone $groupedSubquery)->count();

        if ($scopeCount === 0) {
            return [0, 0, [], null];
        }

        $staleScopesQuery = (clone $groupedSubquery)
            ->where(function ($query) use ($staleThreshold): void {
                $query->whereNull('latest_refreshed_at')
                    ->orWhere('latest_refreshed_at', '<=', $staleThreshold);
            });

        $staleScopeCount = (int) (clone $staleScopesQuery)->count();

        $staleScopes = (clone $staleScopesQuery)
            ->orderBy('latest_refreshed_at')
            ->limit(3)
            ->get();

        $examples = [];
        $healthReferenceRefreshAgeSeconds = null;
        foreach ($staleScopes as $scope) {
            $example = [];
            foreach ($scopeColumns as $column) {
                $example[$column] = $scope->{$column} ?? null;
            }
            $latestRefreshedAt = $scope->latest_refreshed_at ?? null;
            $example['latest_refreshed_at_utc'] = $latestRefreshedAt !== null
                ? Carbon::parse((string) $latestRefreshedAt)->utc()->toIso8601String()
                : null;
            $example['latest_refresh_age_seconds'] = $this->ageSeconds($latestRefreshedAt, $now);

            if ($example['latest_refresh_age_seconds'] !== null) {
                $healthReferenceRefreshAgeSeconds = max(
                    $healthReferenceRefreshAgeSeconds ?? 0,
                    (int) $example['latest_refresh_age_seconds']
                );
            }

            $examples[] = $example;
        }

        if ($staleScopeCount > count($examples)) {
            $allStaleRefreshes = (clone $staleScopesQuery)->pluck('latest_refreshed_at');
            foreach ($allStaleRefreshes as $latestRefreshedAt) {
                $ageSeconds = $this->ageSeconds($latestRefreshedAt, $now);
                if ($ageSeconds !== null) {
                    $healthReferenceRefreshAgeSeconds = max($healthReferenceRefreshAgeSeconds ?? 0, $ageSeconds);
                }
            }
        }

        if ($healthReferenceRefreshAgeSeconds === null) {
            $freshestRefreshAt = (clone $groupedSubquery)->max('latest_refreshed_at');
            $healthReferenceRefreshAgeSeconds = $this->ageSeconds($freshestRefreshAt, $now);
        }

        if ($staleScopeCount > 0) {
            $minimumStaleAgeSeconds = $staleHours * 3600;
            $healthReferenceRefreshAgeSeconds = max(
                $healthReferenceRefreshAgeSeconds ?? 0,
                $minimumStaleAgeSeconds,
            );
        }

        return [$scopeCount, $staleScopeCount, $examples, $healthReferenceRefreshAgeSeconds];
    }

    private function countRowsIfTablePresent(string $table, ?callable $scope = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($scope !== null) {
            $scope($query);
        }

        return (int) $query->count();
    }

    /**
     * @return array<string,mixed>
     */
    public function branchDefaultsSnapshot(): array
    {
        if (! Schema::hasTable('branches')) {
            return [
                'table_present' => false,
                'total_count' => 0,
                'active_count' => 0,
                'default_count' => 0,
                'inactive_default_count' => 0,
                'duplicate_code_count' => 0,
                'default_branch_id' => null,
                'default_branch_code' => null,
                'status' => 'fail',
                'reasons' => ['branches_table_missing'],
            ];
        }

        $defaultBranches = DB::table('branches')
            ->where('is_default', 1)
            ->orderBy('branch_id')
            ->get(['branch_id', 'branch_code', 'is_active']);

        $defaultCount = $defaultBranches->count();
        $defaultBranch = $defaultCount === 1 ? $defaultBranches->first() : null;

        $totalCount = (int) DB::table('branches')->count();
        $activeCount = (int) DB::table('branches')->where('is_active', 1)->count();
        $incompleteSchedulingExamples = [];
        $activeIncompleteSchedulingCount = 0;

        DB::table('branches')
            ->where('is_active', 1)
            ->orderBy('branch_id')
            ->get(['branch_id', 'branch_code'])
            ->each(function (object $branch) use (&$activeIncompleteSchedulingCount, &$incompleteSchedulingExamples): void {
                $readiness = $this->branchSchedulingPolicyService->schedulingReadiness((int) $branch->branch_id, false);
                if (($readiness['bookable'] ?? false) === true) {
                    return;
                }

                $activeIncompleteSchedulingCount++;
                if (count($incompleteSchedulingExamples) >= 3) {
                    return;
                }

                $incompleteSchedulingExamples[] = [
                    'branch_id' => (int) $branch->branch_id,
                    'branch_code' => (string) ($branch->branch_code ?? ''),
                    'reasons' => array_values(array_map('strval', (array) ($readiness['reasons'] ?? []))),
                ];
            });

        $snapshot = [
            'table_present' => true,
            'total_count' => $totalCount,
            'active_count' => $activeCount,
            'default_count' => $defaultCount,
            'inactive_default_count' => (int) $defaultBranches->where('is_active', 0)->count(),
            'duplicate_code_count' => (int) DB::query()
                ->fromSub(
                    DB::table('branches')
                        ->select('branch_code')
                        ->groupBy('branch_code')
                        ->havingRaw('COUNT(*) > 1'),
                    'duplicate_branch_codes'
                )
                ->count(),
            'default_branch_id' => $defaultBranch !== null ? (int) $defaultBranch->branch_id : null,
            'default_branch_code' => $defaultBranch !== null ? (string) $defaultBranch->branch_code : null,
            'active_incomplete_scheduling_count' => $activeIncompleteSchedulingCount,
            'active_incomplete_scheduling_examples' => $incompleteSchedulingExamples,
            'compatibility_bootstrap_available' => ($totalCount === 0) || ($totalCount > 0 && $activeCount > 0 && $defaultCount === 0),
        ];

        $evaluation = OperationalHealthEvaluator::forBranchDefaults($snapshot, []);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array<string,mixed>
     */
    public function staffOperationalRealtimeSnapshot(): array
    {
        $backend = $this->staffOperationalRealtimeService->backendStatus();

        return [
            'enabled' => (bool) ($backend['enabled'] ?? false),
            'store' => $backend['store'] ?? null,
            'driver' => $backend['driver'] ?? null,
            'trusted' => (bool) ($backend['trusted'] ?? false),
            'status' => (string) ($backend['status'] ?? 'degraded'),
            'reasons' => array_values(array_filter([
                is_string($backend['reason'] ?? null) ? $backend['reason'] : null,
            ])),
            'error' => $backend['error'] ?? null,
        ];
    }

    private function conversationLatestActivitySql(): string
    {
        return 'COALESCE((SELECT cm.created_at FROM conversation_messages cm WHERE cm.conversation_id = conversations.conversation_id ORDER BY cm.created_at DESC, cm.message_id DESC LIMIT 1), conversations.workflow_state_changed_at, conversations.created_at)';
    }

    private function kitchenTicketStartedAt(object $row, string $status): mixed
    {
        return match ($status) {
            KitchenTicketStatus::Queued->value => $row->first_dispatched_at ?? $row->created_at ?? null,
            KitchenTicketStatus::Fired->value => $row->fired_at ?? $row->first_dispatched_at ?? $row->created_at ?? null,
            KitchenTicketStatus::Ready->value => $row->ready_at ?? $row->fired_at ?? $row->first_dispatched_at ?? $row->created_at ?? null,
            default => $row->created_at ?? null,
        };
    }

    private function ageSeconds(mixed $value, Carbon $now): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (int) Carbon::parse((string) $value)->utc()->diffInSeconds($now);
        } catch (Throwable) {
            return null;
        }
    }
}

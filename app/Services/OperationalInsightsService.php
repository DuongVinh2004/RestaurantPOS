<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\OperationalHealthEvaluator;
use App\Support\StaffMutationRowVersionContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OperationalInsightsService
{
    public function __construct(
        private readonly DatabaseContractInspector $databaseContractInspector,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function snapshot(?Carbon $now = null, int $paymentSampleLimit = 10): array
    {
        $now ??= Carbon::now('UTC');

        return [
            'notification_outbox' => $this->safeSectionSnapshot('notification_outbox', fn () => $this->notificationOutboxSnapshot($now)),
            'payment_integrity' => $this->safeSectionSnapshot('payment_integrity', fn () => $this->paymentIntegritySnapshot($paymentSampleLimit)),
            'voucher_locks' => $this->safeSectionSnapshot('voucher_locks', fn () => $this->voucherLockSnapshot($now)),
            'session_linkage' => $this->safeSectionSnapshot('session_linkage', fn () => $this->sessionLinkageSnapshot($now)),
            'staff_api_keys' => $this->safeSectionSnapshot('staff_api_keys', fn () => $this->staffApiKeySnapshot($now)),
            'table_state_audit' => $this->safeSectionSnapshot('table_state_audit', fn () => $this->tableStateAuditSnapshot($now)),
            'row_version_contract' => $this->safeSectionSnapshot('row_version_contract', fn () => $this->rowVersionContractSnapshot()),
            'reporting_snapshots' => $this->safeSectionSnapshot('reporting_snapshots', fn () => $this->reportingSnapshotsSnapshot($now)),
            'branch_defaults' => $this->safeSectionSnapshot('branch_defaults', fn () => $this->branchDefaultsSnapshot()),
            'database_contract' => $this->safeSectionSnapshot('database_contract', fn () => $this->databaseContractInspector->snapshot()),
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
                    'captured_amount' => round((float) $row->captured_amount, 2),
                    'refunded_amount' => round((float) $row->refunded_amount, 2),
                    'over_refunded_amount' => round((float) $row->over_refunded_amount, 2),
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
            'max_over_refunded_amount' => round($maxOverRefundAmount, 2),
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
        $snapshot = [
            'database_store_enabled' => $databaseStoreEnabled,
            'env_fallback_enabled' => $envFallbackEnabled,
            'active_count' => $activeCount,
            'revoked_count' => (int) DB::table('staff_api_keys')->whereNotNull('revoked_at')->count(),
            'expired_count' => (int) DB::table('staff_api_keys')
                ->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now)
                ->count(),
            'expiring_soon_count' => (int) DB::table('staff_api_keys')
                ->whereNull('revoked_at')
                ->whereNotNull('expires_at')
                ->where('expires_at', '>', $now)
                ->where('expires_at', '<=', $expiringSoonAt)
                ->count(),
            'never_used_active_count' => (int) (clone $activeQuery)->whereNull('last_used_at')->count(),
        ];

        $status = 'ok';
        $reasons = [];
        if ($databaseStoreEnabled && $activeCount < max(1, (int) config('booking.ops.staff_api_keys_missing_active_fail_count', 1))) {
            $status = 'fail';
            $reasons[] = 'staff_api_keys_missing_active_keys';
        } elseif ($envFallbackEnabled) {
            $status = 'degraded';
            $reasons[] = 'staff_api_keys_env_fallback_enabled';
        } elseif ((int) $snapshot['never_used_active_count'] >= max(1, (int) config('booking.ops.staff_api_keys_never_used_warn_count', 5))) {
            $status = 'degraded';
            $reasons[] = 'staff_api_keys_never_used_backlog';
        } elseif ((int) $snapshot['expiring_soon_count'] > 0) {
            $status = 'degraded';
            $reasons[] = 'staff_api_keys_expiring_soon';
        }

        return array_merge($snapshot, [
            'status' => $status,
            'reasons' => $reasons,
        ]);
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
        $recentMissingActorCount = (int) (clone $baseQuery)->whereNull('actor_user_id')->count();

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

        $families = [
            'sales' => [
                'table' => 'reporting_daily_sales_snapshots',
                'scope_columns' => ['branch_id', 'currency'],
            ],
            'operations' => [
                'table' => 'reporting_daily_operation_snapshots',
                'scope_columns' => ['branch_id'],
            ],
            'inventory' => [
                'table' => 'reporting_daily_inventory_movement_snapshots',
                'scope_columns' => ['branch_id'],
            ],
        ];
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
            $sourceActivityCount = (int) ($sourceActivityCounts[$family] ?? 0);

            if (! Schema::hasTable($table)) {
                $missingTables[] = $table;
                $familySnapshots[$family] = [
                    'table' => $table,
                    'table_present' => false,
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

        $snapshot = [
            'family_count' => count($families),
            'populated_family_count' => $populatedFamilyCount,
            'empty_family_count' => count($emptyFamilies),
            'empty_families' => array_values($emptyFamilies),
            'healthy_family_count' => $healthyFamilyCount,
            'stale_scope_count_total' => $staleScopeCountTotal,
            'latest_refresh_age_seconds_max' => $latestRefreshAgeSecondsMax,
            'total_row_count' => $totalRowCount,
            'source_activity_count_total' => array_sum($sourceActivityCounts),
            'missing_tables' => array_values($missingTables),
            'families' => $familySnapshots,
        ];

        $evaluation = OperationalHealthEvaluator::forReportingSnapshots($snapshot, [
            'stale_hours' => (int) config('booking.ops.reporting_snapshot_stale_hours', 48),
        ]);

        return array_merge($snapshot, $evaluation);
    }

    /**
     * @return array{sales:int,operations:int,inventory:int}
     */
    private function reportingSnapshotSourceActivityCounts(): array
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
     * @param list<string> $scopeColumns
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

        $snapshot = [
            'table_present' => true,
            'total_count' => $totalCount,
            'active_count' => $activeCount,
            'default_count' => $defaultCount,
            'inactive_default_count' => (int) $defaultBranches->where('is_active', 0)->count(),
            'duplicate_code_count' => (int) DB::table('branches')
                ->select('branch_code')
                ->groupBy('branch_code')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count(),
            'default_branch_id' => $defaultBranch !== null ? (int) $defaultBranch->branch_id : null,
            'default_branch_code' => $defaultBranch !== null ? (string) $defaultBranch->branch_code : null,
            'compatibility_bootstrap_available' => ($totalCount === 0) || ($totalCount > 0 && $activeCount > 0 && $defaultCount === 0),
        ];

        $evaluation = OperationalHealthEvaluator::forBranchDefaults($snapshot, []);

        return array_merge($snapshot, $evaluation);
    }

    private function ageSeconds(mixed $value, Carbon $now): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (int) Carbon::parse((string) $value)->utc()->diffInSeconds($now);
        } catch (\Throwable) {
            return null;
        }
    }
}

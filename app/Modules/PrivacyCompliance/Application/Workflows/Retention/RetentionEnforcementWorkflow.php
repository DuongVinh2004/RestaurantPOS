<?php

declare(strict_types=1);

namespace App\Modules\PrivacyCompliance\Application\Workflows\Retention;

use App\Support\AuditEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RetentionEnforcementWorkflow
{
    /**
     * @return array<string,mixed>
     */
    public function enforce(bool $dryRun = false): array
    {
        $summary = [
            'dry_run' => $dryRun,
            'executed_at_utc' => now('UTC')->toIso8601String(),
            'rules' => [
                $this->pruneCustomerAccessSessions($dryRun),
                $this->pruneUserAuthTokens($dryRun),
                $this->pruneNotificationArtifacts($dryRun),
                $this->pruneConversationDerivedArtifacts($dryRun),
                $this->scrubPaymentWebhookReceipts($dryRun),
            ],
            'retained_only' => [
                ['table' => 'audit_logs', 'reason' => 'audit and operational investigation retention'],
                ['table' => 'payments', 'reason' => 'financial reconciliation integrity'],
                ['table' => 'payment_provider_webhook_receipts', 'reason' => 'payment provider audit and dispute support with verbose payload fields scrubbed after retention'],
            ],
        ];

        if (! $dryRun) {
            AuditEvent::info('data_retention_enforced', [
                '_audit' => [
                    'action' => 'data_retention.enforced',
                    'primary_subject' => ['type' => 'retention_policy', 'id' => 'default', 'role' => 'primary'],
                    'summary' => ['dry_run' => false, 'rules' => $summary['rules']],
                ],
            ]);
        }

        return $summary;
    }

    private function pruneCustomerAccessSessions(bool $dryRun): array
    {
        $cutoff = now('UTC')->subDays(max(1, (int) config('data_lifecycle.retention.customer_access_sessions_days', 30)));
        if (! Schema::hasTable('customer_access_sessions')) {
            return $this->skipped('customer_access_sessions');
        }

        $query = DB::table('customer_access_sessions')->where(function ($q) use ($cutoff): void {
            $q->whereNotNull('revoked_at')->where('revoked_at', '<', $cutoff)
                ->orWhere(function ($x) use ($cutoff): void {
                    $x->whereNull('revoked_at')->where('expires_at', '<', $cutoff);
                });
        });

        $eligible = (int) $query->count();
        if (! $dryRun && $eligible > 0) {
            $query->delete();
        }

        return ['table' => 'customer_access_sessions', 'action' => 'delete', 'cutoff_utc' => $cutoff->toIso8601String(), 'eligible_count' => $eligible, 'deleted_count' => $dryRun ? 0 : $eligible];
    }

    private function pruneUserAuthTokens(bool $dryRun): array
    {
        $cutoff = now('UTC')->subDays(max(1, (int) config('data_lifecycle.retention.user_auth_tokens_days', 14)));
        if (! Schema::hasTable('user_auth_tokens')) {
            return $this->skipped('user_auth_tokens');
        }

        $query = DB::table('user_auth_tokens')->where(function ($q) use ($cutoff): void {
            $q->where('expires_at', '<', $cutoff)
                ->orWhere(function ($x) use ($cutoff): void {
                    $x->whereNotNull('used_at')->where('used_at', '<', $cutoff);
                });
        });

        $eligible = (int) $query->count();
        if (! $dryRun && $eligible > 0) {
            $query->delete();
        }

        return ['table' => 'user_auth_tokens', 'action' => 'delete', 'cutoff_utc' => $cutoff->toIso8601String(), 'eligible_count' => $eligible, 'deleted_count' => $dryRun ? 0 : $eligible];
    }

    private function pruneNotificationArtifacts(bool $dryRun): array
    {
        if (! Schema::hasTable('notification_outbox')) {
            return $this->skipped('notification_outbox');
        }

        $cutoff = now('UTC')->subDays(max(1, (int) config('data_lifecycle.retention.notification_outbox_days', 90)));
        $attemptCutoff = now('UTC')->subDays(max(1, (int) config('data_lifecycle.retention.notification_delivery_attempts_days', 90)));
        $outboxIds = DB::table('notification_outbox')
            ->whereIn('status', ['Sent', 'Failed', 'Cancelled'])
            ->where(function ($q) use ($cutoff): void {
                $q->whereNotNull('sent_at')->where('sent_at', '<', $cutoff)
                    ->orWhere(function ($x) use ($cutoff): void {
                        $x->whereNull('sent_at')->where('created_at', '<', $cutoff);
                    });
            })
            ->pluck('outbox_id')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $attemptIds = $this->notificationAttemptIdsForPruning($outboxIds, $attemptCutoff);
        $attemptCount = count($attemptIds);

        if (! $dryRun && $attemptIds !== []) {
            DB::table('notification_delivery_attempts')->whereIn('attempt_id', $attemptIds)->delete();
        }

        if (! $dryRun && $outboxIds !== []) {
            DB::table('notification_outbox')->whereIn('outbox_id', $outboxIds)->delete();
        }

        return ['table' => 'notification_outbox', 'action' => 'delete_terminal_rows', 'cutoff_utc' => $cutoff->toIso8601String(), 'eligible_outbox_count' => count($outboxIds), 'deleted_outbox_count' => $dryRun ? 0 : count($outboxIds), 'eligible_attempt_count' => $attemptCount, 'deleted_attempt_count' => $dryRun ? 0 : $attemptCount];
    }

    private function pruneConversationDerivedArtifacts(bool $dryRun): array
    {
        if (! Schema::hasTable('conversations')) {
            return $this->skipped('conversations');
        }

        $analysisCutoff = now('UTC')->subDays(max(1, (int) config('data_lifecycle.retention.conversation_analyses_days', 30)));
        $entityCutoff = now('UTC')->subDays(max(1, (int) config('data_lifecycle.retention.message_entities_days', 30)));
        $conversationIds = DB::table('conversations')->where(function ($q): void {
            $q->where('status', 'Closed')->orWhereNotNull('closed_at');
        })->pluck('conversation_id')->values()->all();

        $analysisCount = ($conversationIds !== [] && Schema::hasTable('conversation_analyses'))
            ? (int) DB::table('conversation_analyses')->whereIn('conversation_id', $conversationIds)->where('created_at', '<', $analysisCutoff)->count()
            : 0;
        $messageEntityIds = $this->messageEntityIdsForPruning($conversationIds, $entityCutoff);
        $entityCount = count($messageEntityIds);

        if (! $dryRun && $analysisCount > 0) {
            DB::table('conversation_analyses')->whereIn('conversation_id', $conversationIds)->where('created_at', '<', $analysisCutoff)->delete();
        }
        if (! $dryRun && $messageEntityIds !== []) {
            DB::table('message_entities')->whereIn('message_entity_id', $messageEntityIds)->delete();
        }

        return ['table' => 'conversation_artifacts', 'action' => 'delete_derived_rows', 'analysis_cutoff_utc' => $analysisCutoff->toIso8601String(), 'entity_cutoff_utc' => $entityCutoff->toIso8601String(), 'eligible_analysis_count' => $analysisCount, 'deleted_analysis_count' => $dryRun ? 0 : $analysisCount, 'eligible_message_entity_count' => $entityCount, 'deleted_message_entity_count' => $dryRun ? 0 : $entityCount];
    }

    private function scrubPaymentWebhookReceipts(bool $dryRun): array
    {
        if (! Schema::hasTable('payment_provider_webhook_receipts')) {
            return $this->skipped('payment_provider_webhook_receipts');
        }

        $cutoff = now('UTC')->subDays(max(1, (int) config('data_lifecycle.retention.payment_webhook_receipts_days', 365)));
        $query = DB::table('payment_provider_webhook_receipts')
            ->where(function ($q) use ($cutoff): void {
                $q->whereNotNull('processed_at')->where('processed_at', '<', $cutoff)
                    ->orWhere(function ($x) use ($cutoff): void {
                        $x->whereNull('processed_at')->where('created_at', '<', $cutoff);
                    });
            })
            ->where(function ($q): void {
                $q->whereNotNull('request_signature')
                    ->orWhereNotNull('request_headers_json')
                    ->orWhereNotNull('request_body')
                    ->orWhereNotNull('provider_payload_json');
            });

        $eligible = (int) $query->count();

        if (! $dryRun && $eligible > 0) {
            $query->update([
                'request_signature' => null,
                'request_headers_json' => null,
                'request_body' => null,
                'provider_payload_json' => json_encode([
                    '_retention' => [
                        'verbose_payload_scrubbed' => true,
                        'applied_at' => now('UTC')->toIso8601String(),
                    ],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => now('UTC'),
                'row_version' => DB::raw('COALESCE(row_version, 1) + 1'),
            ]);
        }

        return [
            'table' => 'payment_provider_webhook_receipts',
            'action' => 'scrub_verbose_fields',
            'cutoff_utc' => $cutoff->toIso8601String(),
            'eligible_count' => $eligible,
            'scrubbed_count' => $dryRun ? 0 : $eligible,
        ];
    }

    /**
     * @param  list<int>  $outboxIds
     * @return list<int>
     */
    private function notificationAttemptIdsForPruning(array $outboxIds, Carbon $attemptCutoff): array
    {
        if (! Schema::hasTable('notification_delivery_attempts') || ! Schema::hasColumn('notification_delivery_attempts', 'attempt_id')) {
            return [];
        }

        $query = DB::table('notification_delivery_attempts as attempts')
            ->select('attempts.attempt_id')
            ->leftJoin('notification_outbox as outbox', 'outbox.outbox_id', '=', 'attempts.outbox_id')
            ->where(function ($q) use ($outboxIds, $attemptCutoff): void {
                $orphanScope = function ($orphanQuery) use ($attemptCutoff): void {
                    $orphanQuery->whereNull('outbox.outbox_id')
                        ->where(function ($ageQuery) use ($attemptCutoff): void {
                            $ageQuery->whereNull('attempts.attempted_at')
                                ->orWhere('attempts.attempted_at', '<', $attemptCutoff);
                        });
                };

                if ($outboxIds !== []) {
                    $q->whereIn('attempts.outbox_id', $outboxIds)
                        ->orWhere($orphanScope);

                    return;
                }

                $q->where($orphanScope);
            });

        return $query->pluck('attempts.attempt_id')
            ->map(static fn ($value): int => (int) $value)
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $conversationIds
     * @return list<int>
     */
    private function messageEntityIdsForPruning(array $conversationIds, Carbon $entityCutoff): array
    {
        if (
            $conversationIds === []
            || ! Schema::hasTable('message_entities')
            || ! Schema::hasTable('conversation_messages')
            || ! Schema::hasColumn('message_entities', 'message_entity_id')
        ) {
            return [];
        }

        return DB::table('message_entities')
            ->join('conversation_messages', 'conversation_messages.message_id', '=', 'message_entities.message_id')
            ->whereIn('conversation_messages.conversation_id', $conversationIds)
            ->where('message_entities.created_at', '<', $entityCutoff)
            ->pluck('message_entities.message_entity_id')
            ->map(static fn ($value): int => (int) $value)
            ->values()
            ->all();
    }

    private function skipped(string $table): array
    {
        return ['table' => $table, 'action' => 'skipped', 'reason' => 'table_missing'];
    }
}

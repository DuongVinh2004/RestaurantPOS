<?php

declare(strict_types=1);

namespace App\Modules\PrivacyCompliance\Application\Workflows\Redaction;

use App\Enums\ReservationBillPaymentSessionStatus;
use App\Enums\ReservationDepositPaymentSessionStatus;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerAnonymizationWorkflow
{
    /**
     * @return array<string,mixed>
     */
    public function preview(int $userId): array
    {
        /** @var User $user */
        $user = User::query()->with('role')->findOrFail($userId);
        $this->assertCustomerUser($user);

        $sessionIds = $this->sessionIdsForUser($userId);
        $contacts = $this->identityContactValues($user);
        $reservationIds = $this->ids('reservations', 'reservation_id', fn ($q) => $q->where('user_id', $userId));
        $conversationIds = $this->ids('conversations', 'conversation_id', function ($q) use ($userId, $sessionIds): void {
            $q->where('user_id', $userId);
            if ($sessionIds !== []) {
                $q->orWhereIn('session_id', $sessionIds)->orWhereIn('customer_session_id', $sessionIds);
            }
        });
        $messageIds = $this->ids('conversation_messages', 'message_id', fn ($q) => $q->whereIn('conversation_id', $conversationIds));
        $outboxIds = $this->ids('notification_outbox', 'outbox_id', function ($q) use ($userId, $contacts): void {
            $q->where('recipient_user_id', $userId);
            foreach ($contacts as $contact) {
                $q->orWhere('recipient', $contact);
            }
        });

        $counts = [
            'reservations' => count($reservationIds),
            'active_reservations' => $this->count('reservations', fn ($q) => $q->where('user_id', $userId)->whereIn('status', ['Confirmed', 'Reserved'])),
            'payments' => $this->countByIds('payments', 'reservation_id', $reservationIds),
            'deposit_payment_sessions' => $this->count('reservation_deposit_payment_sessions', fn ($q) => $q->where('customer_user_id', $userId)),
            'active_deposit_payment_sessions' => $this->count('reservation_deposit_payment_sessions', fn ($q) => $q->where('customer_user_id', $userId)->whereIn('session_status', [ReservationDepositPaymentSessionStatus::Created->value, ReservationDepositPaymentSessionStatus::Pending->value])),
            'bill_payment_sessions' => $this->count('reservation_bill_payment_sessions', fn ($q) => $q->where('customer_user_id', $userId)),
            'active_bill_payment_sessions' => $this->count('reservation_bill_payment_sessions', fn ($q) => $q->where('customer_user_id', $userId)->whereIn('session_status', [ReservationBillPaymentSessionStatus::Created->value, ReservationBillPaymentSessionStatus::Pending->value])),
            'user_vouchers' => $this->count('user_vouchers', fn ($q) => $q->where('user_id', $userId)),
            'user_points' => $this->count('user_points', fn ($q) => $q->where('user_id', $userId)),
            'loyalty_transactions' => $this->count('loyalty_point_transactions', fn ($q) => $q->where('user_id', $userId)),
            'tier_history' => $this->count('user_tier_history', fn ($q) => $q->where('user_id', $userId)),
            'waiting_list' => $this->countWaitingList($userId, $sessionIds),
            'active_waiting_list' => $this->countWaitingList($userId, $sessionIds, ['Waiting', 'Notified']),
            'customer_access_sessions' => $this->count('customer_access_sessions', fn ($q) => $q->where('user_id', $userId)),
            'user_auth_tokens' => $this->countUserAuthTokens($userId, $contacts),
            'notification_preferences' => $this->count('notification_preferences', fn ($q) => $q->where('user_id', $userId)),
            'notification_outbox' => count($outboxIds),
            'notification_delivery_attempts' => $this->countByIds('notification_delivery_attempts', 'outbox_id', $outboxIds),
            'bank_accounts' => $this->count('bank_accounts', fn ($q) => $q->where('user_id', $userId)),
            'conversations' => count($conversationIds),
            'open_conversations' => $this->countConversations($userId, $sessionIds, ['Open', 'Pending']),
            'conversation_messages' => count($messageIds),
            'conversation_files' => $this->countByIds('conversation_files', 'message_id', $messageIds),
            'message_entities' => $this->countByIds('message_entities', 'message_id', $messageIds),
            'conversation_events' => $this->countByIds('conversation_events', 'conversation_id', $conversationIds),
            'conversation_analyses' => $this->countByIds('conversation_analyses', 'conversation_id', $conversationIds),
        ];

        $blockers = [];
        foreach ([
            'active_reservations' => 'Customer still has active reservations.',
            'active_waiting_list' => 'Customer still has active waiting-list entries.',
            'open_conversations' => 'Customer still has open conversations.',
        ] as $code => $message) {
            if (($counts[$code] ?? 0) > 0) {
                $blockers[] = ['code' => $code, 'message' => $message, 'count' => $counts[$code]];
            }
        }

        $activePaymentSessions = (int) ($counts['active_deposit_payment_sessions'] ?? 0) + (int) ($counts['active_bill_payment_sessions'] ?? 0);
        if ($activePaymentSessions > 0) {
            $blockers[] = ['code' => 'active_payment_sessions', 'message' => 'Customer still has non-terminal payment sessions.', 'count' => $activePaymentSessions];
        }

        if ($user->privacy_anonymized_at !== null || (bool) $user->is_deleted) {
            $blockers[] = ['code' => 'already_anonymized', 'message' => 'Customer account has already been anonymized.', 'count' => 1];
        }

        return [
            'user' => [
                'user_id' => (int) $user->user_id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role_id' => $user->role_id !== null ? (int) $user->role_id : null,
                'role_name' => $user->role?->role_name,
                'is_deleted' => (bool) $user->is_deleted,
                'privacy_anonymized_at_utc' => $user->privacy_anonymized_at?->utc()->toIso8601String(),
            ],
            'counts' => $counts,
            'blockers' => $blockers,
            'can_commit' => $blockers === [],
            'retained_tables' => ['reservations', 'preorders', 'payments', 'reservation_deposit_payment_sessions', 'reservation_bill_payment_sessions', 'user_vouchers', 'user_points', 'loyalty_point_transactions', 'user_tier_history', 'audit_logs', 'payment_provider_webhook_receipts', 'billing_invoices'],
            'anonymized_tables' => ['users', 'reservations', 'preorders', 'waiting_list', 'conversations', 'conversation_messages', 'conversation_files', 'message_entities', 'conversation_events', 'conversation_analyses', 'notification_outbox', 'notification_delivery_attempts'],
            'purged_tables' => ['customer_access_sessions', 'user_auth_tokens', 'notification_preferences', 'bank_accounts', 'user_favorite_menu_items'],
            'redacted_fields' => ['users.username', 'users.full_name', 'users.email', 'users.phone', 'users.password_hash', 'reservations.notes', 'reservations.cancel_reason', 'preorders.notes', 'waiting_list.guest_name', 'waiting_list.phone', 'waiting_list.notes', 'waiting_list.cancel_reason', 'conversations.session_id', 'conversations.customer_session_id', 'conversation_messages.message_text', 'conversation_messages.attachment_url', 'conversation_files.file_url', 'message_entities.entity_text', 'message_entities.entity_normalized', 'conversation_events.event_data', 'conversation_analyses.extracted_info', 'notification_outbox.recipient', 'notification_outbox.payload_json', 'notification_outbox.last_error', 'notification_delivery_attempts.recipient', 'notification_delivery_attempts.error_message', 'notification_delivery_attempts.request_payload_json', 'notification_delivery_attempts.response_payload_json'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function apply(int $userId): array
    {
        $preview = $this->preview($userId);
        if (! ($preview['can_commit'] ?? false)) {
            throw ValidationException::withMessages(['privacy_request' => ['Customer cannot be anonymized while lifecycle blockers still exist.']]);
        }

        /** @var User $user */
        $user = User::query()->lockForUpdate()->findOrFail($userId);
        $sessionIds = $this->sessionIdsForUser($userId);
        $contacts = $this->identityContactValues($user);
        $conversationIds = $this->ids('conversations', 'conversation_id', function ($q) use ($userId, $sessionIds): void {
            $q->where('user_id', $userId);
            if ($sessionIds !== []) {
                $q->orWhereIn('session_id', $sessionIds)->orWhereIn('customer_session_id', $sessionIds);
            }
        });
        $messageIds = $this->ids('conversation_messages', 'message_id', fn ($q) => $q->whereIn('conversation_id', $conversationIds));
        $outboxIds = $this->ids('notification_outbox', 'outbox_id', function ($q) use ($userId, $contacts): void {
            $q->where('recipient_user_id', $userId);
            foreach ($contacts as $contact) {
                $q->orWhere('recipient', $contact);
            }
        });

        $redactedText = (string) config('data_lifecycle.anonymization.redacted_text', '[redacted after privacy anonymization]');
        $redactedRecipient = (string) config('data_lifecycle.anonymization.redacted_recipient', 'redacted://privacy/recipient');
        $redactedFileUrl = (string) config('data_lifecycle.anonymization.redacted_file_url', 'redacted://privacy/file');
        $redactedJson = json_encode(['redacted' => true, 'reason' => (string) config('data_lifecycle.anonymization.redacted_json_reason', 'privacy_anonymized')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $displayName = sprintf((string) config('data_lifecycle.anonymization.display_name_template', 'Deleted Customer #%d'), $userId);
        $username = (string) config('data_lifecycle.anonymization.username_prefix', 'anon_user_').$userId;

        $updated = [];
        $purged = [];

        $updated['users'] = DB::table('users')->where('user_id', $userId)->update($this->touch('users', [
            'username' => $username,
            'password_hash' => Hash::make((string) Str::uuid()),
            'full_name' => $displayName,
            'email' => null,
            'phone' => null,
            'is_deleted' => true,
            'privacy_anonymized_at' => now('UTC'),
        ]));

        $updated['reservations'] = $this->updateIfTable('reservations', fn ($q) => $q->where('user_id', $userId)->where(fn ($x) => $x->whereNotNull('notes')->orWhereNotNull('cancel_reason')), $this->touch('reservations', ['notes' => null, 'cancel_reason' => null]));
        $updated['preorders'] = $this->updateIfTable('preorders', fn ($q) => $q->where(fn ($x) => $x->where('customer_user_id', $userId)->orWhereIn('reservation_id', DB::table('reservations')->select('reservation_id')->where('user_id', $userId)))->whereNotNull('notes'), $this->touch('preorders', ['notes' => null]));
        $updated['waiting_list'] = $this->updateWaitingList($userId, $sessionIds, $displayName);
        $updated['conversations'] = $this->updateConversations($userId, $sessionIds);
        $updated['conversation_messages'] = $this->updateByIds('conversation_messages', 'message_id', $messageIds, ['message_text' => $redactedText, 'attachment_url' => null]);
        $updated['conversation_files'] = $this->updateByIds('conversation_files', 'message_id', $messageIds, ['file_url' => $redactedFileUrl]);
        $updated['message_entities'] = $this->updateByIds('message_entities', 'message_id', $messageIds, ['entity_text' => $redactedText, 'entity_normalized' => $redactedText, 'extra_json' => $redactedJson]);
        $updated['conversation_events'] = $this->updateByIds('conversation_events', 'conversation_id', $conversationIds, ['event_data' => $redactedJson]);
        $updated['conversation_analyses'] = $this->updateByIds('conversation_analyses', 'conversation_id', $conversationIds, ['extracted_info' => $redactedJson]);
        $updated['notification_outbox'] = $this->updateByIds('notification_outbox', 'outbox_id', $outboxIds, ['recipient' => $redactedRecipient, 'payload_json' => $redactedJson, 'last_error' => null]);
        $updated['notification_delivery_attempts'] = $this->updateByIds('notification_delivery_attempts', 'outbox_id', $outboxIds, ['recipient' => $redactedRecipient, 'error_message' => null, 'request_payload_json' => $redactedJson, 'response_payload_json' => $redactedJson]);

        $purged['notification_preferences'] = $this->delete('notification_preferences', fn ($q) => $q->where('user_id', $userId));
        $purged['bank_accounts'] = $this->delete('bank_accounts', fn ($q) => $q->where('user_id', $userId));
        $purged['customer_access_sessions'] = $this->delete('customer_access_sessions', fn ($q) => $q->where('user_id', $userId));
        $purged['user_favorite_menu_items'] = $this->delete('user_favorite_menu_items', fn ($q) => $q->where('user_id', $userId));
        $purged['user_auth_tokens'] = $this->delete('user_auth_tokens', function ($q) use ($userId, $contacts): void {
            $q->where('user_id', $userId);
            foreach ($contacts as $contact) {
                $q->orWhere('recipient', $contact);
            }
        });

        return [
            'user_id' => $userId,
            'anonymized_at_utc' => now('UTC')->toIso8601String(),
            'updated' => $updated,
            'purged' => $purged,
            'retained_counts' => [
                'reservations' => $this->count('reservations', fn ($q) => $q->where('user_id', $userId)),
                'preorders' => $this->count('preorders', fn ($q) => $q->where('customer_user_id', $userId)->orWhereIn('reservation_id', DB::table('reservations')->select('reservation_id')->where('user_id', $userId))),
                'payments' => $this->count('payments', fn ($q) => $q->whereIn('reservation_id', DB::table('reservations')->select('reservation_id')->where('user_id', $userId))),
                'user_vouchers' => $this->count('user_vouchers', fn ($q) => $q->where('user_id', $userId)),
                'loyalty_transactions' => $this->count('loyalty_point_transactions', fn ($q) => $q->where('user_id', $userId)),
            ],
            'redacted_fields' => $preview['redacted_fields'],
        ];
    }

    private function assertCustomerUser(User $user): void
    {
        $allowedRoleIds = array_values(array_filter(array_map('intval', (array) config('customer_auth.allowed_role_ids', [3])), static fn (int $v): bool => $v > 0));
        if ($allowedRoleIds !== [] && ! in_array((int) ($user->role_id ?? 0), $allowedRoleIds, true)) {
            throw ValidationException::withMessages(['user_id' => ['Target user is not a customer account.']]);
        }
    }

    /**
     * @return list<string>
     */
    private function sessionIdsForUser(int $userId): array
    {
        if (! Schema::hasTable('customer_access_sessions') || ! Schema::hasColumn('customer_access_sessions', 'session_id')) {
            return [];
        }

        return DB::table('customer_access_sessions')->where('user_id', $userId)->whereNotNull('session_id')->pluck('session_id')->map(fn ($value): string => trim((string) $value))->filter()->unique()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function identityContactValues(User $user): array
    {
        return array_values(array_filter([trim((string) ($user->email ?? '')) ?: null, trim((string) ($user->phone ?? '')) ?: null]));
    }

    private function countWaitingList(int $userId, array $sessionIds, array $statuses = []): int
    {
        return $this->count('waiting_list', function ($q) use ($userId, $sessionIds, $statuses): void {
            $q->where(function ($x) use ($userId, $sessionIds): void {
                $x->where('user_id', $userId);
                if ($sessionIds !== []) {
                    $x->orWhereIn('customer_session_id', $sessionIds);
                }
            });
            if ($statuses !== []) {
                $q->whereIn('status', $statuses);
            }
        });
    }

    private function countConversations(int $userId, array $sessionIds, array $statuses = []): int
    {
        return $this->count('conversations', function ($q) use ($userId, $sessionIds, $statuses): void {
            $q->where(function ($x) use ($userId, $sessionIds): void {
                $x->where('user_id', $userId);
                if ($sessionIds !== []) {
                    $x->orWhereIn('session_id', $sessionIds)->orWhereIn('customer_session_id', $sessionIds);
                }
            });
            if ($statuses !== []) {
                $q->whereIn('status', $statuses);
            }
        });
    }

    private function countUserAuthTokens(int $userId, array $contacts): int
    {
        return $this->count('user_auth_tokens', function ($q) use ($userId, $contacts): void {
            $q->where('user_id', $userId);
            foreach ($contacts as $contact) {
                $q->orWhere('recipient', $contact);
            }
        });
    }

    private function updateWaitingList(int $userId, array $sessionIds, string $displayName): int
    {
        if (! Schema::hasTable('waiting_list')) {
            return 0;
        }

        $payload = ['guest_name' => $displayName, 'phone' => null, 'notes' => null, 'cancel_reason' => null];
        if (Schema::hasColumn('waiting_list', 'customer_session_id')) {
            $payload['customer_session_id'] = null;
        }

        return DB::table('waiting_list')->where(function ($q) use ($userId, $sessionIds): void {
            $q->where('user_id', $userId);
            if ($sessionIds !== []) {
                $q->orWhereIn('customer_session_id', $sessionIds);
            }
        })->update($this->touch('waiting_list', $payload));
    }

    private function updateConversations(int $userId, array $sessionIds): int
    {
        if (! Schema::hasTable('conversations')) {
            return 0;
        }

        $payload = [];
        if (Schema::hasColumn('conversations', 'session_id')) {
            $payload['session_id'] = null;
        }
        if (Schema::hasColumn('conversations', 'customer_session_id')) {
            $payload['customer_session_id'] = null;
        }
        if ($payload === []) {
            return 0;
        }

        return DB::table('conversations')->where(function ($q) use ($userId, $sessionIds): void {
            $q->where('user_id', $userId);
            if ($sessionIds !== []) {
                $q->orWhereIn('session_id', $sessionIds)->orWhereIn('customer_session_id', $sessionIds);
            }
        })->update($this->touch('conversations', $payload));
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function updateByIds(string $table, string $column, array $ids, array $payload): int
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $ids)->update($payload);
    }

    private function updateIfTable(string $table, callable $scope, array $payload): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $scope($query);

        return $query->update($payload);
    }

    private function delete(string $table, callable $scope): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $scope($query);

        return $query->delete();
    }

    private function count(string $table, callable $scope): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        $scope($query);

        return (int) $query->count();
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function countByIds(string $table, string $column, array $ids): int
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->whereIn($column, $ids)->count();
    }

    /**
     * @param  callable(Builder):void  $scope
     * @return list<int|string>
     */
    private function ids(string $table, string $column, callable $scope): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        $query = DB::table($table);
        $scope($query);

        return $query->pluck($column)->values()->all();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function touch(string $table, array $payload): array
    {
        if (Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now('UTC');
        }
        if (Schema::hasColumn($table, 'row_version')) {
            $payload['row_version'] = DB::raw('COALESCE(row_version, 0) + 1');
        }

        return $payload;
    }
}

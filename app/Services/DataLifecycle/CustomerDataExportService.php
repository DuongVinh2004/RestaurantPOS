<?php

declare(strict_types=1);

namespace App\Services\DataLifecycle;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerDataExportService
{
    /**
     * @return array<string,mixed>
     */
    public function exportForUser(int $userId): array
    {
        /** @var User $user */
        $user = User::query()->with(['role', 'currentTier', 'points'])->findOrFail($userId);
        $this->assertCustomerUser($user);

        $sessionIds = $this->sessionIdsForUser($userId);
        $contacts = $this->identityContactValues($user);
        $reservationIds = $this->ids('reservations', 'reservation_id', fn ($q) => $q->where('user_id', $userId));
        $conversationIds = $this->ids('conversations', 'conversation_id', function ($q) use ($userId, $sessionIds): void {
            $q->where('user_id', $userId);

            if ($sessionIds !== []) {
                $q->orWhereIn('session_id', $sessionIds)
                    ->orWhereIn('customer_session_id', $sessionIds);
            }
        });
        $messageIds = $this->ids('conversation_messages', 'message_id', fn ($q) => $q->whereIn('conversation_id', $conversationIds));
        $outboxIds = $this->ids('notification_outbox', 'outbox_id', function ($q) use ($userId, $contacts): void {
            $q->where('recipient_user_id', $userId);

            foreach ($contacts as $contact) {
                $q->orWhere('recipient', $contact);
            }
        });

        return [
            'exported_at_utc' => now('UTC')->toIso8601String(),
            'customer' => [
                'user' => $this->normalizeRows(DB::table('users')->where('user_id', $userId)->get())[0] ?? null,
                'role' => $this->normalizeRows(DB::table('roles')->where('role_id', $user->role_id)->get())[0] ?? null,
                'current_tier' => $user->current_tier_id !== null
                    ? ($this->normalizeRows(DB::table('loyalty_tiers')->where('tier_id', $user->current_tier_id)->get())[0] ?? null)
                    : null,
                'points' => Schema::hasTable('user_points')
                    ? ($this->normalizeRows(DB::table('user_points')->where('user_id', $userId)->get())[0] ?? null)
                    : null,
            ],
            'tables' => [
                'customer_access_sessions' => $this->normalizeRows(
                    Schema::hasTable('customer_access_sessions')
                        ? DB::table('customer_access_sessions')->where('user_id', $userId)->orderByDesc('access_session_id')->get()
                        : collect()
                ),
                'bank_accounts' => $this->normalizeRows(
                    Schema::hasTable('bank_accounts')
                        ? DB::table('bank_accounts')->where('user_id', $userId)->orderByDesc('bank_account_id')->get()
                        : collect()
                ),
                'user_auth_tokens' => $this->normalizeRows(
                    Schema::hasTable('user_auth_tokens')
                        ? DB::table('user_auth_tokens')
                            ->where(function ($q) use ($userId, $contacts): void {
                                $q->where('user_id', $userId);

                                foreach ($contacts as $contact) {
                                    $q->orWhere('recipient', $contact);
                                }
                            })
                            ->orderByDesc('token_id')
                            ->get()
                        : collect()
                ),
                'notification_preferences' => $this->normalizeRows(
                    Schema::hasTable('notification_preferences')
                        ? DB::table('notification_preferences')->where('user_id', $userId)->orderByDesc('notification_preference_id')->get()
                        : collect()
                ),
                'reservations' => $this->normalizeRows(
                    Schema::hasTable('reservations')
                        ? DB::table('reservations')->where('user_id', $userId)->orderByDesc('reservation_id')->get()
                        : collect()
                ),
                'reservation_tables' => $this->normalizeRows(
                    ($reservationIds !== [] && Schema::hasTable('reservation_tables'))
                        ? DB::table('reservation_tables')->whereIn('reservation_id', $reservationIds)->orderByDesc('reservation_table_id')->get()
                        : collect()
                ),
                'reservation_orders' => $this->normalizeRows(
                    ($reservationIds !== [] && Schema::hasTable('reservation_orders'))
                        ? DB::table('reservation_orders')->whereIn('reservation_id', $reservationIds)->orderByDesc('order_id')->get()
                        : collect()
                ),
                'reservation_order_items' => $this->normalizeRows(
                    ($reservationIds !== [] && Schema::hasTable('reservation_order_items'))
                        ? DB::table('reservation_order_items')
                            ->whereIn('order_id', function ($q) use ($reservationIds): void {
                                $q->from('reservation_orders')->select('order_id')->whereIn('reservation_id', $reservationIds);
                            })
                            ->orderByDesc('order_item_id')
                            ->get()
                        : collect()
                ),
                'payments' => $this->normalizeRows(
                    ($reservationIds !== [] && Schema::hasTable('payments'))
                        ? DB::table('payments')->whereIn('reservation_id', $reservationIds)->orderByDesc('payment_id')->get()
                        : collect()
                ),
                'billing_invoices' => $this->normalizeRows(
                    ($reservationIds !== [] && Schema::hasTable('billing_invoices'))
                        ? DB::table('billing_invoices')->whereIn('reservation_id', $reservationIds)->orderByDesc('billing_invoice_id')->get()
                        : collect()
                ),
                'reservation_deposit_payment_sessions' => $this->normalizeRows(
                    Schema::hasTable('reservation_deposit_payment_sessions')
                        ? DB::table('reservation_deposit_payment_sessions')->where('customer_user_id', $userId)->orderByDesc('deposit_payment_session_id')->get()
                        : collect()
                ),
                'reservation_bill_payment_sessions' => $this->normalizeRows(
                    Schema::hasTable('reservation_bill_payment_sessions')
                        ? DB::table('reservation_bill_payment_sessions')->where('customer_user_id', $userId)->orderByDesc('bill_payment_session_id')->get()
                        : collect()
                ),
                'user_vouchers' => $this->normalizeRows(
                    Schema::hasTable('user_vouchers')
                        ? DB::table('user_vouchers')->where('user_id', $userId)->orderByDesc('user_voucher_id')->get()
                        : collect()
                ),
                'loyalty_point_transactions' => $this->normalizeRows(
                    Schema::hasTable('loyalty_point_transactions')
                        ? DB::table('loyalty_point_transactions')->where('user_id', $userId)->orderByDesc('txn_id')->get()
                        : collect()
                ),
                'user_tier_history' => $this->normalizeRows(
                    Schema::hasTable('user_tier_history')
                        ? DB::table('user_tier_history')->where('user_id', $userId)->orderByDesc('history_id')->get()
                        : collect()
                ),
                'waiting_list' => $this->normalizeRows(
                    Schema::hasTable('waiting_list')
                        ? DB::table('waiting_list')
                            ->where(function ($q) use ($userId, $sessionIds): void {
                                $q->where('user_id', $userId);

                                if ($sessionIds !== []) {
                                    $q->orWhereIn('customer_session_id', $sessionIds);
                                }
                            })
                            ->orderByDesc('waiting_id')
                            ->get()
                        : collect()
                ),
                'conversations' => $this->normalizeRows(
                    ($conversationIds !== [] && Schema::hasTable('conversations'))
                        ? DB::table('conversations')->whereIn('conversation_id', $conversationIds)->orderByDesc('created_at')->get()
                        : collect()
                ),
                'conversation_messages' => $this->normalizeRows(
                    ($messageIds !== [] && Schema::hasTable('conversation_messages'))
                        ? DB::table('conversation_messages')->whereIn('message_id', $messageIds)->orderByDesc('message_id')->get()
                        : collect()
                ),
                'conversation_files' => $this->normalizeRows(
                    ($messageIds !== [] && Schema::hasTable('conversation_files'))
                        ? DB::table('conversation_files')->whereIn('message_id', $messageIds)->orderByDesc('file_id')->get()
                        : collect()
                ),
                'message_entities' => $this->normalizeRows(
                    ($messageIds !== [] && Schema::hasTable('message_entities'))
                        ? DB::table('message_entities')->whereIn('message_id', $messageIds)->orderByDesc('message_entity_id')->get()
                        : collect()
                ),
                'conversation_events' => $this->normalizeRows(
                    ($conversationIds !== [] && Schema::hasTable('conversation_events'))
                        ? DB::table('conversation_events')->whereIn('conversation_id', $conversationIds)->orderByDesc('event_id')->get()
                        : collect()
                ),
                'conversation_analyses' => $this->normalizeRows(
                    ($conversationIds !== [] && Schema::hasTable('conversation_analyses'))
                        ? DB::table('conversation_analyses')->whereIn('conversation_id', $conversationIds)->orderByDesc('analysis_id')->get()
                        : collect()
                ),
                'notification_outbox' => $this->normalizeRows(
                    ($outboxIds !== [] && Schema::hasTable('notification_outbox'))
                        ? DB::table('notification_outbox')->whereIn('outbox_id', $outboxIds)->orderByDesc('outbox_id')->get()
                        : collect()
                ),
                'notification_delivery_attempts' => $this->normalizeRows(
                    ($outboxIds !== [] && Schema::hasTable('notification_delivery_attempts'))
                        ? DB::table('notification_delivery_attempts')->whereIn('outbox_id', $outboxIds)->orderByDesc('attempt_id')->get()
                        : collect()
                ),
                'customer_privacy_requests' => $this->normalizeRows(
                    Schema::hasTable('customer_privacy_requests')
                        ? DB::table('customer_privacy_requests')->where('user_id', $userId)->orderByDesc('customer_privacy_request_id')->get()
                        : collect()
                ),
            ],
            'summary' => [
                'reservation_count' => count($reservationIds),
                'conversation_count' => count($conversationIds),
                'message_count' => count($messageIds),
                'notification_count' => count($outboxIds),
            ],
        ];
    }

    private function assertCustomerUser(User $user): void
    {
        $allowedRoleIds = array_values(array_filter(
            array_map('intval', (array) config('customer_auth.allowed_role_ids', [3])),
            static fn (int $value): bool => $value > 0
        ));

        if ($allowedRoleIds !== [] && ! in_array((int) ($user->role_id ?? 0), $allowedRoleIds, true)) {
            throw new \InvalidArgumentException('Target user is not a customer account.');
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

        return DB::table('customer_access_sessions')
            ->where('user_id', $userId)
            ->whereNotNull('session_id')
            ->pluck('session_id')
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function identityContactValues(User $user): array
    {
        return array_values(array_filter([
            trim((string) ($user->email ?? '')) ?: null,
            trim((string) ($user->phone ?? '')) ?: null,
        ]));
    }

    /**
     * @param callable(\Illuminate\Database\Query\Builder):void $scope
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
     * @param \Illuminate\Support\Collection<int,object>|\Illuminate\Support\Collection<int,mixed> $rows
     * @return list<array<string,mixed>>
     */
    private function normalizeRows($rows): array
    {
        return collect($rows)
            ->map(function ($row): array {
                $payload = [];
                foreach ((array) $row as $key => $value) {
                    $payload[$key] = $this->normalizeValue($value);
                }

                return $payload;
            })
            ->values()
            ->all();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value))->utc()->toIso8601String();
        }

        if (is_resource($value)) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && in_array($trimmed[0], ['{', '['], true)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
                    return $decoded;
                }
            }
        }

        return $value;
    }
}

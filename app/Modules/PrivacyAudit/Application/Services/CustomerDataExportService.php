<?php

declare(strict_types=1);

namespace App\Modules\PrivacyAudit\Application\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class CustomerDataExportService
{
    private const PROFILE_FIELD_WHITELISTS = [
        'user' => [
            'user_id',
            'username',
            'full_name',
            'email',
            'phone',
            'role_id',
            'current_tier_id',
            'language_pref',
            'is_deleted',
            'privacy_anonymized_at',
            'created_at',
            'updated_at',
        ],
        'role' => [
            'role_id',
            'role_name',
        ],
        'current_tier' => [
            'tier_id',
            'tier_code',
            'tier_name',
            'description',
            'min_points',
            'max_points',
            'created_at',
            'updated_at',
        ],
        'points' => [
            'user_id',
            'available_points',
            'lifetime_earned_points',
            'lifetime_redeemed_points',
            'created_at',
            'updated_at',
        ],
    ];

    private const TABLE_FIELD_WHITELISTS = [
        'customer_access_sessions' => [
            'access_session_id',
            'user_id',
            'guest_name',
            'phone',
            'expires_at',
            'last_used_at',
            'revoked_at',
            'created_at',
            'updated_at',
        ],
        'bank_accounts' => [
            'bank_account_id',
            'user_id',
            'bank_name',
            'account_holder_name',
            'is_default',
            'created_at',
            'updated_at',
        ],
        'user_auth_tokens' => [
            'token_id',
            'user_id',
            'purpose',
            'channel',
            'recipient',
            'attempt_count',
            'max_attempts',
            'expires_at',
            'used_at',
            'created_at',
        ],
        'notification_preferences' => [
            'notification_preference_id',
            'user_id',
            'channel',
            'is_enabled',
            'quiet_hours_start_minute',
            'quiet_hours_end_minute',
            'created_at',
            'updated_at',
        ],
        'reservations' => [
            'reservation_id',
            'branch_id',
            'user_id',
            'reservation_code',
            'source',
            'status',
            'guest_count',
            'guest_name',
            'guest_phone',
            'guest_email',
            'notes',
            'cancel_reason',
            'start_time',
            'end_time',
            'checked_in_at',
            'checked_out_at',
            'cancelled_at',
            'no_show_at',
            'final_bill_amount',
            'bill_currency',
            'discount_amount',
            'deposit_required_amount',
            'deposit_paid_amount',
            'deposit_status',
            'deposit_requirement_acknowledged_at',
            'deposit_intent_status',
            'billed_at',
            'created_at',
            'updated_at',
        ],
        'reservation_tables' => [
            'reservation_table_id',
            'reservation_id',
            'table_id',
            'created_at',
            'updated_at',
        ],
        'reservation_orders' => [
            'order_id',
            'reservation_id',
            'order_code',
            'order_type',
            'status',
            'subtotal',
            'discount_amount',
            'total_amount',
            'currency',
            'created_at',
            'updated_at',
        ],
        'reservation_order_items' => [
            'order_item_id',
            'order_id',
            'item_id',
            'quantity',
            'unit_price',
            'currency',
            'line_total',
            'status',
            'notes',
            'created_at',
            'updated_at',
        ],
        'payments' => [
            'payment_id',
            'reservation_id',
            'branch_id',
            'refund_of_payment_id',
            'amount',
            'currency',
            'payment_method',
            'payment_provider',
            'payment_type',
            'status',
            'transaction_code',
            'paid_at',
            'created_at',
            'updated_at',
            'created_by',
            'updated_by',
            'notes',
        ],
        'billing_invoices' => [
            'billing_invoice_id',
            'reservation_id',
            'invoice_number',
            'invoice_status',
            'subtotal_amount',
            'discount_amount',
            'total_amount',
            'currency',
            'tax_code',
            'tax_name',
            'tax_rate_percentage',
            'prices_include_tax',
            'taxable_amount',
            'tax_amount',
            'seller_name',
            'seller_tax_id',
            'seller_address',
            'issued_at',
            'issued_by',
            'voided_at',
            'voided_by',
            'created_at',
            'updated_at',
        ],
        'reservation_deposit_payment_sessions' => [
            'deposit_payment_session_id',
            'reservation_id',
            'customer_user_id',
            'linked_payment_id',
            'provider_code',
            'payment_method',
            'amount',
            'currency',
            'session_status',
            'settlement_status',
            'failure_code',
            'failure_message',
            'provider_expires_at',
            'last_reconciled_at',
            'confirmed_at',
            'failed_at',
            'cancelled_at',
            'expired_at',
            'created_at',
            'updated_at',
        ],
        'reservation_bill_payment_sessions' => [
            'bill_payment_session_id',
            'reservation_id',
            'order_id',
            'customer_user_id',
            'linked_payment_id',
            'provider_code',
            'payment_method',
            'amount',
            'currency',
            'session_status',
            'settlement_status',
            'failure_code',
            'failure_message',
            'provider_expires_at',
            'last_reconciled_at',
            'confirmed_at',
            'failed_at',
            'cancelled_at',
            'expired_at',
            'created_at',
            'updated_at',
        ],
        'user_vouchers' => [
            'user_voucher_id',
            'user_id',
            'voucher_id',
            'status',
            'issued_at',
            'expires_at',
            'used_at',
            'used_reservation_id',
            'created_at',
            'updated_at',
        ],
        'loyalty_point_transactions' => [
            'txn_id',
            'user_id',
            'reservation_id',
            'points_delta',
            'balance_before',
            'balance_after',
            'reason_code',
            'reason',
            'created_by',
            'created_at',
        ],
        'user_tier_history' => [
            'history_id',
            'user_id',
            'from_tier_id',
            'to_tier_id',
            'reason_code',
            'created_by',
            'created_at',
        ],
        'waiting_list' => [
            'waiting_id',
            'branch_id',
            'user_id',
            'status',
            'guest_name',
            'phone',
            'guest_count',
            'quoted_wait_minutes',
            'service_minutes',
            'notes',
            'cancel_reason',
            'wait_started_at',
            'notified_at',
            'notify_expires_at',
            'confirmed_arrival_at',
            'cancelled_at',
            'seated_at',
            'created_at',
            'updated_at',
        ],
        'conversations' => [
            'conversation_id',
            'branch_id',
            'user_id',
            'channel',
            'status',
            'workflow_state',
            'workflow_state_reason',
            'intent_detected',
            'linked_reservation_id',
            'linked_waiting_list_id',
            'created_at',
            'workflow_state_changed_at',
            'first_triaged_at',
            'resolved_at',
            'closed_at',
        ],
        'conversation_messages' => [
            'message_id',
            'conversation_id',
            'sender',
            'sender_id',
            'message_text',
            'attachment_url',
            'is_internal_note',
            'processing_status',
            'related_reservation_id',
            'related_order_id',
            'created_at',
        ],
        'conversation_files' => [
            'file_id',
            'message_id',
            'file_url',
            'file_name',
            'mime_type',
            'size_bytes',
            'created_at',
        ],
        'message_entities' => [
            'message_entity_id',
            'message_id',
            'entity_type',
            'entity_text',
            'entity_normalized',
            'created_at',
        ],
        'conversation_events' => [
            'event_id',
            'conversation_id',
            'event_type',
            'event_by_user_id',
            'created_at',
        ],
        'conversation_analyses' => [
            'analysis_id',
            'conversation_id',
            'analyzer_name',
            'is_spam',
            'quality_score',
            'created_at',
        ],
        'notification_outbox' => [
            'outbox_id',
            'channel',
            'recipient',
            'recipient_user_id',
            'template_key',
            'status',
            'attempt_count',
            'last_attempted_at',
            'next_retry_at',
            'related_reservation_id',
            'created_at',
            'sent_at',
        ],
        'notification_delivery_attempts' => [
            'attempt_id',
            'outbox_id',
            'channel',
            'provider_key',
            'attempt_number',
            'status',
            'recipient',
            'provider_message_id',
            'provider_status',
            'error_code',
            'attempted_at',
            'completed_at',
            'created_at',
        ],
        'customer_privacy_requests' => [
            'customer_privacy_request_id',
            'user_id',
            'request_type',
            'status',
            'requested_via',
            'requested_by_user_id',
            'reason',
            'decision',
            'reviewed_by_user_id',
            'requested_at',
            'reviewed_at',
            'completed_at',
            'created_at',
            'updated_at',
        ],
    ];

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
                'user' => $this->sanitizeFirstRow(
                    DB::table('users')->where('user_id', $userId)->get(),
                    self::PROFILE_FIELD_WHITELISTS['user'],
                ),
                'role' => $this->sanitizeFirstRow(
                    DB::table('roles')->where('role_id', $user->role_id)->get(),
                    self::PROFILE_FIELD_WHITELISTS['role'],
                ),
                'current_tier' => $user->current_tier_id !== null
                    ? $this->sanitizeFirstRow(
                        DB::table('loyalty_tiers')->where('tier_id', $user->current_tier_id)->get(),
                        self::PROFILE_FIELD_WHITELISTS['current_tier'],
                    )
                    : null,
                'points' => Schema::hasTable('user_points')
                    ? $this->sanitizeFirstRow(
                        DB::table('user_points')->where('user_id', $userId)->get(),
                        self::PROFILE_FIELD_WHITELISTS['points'],
                    )
                    : null,
            ],
            'tables' => [
                'customer_access_sessions' => $this->sanitizeTableRows(
                    'customer_access_sessions',
                    Schema::hasTable('customer_access_sessions')
                        ? DB::table('customer_access_sessions')->where('user_id', $userId)->orderByDesc('access_session_id')->get()
                        : collect(),
                ),
                'bank_accounts' => $this->sanitizeTableRows(
                    'bank_accounts',
                    Schema::hasTable('bank_accounts')
                        ? DB::table('bank_accounts')->where('user_id', $userId)->orderByDesc('bank_account_id')->get()
                        : collect(),
                ),
                'user_auth_tokens' => $this->sanitizeTableRows(
                    'user_auth_tokens',
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
                        : collect(),
                ),
                'notification_preferences' => $this->sanitizeTableRows(
                    'notification_preferences',
                    Schema::hasTable('notification_preferences')
                        ? DB::table('notification_preferences')->where('user_id', $userId)->orderByDesc('notification_preference_id')->get()
                        : collect(),
                ),
                'reservations' => $this->sanitizeTableRows(
                    'reservations',
                    Schema::hasTable('reservations')
                        ? DB::table('reservations')->where('user_id', $userId)->orderByDesc('reservation_id')->get()
                        : collect(),
                ),
                'reservation_tables' => $this->sanitizeTableRows(
                    'reservation_tables',
                    ($reservationIds !== [] && Schema::hasTable('reservation_tables'))
                        ? DB::table('reservation_tables')->whereIn('reservation_id', $reservationIds)->orderByDesc('reservation_table_id')->get()
                        : collect(),
                ),
                'reservation_orders' => $this->sanitizeTableRows(
                    'reservation_orders',
                    ($reservationIds !== [] && Schema::hasTable('reservation_orders'))
                        ? DB::table('reservation_orders')->whereIn('reservation_id', $reservationIds)->orderByDesc('order_id')->get()
                        : collect(),
                ),
                'reservation_order_items' => $this->sanitizeTableRows(
                    'reservation_order_items',
                    ($reservationIds !== [] && Schema::hasTable('reservation_order_items'))
                        ? DB::table('reservation_order_items')
                            ->whereIn('order_id', function ($q) use ($reservationIds): void {
                                $q->from('reservation_orders')->select('order_id')->whereIn('reservation_id', $reservationIds);
                            })
                            ->orderByDesc('order_item_id')
                            ->get()
                        : collect(),
                ),
                'payments' => $this->sanitizeTableRows(
                    'payments',
                    ($reservationIds !== [] && Schema::hasTable('payments'))
                        ? DB::table('payments')->whereIn('reservation_id', $reservationIds)->orderByDesc('payment_id')->get()
                        : collect(),
                ),
                'billing_invoices' => $this->sanitizeTableRows(
                    'billing_invoices',
                    ($reservationIds !== [] && Schema::hasTable('billing_invoices'))
                        ? DB::table('billing_invoices')->whereIn('reservation_id', $reservationIds)->orderByDesc('billing_invoice_id')->get()
                        : collect(),
                ),
                'reservation_deposit_payment_sessions' => $this->sanitizeTableRows(
                    'reservation_deposit_payment_sessions',
                    Schema::hasTable('reservation_deposit_payment_sessions')
                        ? DB::table('reservation_deposit_payment_sessions')->where('customer_user_id', $userId)->orderByDesc('deposit_payment_session_id')->get()
                        : collect(),
                ),
                'reservation_bill_payment_sessions' => $this->sanitizeTableRows(
                    'reservation_bill_payment_sessions',
                    Schema::hasTable('reservation_bill_payment_sessions')
                        ? DB::table('reservation_bill_payment_sessions')->where('customer_user_id', $userId)->orderByDesc('bill_payment_session_id')->get()
                        : collect(),
                ),
                'user_vouchers' => $this->sanitizeTableRows(
                    'user_vouchers',
                    Schema::hasTable('user_vouchers')
                        ? DB::table('user_vouchers')->where('user_id', $userId)->orderByDesc('user_voucher_id')->get()
                        : collect(),
                ),
                'loyalty_point_transactions' => $this->sanitizeTableRows(
                    'loyalty_point_transactions',
                    Schema::hasTable('loyalty_point_transactions')
                        ? DB::table('loyalty_point_transactions')->where('user_id', $userId)->orderByDesc('txn_id')->get()
                        : collect(),
                ),
                'user_tier_history' => $this->sanitizeTableRows(
                    'user_tier_history',
                    Schema::hasTable('user_tier_history')
                        ? DB::table('user_tier_history')->where('user_id', $userId)->orderByDesc('history_id')->get()
                        : collect(),
                ),
                'waiting_list' => $this->sanitizeTableRows(
                    'waiting_list',
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
                        : collect(),
                ),
                'conversations' => $this->sanitizeTableRows(
                    'conversations',
                    ($conversationIds !== [] && Schema::hasTable('conversations'))
                        ? DB::table('conversations')->whereIn('conversation_id', $conversationIds)->orderByDesc('created_at')->get()
                        : collect(),
                ),
                'conversation_messages' => $this->sanitizeTableRows(
                    'conversation_messages',
                    ($messageIds !== [] && Schema::hasTable('conversation_messages'))
                        ? DB::table('conversation_messages')->whereIn('message_id', $messageIds)->orderByDesc('message_id')->get()
                        : collect(),
                ),
                'conversation_files' => $this->sanitizeTableRows(
                    'conversation_files',
                    ($messageIds !== [] && Schema::hasTable('conversation_files'))
                        ? DB::table('conversation_files')->whereIn('message_id', $messageIds)->orderByDesc('file_id')->get()
                        : collect(),
                ),
                'message_entities' => $this->sanitizeTableRows(
                    'message_entities',
                    ($messageIds !== [] && Schema::hasTable('message_entities'))
                        ? DB::table('message_entities')->whereIn('message_id', $messageIds)->orderByDesc('message_entity_id')->get()
                        : collect(),
                ),
                'conversation_events' => $this->sanitizeTableRows(
                    'conversation_events',
                    ($conversationIds !== [] && Schema::hasTable('conversation_events'))
                        ? DB::table('conversation_events')->whereIn('conversation_id', $conversationIds)->orderByDesc('event_id')->get()
                        : collect(),
                ),
                'conversation_analyses' => $this->sanitizeTableRows(
                    'conversation_analyses',
                    ($conversationIds !== [] && Schema::hasTable('conversation_analyses'))
                        ? DB::table('conversation_analyses')->whereIn('conversation_id', $conversationIds)->orderByDesc('analysis_id')->get()
                        : collect(),
                ),
                'notification_outbox' => $this->sanitizeTableRows(
                    'notification_outbox',
                    ($outboxIds !== [] && Schema::hasTable('notification_outbox'))
                        ? DB::table('notification_outbox')->whereIn('outbox_id', $outboxIds)->orderByDesc('outbox_id')->get()
                        : collect(),
                ),
                'notification_delivery_attempts' => $this->sanitizeTableRows(
                    'notification_delivery_attempts',
                    ($outboxIds !== [] && Schema::hasTable('notification_delivery_attempts'))
                        ? DB::table('notification_delivery_attempts')->whereIn('outbox_id', $outboxIds)->orderByDesc('attempt_id')->get()
                        : collect(),
                ),
                'customer_privacy_requests' => $this->sanitizeTableRows(
                    'customer_privacy_requests',
                    Schema::hasTable('customer_privacy_requests')
                        ? DB::table('customer_privacy_requests')->where('user_id', $userId)->orderByDesc('customer_privacy_request_id')->get()
                        : collect(),
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
            throw new InvalidArgumentException('Target user is not a customer account.');
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
    private function sanitizeTableRows(string $table, $rows): array
    {
        return $this->sanitizeRows($rows, self::TABLE_FIELD_WHITELISTS[$table] ?? []);
    }

    /**
     * @param \Illuminate\Support\Collection<int,object>|\Illuminate\Support\Collection<int,mixed> $rows
     * @param  list<string>  $allowedFields
     * @return list<array<string,mixed>>
     */
    private function sanitizeRows($rows, array $allowedFields): array
    {
        return collect($rows)
            ->map(fn ($row): array => $this->sanitizeRow($row, $allowedFields))
            ->values()
            ->all();
    }

    /**
     * @param \Illuminate\Support\Collection<int,object>|\Illuminate\Support\Collection<int,mixed> $rows
     * @param  list<string>  $allowedFields
     * @return array<string,mixed>|null
     */
    private function sanitizeFirstRow($rows, array $allowedFields): ?array
    {
        return $this->sanitizeRows($rows, $allowedFields)[0] ?? null;
    }

    /**
     * @param  list<string>  $allowedFields
     * @return array<string,mixed>
     */
    private function sanitizeRow(mixed $row, array $allowedFields): array
    {
        $source = (array) $row;
        $payload = [];

        foreach ($allowedFields as $field) {
            if (! array_key_exists($field, $source)) {
                continue;
            }

            $payload[$field] = $this->normalizeValue($source[$field]);
        }

        return $payload;
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

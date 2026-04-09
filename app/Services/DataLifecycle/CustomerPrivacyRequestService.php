<?php

declare(strict_types=1);

namespace App\Services\DataLifecycle;

use App\Models\CustomerPrivacyRequest;
use App\Models\User;
use App\Support\AuditEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerPrivacyRequestService
{
    public function __construct(
        private readonly CustomerAnonymizationService $anonymizationService,
    ) {}

    /**
     * @param array<string,mixed> $filters
     */
    public function listForCustomer(int $userId, array $filters = []): LengthAwarePaginator
    {
        return $this->baseListQuery($filters)
            ->where('user_id', $userId)
            ->paginate($this->perPage($filters));
    }

    /**
     * @param array<string,mixed> $filters
     */
    public function listForAdmin(array $filters = []): LengthAwarePaginator
    {
        return $this->baseListQuery($filters)
            ->when(isset($filters['user_id']) && (int) $filters['user_id'] > 0, fn ($q) => $q->where('user_id', (int) $filters['user_id']))
            ->paginate($this->perPage($filters));
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{request:CustomerPrivacyRequest,created:bool}
     */
    public function submitAnonymizationRequest(int $userId, array $payload = []): array
    {
        /** @var User $user */
        $user = User::query()->with('role')->findOrFail($userId);
        $this->assertCustomerUser($user);

        if ($user->privacy_anonymized_at !== null || (bool) $user->is_deleted) {
            throw ValidationException::withMessages(['user_id' => ['Customer account has already been anonymized.']]);
        }

        /** @var CustomerPrivacyRequest|null $existing */
        $existing = CustomerPrivacyRequest::query()
            ->where('user_id', $userId)
            ->where('request_type', CustomerPrivacyRequest::TYPE_ANONYMIZE)
            ->where('status', CustomerPrivacyRequest::STATUS_REQUESTED)
            ->latest('customer_privacy_request_id')
            ->first();

        if ($existing instanceof CustomerPrivacyRequest) {
            return ['request' => $existing->loadMissing(['user.role', 'requestedByUser.role', 'reviewedByUser.role']), 'created' => false];
        }

        /** @var CustomerPrivacyRequest $request */
        $request = CustomerPrivacyRequest::query()->create([
            'user_id' => $userId,
            'request_type' => CustomerPrivacyRequest::TYPE_ANONYMIZE,
            'status' => CustomerPrivacyRequest::STATUS_REQUESTED,
            'requested_by_actor_type' => $this->actorType($payload['actor_type'] ?? null),
            'requested_by_user_id' => isset($payload['requested_by_user_id']) && (int) $payload['requested_by_user_id'] > 0 ? (int) $payload['requested_by_user_id'] : null,
            'requested_via' => trim((string) ($payload['requested_via'] ?? 'self_service')) ?: 'self_service',
            'reason' => $this->nullableString($payload['reason'] ?? null),
        ]);

        AuditEvent::info('customer_privacy_request_created', [
            '_audit' => [
                'action' => 'customer_privacy_request.created',
                'primary_subject' => ['type' => 'customer_privacy_request', 'id' => (string) $request->customer_privacy_request_id, 'role' => 'primary'],
                'subjects' => [['type' => 'user', 'id' => (string) $userId, 'role' => 'customer']],
                'summary' => ['request_type' => CustomerPrivacyRequest::TYPE_ANONYMIZE, 'status' => CustomerPrivacyRequest::STATUS_REQUESTED, 'requested_via' => $request->requested_via],
                'meta' => ['reason_present' => $request->reason !== null],
            ],
        ]);

        return ['request' => $request->loadMissing(['user.role', 'requestedByUser.role', 'reviewedByUser.role']), 'created' => true];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,data:array<string,mixed>}
     */
    public function reviewRequest(int $requestId, array $payload, int $actorUserId): array
    {
        /** @var CustomerPrivacyRequest $request */
        $request = CustomerPrivacyRequest::query()->with('user.role')->findOrFail($requestId);
        $this->assertCustomerUser($request->user);

        $decision = strtolower(trim((string) ($payload['decision'] ?? '')));
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'commit')));
        $notes = $this->nullableString($payload['notes'] ?? null);

        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages(['decision' => ['Unsupported review decision.']]);
        }

        if (! in_array($mode, ['dry_run', 'commit'], true)) {
            throw ValidationException::withMessages(['mode' => ['Unsupported review mode.']]);
        }

        if ($request->status !== CustomerPrivacyRequest::STATUS_REQUESTED) {
            throw ValidationException::withMessages(['customer_privacy_request_id' => ['Only requested privacy requests can be reviewed.']]);
        }

        if ($decision === 'reject') {
            if ($mode !== 'commit') {
                throw ValidationException::withMessages(['mode' => ['Reject flow only supports commit mode.']]);
            }

            $request->forceFill([
                'status' => CustomerPrivacyRequest::STATUS_REJECTED,
                'reviewed_by' => $actorUserId,
                'reviewed_at' => now('UTC'),
                'processed_at' => now('UTC'),
                'resolution_notes' => $notes,
                'result_summary_json' => ['decision' => 'reject'],
            ])->save();

            AuditEvent::warning('customer_privacy_request_rejected', [
                '_audit' => [
                    'action' => 'customer_privacy_request.rejected',
                    'primary_subject' => ['type' => 'customer_privacy_request', 'id' => (string) $request->customer_privacy_request_id, 'role' => 'primary'],
                    'subjects' => [['type' => 'user', 'id' => (string) $request->user_id, 'role' => 'customer']],
                    'summary' => ['status' => CustomerPrivacyRequest::STATUS_REJECTED],
                    'meta' => ['notes_present' => $notes !== null],
                ],
            ]);

            return ['status' => 200, 'data' => ['request' => $this->serializeRequest($request->fresh(['user.role', 'requestedByUser.role', 'reviewedByUser.role'])), 'decision' => 'reject']];
        }

        $preview = $this->anonymizationService->preview((int) $request->user_id);
        if ($mode === 'dry_run') {
            return ['status' => 200, 'data' => ['request' => $this->serializeRequest($request), 'decision' => 'approve', 'mode' => 'dry_run', 'preview' => $preview, 'can_commit' => (bool) ($preview['can_commit'] ?? false)]];
        }

        if (! ($preview['can_commit'] ?? false)) {
            return ['status' => 422, 'data' => ['request' => $this->serializeRequest($request), 'decision' => 'approve', 'mode' => 'commit', 'preview' => $preview, 'can_commit' => false]];
        }

        $summary = DB::transaction(function () use ($request, $actorUserId, $notes): array {
            $summary = $this->anonymizationService->apply((int) $request->user_id);
            $request->forceFill([
                'status' => CustomerPrivacyRequest::STATUS_COMPLETED,
                'reviewed_by' => $actorUserId,
                'reviewed_at' => now('UTC'),
                'processed_at' => now('UTC'),
                'resolution_notes' => $notes,
                'result_summary_json' => $summary,
            ])->save();

            return $summary;
        });

        AuditEvent::info('customer_privacy_request_completed', [
            '_audit' => [
                'action' => 'customer_privacy_request.completed',
                'primary_subject' => ['type' => 'customer_privacy_request', 'id' => (string) $request->customer_privacy_request_id, 'role' => 'primary'],
                'subjects' => [['type' => 'user', 'id' => (string) $request->user_id, 'role' => 'customer']],
                'summary' => ['status' => CustomerPrivacyRequest::STATUS_COMPLETED, 'updated_rows' => $summary['updated'] ?? [], 'purged_rows' => $summary['purged'] ?? []],
                'meta' => ['notes_present' => $notes !== null],
            ],
        ]);

        return ['status' => 200, 'data' => ['request' => $this->serializeRequest($request->fresh(['user.role', 'requestedByUser.role', 'reviewedByUser.role'])), 'decision' => 'approve', 'mode' => 'commit', 'summary' => $summary]];
    }

    /**
     * @return array<string,mixed>
     */
    public function serializeRequest(?CustomerPrivacyRequest $request): array
    {
        if (! $request instanceof CustomerPrivacyRequest) {
            return [];
        }

        return [
            'customer_privacy_request_id' => (int) $request->customer_privacy_request_id,
            'user_id' => (int) $request->user_id,
            'request_type' => (string) $request->request_type,
            'status' => (string) $request->status,
            'requested_by_actor_type' => $request->requested_by_actor_type,
            'requested_by_user_id' => $request->requested_by_user_id !== null ? (int) $request->requested_by_user_id : null,
            'requested_via' => $request->requested_via,
            'reason' => $request->reason,
            'reviewed_by' => $request->reviewed_by !== null ? (int) $request->reviewed_by : null,
            'reviewed_at_utc' => $request->reviewed_at?->utc()->toIso8601String(),
            'processed_at_utc' => $request->processed_at?->utc()->toIso8601String(),
            'resolution_notes' => $request->resolution_notes,
            'result_summary_json' => $request->result_summary_json,
            'created_at_utc' => $request->created_at?->utc()->toIso8601String(),
            'updated_at_utc' => $request->updated_at?->utc()->toIso8601String(),
            'user' => $request->relationLoaded('user') && $request->user !== null ? [
                'user_id' => (int) $request->user->user_id,
                'full_name' => $request->user->full_name,
                'email' => $request->user->email,
                'phone' => $request->user->phone,
                'role_id' => $request->user->role_id !== null ? (int) $request->user->role_id : null,
                'role_name' => $request->user->role?->role_name,
                'is_deleted' => (bool) $request->user->is_deleted,
                'privacy_anonymized_at_utc' => $request->user->privacy_anonymized_at?->utc()->toIso8601String(),
            ] : null,
        ];
    }

    private function baseListQuery(array $filters)
    {
        return CustomerPrivacyRequest::query()
            ->with(['user.role', 'requestedByUser.role', 'reviewedByUser.role'])
            ->when(isset($filters['status']) && trim((string) $filters['status']) !== '', fn ($q) => $q->where('status', $this->status((string) $filters['status'])))
            ->orderByDesc('created_at')
            ->orderByDesc('customer_privacy_request_id');
    }

    private function assertCustomerUser(?User $user): void
    {
        if (! $user instanceof User) {
            throw ValidationException::withMessages(['user_id' => ['Customer account was not found.']]);
        }

        $allowedRoleIds = array_values(array_filter(array_map('intval', (array) config('customer_auth.allowed_role_ids', [3])), static fn (int $v): bool => $v > 0));
        if ($allowedRoleIds !== [] && ! in_array((int) ($user->role_id ?? 0), $allowedRoleIds, true)) {
            throw ValidationException::withMessages(['user_id' => ['Target user is not a customer account.']]);
        }
    }

    private function perPage(array $filters): int
    {
        return max(1, min(100, (int) ($filters['per_page'] ?? 20)));
    }

    private function actorType(mixed $value): string
    {
        return match (strtolower(trim((string) ($value ?? 'customer_account')))) {
            'customer_access_session' => 'customer_access_session',
            'staff_user' => 'staff_user',
            'system' => 'system',
            default => 'customer_account',
        };
    }

    private function status(string $value): string
    {
        return match (strtolower(trim($value))) {
            'requested' => CustomerPrivacyRequest::STATUS_REQUESTED,
            'rejected' => CustomerPrivacyRequest::STATUS_REJECTED,
            'completed' => CustomerPrivacyRequest::STATUS_COMPLETED,
            'failed' => CustomerPrivacyRequest::STATUS_FAILED,
            default => throw ValidationException::withMessages(['status' => ['Unsupported privacy request status filter.']]),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        return $normalized !== '' ? $normalized : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Platform\FeatureFlags\Services;

use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Platform\FeatureFlags\Domain\Models\FeatureFlag;
use App\Support\AuditEvent;
use App\Support\ValidationExceptionFactory;
use Illuminate\Support\Facades\DB;

class FeatureFlagManagementService
{
    public function __construct(
        private readonly FeatureFlagService $featureFlags,
        private readonly BranchContextService $branchContext,
    ) {}

    /**
     * @return list<array<string,mixed>>
     */
    public function listEffective(?string $environment = null, ?int $branchId = null, ?string $featureKey = null): array
    {
        $environment = $this->normalizeEnvironment($environment);
        $branchScopeId = $this->normalizeBranchScope($branchId);
        $keys = $featureKey !== null && trim($featureKey) !== ''
            ? [$this->normalizeFeatureKey($featureKey)]
            : $this->featureFlags->knownFeatureKeys();

        $rows = [];
        foreach ($keys as $key) {
            if (! $this->featureFlags->hasFeature($key)) {
                throw ValidationExceptionFactory::make([
                    'feature' => ['Selected feature flag is not registered.'],
                ]);
            }

            $rows[] = $this->featureFlags->resolve(
                $key,
                $branchScopeId > 0 ? $branchScopeId : null,
                $environment,
            );
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    public function upsertOverride(
        string $featureKey,
        bool $enabled,
        ?string $environment = null,
        ?int $branchId = null,
        ?string $reason = null,
        ?int $actorUserId = null,
        string $actorType = 'console',
        ?string $actorKey = null,
    ): array {
        $featureKey = $this->normalizeFeatureKey($featureKey);
        $environment = $this->normalizeEnvironment($environment);
        $branchScopeId = $this->normalizeBranchScope($branchId);
        $reason = $this->normalizeNullableString($reason);

        $this->assertKnownFeature($featureKey);

        $result = DB::transaction(function () use ($featureKey, $enabled, $environment, $branchScopeId, $reason, $actorUserId, $actorType, $actorKey): array {
            $existing = FeatureFlag::query()
                ->where('feature_key', $featureKey)
                ->where('environment', $environment)
                ->where('branch_id', $branchScopeId)
                ->lockForUpdate()
                ->first();

            $before = $this->presentOverride($existing);
            $action = 'created';

            if (! $existing instanceof FeatureFlag) {
                $existing = new FeatureFlag;
                $existing->feature_key = $featureKey;
                $existing->environment = $environment;
                $existing->branch_id = $branchScopeId;
            } else {
                $action = 'updated';
            }

            $existing->enabled = $enabled;
            $existing->reason = $reason;
            $existing->updated_by = $actorUserId;

            if (! $existing->exists || $existing->isDirty()) {
                $existing->save();

                $this->recordAudit(
                    eventName: 'feature_flag.updated',
                    action: $action,
                    featureKey: $featureKey,
                    environment: $environment,
                    branchId: $branchScopeId,
                    actorUserId: $actorUserId,
                    actorType: $actorType,
                    actorKey: $actorKey,
                    before: $before,
                    after: $this->presentOverride($existing),
                    summary: [
                        'enabled' => $enabled,
                        'reason' => $reason,
                    ],
                );
            } else {
                $action = 'unchanged';
            }

            return [
                'action' => $action,
                'feature' => $this->featureFlags->resolve($featureKey, $branchScopeId > 0 ? $branchScopeId : null, $environment),
                'override' => $this->presentOverride($existing),
            ];
        }, 3);

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function clearOverride(
        string $featureKey,
        ?string $environment = null,
        ?int $branchId = null,
        ?string $reason = null,
        ?int $actorUserId = null,
        string $actorType = 'console',
        ?string $actorKey = null,
    ): array {
        $featureKey = $this->normalizeFeatureKey($featureKey);
        $environment = $this->normalizeEnvironment($environment);
        $branchScopeId = $this->normalizeBranchScope($branchId);
        $reason = $this->normalizeNullableString($reason);

        $this->assertKnownFeature($featureKey);

        $result = DB::transaction(function () use ($featureKey, $environment, $branchScopeId, $reason, $actorUserId, $actorType, $actorKey): array {
            $existing = FeatureFlag::query()
                ->where('feature_key', $featureKey)
                ->where('environment', $environment)
                ->where('branch_id', $branchScopeId)
                ->lockForUpdate()
                ->first();

            $before = $this->presentOverride($existing);
            $hadOverride = $existing instanceof FeatureFlag;
            if ($existing instanceof FeatureFlag) {
                $existing->delete();

                $this->recordAudit(
                    eventName: 'feature_flag.cleared',
                    action: 'cleared',
                    featureKey: $featureKey,
                    environment: $environment,
                    branchId: $branchScopeId,
                    actorUserId: $actorUserId,
                    actorType: $actorType,
                    actorKey: $actorKey,
                    before: $before,
                    after: null,
                    summary: [
                        'reason' => $reason,
                    ],
                );
            }

            return [
                'action' => $hadOverride ? 'cleared' : 'noop',
                'had_override' => $hadOverride,
                'feature' => $this->featureFlags->resolve($featureKey, $branchScopeId > 0 ? $branchScopeId : null, $environment),
                'override' => null,
            ];
        }, 3);

        return $result;
    }

    private function assertKnownFeature(string $featureKey): void
    {
        if ($this->featureFlags->hasFeature($featureKey)) {
            return;
        }

        throw ValidationExceptionFactory::make([
            'feature' => ['Selected feature flag is not registered.'],
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function presentOverride(?FeatureFlag $override): ?array
    {
        if (! $override instanceof FeatureFlag) {
            return null;
        }

        return [
            'feature_flag_id' => (int) $override->feature_flag_id,
            'feature_key' => (string) $override->feature_key,
            'environment' => (string) $override->environment,
            'branch_id' => (int) $override->branch_id,
            'enabled' => (bool) $override->enabled,
            'reason' => $this->normalizeNullableString($override->reason),
            'updated_by' => $override->updated_by !== null ? (int) $override->updated_by : null,
            'row_version' => $override->row_version !== null ? (int) $override->row_version : null,
            'updated_at' => $override->updated_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string,mixed>|null  $before
     * @param  array<string,mixed>|null  $after
     * @param  array<string,mixed>|null  $summary
     */
    private function recordAudit(
        string $eventName,
        string $action,
        string $featureKey,
        string $environment,
        int $branchId,
        ?int $actorUserId,
        string $actorType,
        ?string $actorKey,
        ?array $before,
        ?array $after,
        ?array $summary,
    ): void {
        $entityId = $this->featureEntityId($featureKey, $environment, $branchId);
        $subjects = [];

        if ($branchId > 0) {
            $subjects[] = [
                'type' => 'branch',
                'id' => (string) $branchId,
                'role' => 'branch',
            ];
        }

        AuditEvent::info($eventName, [
            'feature_key' => $featureKey,
            'environment' => $environment,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'action_name' => $action,
            '_audit' => [
                'action' => $eventName,
                'entity_type' => 'feature_flag',
                'entity_id' => $entityId,
                'subjects' => $subjects,
                'before' => $before,
                'after' => $after,
                'summary' => array_merge([
                    'feature_key' => $featureKey,
                    'environment' => $environment,
                    'branch_id' => $branchId > 0 ? $branchId : null,
                    'action_name' => $action,
                ], $summary ?? []),
                'actor' => array_filter([
                    'type' => trim($actorType) !== '' ? $actorType : 'console',
                    'user_id' => $actorUserId,
                    'key' => $this->normalizeNullableString($actorKey),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ],
        ]);
    }

    private function featureEntityId(string $featureKey, string $environment, int $branchId): string
    {
        return implode('|', [
            $featureKey,
            $environment,
            $branchId > 0 ? (string) $branchId : 'global',
        ]);
    }

    private function normalizeFeatureKey(string $featureKey): string
    {
        return strtolower(trim($featureKey));
    }

    private function normalizeEnvironment(?string $environment): string
    {
        $wildcard = trim((string) config('feature_flags.wildcard_environment', '*')) ?: '*';
        $normalized = strtolower(trim((string) ($environment ?? $wildcard)));

        return $normalized !== '' ? $normalized : $wildcard;
    }

    private function normalizeBranchScope(?int $branchId): int
    {
        if ($branchId === null || $branchId <= 0) {
            return max(0, (int) config('feature_flags.global_branch_id', 0));
        }

        return $this->branchContext->resolveBranchId($branchId, false);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}

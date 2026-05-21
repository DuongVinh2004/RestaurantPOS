<?php

declare(strict_types=1);

namespace App\Platform\FeatureFlags\Services;

use App\Platform\FeatureFlags\Domain\Models\FeatureFlag;
use App\Support\ValidationExceptionFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FeatureFlagService
{
    private ?bool $tableAvailable = null;

    /**
     * Per-instance resolve() memoization cache, keyed by "featureKey:environment:branchId".
     *
     * This prevents repeated DB queries when the same feature flag is resolved multiple
     * times within a single request (e.g. inside hydrateCollection loops).
     *
     * @var array<string,array<string,mixed>>
     */
    private array $resolvedCache = [];

    /**
     * @return array<string,array<string,mixed>>
     */
    public function registry(): array
    {
        $registry = (array) config('feature_flags.features', []);

        /** @var array<string,array<string,mixed>> $registry */
        return $registry;
    }

    /**
     * @return list<string>
     */
    public function knownFeatureKeys(): array
    {
        $keys = array_keys($this->registry());
        sort($keys);

        return array_values($keys);
    }

    public function hasFeature(string $featureKey): bool
    {
        return array_key_exists($this->normalizeFeatureKey($featureKey), $this->registry());
    }

    /**
     * @return array<string,mixed>
     */
    public function resolve(string $featureKey, ?int $branchId = null, ?string $environment = null): array
    {
        $featureKey = $this->normalizeFeatureKey($featureKey);
        $environment = $this->normalizeEnvironment($environment);
        $requestedBranchId = $this->normalizeBranchId($branchId);

        // Per-instance cache: avoid repeated DB queries for the same (key, env, branch)
        // within a single request lifecycle (e.g. inside collection-iteration loops).
        $cacheKey = $featureKey.':'.$environment.':'.$requestedBranchId;
        if (array_key_exists($cacheKey, $this->resolvedCache)) {
            return $this->resolvedCache[$cacheKey];
        }

        $feature = $this->registry()[$featureKey] ?? null;

        $base = [
            'feature_key' => $featureKey,
            'environment' => $environment,
            'branch_id' => $requestedBranchId > 0 ? $requestedBranchId : null,
            'description' => (string) ($feature['description'] ?? ''),
            'kill_switch' => (bool) ($feature['kill_switch'] ?? false),
            'safe_default' => (bool) ($feature['safe_default'] ?? false),
            'disabled_message' => (string) ($feature['disabled_message'] ?? 'Feature is disabled for this rollout.'),
        ];

        if (! is_array($feature)) {
            return $this->resolvedCache[$cacheKey] = array_merge($base, [
                'enabled' => false,
                'source' => 'unknown_feature',
                'matched_environment' => null,
                'matched_branch_id' => null,
                'default_enabled' => false,
                'override_reason' => null,
                'updated_at' => null,
                'updated_by' => null,
                'row_version' => null,
                'message' => 'Feature is disabled because it is not registered.',
            ]);
        }

        // Load all overrides for this feature in one DB query (called once, not per candidate).
        $overrides = $this->loadOverridesForFeature($featureKey, $environment);

        foreach ($this->resolutionCandidates($environment, $requestedBranchId) as [$candidateEnvironment, $candidateBranchId]) {
            $override = $overrides[$candidateEnvironment][$candidateBranchId] ?? null;
            if (! $override instanceof FeatureFlag) {
                continue;
            }

            return $this->resolvedCache[$cacheKey] = array_merge($base, [
                'enabled' => (bool) $override->enabled,
                'source' => 'database_override',
                'matched_environment' => $candidateEnvironment,
                'matched_branch_id' => $candidateBranchId > 0 ? $candidateBranchId : null,
                'default_enabled' => $this->resolveDefaultEnabled($feature, $environment)['enabled'],
                'override_reason' => $this->normalizeNullableString($override->reason),
                'updated_at' => $override->updated_at instanceof Carbon ? $override->updated_at->utc()->toIso8601String() : null,
                'updated_by' => $override->updated_by !== null ? (int) $override->updated_by : null,
                'row_version' => $override->row_version !== null ? (int) $override->row_version : null,
                'message' => (bool) $override->enabled ? 'Feature flag is enabled.' : $base['disabled_message'],
            ]);
        }

        $default = $this->resolveDefaultEnabled($feature, $environment);

        return $this->resolvedCache[$cacheKey] = array_merge($base, [
            'enabled' => $default['enabled'],
            'source' => 'config_default',
            'matched_environment' => $default['matched_environment'],
            'matched_branch_id' => null,
            'default_enabled' => $default['enabled'],
            'override_reason' => null,
            'updated_at' => null,
            'updated_by' => null,
            'row_version' => null,
            'message' => $default['enabled'] ? 'Feature flag is enabled by config default.' : $base['disabled_message'],
        ]);
    }

    /**
     * Evict a cached resolve() result so the next call re-queries the DB.
     * Call this after admin-side feature flag updates to ensure the new value
     * is visible within the same request.
     */
    public function forgetResolved(string $featureKey, ?int $branchId = null, ?string $environment = null): void
    {
        $featureKey = $this->normalizeFeatureKey($featureKey);
        $environment = $this->normalizeEnvironment($environment);
        $requestedBranchId = $this->normalizeBranchId($branchId);
        $cacheKey = $featureKey.':'.$environment.':'.$requestedBranchId;
        unset($this->resolvedCache[$cacheKey]);
    }

    /**
     * Evict all cached resolve() results.
     * Use after bulk admin mutations or in tests that change flag state mid-request.
     */
    public function forgetAllResolved(): void
    {
        $this->resolvedCache = [];
    }

    public function enabled(string $featureKey, ?int $branchId = null, ?string $environment = null): bool
    {
        return (bool) ($this->resolve($featureKey, $branchId, $environment)['enabled'] ?? false);
    }

    public function assertEnabled(
        string $featureKey,
        ?int $branchId = null,
        ?string $environment = null,
        string $field = 'feature_flag',
        ?string $message = null,
    ): void {
        $resolution = $this->resolve($featureKey, $branchId, $environment);
        if ((bool) ($resolution['enabled'] ?? false)) {
            return;
        }

        throw ValidationExceptionFactory::make([
            $field => [$message ?? (string) ($resolution['message'] ?? 'Feature is disabled for this rollout.')],
        ]);
    }

    /**
     * @return array{enabled:bool,matched_environment:string}
     */
    private function resolveDefaultEnabled(array $feature, string $environment): array
    {
        $defaults = is_array($feature['defaults'] ?? null) ? $feature['defaults'] : [];
        $defaults = array_change_key_case($defaults, CASE_LOWER);
        $wildcard = $this->wildcardEnvironment();

        if (array_key_exists($environment, $defaults)) {
            return [
                'enabled' => (bool) $defaults[$environment],
                'matched_environment' => $environment,
            ];
        }

        return [
            'enabled' => (bool) ($defaults[$wildcard] ?? false),
            'matched_environment' => array_key_exists($wildcard, $defaults) ? $wildcard : $environment,
        ];
    }

    /**
     * @return list<array{0:string,1:int}>
     */
    private function resolutionCandidates(string $environment, int $branchId): array
    {
        $globalBranchId = $this->globalBranchId();
        $wildcard = $this->wildcardEnvironment();

        if ($branchId <= 0) {
            return [
                [$environment, $globalBranchId],
                [$wildcard, $globalBranchId],
            ];
        }

        return [
            [$environment, $branchId],
            [$environment, $globalBranchId],
            [$wildcard, $branchId],
            [$wildcard, $globalBranchId],
        ];
    }

    /**
     * @return array<string,array<int,FeatureFlag>>
     */
    private function loadOverridesForFeature(string $featureKey, string $environment): array
    {
        if (! $this->featureFlagTableAvailable()) {
            return [];
        }

        try {
            $rows = FeatureFlag::query()
                ->where('feature_key', $featureKey)
                ->whereIn('environment', [$environment, $this->wildcardEnvironment()])
                ->orderBy('feature_flag_id')
                ->get();
        } catch (Throwable) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $candidateEnvironment = $this->normalizeEnvironment((string) $row->environment);
            $candidateBranchId = $this->normalizeBranchId((int) $row->branch_id);
            $grouped[$candidateEnvironment][$candidateBranchId] = $row;
        }

        return $grouped;
    }

    private function featureFlagTableAvailable(): bool
    {
        if ($this->tableAvailable !== null) {
            return $this->tableAvailable;
        }

        try {
            return $this->tableAvailable = Schema::hasTable('feature_flags');
        } catch (Throwable) {
            return $this->tableAvailable = false;
        }
    }

    private function normalizeFeatureKey(string $featureKey): string
    {
        return strtolower(trim($featureKey));
    }

    private function normalizeEnvironment(?string $environment): string
    {
        $normalized = strtolower(trim((string) ($environment ?? config('app.env', 'production'))));

        return $normalized !== '' ? $normalized : 'production';
    }

    private function normalizeBranchId(?int $branchId): int
    {
        return $branchId !== null && $branchId > 0
            ? $branchId
            : $this->globalBranchId();
    }

    private function wildcardEnvironment(): string
    {
        return trim((string) config('feature_flags.wildcard_environment', '*')) ?: '*';
    }

    private function globalBranchId(): int
    {
        return max(0, (int) config('feature_flags.global_branch_id', 0));
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

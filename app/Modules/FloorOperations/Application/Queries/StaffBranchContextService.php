<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\Queries;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\IdentityAccess\Application\Queries\StaffCapabilityResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class StaffBranchContextService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
        private readonly StaffCapabilityResolver $staffCapabilityResolver,
    ) {}

    /**
     * @return list<int>
     */
    public function accessibleBranchIds(?int $staffActorUserId = null): array
    {
        $this->branchContextService->ensureDefaultBranchExists();

        return $this->resolveConfiguredBranchScope(
            $this->configuredBranchScopeTokens($staffActorUserId),
        );
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(?int $staffActorUserId = null): Collection
    {
        $branchIds = $this->accessibleBranchIds($staffActorUserId);
        if ($branchIds === []) {
            return new Collection;
        }

        return Branch::query()
            ->where('is_active', true)
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('is_default')
            ->orderBy('branch_name')
            ->orderBy('branch_id')
            ->get();
    }

    /**
     * @return array{
     *   accessible_branch_ids:list<int>,
     *   default_branch_id:int|null,
     *   current_branch_id:int|null,
     *   has_default_branch_access:bool,
     *   has_multi_branch_access:bool,
     *   branch_selector_enabled:bool,
     *   access_source:string,
     *   branches_uri:string
     * }
     */
    public function branchAccessContext(?int $staffActorUserId = null): array
    {
        $branchIds = $this->accessibleBranchIds($staffActorUserId);
        $defaultBranchId = null;

        try {
            $defaultBranchId = (int) $this->branchContextService->defaultBranch()->branch_id;
        } catch (ModelNotFoundException) {
            $defaultBranchId = null;
        }

        $hasDefaultBranchAccess = $defaultBranchId !== null && in_array($defaultBranchId, $branchIds, true);
        $currentBranchId = $hasDefaultBranchAccess ? $defaultBranchId : ($branchIds[0] ?? null);

        return [
            'accessible_branch_ids' => $branchIds,
            'default_branch_id' => $defaultBranchId,
            'current_branch_id' => $currentBranchId,
            'has_default_branch_access' => $hasDefaultBranchAccess,
            'has_multi_branch_access' => count($branchIds) > 1,
            'branch_selector_enabled' => count($branchIds) > 1,
            'access_source' => $this->branchAccessSource($staffActorUserId),
            'branches_uri' => '/api/v1/staff/branches',
        ];
    }

    /**
     * @return list<int>
     */
    public function branchScopeOrAccessible(?int $staffActorUserId = null, ?int $requestedBranchId = null): array
    {
        if ($requestedBranchId !== null && $requestedBranchId > 0) {
            return [$this->assertAccessibleBranch($staffActorUserId, $requestedBranchId)];
        }

        return $this->accessibleBranchIds($staffActorUserId);
    }

    public function assertAccessibleBranch(?int $staffActorUserId = null, ?int $branchId = null): int
    {
        $branchIds = $this->accessibleBranchIds($staffActorUserId);
        if ($branchId !== null && $branchId > 0 && in_array($branchId, $branchIds, true)) {
            return $branchId;
        }

        throw (new ModelNotFoundException)->setModel(Branch::class, $branchId !== null ? [$branchId] : []);
    }

    public function assertCashierShiftBranchEligible(?int $staffActorUserId = null, mixed $branchId = null): int
    {
        $resolvedBranchId = $this->branchContextService->resolveBranchId($branchId);
        if (in_array($resolvedBranchId, $this->accessibleBranchIds($staffActorUserId), true)) {
            return $resolvedBranchId;
        }

        throw ValidationException::withMessages([
            'branch_id' => [
                (string) config(
                    'staff_capabilities.messages.branch_scope_denied',
                    'Resolved staff actor is not allowed to operate on the selected branch.',
                ),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function configuredBranchScopeTokens(?int $staffActorUserId = null): array
    {
        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return $this->fallbackBranchScopeTokens();
        }

        /** @var User|null $user */
        $user = User::query()->with('role')->find($staffActorUserId);
        if (! $user instanceof User) {
            return $this->fallbackBranchScopeTokens();
        }

        $roleId = (int) ($user->role_id ?? 0);
        $roleName = (string) ($user->role?->role_name ?? '');

        $explicitScopes = $this->roleScopedBranchTokens($roleId, $roleName);
        if ($explicitScopes !== []) {
            return $explicitScopes;
        }

        $capabilities = (array) ($this->staffCapabilityResolver->resolveForActor($roleId, $roleName)['capabilities'] ?? []);
        if (in_array('*', $capabilities, true)) {
            return ['*'];
        }

        return $this->fallbackBranchScopeTokens();
    }

    private function branchAccessSource(?int $staffActorUserId = null): string
    {
        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return 'fallback_branch_scopes';
        }

        /** @var User|null $user */
        $user = User::query()->with('role')->find($staffActorUserId);
        if (! $user instanceof User) {
            return 'fallback_branch_scopes';
        }

        $roleId = (int) ($user->role_id ?? 0);
        $roleName = (string) ($user->role?->role_name ?? '');
        $roleIdScopes = (array) config('staff_capabilities.role_id_branch_scopes', []);

        if ($roleId > 0 && (array_key_exists($roleId, $roleIdScopes) || array_key_exists((string) $roleId, $roleIdScopes))) {
            return 'role_id_branch_scopes';
        }

        $normalizedRoleName = mb_strtolower(trim($roleName));
        foreach ((array) config('staff_capabilities.role_branch_scopes', []) as $configuredRoleName => $scopeTokens) {
            if (mb_strtolower(trim((string) $configuredRoleName)) === $normalizedRoleName) {
                return 'role_branch_scopes';
            }
        }

        $capabilities = (array) ($this->staffCapabilityResolver->resolveForActor($roleId, $roleName)['capabilities'] ?? []);
        if (in_array('*', $capabilities, true)) {
            return 'capability_wildcard';
        }

        return 'fallback_branch_scopes';
    }

    /**
     * @return list<string>
     */
    private function fallbackBranchScopeTokens(): array
    {
        return $this->normalizeBranchScopeTokens(
            config('staff_capabilities.fallback_branch_scopes', ['default']),
        );
    }

    /**
     * @return list<string>
     */
    private function roleScopedBranchTokens(int $roleId, string $roleName): array
    {
        $roleIdScopes = (array) config('staff_capabilities.role_id_branch_scopes', []);
        if ($roleId > 0 && array_key_exists($roleId, $roleIdScopes)) {
            return $this->normalizeBranchScopeTokens($roleIdScopes[$roleId]);
        }

        if ($roleId > 0 && array_key_exists((string) $roleId, $roleIdScopes)) {
            return $this->normalizeBranchScopeTokens($roleIdScopes[(string) $roleId]);
        }

        $normalizedRoleName = mb_strtolower(trim($roleName));
        foreach ((array) config('staff_capabilities.role_branch_scopes', []) as $configuredRoleName => $scopeTokens) {
            if (mb_strtolower(trim((string) $configuredRoleName)) !== $normalizedRoleName) {
                continue;
            }

            return $this->normalizeBranchScopeTokens($scopeTokens);
        }

        return [];
    }

    /**
     * @param  list<string>  $scopeTokens
     * @return list<int>
     */
    private function resolveConfiguredBranchScope(array $scopeTokens): array
    {
        if ($scopeTokens === []) {
            return [];
        }

        if (in_array('*', $scopeTokens, true)) {
            return Branch::query()
                ->where('is_active', true)
                ->pluck('branch_id')
                ->map(static fn ($value): int => (int) $value)
                ->values()
                ->all();
        }

        $candidateIds = [];
        $branchCodes = [];

        foreach ($scopeTokens as $scopeToken) {
            if ($scopeToken === 'default') {
                try {
                    $candidateIds[] = (int) $this->branchContextService->defaultBranch()->branch_id;
                } catch (ModelNotFoundException) {
                    continue;
                }

                continue;
            }

            if (ctype_digit($scopeToken)) {
                $candidateIds[] = (int) $scopeToken;

                continue;
            }

            $branchCodes[] = $scopeToken;
        }

        if ($branchCodes !== []) {
            $candidateIds = array_merge(
                $candidateIds,
                Branch::query()
                    ->where('is_active', true)
                    ->whereIn('branch_code', $branchCodes)
                    ->pluck('branch_id')
                    ->map(static fn ($value): int => (int) $value)
                    ->all(),
            );
        }

        $activeBranchIds = Branch::query()
            ->where('is_active', true)
            ->whereIn('branch_id', array_values(array_unique($candidateIds)))
            ->pluck('branch_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $activeBranchIds = array_values(array_unique($activeBranchIds));
        sort($activeBranchIds);

        return $activeBranchIds;
    }

    /**
     * @return list<string>
     */
    private function normalizeBranchScopeTokens(mixed $values): array
    {
        $tokens = [];

        foreach ((array) $values as $value) {
            $token = trim((string) $value);
            if ($token === '') {
                continue;
            }

            $tokens[] = $token === '*' ? '*' : $token;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }
}



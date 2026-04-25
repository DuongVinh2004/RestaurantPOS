<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\Queries;

use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\IdentityAccess\Application\Queries\StaffCapabilityResolver;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
    public function accessibleBranchIds(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        $assignedBranchIds = $this->assignedBranchIds($staffActorUserId);
        if ($assignedBranchIds !== null) {
            return $assignedBranchIds;
        }

        return $this->resolveConfiguredBranchScope(
            $this->configuredBranchScopeTokens($staffActorUserId, $staffActorRoleId, $staffActorRoleName),
        );
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): Collection {
        $branchIds = $this->accessibleBranchIds($staffActorUserId, $staffActorRoleId, $staffActorRoleName);
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
    public function branchAccessContext(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        $branchIds = $this->accessibleBranchIds($staffActorUserId, $staffActorRoleId, $staffActorRoleName);
        $defaultBranchId = null;

        try {
            $defaultBranchId = (int) $this->branchContextService->defaultBranch()->branch_id;
        } catch (ModelNotFoundException) {
            $defaultBranchId = null;
        }

        $hasDefaultBranchAccess = $defaultBranchId !== null && in_array($defaultBranchId, $branchIds, true);
        $accessSource = $this->branchAccessSource($staffActorUserId, $staffActorRoleId, $staffActorRoleName);
        $assignedPrimaryBranchId = $accessSource === 'staff_branch_assignments'
            ? $this->primaryAssignedBranchId($staffActorUserId)
            : null;
        $currentBranchId = $assignedPrimaryBranchId !== null && in_array($assignedPrimaryBranchId, $branchIds, true)
            ? $assignedPrimaryBranchId
            : ($hasDefaultBranchAccess ? $defaultBranchId : ($branchIds[0] ?? null));

        return [
            'accessible_branch_ids' => $branchIds,
            'default_branch_id' => $defaultBranchId,
            'current_branch_id' => $currentBranchId,
            'has_default_branch_access' => $hasDefaultBranchAccess,
            'has_multi_branch_access' => count($branchIds) > 1,
            'branch_selector_enabled' => count($branchIds) > 1,
            'access_source' => $accessSource,
            'branches_uri' => '/api/v1/staff/branches',
        ];
    }

    /**
     * @return list<int>
     */
    public function branchScopeOrAccessible(
        ?int $staffActorUserId = null,
        ?int $requestedBranchId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        if ($requestedBranchId !== null && $requestedBranchId > 0) {
            return [$this->assertAccessibleBranch($staffActorUserId, $requestedBranchId)];
        }

        return $this->accessibleBranchIds($staffActorUserId, $staffActorRoleId, $staffActorRoleName);
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
    private function configuredBranchScopeTokens(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return $this->fallbackBranchScopeTokens();
        }

        [$roleId, $roleName, $resolvedFromActorContext] = $this->resolveActorRoleContext(
            $staffActorUserId,
            $staffActorRoleId,
            $staffActorRoleName,
        );
        if (! $resolvedFromActorContext && $roleId <= 0 && $roleName === '') {
            return $this->fallbackBranchScopeTokens();
        }

        if ($this->operationalRoleRequiresExplicitBranchAssignment($roleId, $roleName)) {
            return [];
        }

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

    private function branchAccessSource(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): string {
        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return 'fallback_branch_scopes';
        }

        if ($this->assignedBranchIds($staffActorUserId) !== null) {
            return 'staff_branch_assignments';
        }

        [$roleId, $roleName, $resolvedFromActorContext] = $this->resolveActorRoleContext(
            $staffActorUserId,
            $staffActorRoleId,
            $staffActorRoleName,
        );
        if (! $resolvedFromActorContext && $roleId <= 0 && $roleName === '') {
            return 'fallback_branch_scopes';
        }
        $roleIdScopes = (array) config('staff_capabilities.role_id_branch_scopes', []);

        if ($this->operationalRoleRequiresExplicitBranchAssignment($roleId, $roleName)) {
            return 'explicit_branch_assignment_required';
        }

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

    private function operationalRoleRequiresExplicitBranchAssignment(int $roleId, string $roleName): bool
    {
        if ($roleId <= 0 || ! $this->deniesOperationalRoleBranchFallback()) {
            return false;
        }

        $normalizedRoleName = mb_strtolower(trim($roleName));
        if ($normalizedRoleName === '') {
            return false;
        }

        return in_array($normalizedRoleName, $this->operationalBranchAssignmentRoleNames(), true);
    }

    /**
     * @return array{0:int,1:string,2:bool}
     */
    private function resolveActorRoleContext(
        ?int $staffActorUserId,
        ?int $staffActorRoleId,
        ?string $staffActorRoleName,
    ): array {
        $roleId = max(0, (int) ($staffActorRoleId ?? 0));
        $roleName = trim((string) ($staffActorRoleName ?? ''));

        if ($roleId > 0 || $roleName !== '') {
            return [$roleId, $roleName, true];
        }

        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return [0, '', false];
        }

        /** @var User|null $user */
        $user = User::query()->with('role')->find($staffActorUserId);
        if (! $user instanceof User) {
            return [0, '', false];
        }

        return [
            (int) ($user->role_id ?? 0),
            (string) ($user->role?->role_name ?? ''),
            false,
        ];
    }

    private function deniesOperationalRoleBranchFallback(): bool
    {
        if (! (bool) config('staff_capabilities.deny_operational_role_branch_fallback_in_production_like', true)) {
            return false;
        }

        return $this->isProductionLikeEnvironment();
    }

    private function isProductionLikeEnvironment(): bool
    {
        $environment = mb_strtolower(trim((string) config('app.env', 'production')));
        if ($environment === '') {
            return false;
        }

        $productionLikeEnvironments = config(
            'staff_capabilities.production_like_environments',
            config('staff_auth.production_like_environments', ['production']),
        );

        $normalizedEnvironments = array_values(array_filter(array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) $productionLikeEnvironments,
        )));

        return in_array($environment, $normalizedEnvironments, true);
    }

    /**
     * @return list<string>
     */
    private function operationalBranchAssignmentRoleNames(): array
    {
        $roles = config('staff_capabilities.operational_branch_assignment_roles', [
            'Staff',
            'Server',
            'Waiter',
            'Cashier',
            'Kitchen',
        ]);

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) $roles,
        )));
    }

    /**
     * Returns null when no per-staff assignment contract exists for the actor.
     *
     * @return list<int>|null
     */
    private function assignedBranchIds(?int $staffActorUserId = null): ?array
    {
        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return null;
        }

        try {
            $assignmentQuery = DB::table('staff_branch_assignments')
                ->where('user_id', $staffActorUserId);

            if (! $assignmentQuery->exists()) {
                return null;
            }

            $branchIds = DB::table('staff_branch_assignments as sba')
                ->join('branches as b', 'b.branch_id', '=', 'sba.branch_id')
                ->where('sba.user_id', $staffActorUserId)
                ->where('b.is_active', true)
                ->whereNull('sba.revoked_at')
                ->orderByDesc('sba.is_primary')
                ->orderBy('sba.branch_id')
                ->pluck('sba.branch_id')
                ->map(static fn ($value): int => (int) $value)
                ->filter(static fn (int $branchId): bool => $branchId > 0)
                ->values()
                ->all();
        } catch (QueryException) {
            return null;
        }

        return array_values(array_unique($branchIds));
    }

    private function primaryAssignedBranchId(?int $staffActorUserId = null): ?int
    {
        $branchIds = $this->assignedBranchIds($staffActorUserId);
        if ($branchIds === null || $branchIds === []) {
            return null;
        }

        return $branchIds[0];
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

        if ($scopeTokens === ['default']) {
            return $this->resolveDefaultBranchScope();
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
     * @return list<int>
     */
    private function resolveDefaultBranchScope(): array
    {
        /** @var Branch|null $defaultBranch */
        $defaultBranch = Branch::query()
            ->where('is_default', true)
            ->orderBy('branch_id')
            ->first(['branch_id', 'is_active']);

        if ($defaultBranch instanceof Branch) {
            return (bool) $defaultBranch->is_active ? [(int) $defaultBranch->branch_id] : [];
        }

        $activeBranchId = Branch::query()
            ->where('is_active', true)
            ->orderBy('branch_id')
            ->value('branch_id');

        return $activeBranchId !== null ? [(int) $activeBranchId] : [];
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

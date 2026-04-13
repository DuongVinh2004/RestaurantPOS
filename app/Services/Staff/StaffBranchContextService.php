<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Services\Branch\BranchContextService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

class StaffBranchContextService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
    ) {}

    /**
     * @return list<int>
     */
    public function accessibleBranchIds(?int $staffActorUserId = null): array
    {
        $this->branchContextService->ensureDefaultBranchExists();

        $candidateIds = [];

        try {
            $candidateIds[] = (int) $this->branchContextService->defaultBranch()->branch_id;
        } catch (ModelNotFoundException) {
            return [];
        }

        if ($staffActorUserId !== null && $staffActorUserId > 0 && Schema::hasTable('cashier_shifts')) {
            $shiftBranchIds = CashierShift::query()
                ->where('cashier_user_id', $staffActorUserId)
                ->where('status', 'Open')
                ->whereNotNull('branch_id')
                ->pluck('branch_id')
                ->map(static fn ($value): int => (int) $value)
                ->all();

            $candidateIds = array_merge($candidateIds, $shiftBranchIds);
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

        throw (new ModelNotFoundException())->setModel(Branch::class, $branchId !== null ? [$branchId] : []);
    }
}

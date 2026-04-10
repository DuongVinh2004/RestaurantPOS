<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Branch;
use App\Services\Branch\BranchContextService;
use Illuminate\Database\Eloquent\Collection;

class StaffBranchContextService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
    ) {}

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(): Collection
    {
        $this->branchContextService->ensureDefaultBranchExists();

        return Branch::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('branch_name')
            ->orderBy('branch_id')
            ->get();
    }
}

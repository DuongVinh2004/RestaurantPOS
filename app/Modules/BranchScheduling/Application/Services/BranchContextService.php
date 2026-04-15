<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchContextService
{
    public function defaultBranch(): Branch
    {
        $this->ensureDefaultBranchExists();

        /** @var Branch|null $branch */
        $branch = Branch::query()
            ->where('is_default', true)
            ->orderBy('branch_id')
            ->first();

        if ($branch instanceof Branch) {
            return $branch;
        }

        /** @var Branch|null $active */
        $active = Branch::query()->where('is_active', true)->orderBy('branch_id')->first();
        if ($active instanceof Branch) {
            return $active;
        }

        throw (new ModelNotFoundException)->setModel(Branch::class);
    }

    public function resolveBranchId(mixed $branchId = null, bool $activeOnly = true): int
    {
        if ($branchId === null || $branchId === '') {
            return (int) $this->defaultBranch()->branch_id;
        }

        /** @var Branch|null $branch */
        $branch = Branch::query()
            ->when($activeOnly, static fn ($query) => $query->where('is_active', true))
            ->find((int) $branchId);

        if (! $branch instanceof Branch) {
            throw ValidationException::withMessages([
                'branch_id' => ['Selected branch is invalid or inactive.'],
            ]);
        }

        return (int) $branch->branch_id;
    }

    /**
     * @param  iterable<mixed>  $branchIds
     */
    public function assertSingleBranch(
        iterable $branchIds,
        string $message = 'Resources must belong to a single branch.',
        string $field = 'branch_id',
        bool $activeOnly = false
    ): int {
        $resolved = [];

        foreach ($branchIds as $branchId) {
            $resolved[] = $this->resolveBranchId($branchId, $activeOnly);
        }

        $resolved = array_values(array_unique($resolved));
        if ($resolved === []) {
            return $this->resolveBranchId(null, $activeOnly);
        }

        if (count($resolved) > 1) {
            throw ValidationException::withMessages([
                $field => [$message],
            ]);
        }

        return (int) $resolved[0];
    }

    public function assertSameBranch(
        mixed $expectedBranchId,
        mixed $actualBranchId,
        string $message,
        string $field = 'branch_id',
        bool $activeOnly = false
    ): int {
        $expected = $this->resolveBranchId($expectedBranchId, $activeOnly);
        $actual = $this->resolveBranchId($actualBranchId, $activeOnly);

        if ($expected !== $actual) {
            throw ValidationException::withMessages([
                $field => [$message],
            ]);
        }

        return $actual;
    }

    public function ensureDefaultBranchExists(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('branches')) {
            return;
        }

        if (Branch::query()->exists()) {
            if (! Branch::query()->where('is_default', true)->exists()) {
                /** @var Branch|null $branch */
                $branch = Branch::query()->where('is_active', true)->orderBy('branch_id')->first();
                if ($branch instanceof Branch) {
                    $branch->is_default = true;
                    $branch->save();
                }
            }

            return;
        }

        Branch::query()->create([
            'branch_code' => (string) config('booking.multi_branch.default_branch_code', 'MAIN'),
            'branch_name' => (string) config('booking.multi_branch.default_branch_name', 'Chi nhanh chinh'),
            'description' => 'Single-site compatibility default branch.',
            'timezone' => (string) config('booking.multi_branch.default_branch_timezone', config('app.timezone', 'UTC')),
            'currency' => (string) config('booking.multi_branch.default_branch_currency', 'VND'),
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}

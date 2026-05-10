<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chuan hoa branch context cho cac flow nghiep vu:
 * tim branch mac dinh, resolve branch id, va chan scope sai chi nhanh.
 */
class BranchContextService
{
    public function defaultBranch(): Branch
    {
        // Uu tien branch default; neu chua cau hinh thi roi xuong active branch dau tien.
        // Pha 1: uu tien branch duoc danh dau default de giu compatibility cho flow single-site.
        /** @var Branch|null $branch */
        $branch = Branch::query()
            ->where('is_default', true)
            ->orderBy('branch_id')
            ->first();

        if ($branch instanceof Branch) {
            return $branch;
        }

        // Neu chua co default thi fallback ve active branch dau tien de he thong van boot duoc.
        /** @var Branch|null $active */
        $active = Branch::query()->where('is_active', true)->orderBy('branch_id')->first();
        if ($active instanceof Branch) {
            return $active;
        }

        throw (new ModelNotFoundException)->setModel(Branch::class);
    }

    public function resolveBranchId(mixed $branchId = null, bool $activeOnly = true): int
    {
        // Null branch duoc hieu la "lay branch van hanh mac dinh".
        if ($branchId === null || $branchId === '') {
            return (int) $this->defaultBranch()->branch_id;
        }

        // resolveBranchId la diem normalize chung cho moi branch input tu request/config/model.
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
        // Dung khi request gom nhieu resource; tat ca phai quy ve cung mot branch.
        $resolved = [];

        // Tat ca id di qua resolveBranchId de active/default semantics duoc ap dung dong nhat.
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
        // Chot chan cuoi de reservation, table, hold, payment... khong bi drift branch.
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
        // Helper bootstrap nay giup code da branch-aware van chay tren moi truong chua setup branch day du.
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

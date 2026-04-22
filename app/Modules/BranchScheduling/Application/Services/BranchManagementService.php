<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Support\AuditEvent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchManagementService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
        private readonly BranchSchedulingPolicyService $branchSchedulingPolicyService,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return Collection<int, Branch>
     */
    public function listBranches(array $filters = []): Collection
    {
        $this->branchContextService->ensureDefaultBranchExists();

        $keyword = trim((string) ($filters['q'] ?? ''));

        /** @var Collection<int, Branch> $branches */
        $branches = Branch::query()
            ->when(array_key_exists('is_active', $filters), static fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($keyword !== '', static function ($query) use ($keyword): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $keyword).'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('branch_code', 'like', $like)
                        ->orWhere('branch_name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('branch_name')
            ->orderBy('branch_id')
            ->get();

        return $branches;
    }

    public function showBranch(int $branchId): Branch
    {
        $this->branchContextService->ensureDefaultBranchExists();

        return Branch::query()->findOrFail($branchId);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createBranch(array $payload, ?int $actorUserId = null): Branch
    {
        return DB::transaction(function () use ($payload, $actorUserId): Branch {
            $isDefault = (bool) ($payload['is_default'] ?? false);
            $isActive = (bool) ($payload['is_active'] ?? true);

            if ($isDefault && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_default' => ['Default branch must be active.'],
                ]);
            }

            $branch = new Branch;
            $branch->fill($this->normalizePayload($payload, true));
            $branch->save();

            if ($isDefault) {
                $this->makeDefault((int) $branch->branch_id);
            }

            $fresh = $this->showBranch((int) $branch->branch_id);

            AuditEvent::info('admin.branch.created', [
                'branch_id' => (int) $fresh->branch_id,
                'branch_code' => (string) $fresh->branch_code,
                '_audit' => [
                    'action' => 'master_data.branch.created',
                    'entity_type' => 'branch',
                    'entity_id' => (string) $fresh->branch_id,
                    'after' => $this->auditSnapshot($fresh),
                    'summary' => [
                        'branch_code' => (string) $fresh->branch_code,
                        'is_active' => (bool) $fresh->is_active,
                        'is_default' => (bool) $fresh->is_default,
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        }, 3);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateBranch(int $branchId, array $payload, ?int $actorUserId = null): Branch
    {
        return DB::transaction(function () use ($branchId, $payload, $actorUserId): Branch {
            /** @var Branch $branch */
            $branch = Branch::query()->lockForUpdate()->findOrFail($branchId);
            $expectedRowVersion = (int) ($payload['row_version'] ?? 0);
            if ($expectedRowVersion <= 0 || (int) $branch->row_version !== $expectedRowVersion) {
                throw ValidationException::withMessages([
                    'row_version' => ['Branch has been modified by another operation. Please reload and retry.'],
                ]);
            }

            $normalized = $this->normalizePayload($payload, false);
            $nextIsActive = array_key_exists('is_active', $normalized) ? (bool) $normalized['is_active'] : (bool) $branch->is_active;
            $nextIsDefault = array_key_exists('is_default', $normalized) ? (bool) $normalized['is_default'] : (bool) $branch->is_default;

            if ($nextIsDefault && ! $nextIsActive) {
                throw ValidationException::withMessages([
                    'is_default' => ['Default branch must be active.'],
                ]);
            }

            if ((bool) $branch->is_default && ! $nextIsDefault) {
                throw ValidationException::withMessages([
                    'is_default' => ['Use another branch as default before removing the current default branch.'],
                ]);
            }

            if ((bool) $branch->is_default && ! $nextIsActive) {
                throw ValidationException::withMessages([
                    'is_active' => ['Cannot deactivate the default branch.'],
                ]);
            }

            $before = $this->auditSnapshot($branch);
            $branch->fill($normalized);
            $branch->save();

            if ($nextIsDefault) {
                $this->makeDefault($branchId);
            }

            $fresh = $this->showBranch($branchId);

            AuditEvent::info('admin.branch.updated', [
                'branch_id' => (int) $fresh->branch_id,
                'branch_code' => (string) $fresh->branch_code,
                '_audit' => [
                    'action' => 'master_data.branch.updated',
                    'entity_type' => 'branch',
                    'entity_id' => (string) $fresh->branch_id,
                    'before' => $before,
                    'after' => $this->auditSnapshot($fresh),
                    'summary' => [
                        'branch_code' => (string) $fresh->branch_code,
                        'is_active' => (bool) $fresh->is_active,
                        'is_default' => (bool) $fresh->is_default,
                    ],
                    'actor' => $this->auditActor($actorUserId),
                ],
            ]);

            return $fresh;
        }, 3);
    }

    private function makeDefault(int $branchId): void
    {
        Branch::query()->where('branch_id', '!=', $branchId)->update(['is_default' => false]);
        Branch::query()->where('branch_id', $branchId)->update(['is_default' => true, 'is_active' => true]);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizePayload(array $payload, bool $creating): array
    {
        $normalized = [];

        if ($creating || array_key_exists('branch_code', $payload)) {
            $normalized['branch_code'] = strtoupper(trim((string) ($payload['branch_code'] ?? '')));
        }

        if ($creating || array_key_exists('branch_name', $payload)) {
            $normalized['branch_name'] = trim((string) ($payload['branch_name'] ?? ''));
        }

        foreach (['description', 'timezone', 'currency'] as $field) {
            if ($creating || array_key_exists($field, $payload)) {
                $value = trim((string) ($payload[$field] ?? ''));
                $normalized[$field] = $value !== '' ? $value : null;
            }
        }

        if (($creating || array_key_exists('currency', $payload)) && ($normalized['currency'] ?? null) === null) {
            $normalized['currency'] = (string) config('booking.multi_branch.default_branch_currency', 'VND');
        }

        if (($creating || array_key_exists('timezone', $payload)) && ($normalized['timezone'] ?? null) === null) {
            $normalized['timezone'] = (string) config('booking.multi_branch.default_branch_timezone', config('app.timezone', 'UTC'));
        }

        $effectiveTimezone = (string) ($normalized['timezone'] ?? config('booking.multi_branch.default_branch_timezone', config('app.timezone', 'UTC')));

        if (array_key_exists('business_hours', $payload)) {
            $normalized['business_hours'] = $this->branchSchedulingPolicyService->normalizeBusinessHoursPayload(
                $payload['business_hours'] ?? null
            );
        }

        if (array_key_exists('closure_windows', $payload)) {
            $normalized['closure_windows'] = $this->branchSchedulingPolicyService->normalizeClosureWindowsPayload(
                $payload['closure_windows'] ?? null,
                $effectiveTimezone,
            );
        }

        if (array_key_exists('booking_policy', $payload)) {
            $normalized['booking_policy'] = $this->branchSchedulingPolicyService->normalizeBookingPolicyPayload(
                $payload['booking_policy'] ?? null
            );
        }

        if ($creating || array_key_exists('is_active', $payload)) {
            $normalized['is_active'] = (bool) ($payload['is_active'] ?? true);
        }

        if ($creating || array_key_exists('is_default', $payload)) {
            $normalized['is_default'] = (bool) ($payload['is_default'] ?? false);
        }

        if (($normalized['branch_code'] ?? null) === '' || ($normalized['branch_name'] ?? null) === '') {
            throw ValidationException::withMessages([
                'branch' => ['Branch code and branch name are required.'],
            ]);
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>
     */
    private function auditSnapshot(Branch $branch): array
    {
        return [
            'branch_code' => (string) $branch->branch_code,
            'branch_name' => (string) $branch->branch_name,
            'description' => $branch->description,
            'timezone' => $branch->timezone,
            'currency' => $branch->currency,
            'business_hours' => $branch->business_hours,
            'closure_windows' => $branch->closure_windows,
            'booking_policy' => $branch->booking_policy,
            'is_active' => (bool) $branch->is_active,
            'is_default' => (bool) $branch->is_default,
            'row_version' => (int) ($branch->row_version ?? 1),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function auditActor(?int $actorUserId): ?array
    {
        if ($actorUserId === null || $actorUserId <= 0) {
            return null;
        }

        return [
            'type' => 'staff_user',
            'user_id' => $actorUserId,
            'key' => 'staff_user:'.$actorUserId,
        ];
    }
}

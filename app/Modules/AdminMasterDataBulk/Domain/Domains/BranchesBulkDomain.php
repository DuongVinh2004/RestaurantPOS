<?php

declare(strict_types=1);

namespace App\Modules\AdminMasterDataBulk\Domain\Domains;

use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\AdminMasterDataBulk\Domain\Contracts\MasterDataBulkDomain;
use App\Modules\AdminMasterDataBulk\Infrastructure\Support\AbstractMasterDataBulkDomain;
use App\Modules\BranchScheduling\Application\Services\BranchManagementService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class BranchesBulkDomain extends AbstractMasterDataBulkDomain implements MasterDataBulkDomain
{
    public function __construct(
        private readonly BranchManagementService $branchManagementService,
    ) {
    }

    public function key(): string
    {
        return 'branches';
    }

    public function label(): string
    {
        return 'Branches';
    }

    public function importColumns(): array
    {
        return [
            'branch_code',
            'branch_name',
            'description',
            'timezone',
            'currency',
            'is_active',
            'is_default',
        ];
    }

    public function requiredColumns(): array
    {
        return [
            'branch_code',
            'branch_name',
        ];
    }

    public function exportRows(string $format): array
    {
        return Branch::query()
            ->orderByDesc('is_default')
            ->orderBy('branch_name')
            ->get()
            ->map(fn (Branch $branch): array => $this->snapshot($branch))
            ->all();
    }

    public function analyze(array $rows): array
    {
        $prepared = [];

        foreach ($rows as $row) {
            $rowNumber = $this->rowNumber($row);
            $raw = $this->rawRow($row);
            $errors = [];
            $normalized = null;
            $matchKey = [
                'branch_code' => strtoupper($this->trimmed($raw['branch_code'] ?? '')),
            ];

            try {
                Validator::make($raw, [
                    'branch_code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
                    'branch_name' => ['required', 'string', 'max:150'],
                    'description' => ['nullable', 'string', 'max:400'],
                    'timezone' => ['nullable', 'string', 'max:64'],
                    'currency' => ['nullable', 'string', 'max:10'],
                    'is_active' => ['nullable', 'boolean'],
                    'is_default' => ['nullable', 'boolean'],
                ])->validate();

                $normalized = [
                    'branch_code' => strtoupper($this->trimmed($raw['branch_code'] ?? '')),
                    'branch_name' => $this->trimmed($raw['branch_name'] ?? ''),
                    'description' => $this->nullableString($raw['description'] ?? null),
                    'timezone' => $this->nullableString($raw['timezone'] ?? null) ?? (string) config('booking.multi_branch.default_branch_timezone', config('app.timezone', 'UTC')),
                    'currency' => $this->nullableString($raw['currency'] ?? null) ?? (string) config('booking.multi_branch.default_branch_currency', 'VND'),
                    'is_active' => $this->booleanValue($raw['is_active'] ?? null, true),
                    'is_default' => $this->booleanValue($raw['is_default'] ?? null, false),
                ];

                if ($normalized['is_default'] && ! $normalized['is_active']) {
                    $errors[] = $this->error('is_default', 'Default branch must be active.');
                }
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $this->validationErrors($exception));
            }

            $prepared[] = [
                'row_number' => $rowNumber,
                'match_key' => $matchKey,
                'match_key_value' => (string) ($matchKey['branch_code'] ?? ''),
                'status' => $errors === [] ? 'valid' : 'invalid',
                'operation' => $errors === [] ? 'pending' : 'invalid',
                'errors' => $errors,
                'before' => null,
                'after' => $normalized,
                'normalized' => $normalized,
            ];
        }

        $this->applyDuplicateKeyErrors($prepared);

        $existingMap = Branch::query()
            ->whereIn('branch_code', collect($prepared)
                ->filter(fn (array $row): bool => ($row['operation'] ?? 'invalid') !== 'invalid')
                ->pluck('match_key_value')
                ->filter()
                ->all())
            ->get()
            ->keyBy('branch_code');

        $currentDefaultBranchCode = (string) (Branch::query()->where('is_default', true)->value('branch_code') ?? '');
        $defaultRows = [];
        $defaultRemovalRows = [];

        foreach ($prepared as $index => $row) {
            if (($row['operation'] ?? 'invalid') === 'invalid') {
                continue;
            }

            /** @var Branch|null $existing */
            $existing = $existingMap->get((string) $row['match_key_value']);
            $before = $existing instanceof Branch ? $this->snapshot($existing) : null;
            $after = (array) ($row['normalized'] ?? []);
            $operation = 'create';

            if ($existing instanceof Branch) {
                $operation = $this->sameSnapshot($before ?? [], $after) ? 'noop' : 'update';
            }

            if (($after['is_default'] ?? false) === true) {
                $defaultRows[] = $index;
            }

            if (
                $existing instanceof Branch
                && (string) $existing->branch_code === $currentDefaultBranchCode
                && ($after['is_default'] ?? false) === false
            ) {
                $defaultRemovalRows[] = $index;
            }

            $prepared[$index]['before'] = $before;
            $prepared[$index]['after'] = $after;
            $prepared[$index]['operation'] = $operation;
            $prepared[$index]['status'] = 'valid';
            $prepared[$index]['_apply'] = [
                'branch_id' => $existing?->branch_id !== null ? (int) $existing->branch_id : null,
                'row_version' => $existing?->row_version !== null ? (int) $existing->row_version : null,
                'payload' => $after,
                'is_default' => (bool) ($after['is_default'] ?? false),
            ];
        }

        if (count($defaultRows) > 1) {
            foreach ($defaultRows as $index) {
                $prepared[$index]['errors'][] = $this->error('is_default', 'Only one imported branch row may set is_default=true.');
                $prepared[$index]['operation'] = 'invalid';
                $prepared[$index]['status'] = 'invalid';
            }
        }

        if ($defaultRows === [] && $defaultRemovalRows !== []) {
            foreach ($defaultRemovalRows as $index) {
                $prepared[$index]['errors'][] = $this->error('is_default', 'Import must promote another branch as default before removing the current default branch.');
                $prepared[$index]['operation'] = 'invalid';
                $prepared[$index]['status'] = 'invalid';
            }
        }

        return [
            'rows' => $prepared,
            'summary' => $this->makeSummary($prepared),
        ];
    }

    public function apply(array $rows, int $actorUserId): array
    {
        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $changes = [];

        $applicableRows = collect($rows)
            ->filter(static fn (array $row): bool => in_array((string) ($row['operation'] ?? 'invalid'), ['create', 'update', 'noop'], true))
            ->sortByDesc(static fn (array $row): int => (int) data_get($row, '_apply.is_default', 0))
            ->values();

        foreach ($applicableRows as $row) {
            $operation = (string) ($row['operation'] ?? 'invalid');
            if ($operation === 'noop') {
                $unchanged++;
                continue;
            }

            $payload = (array) data_get($row, '_apply.payload', []);
            $before = is_array($row['before'] ?? null) ? $row['before'] : null;

            if ($operation === 'create') {
                $branch = $this->branchManagementService->createBranch($payload, $actorUserId);
                $created++;
            } else {
                $branch = $this->branchManagementService->updateBranch(
                    (int) data_get($row, '_apply.branch_id'),
                    array_merge($payload, [
                        'row_version' => (int) data_get($row, '_apply.row_version'),
                    ]),
                    $actorUserId,
                );
                $updated++;
            }

            $changes[] = [
                'entity_type' => 'branch',
                'entity_id' => (string) $branch->branch_id,
                'operation' => $operation,
                'before' => $before,
                'after' => $this->snapshot($branch),
            ];
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'changes' => $changes,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(Branch $branch): array
    {
        return $this->projectSnapshot([
            'branch_code' => (string) $branch->branch_code,
            'branch_name' => (string) $branch->branch_name,
            'description' => $branch->description !== null ? (string) $branch->description : null,
            'timezone' => $branch->timezone !== null ? (string) $branch->timezone : null,
            'currency' => $branch->currency !== null ? (string) $branch->currency : null,
            'is_active' => (bool) $branch->is_active,
            'is_default' => (bool) $branch->is_default,
        ], $this->importColumns());
    }
}

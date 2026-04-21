<?php

declare(strict_types=1);

namespace App\Modules\MasterDataExchange\Domain\Registries;

use App\Modules\Catalog\Domain\Models\MenuCategory;
use App\Modules\Catalog\Application\UseCases\Management\MenuCatalogManagementService;
use App\Modules\MasterDataExchange\Domain\Contracts\MasterDataDomain;
use App\Modules\MasterDataExchange\Infrastructure\Internal\AbstractMasterDataDomain;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MenuCategoriesMasterDataDomain extends AbstractMasterDataDomain implements MasterDataDomain
{
    public function __construct(
        private readonly MenuCatalogManagementService $menuService,
    ) {
    }

    public function key(): string
    {
        return 'menu-categories';
    }

    public function label(): string
    {
        return 'Menu Categories';
    }

    public function importColumns(): array
    {
        return [
            'name',
            'description',
            'sort_order',
            'is_deleted',
        ];
    }

    public function requiredColumns(): array
    {
        return [
            'name',
        ];
    }

    public function exportRows(string $format): array
    {
        return MenuCategory::query()
            ->ordered()
            ->get()
            ->map(fn (MenuCategory $category): array => $this->snapshot($category))
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
            $name = $this->trimmed($raw['name'] ?? '');

            try {
                Validator::make($raw, [
                    'name' => ['required', 'string', 'max:150'],
                    'description' => ['nullable', 'string', 'max:400'],
                    'sort_order' => ['nullable', 'integer'],
                    'is_deleted' => ['nullable', 'boolean'],
                ])->validate();

                $normalized = [
                    'name' => $name,
                    'description' => $this->nullableString($raw['description'] ?? null),
                    'sort_order' => (int) ($raw['sort_order'] ?? 0),
                    'is_deleted' => $this->booleanValue($raw['is_deleted'] ?? null, false),
                ];
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $this->validationErrors($exception));
            }

            $prepared[] = [
                'row_number' => $rowNumber,
                'match_key' => ['name' => $name],
                'match_key_value' => $name,
                'status' => $errors === [] ? 'valid' : 'invalid',
                'operation' => $errors === [] ? 'pending' : 'invalid',
                'errors' => $errors,
                'before' => null,
                'after' => $normalized,
                'normalized' => $normalized,
            ];
        }

        $this->applyDuplicateKeyErrors($prepared);

        $existingMap = MenuCategory::query()
            ->whereIn('name', collect($prepared)
                ->filter(fn (array $row): bool => ($row['operation'] ?? 'invalid') !== 'invalid')
                ->pluck('match_key_value')
                ->filter()
                ->all())
            ->get()
            ->keyBy('name');

        foreach ($prepared as $index => $row) {
            if (($row['operation'] ?? 'invalid') === 'invalid') {
                continue;
            }

            /** @var MenuCategory|null $existing */
            $existing = $existingMap->get((string) $row['match_key_value']);
            $before = $existing instanceof MenuCategory ? $this->snapshot($existing) : null;
            $after = (array) ($row['normalized'] ?? []);
            $operation = 'create';

            if ($existing instanceof MenuCategory) {
                $operation = $this->sameSnapshot($before ?? [], $after) ? 'noop' : 'update';
            }

            $prepared[$index]['before'] = $before;
            $prepared[$index]['after'] = $after;
            $prepared[$index]['operation'] = $operation;
            $prepared[$index]['status'] = 'valid';
            $prepared[$index]['_apply'] = [
                'category_id' => $existing?->category_id !== null ? (int) $existing->category_id : null,
                'payload' => $after,
            ];
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

        foreach ($rows as $row) {
            $operation = (string) ($row['operation'] ?? 'invalid');
            if ($operation === 'invalid') {
                continue;
            }

            if ($operation === 'noop') {
                $unchanged++;
                continue;
            }

            $payload = (array) data_get($row, '_apply.payload', []);
            $before = is_array($row['before'] ?? null) ? $row['before'] : null;

            if ($operation === 'create') {
                $category = $this->menuService->createCategory($payload, $actorUserId);
                $created++;
            } else {
                $category = $this->menuService->updateCategory(
                    (int) data_get($row, '_apply.category_id'),
                    $payload,
                    $actorUserId,
                );
                $updated++;
            }

            $changes[] = [
                'entity_type' => 'menu_category',
                'entity_id' => (string) $category->category_id,
                'operation' => $operation,
                'before' => $before,
                'after' => $this->snapshot($category),
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
    private function snapshot(MenuCategory $category): array
    {
        return $this->projectSnapshot([
            'name' => (string) $category->name,
            'description' => $category->description,
            'sort_order' => (int) ($category->sort_order ?? 0),
            'is_deleted' => (bool) ($category->is_deleted ?? false),
        ], $this->importColumns());
    }
}

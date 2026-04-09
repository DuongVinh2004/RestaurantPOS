<?php

declare(strict_types=1);

namespace App\Services\Admin\MasterDataBulk\Domains;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Admin\AdminMenuManagementService;
use App\Services\Admin\MasterDataBulk\Contracts\MasterDataBulkDomain;
use App\Services\Admin\MasterDataBulk\Support\AbstractMasterDataBulkDomain;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MenuItemsBulkDomain extends AbstractMasterDataBulkDomain implements MasterDataBulkDomain
{
    public function __construct(
        private readonly AdminMenuManagementService $menuService,
    ) {
    }

    public function key(): string
    {
        return 'menu-items';
    }

    public function label(): string
    {
        return 'Menu Items';
    }

    public function importColumns(): array
    {
        return [
            'code',
            'name',
            'category_name',
            'description',
            'img_url',
            'is_available',
            'is_preorder_enabled',
            'preorder_quota_per_day',
            'preorder_cutoff_minutes',
        ];
    }

    public function requiredColumns(): array
    {
        return [
            'code',
            'name',
        ];
    }

    public function exportRows(string $format): array
    {
        return MenuItem::query()
            ->with('category')
            ->orderBy('name')
            ->orderBy('item_id')
            ->get()
            ->map(fn (MenuItem $item): array => $this->snapshot($item))
            ->all();
    }

    public function analyze(array $rows): array
    {
        $prepared = [];
        $categoryNames = [];

        foreach ($rows as $row) {
            $raw = $this->rawRow($row);
            $categoryName = $this->nullableString($raw['category_name'] ?? null);
            if ($categoryName !== null) {
                $categoryNames[] = $categoryName;
            }
        }

        $categories = MenuCategory::query()
            ->whereIn('name', array_values(array_unique($categoryNames)))
            ->get()
            ->keyBy('name');

        foreach ($rows as $row) {
            $rowNumber = $this->rowNumber($row);
            $raw = $this->rawRow($row);
            $errors = [];
            $normalized = null;
            $code = $this->trimmed($raw['code'] ?? '');
            $categoryName = $this->nullableString($raw['category_name'] ?? null);

            try {
                Validator::make($raw, [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:200'],
                    'category_name' => ['nullable', 'string', 'max:150'],
                    'description' => ['nullable', 'string', 'max:1000'],
                    'img_url' => ['nullable', 'string', 'max:255'],
                    'is_available' => ['nullable', 'boolean'],
                    'is_preorder_enabled' => ['nullable', 'boolean'],
                    'preorder_quota_per_day' => ['nullable', 'integer', 'min:1'],
                    'preorder_cutoff_minutes' => ['nullable', 'integer', 'min:0'],
                ])->validate();

                if ($categoryName !== null && ! $categories->has($categoryName)) {
                    $errors[] = $this->error('category_name', 'Menu category [' . $categoryName . '] does not exist.');
                }

                $normalized = [
                    'code' => $code,
                    'name' => $this->trimmed($raw['name'] ?? ''),
                    'category_name' => $categoryName,
                    'description' => $this->nullableString($raw['description'] ?? null),
                    'img_url' => $this->nullableString($raw['img_url'] ?? null),
                    'is_available' => $this->booleanValue($raw['is_available'] ?? null, true),
                    'is_preorder_enabled' => $this->booleanValue($raw['is_preorder_enabled'] ?? null, false),
                    'preorder_quota_per_day' => $this->integerValue($raw['preorder_quota_per_day'] ?? null),
                    'preorder_cutoff_minutes' => (int) ($raw['preorder_cutoff_minutes'] ?? 0),
                ];
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $this->validationErrors($exception));
            }

            $prepared[] = [
                'row_number' => $rowNumber,
                'match_key' => ['code' => $code],
                'match_key_value' => $code,
                'status' => $errors === [] ? 'valid' : 'invalid',
                'operation' => $errors === [] ? 'pending' : 'invalid',
                'errors' => $errors,
                'before' => null,
                'after' => $normalized,
                'normalized' => $normalized,
                '_category_id' => $categoryName !== null && $categories->has($categoryName)
                    ? (int) $categories->get($categoryName)->category_id
                    : null,
            ];
        }

        $this->applyDuplicateKeyErrors($prepared);

        $existingMap = MenuItem::query()
            ->with('category')
            ->whereIn('code', collect($prepared)
                ->filter(fn (array $row): bool => ($row['operation'] ?? 'invalid') !== 'invalid')
                ->pluck('match_key_value')
                ->filter()
                ->all())
            ->get()
            ->keyBy('code');

        foreach ($prepared as $index => $row) {
            if (($row['operation'] ?? 'invalid') === 'invalid') {
                continue;
            }

            /** @var MenuItem|null $existing */
            $existing = $existingMap->get((string) $row['match_key_value']);
            $before = $existing instanceof MenuItem ? $this->snapshot($existing) : null;
            $after = (array) ($row['normalized'] ?? []);
            $operation = 'create';

            if ($existing instanceof MenuItem) {
                $operation = $this->sameSnapshot($before ?? [], $after) ? 'noop' : 'update';
            }

            $prepared[$index]['before'] = $before;
            $prepared[$index]['after'] = $after;
            $prepared[$index]['operation'] = $operation;
            $prepared[$index]['status'] = 'valid';
            $prepared[$index]['_apply'] = [
                'item_id' => $existing?->item_id !== null ? (int) $existing->item_id : null,
                'payload' => [
                    'category_id' => data_get($row, '_category_id'),
                    'code' => $after['code'] ?? null,
                    'name' => $after['name'] ?? null,
                    'description' => $after['description'] ?? null,
                    'img_url' => $after['img_url'] ?? null,
                    'is_available' => $after['is_available'] ?? true,
                    'is_preorder_enabled' => $after['is_preorder_enabled'] ?? false,
                    'preorder_quota_per_day' => $after['preorder_quota_per_day'] ?? null,
                    'preorder_cutoff_minutes' => $after['preorder_cutoff_minutes'] ?? 0,
                ],
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
                $item = $this->menuService->createItem($payload, $actorUserId);
                $created++;
            } else {
                $item = $this->menuService->updateItem(
                    (int) data_get($row, '_apply.item_id'),
                    $payload,
                    $actorUserId,
                );
                $updated++;
            }

            $changes[] = [
                'entity_type' => 'menu_item',
                'entity_id' => (string) $item->item_id,
                'operation' => $operation,
                'before' => $before,
                'after' => $this->snapshot($item),
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
    private function snapshot(MenuItem $item): array
    {
        return $this->projectSnapshot([
            'code' => $item->code !== null ? (string) $item->code : null,
            'name' => (string) $item->name,
            'category_name' => $item->relationLoaded('category')
                ? ($item->category?->name !== null ? (string) $item->category->name : null)
                : ($item->category()->value('name') !== null ? (string) $item->category()->value('name') : null),
            'description' => $item->description,
            'img_url' => $item->img_url,
            'is_available' => (bool) ($item->is_available ?? false),
            'is_preorder_enabled' => $item->is_preorder_enabled !== null ? (bool) $item->is_preorder_enabled : false,
            'preorder_quota_per_day' => $item->preorder_quota_per_day !== null ? (int) $item->preorder_quota_per_day : null,
            'preorder_cutoff_minutes' => $item->preorder_cutoff_minutes !== null ? (int) $item->preorder_cutoff_minutes : 0,
        ], $this->importColumns());
    }
}

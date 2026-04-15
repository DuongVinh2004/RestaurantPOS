<?php

declare(strict_types=1);

namespace App\Modules\AdminMasterDataBulk\Domain\Domains;

use App\Models\MenuItem;
use App\Models\MenuItemPrice;
use App\Services\Admin\AdminMenuManagementService;
use App\Modules\AdminMasterDataBulk\Domain\Contracts\MasterDataBulkDomain;
use App\Modules\AdminMasterDataBulk\Infrastructure\Support\AbstractMasterDataBulkDomain;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MenuPricesBulkDomain extends AbstractMasterDataBulkDomain implements MasterDataBulkDomain
{
    public function __construct(
        private readonly AdminMenuManagementService $menuService,
    ) {
    }

    public function key(): string
    {
        return 'menu-prices';
    }

    public function label(): string
    {
        return 'Menu Prices';
    }

    public function importColumns(): array
    {
        return [
            'item_code',
            'price',
            'currency',
            'effective_from',
            'effective_to',
        ];
    }

    public function requiredColumns(): array
    {
        return [
            'item_code',
            'price',
            'effective_from',
        ];
    }

    public function exportRows(string $format): array
    {
        return MenuItemPrice::query()
            ->with('item')
            ->orderBy('item_id')
            ->orderBy('effective_from')
            ->orderBy('price_id')
            ->get()
            ->map(fn (MenuItemPrice $price): array => $this->snapshot($price))
            ->all();
    }

    public function analyze(array $rows): array
    {
        $prepared = [];
        $itemCodes = [];

        foreach ($rows as $row) {
            $itemCode = $this->trimmed($this->rawRow($row)['item_code'] ?? '');
            if ($itemCode !== '') {
                $itemCodes[] = $itemCode;
            }
        }

        $items = MenuItem::query()
            ->whereIn('code', array_values(array_unique($itemCodes)))
            ->get()
            ->keyBy('code');

        foreach ($rows as $row) {
            $rowNumber = $this->rowNumber($row);
            $raw = $this->rawRow($row);
            $errors = [];
            $normalized = null;
            $itemCode = $this->trimmed($raw['item_code'] ?? '');

            try {
                Validator::make($raw, [
                    'item_code' => ['required', 'string', 'max:50'],
                    'price' => ['required', 'numeric', 'min:0'],
                    'currency' => ['nullable', 'string', 'max:10'],
                    'effective_from' => ['required', 'date'],
                    'effective_to' => ['nullable', 'date', 'after:effective_from'],
                ])->validate();

                if (! $items->has($itemCode)) {
                    $errors[] = $this->error('item_code', 'Menu item [' . $itemCode . '] does not exist.');
                }

                $normalized = [
                    'item_code' => $itemCode,
                    'price' => $this->decimalValue($raw['price'] ?? null),
                    'currency' => $this->nullableString($raw['currency'] ?? null) ?? 'VND',
                    'effective_from' => $this->isoDateTime($raw['effective_from'] ?? null),
                    'effective_to' => $this->isoDateTime($raw['effective_to'] ?? null),
                ];
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $this->validationErrors($exception));
            }

            $prepared[] = [
                'row_number' => $rowNumber,
                'match_key' => [
                    'item_code' => $itemCode,
                    'effective_from' => $normalized['effective_from'] ?? null,
                ],
                'match_key_value' => $this->compositeKey($itemCode, $normalized['effective_from'] ?? null),
                'status' => $errors === [] ? 'valid' : 'invalid',
                'operation' => $errors === [] ? 'pending' : 'invalid',
                'errors' => $errors,
                'before' => null,
                'after' => $normalized,
                'normalized' => $normalized,
                '_item_id' => $items->has($itemCode) ? (int) $items->get($itemCode)->item_id : null,
            ];
        }

        $this->applyDuplicateKeyErrors($prepared);

        $existingPrices = MenuItemPrice::query()
            ->with('item')
            ->whereIn('item_id', collect($prepared)
                ->pluck('_item_id')
                ->filter()
                ->map(static fn (mixed $value): int => (int) $value)
                ->all())
            ->get();

        $existingMap = [];
        foreach ($existingPrices as $price) {
            $existingMap[$this->compositeKey(
                (string) ($price->item?->code ?? $price->item()->value('code') ?? ''),
                $this->isoDateTime($price->effective_from),
            )] = $price;
        }

        foreach ($prepared as $index => $row) {
            if (($row['operation'] ?? 'invalid') === 'invalid') {
                continue;
            }

            /** @var MenuItemPrice|null $existing */
            $existing = $existingMap[(string) $row['match_key_value']] ?? null;
            $before = $existing instanceof MenuItemPrice ? $this->snapshot($existing) : null;
            $after = (array) ($row['normalized'] ?? []);
            $operation = 'create';

            if ($existing instanceof MenuItemPrice) {
                $operation = $this->sameSnapshot($before ?? [], $after) ? 'noop' : 'update';
            }

            $prepared[$index]['before'] = $before;
            $prepared[$index]['after'] = $after;
            $prepared[$index]['operation'] = $operation;
            $prepared[$index]['status'] = 'valid';
            $prepared[$index]['_apply'] = [
                'price_id' => $existing?->price_id !== null ? (int) $existing->price_id : null,
                'item_id' => (int) data_get($row, '_item_id'),
                'payload' => [
                    'price' => $after['price'] ?? null,
                    'currency' => $after['currency'] ?? 'VND',
                    'effective_from' => $after['effective_from'] ?? null,
                    'effective_to' => $after['effective_to'] ?? null,
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
                $price = $this->menuService->createPriceRow(
                    (int) data_get($row, '_apply.item_id'),
                    $payload,
                    $actorUserId,
                );
                $created++;
            } else {
                $price = $this->menuService->updatePriceRow(
                    (int) data_get($row, '_apply.price_id'),
                    $payload,
                    $actorUserId,
                );
                $updated++;
            }

            $changes[] = [
                'entity_type' => 'menu_price',
                'entity_id' => (string) $price->price_id,
                'operation' => $operation,
                'before' => $before,
                'after' => $this->snapshot($price),
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
    private function snapshot(MenuItemPrice $price): array
    {
        return $this->projectSnapshot([
            'item_code' => $price->relationLoaded('item')
                ? ($price->item?->code !== null ? (string) $price->item->code : null)
                : ($price->item()->value('code') !== null ? (string) $price->item()->value('code') : null),
            'price' => number_format((float) $price->price, 2, '.', ''),
            'currency' => (string) ($price->currency ?? 'VND'),
            'effective_from' => $this->isoDateTime($price->effective_from),
            'effective_to' => $this->isoDateTime($price->effective_to),
        ], $this->importColumns());
    }

    private function compositeKey(string $itemCode, ?string $effectiveFrom): string
    {
        return $itemCode . '|' . ($effectiveFrom ?? '');
    }
}

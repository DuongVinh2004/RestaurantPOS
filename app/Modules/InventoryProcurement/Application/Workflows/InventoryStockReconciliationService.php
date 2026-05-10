<?php

declare(strict_types=1);

namespace App\Modules\InventoryProcurement\Application\Workflows;

use App\Modules\InventoryProcurement\Domain\Models\IngredientStockMovement;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class InventoryStockReconciliationService
{
    private const EPSILON = 0.0005;

    /**
     * @return array<string,mixed>
     */
    public function summary(int $sampleLimit = 5, int $movementSampleLimit = 5): array
    {
        $sampleLimit = max(1, min(25, $sampleLimit));
        $movementSampleLimit = max(1, min(25, $movementSampleLimit));

        // Reconciliation nay co tinh schema-tolerant de van chay duoc ca khi local db chua co du bang/cot.
        if (! Schema::hasTable('ingredient_stock_movements')) {
            return [
                'table_present' => false,
                'missing_columns' => [],
                'dimensions' => [],
                'stock_on_hand_group_count' => 0,
                'negative_group_count' => 0,
                'impossible_movement_count' => 0,
                'negative_examples' => [],
                'impossible_examples' => [],
            ];
        }

        $dimensions = $this->stockDimensions();
        $requiredColumns = array_values(array_unique(array_merge([
            'movement_id',
            'ingredient_id',
            'movement_type',
            'quantity_delta',
        ], $dimensions)));
        $missingColumns = array_values(array_filter(
            $requiredColumns,
            static fn (string $column): bool => ! Schema::hasColumn('ingredient_stock_movements', $column),
        ));

        // Neu contract bang chua du, service tra ve report thieu cot thay vi fail cung.
        if ($missingColumns !== []) {
            return [
                'table_present' => true,
                'missing_columns' => $missingColumns,
                'dimensions' => $dimensions,
                'stock_on_hand_group_count' => 0,
                'negative_group_count' => 0,
                'impossible_movement_count' => 0,
                'negative_examples' => [],
                'impossible_examples' => [],
            ];
        }

        $groupQuery = $this->stockOnHandGroupQuery($dimensions);
        $negativeRows = (clone $groupQuery)
            ->havingRaw('SUM(CAST(quantity_delta AS DECIMAL(18,3))) < ?', [self::EPSILON * -1])
            ->orderByRaw('SUM(CAST(quantity_delta AS DECIMAL(18,3))) ASC')
            ->limit($sampleLimit)
            ->get();

        // negative_examples giup operator thay ngay nhom ton kho nao dang am va movement nao tham gia vao nhom do.
        $negativeExamples = $negativeRows
            ->map(function (object $row) use ($dimensions, $movementSampleLimit): array {
                $example = [
                    'computed_quantity' => $this->formatQuantity($row->computed_quantity ?? 0),
                    'movement_count' => (int) ($row->movement_count ?? 0),
                    'movement_sample_ids' => $this->movementIdsForGroup($dimensions, $row, $movementSampleLimit),
                ];

                foreach ($dimensions as $dimension) {
                    $example[$dimension] = $this->normalizeDimensionValue($dimension, $row->{$dimension} ?? null);
                }

                return $example;
            })
            ->values()
            ->all();

        $impossibleQuery = $this->impossibleMovementQuery();
        // impossible_examples bat cac dong ledger vo ly nhu quantity 0, movement_type sai dau, thieu ingredient...
        $impossibleExamples = (clone $impossibleQuery)
            ->select($this->movementSampleColumns())
            ->orderBy('movement_id')
            ->limit($sampleLimit)
            ->get()
            ->map(fn (object $row): array => [
                'movement_id' => (int) ($row->movement_id ?? 0),
                'branch_id' => isset($row->branch_id) ? (int) $row->branch_id : null,
                'ingredient_id' => isset($row->ingredient_id) ? (int) $row->ingredient_id : null,
                'movement_type' => $row->movement_type !== null ? (string) $row->movement_type : null,
                'quantity_delta' => $this->formatQuantity($row->quantity_delta ?? 0),
                'unit_code' => $row->unit_code !== null ? (string) $row->unit_code : null,
                'reference_type' => $row->reference_type !== null ? (string) $row->reference_type : null,
                'reference_id' => $row->reference_id !== null ? (string) $row->reference_id : null,
            ])
            ->values()
            ->all();

        return [
            'table_present' => true,
            'missing_columns' => [],
            'dimensions' => $dimensions,
            'stock_on_hand_group_count' => (int) DB::query()
                ->fromSub($this->stockOnHandGroupQuery($dimensions), 'stock_groups')
                ->count(),
            'negative_group_count' => (int) DB::query()
                ->fromSub($this->stockOnHandGroupQuery($dimensions), 'stock_groups')
                ->whereRaw('CAST(computed_quantity AS DECIMAL(18,3)) < ?', [self::EPSILON * -1])
                ->count(),
            'impossible_movement_count' => (int) (clone $impossibleQuery)->count(),
            'negative_examples' => $negativeExamples,
            'impossible_examples' => $impossibleExamples,
        ];
    }

    /**
     * @return list<string>
     */
    private function stockDimensions(): array
    {
        // Dimension ton kho co the khac nhau giua cac schema, nen duoc detect dong.
        $candidates = [
            'branch_id',
            'storage_location_id',
            'storage_id',
            'location_id',
            'ingredient_id',
            'sku_id',
            'item_id',
            'unit_code',
        ];

        return array_values(array_filter(
            $candidates,
            static fn (string $column): bool => Schema::hasColumn('ingredient_stock_movements', $column),
        ));
    }

    /**
     * @param  list<string>  $dimensions
     */
    private function stockOnHandGroupQuery(array $dimensions): Builder
    {
        // Moi toan bo report ton kho deu xoay quanh group query nay: gom nhom va tinh quantity ledger.
        $query = DB::table('ingredient_stock_movements')
            ->select($dimensions)
            ->selectRaw('SUM(CAST(quantity_delta AS DECIMAL(18,3))) AS computed_quantity')
            ->selectRaw('COUNT(*) AS movement_count');

        foreach ($dimensions as $dimension) {
            $query->groupBy($dimension);
        }

        return $query;
    }

    private function impossibleMovementQuery(): Builder
    {
        // Ledger "impossible" la cac dong khong the xuat hien trong su that nghiep vu inventory.
        $positiveTypes = [
            IngredientStockMovement::TYPE_STOCK_IN,
            IngredientStockMovement::TYPE_ADJUSTMENT_INCREASE,
        ];
        $negativeTypes = [
            IngredientStockMovement::TYPE_STOCK_OUT,
            IngredientStockMovement::TYPE_ADJUSTMENT_DECREASE,
            IngredientStockMovement::TYPE_WASTAGE,
        ];

        return DB::table('ingredient_stock_movements')
            ->where(function ($query) use ($positiveTypes, $negativeTypes): void {
                $query->whereNull('ingredient_id')
                    ->orWhereNull('movement_type')
                    ->orWhereNull('quantity_delta')
                    ->orWhere('quantity_delta', '=', 0)
                    ->orWhereNotIn('movement_type', array_merge($positiveTypes, $negativeTypes))
                    ->orWhere(function ($inner) use ($positiveTypes): void {
                        $inner->whereIn('movement_type', $positiveTypes)
                            ->where('quantity_delta', '<', 0);
                    })
                    ->orWhere(function ($inner) use ($negativeTypes): void {
                        $inner->whereIn('movement_type', $negativeTypes)
                            ->where('quantity_delta', '>', 0);
                    });
            });
    }

    /**
     * @return list<mixed>
     */
    private function movementSampleColumns(): array
    {
        $columns = [];
        foreach ([
            'movement_id',
            'branch_id',
            'ingredient_id',
            'movement_type',
            'quantity_delta',
            'unit_code',
            'reference_type',
            'reference_id',
        ] as $column) {
            $columns[] = Schema::hasColumn('ingredient_stock_movements', $column)
                ? $column
                : DB::raw('NULL AS '.$column);
        }

        return $columns;
    }

    /**
     * @param  list<string>  $dimensions
     * @return list<int>
     */
    private function movementIdsForGroup(array $dimensions, object $row, int $limit): array
    {
        // Lay mot sample movement_id de operator tra nguoc tu group bi am ve cac dong ledger cu the.
        $query = DB::table('ingredient_stock_movements')->select('movement_id');

        foreach ($dimensions as $dimension) {
            $value = $row->{$dimension} ?? null;
            $value === null
                ? $query->whereNull($dimension)
                : $query->where($dimension, $value);
        }

        return $query
            ->orderBy('movement_id')
            ->limit($limit)
            ->pluck('movement_id')
            ->map(static fn (mixed $movementId): int => (int) $movementId)
            ->values()
            ->all();
    }

    private function normalizeDimensionValue(string $dimension, mixed $value): int|string|null
    {
        if ($value === null) {
            return null;
        }

        return str_ends_with($dimension, '_id') ? (int) $value : (string) $value;
    }

    private function formatQuantity(mixed $quantity): string
    {
        return number_format((float) $quantity, 3, '.', '');
    }
}

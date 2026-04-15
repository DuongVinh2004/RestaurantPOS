<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Support\AvailabilityCacheVersion;
use App\Modules\FloorOps\Domain\Audit\TableStateAuditLogger;
use Illuminate\Support\Carbon;

class RestaurantTableStateService
{
    public function isOperationallyBlocked(string $status): bool
    {
        return in_array($status, [
            RestaurantTableStatus::Blocked->value,
            RestaurantTableStatus::Maintenance->value,
        ], true);
    }

    public function isAllocatableForBooking(string $status): bool
    {
        return $status === RestaurantTableStatus::Available->value;
    }

    public function occupyTables(array $tableIds, ?Carbon $now = null, ?int $actorUserId = null, array $context = []): void
    {
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return;
        }

        $now ??= Carbon::now('UTC');

        $tables = RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            ->lockForUpdate()
            ->get()
            ->sortBy('table_id')
            ->values();

        $beforeRows = $tables->map(fn (RestaurantTable $table): array => $this->tableSnapshot($table))->all();

        $updated = 0;
        foreach ($tables as $table) {
            $status = (string) ($table->status?->value ?? $table->status);
            if ($this->isOperationallyBlocked($status) || $status === RestaurantTableStatus::Occupied->value) {
                continue;
            }

            $table->status = RestaurantTableStatus::Occupied;
            $table->updated_at = $now;
            $table->save();
            $updated++;
        }

        if ($updated > 0) {
            $afterRows = RestaurantTable::query()
                ->whereIn('table_id', $tableIds)
                ->get()
                ->sortBy('table_id')
                ->values()
                ->map(fn (RestaurantTable $table): array => $this->tableSnapshot($table))
                ->all();

            TableStateAuditLogger::insertTransitions(
                beforeRows: $beforeRows,
                afterRows: $afterRows,
                action: (string) ($context['audit_action'] ?? 'table_state_occupied'),
                actorUserId: $actorUserId,
                context: $context,
                occurredAt: $now,
            );
            AvailabilityCacheVersion::bump();
        }
    }

    public function releaseTablesSafely(array $tableIds, ?Carbon $now = null, ?int $actorUserId = null, array $context = []): void
    {
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return;
        }

        $now ??= Carbon::now('UTC');

        $tables = RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            ->lockForUpdate()
            ->get()
            ->sortBy('table_id')
            ->values();

        $beforeRows = $tables->map(fn (RestaurantTable $table): array => $this->tableSnapshot($table))->all();

        $updated = 0;
        foreach ($tables as $table) {
            $status = (string) ($table->status?->value ?? $table->status);
            if ($this->isOperationallyBlocked($status) || $status === RestaurantTableStatus::Available->value) {
                continue;
            }

            $table->status = RestaurantTableStatus::Available;
            $table->updated_at = $now;
            $table->save();
            $updated++;
        }

        if ($updated > 0) {
            $afterRows = RestaurantTable::query()
                ->whereIn('table_id', $tableIds)
                ->get()
                ->sortBy('table_id')
                ->values()
                ->map(fn (RestaurantTable $table): array => $this->tableSnapshot($table))
                ->all();

            TableStateAuditLogger::insertTransitions(
                beforeRows: $beforeRows,
                afterRows: $afterRows,
                action: (string) ($context['audit_action'] ?? 'table_state_released'),
                actorUserId: $actorUserId,
                context: $context,
                occurredAt: $now,
            );
            AvailabilityCacheVersion::bump();
        }
    }

    public function releaseModelSafely(RestaurantTable $table, ?Carbon $now = null, ?int $actorUserId = null, array $context = []): RestaurantTable
    {
        $status = (string) ($table->status?->value ?? $table->status);
        if ($this->isOperationallyBlocked($status) || $status === RestaurantTableStatus::Available->value) {
            return $table;
        }

        $now ??= Carbon::now('UTC');
        $beforeRows = [[
            'table_id' => (int) $table->table_id,
            'status' => $status,
            'row_version' => (int) ($table->row_version ?? 1),
            'updated_at' => $table->updated_at,
        ]];

        $table->status = RestaurantTableStatus::Available;
        $table->updated_at = $now;
        $table->save();

        $fresh = $table->refresh();
        TableStateAuditLogger::insertTransitions(
            beforeRows: $beforeRows,
            afterRows: [[
                'table_id' => (int) $fresh->table_id,
                'status' => (string) ($fresh->status?->value ?? $fresh->status),
                'row_version' => (int) ($fresh->row_version ?? 1),
                'updated_at' => $fresh->updated_at,
            ]],
            action: (string) ($context['audit_action'] ?? 'table_state_released'),
            actorUserId: $actorUserId,
            context: $context,
            occurredAt: $now,
        );
        AvailabilityCacheVersion::bump();

        return $fresh;
    }

    /**
     * @return array{table_id:int,status:?string,row_version:int,updated_at:mixed}
     */
    private function tableSnapshot(RestaurantTable $table): array
    {
        return [
            'table_id' => (int) $table->table_id,
            'status' => (string) ($table->status?->value ?? $table->status),
            'row_version' => (int) ($table->row_version ?? 1),
            'updated_at' => $table->updated_at,
        ];
    }

    /**
     * @param array<int|string> $tableIds
     * @return array<int,int>
     */
    private function normalizeTableIds(array $tableIds): array
    {
        $normalized = array_values(array_unique(array_map('intval', $tableIds)));
        sort($normalized);

        return array_values(array_filter($normalized, static fn (int $id) => $id > 0));
    }
}

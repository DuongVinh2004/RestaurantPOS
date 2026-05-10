<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Domain\Audit\TableStateAuditLogger;
use App\Support\AvailabilityCacheVersion;
use Illuminate\Support\Carbon;

/**
 * Chuyen trang thai ban tren so do van hanh va ghi audit cho moi lan occupy/release.
 */
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
        // Occupy chi danh dau nhung ban thuc su co the nhan khach tai thoi diem hien tai.
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return;
        }

        $now ??= Carbon::now('UTC');

        // Pha 1: lock tap table hien tai, chup before-snapshot roi moi mutate tung row.
        $tables = RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            ->lockForUpdate()
            ->get()
            ->sortBy('table_id')
            ->values();

        $beforeRows = $tables->map(fn (RestaurantTable $table): array => $this->tableSnapshot($table))->all();

        $updated = 0;
        foreach ($tables as $table) {
            // Ban dang blocked/maintenance/occupied thi bo qua, tranh overwrite state van hanh dac biet.
            $status = (string) ($table->status?->value ?? $table->status);
            if ($this->isOperationallyBlocked($status) || $status === RestaurantTableStatus::Occupied->value) {
                continue;
            }

            $table->status = RestaurantTableStatus::Occupied;
            $table->updated_at = $now;
            $table->save();
            $updated++;
        }

        // Audit va bump cache chi can khi co row thuc su doi state.
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
        // Release khong "bat" cac ban dang blocked/maintenance ve Available mot cach mu quang.
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return;
        }

        $now ??= Carbon::now('UTC');

        // Pha 1: lock tap table can release va chup before-snapshot y nhu occupy flow.
        $tables = RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            ->lockForUpdate()
            ->get()
            ->sortBy('table_id')
            ->values();

        $beforeRows = $tables->map(fn (RestaurantTable $table): array => $this->tableSnapshot($table))->all();

        $updated = 0;
        foreach ($tables as $table) {
            // Ban dang blocked/maintenance/available thi giu nguyen, khong "ep" state quay ve available.
            $status = (string) ($table->status?->value ?? $table->status);
            if ($this->isOperationallyBlocked($status) || $status === RestaurantTableStatus::Available->value) {
                continue;
            }

            $table->status = RestaurantTableStatus::Available;
            $table->updated_at = $now;
            $table->save();
            $updated++;
        }

        // Chi khi co mutate moi can ghi audit transition va bump availability generation.
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
        // Dung cho cac flow dang giu san model da lock va chi muon nha dung 1 ban.
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

        // releaseModelSafely dung cho flow da co san model lock, nen chi mutate 1 row roi ghi audit tuong ung.
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
     * @param  array<int|string>  $tableIds
     * @return array<int,int>
     */
    private function normalizeTableIds(array $tableIds): array
    {
        $normalized = array_values(array_unique(array_map('intval', $tableIds)));
        sort($normalized);

        return array_values(array_filter($normalized, static fn (int $id) => $id > 0));
    }
}

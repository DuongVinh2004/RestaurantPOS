<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\TableHold;
use App\Models\TableTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRestaurantMasterDataService
{
    public function listZones(): array
    {
        $tables = RestaurantTable::query()
            ->with('template')
            ->orderByRaw("COALESCE(zone, '')")
            ->orderBy('table_code')
            ->get();

        $grouped = [];
        foreach ($tables as $table) {
            $zone = trim((string) ($table->zone ?? ''));
            if (! array_key_exists($zone, $grouped)) {
                $grouped[$zone] = [
                    'zone' => $zone !== '' ? $zone : null,
                    'table_count' => 0,
                    'active_table_count' => 0,
                    'active_reservation_count' => 0,
                    'active_hold_count' => 0,
                    'capacity_total' => 0,
                    'is_mutable' => true,
                ];
            }

            $grouped[$zone]['table_count']++;
            if (! (bool) $table->is_deleted) {
                $grouped[$zone]['active_table_count']++;
            }
            $grouped[$zone]['capacity_total'] += (int) ($table->template?->seats ?? 0);
        }

        foreach (array_keys($grouped) as $zoneKey) {
            $usage = $this->summarizeZoneUsage($zoneKey !== '' ? $zoneKey : null);
            $grouped[$zoneKey]['active_reservation_count'] = $usage['active_reservation_count'];
            $grouped[$zoneKey]['active_hold_count'] = $usage['active_hold_count'];
            $grouped[$zoneKey]['is_mutable'] = $usage['active_reservation_count'] === 0 && $usage['active_hold_count'] === 0;
        }

        return array_values($grouped);
    }

    public function showZone(?string $zone): array
    {
        $normalized = $this->normalizeZone($zone);
        $row = collect($this->listZones())->first(static fn (array $item): bool => ($item['zone'] ?? null) === $normalized);

        if (! $row) {
            abort(404);
        }

        return $row;
    }

    public function renameZone(?string $currentZone, string $newZone): array
    {
        $currentZone = $this->normalizeZone($currentZone);
        $newZone = $this->normalizeZone($newZone);

        if ($newZone === null) {
            throw ValidationException::withMessages([
                'zone' => ['Zone name cannot be empty.'],
            ]);
        }

        if ($currentZone === $newZone) {
            return $this->showZone($currentZone);
        }

        $usage = $this->summarizeZoneUsage($currentZone);
        if ($usage['active_reservation_count'] > 0 || $usage['active_hold_count'] > 0) {
            throw ValidationException::withMessages([
                'zone' => ['Cannot rename zone while active reservations or holds still reference tables in this zone.'],
            ]);
        }

        $updated = RestaurantTable::query()
            ->when($currentZone === null,
                static fn ($query) => $query->whereNull('zone')->orWhere('zone', ''),
                static fn ($query) => $query->where('zone', $currentZone)
            )
            ->update(['zone' => $newZone]);

        if ($updated === 0) {
            abort(404);
        }

        return $this->showZone($newZone);
    }

    public function listTables(array $filters = []): Collection
    {
        return RestaurantTable::query()
            ->with('template')
            ->when(array_key_exists('zone', $filters) && trim((string) $filters['zone']) !== '', function ($query) use ($filters) {
                $query->where('zone', trim((string) $filters['zone']));
            })
            ->when(isset($filters['include_deleted']) && ! $filters['include_deleted'], fn ($query) => $query->where('is_deleted', false))
            ->orderByRaw("COALESCE(zone, '')")
            ->orderBy('table_code')
            ->get();
    }

    public function showTable(int $tableId): RestaurantTable
    {
        return RestaurantTable::query()->with('template')->findOrFail($tableId);
    }

    public function createTable(array $payload): RestaurantTable
    {
        return DB::transaction(function () use ($payload): RestaurantTable {
            $table = new RestaurantTable();
            $table->table_code = (string) $payload['table_code'];
            $table->template_id = $this->resolveTemplateId($payload);
            $table->zone = $this->normalizeZone($payload['zone'] ?? null);
            $table->pos_x = array_key_exists('pos_x', $payload) && $payload['pos_x'] !== null ? (int) $payload['pos_x'] : null;
            $table->pos_y = array_key_exists('pos_y', $payload) && $payload['pos_y'] !== null ? (int) $payload['pos_y'] : null;
            $table->status = $payload['status'] ?? RestaurantTableStatus::Available->value;
            $table->description = $payload['description'] ?? null;
            $table->is_deleted = (bool) ($payload['is_deleted'] ?? false);
            $table->price = array_key_exists('price', $payload) && $payload['price'] !== null ? (float) $payload['price'] : null;
            $table->save();

            return $table->fresh('template');
        });
    }

    public function updateTable(int $tableId, array $payload, ?int $expectedRowVersion = null): RestaurantTable
    {
        return DB::transaction(function () use ($tableId, $payload, $expectedRowVersion): RestaurantTable {
            /** @var RestaurantTable $table */
            $table = RestaurantTable::query()->with('template')->whereKey($tableId)->lockForUpdate()->firstOrFail();
            $this->assertRowVersion($table->row_version ?? null, $expectedRowVersion);

            $isUsageSensitiveChange = array_intersect(array_keys($payload), ['zone', 'template_id', 'capacity', 'seats', 'status', 'is_deleted']) !== [];
            if ($isUsageSensitiveChange && $this->tableHasActiveUsage($tableId)) {
                throw ValidationException::withMessages([
                    'table_id' => ['Cannot change zone/capacity/status/delete fields while active reservations or holds still reference this table.'],
                ]);
            }

            foreach (['table_code', 'description'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $table->{$field} = $payload[$field] !== null ? (string) $payload[$field] : null;
                }
            }

            if (array_key_exists('zone', $payload)) {
                $table->zone = $this->normalizeZone($payload['zone']);
            }

            if (array_key_exists('pos_x', $payload)) {
                $table->pos_x = $payload['pos_x'] !== null ? (int) $payload['pos_x'] : null;
            }

            if (array_key_exists('pos_y', $payload)) {
                $table->pos_y = $payload['pos_y'] !== null ? (int) $payload['pos_y'] : null;
            }

            if (array_key_exists('status', $payload)) {
                $table->status = (string) $payload['status'];
            }

            if (array_key_exists('is_deleted', $payload)) {
                $table->is_deleted = (bool) $payload['is_deleted'];
            }

            if (array_key_exists('price', $payload)) {
                $table->price = $payload['price'] !== null ? (float) $payload['price'] : null;
            }

            if (array_intersect(array_keys($payload), ['template_id', 'capacity', 'seats']) !== []) {
                $table->template_id = $this->resolveTemplateId($payload, $table->template_id !== null ? (int) $table->template_id : null);
            }

            $table->save();

            return $table->fresh('template');
        });
    }

    public function deleteTable(int $tableId, ?int $expectedRowVersion = null): RestaurantTable
    {
        return DB::transaction(function () use ($tableId, $expectedRowVersion): RestaurantTable {
            /** @var RestaurantTable $table */
            $table = RestaurantTable::query()->with('template')->whereKey($tableId)->lockForUpdate()->firstOrFail();
            $this->assertRowVersion($table->row_version ?? null, $expectedRowVersion);

            if ($this->tableHasActiveUsage($tableId)) {
                throw ValidationException::withMessages([
                    'table_id' => ['Cannot delete table while active reservations or holds still reference it.'],
                ]);
            }

            $table->is_deleted = true;
            $table->save();

            return $table->fresh('template');
        });
    }

    public function tableHasActiveUsage(int $tableId): bool
    {
        $activeReservationExists = Reservation::query()
            ->whereIn('status', ReservationStatus::activeDbValues())
            ->whereHas('tables', fn ($query) => $query->where('restaurant_tables.table_id', $tableId))
            ->exists();

        if ($activeReservationExists) {
            return true;
        }

        return TableHold::query()
            ->active()
            ->notExpired()
            ->whereHas('tables', fn ($query) => $query->where('restaurant_tables.table_id', $tableId))
            ->exists();
    }

    /** @return array{active_reservation_count:int,active_hold_count:int} */
    private function summarizeZoneUsage(?string $zone): array
    {
        $tableIds = RestaurantTable::query()
            ->when($zone === null,
                static fn ($query) => $query->whereNull('zone')->orWhere('zone', ''),
                static fn ($query) => $query->where('zone', $zone)
            )
            ->pluck('table_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($tableIds === []) {
            return [
                'active_reservation_count' => 0,
                'active_hold_count' => 0,
            ];
        }

        $activeReservationCount = Reservation::query()
            ->whereIn('status', ReservationStatus::activeDbValues())
            ->whereHas('tables', fn ($query) => $query->whereIn('restaurant_tables.table_id', $tableIds))
            ->count();

        $activeHoldCount = TableHold::query()
            ->active()
            ->notExpired()
            ->whereHas('tables', fn ($query) => $query->whereIn('restaurant_tables.table_id', $tableIds))
            ->count();

        return [
            'active_reservation_count' => (int) $activeReservationCount,
            'active_hold_count' => (int) $activeHoldCount,
        ];
    }

    private function normalizeZone(mixed $zone): ?string
    {
        $value = trim((string) ($zone ?? ''));

        return $value !== '' ? $value : null;
    }

    private function resolveTemplateId(array $payload, ?int $fallbackTemplateId = null): ?int
    {
        if (array_key_exists('template_id', $payload) && $payload['template_id'] !== null) {
            return (int) $payload['template_id'];
        }

        $capacity = $payload['capacity'] ?? $payload['seats'] ?? null;
        if ($capacity === null || $capacity === '') {
            return $fallbackTemplateId;
        }

        $seats = max(1, (int) $capacity);

        $template = TableTemplate::query()->where('seats', $seats)->orderBy('template_id')->first();
        if ($template) {
            return (int) $template->template_id;
        }

        $template = new TableTemplate();
        $template->template_code = sprintf('AUTO-%02d', $seats);
        $template->seats = $seats;
        $template->description = 'Auto-generated template';
        $template->save();

        return (int) $template->template_id;
    }

    private function assertRowVersion(mixed $actual, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($actual ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }
}

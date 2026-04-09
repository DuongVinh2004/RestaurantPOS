<?php

declare(strict_types=1);

namespace App\Services\Admin\Restaurant;

use App\Enums\ReservationStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\RestaurantTableStatus;
use App\Models\RestaurantTable;
use App\Support\HoldConflictScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRestaurantZoneService
{
    /**
     * @param array<string,mixed> $filters
     * @return array{zones: array<int, array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listZones(array $filters = []): array
    {
        $includeDeleted = (bool) ($filters['include_deleted'] ?? false);
        $includeUnzoned = array_key_exists('include_unzoned', $filters)
            ? (bool) $filters['include_unzoned']
            : true;

        $query = DB::table('restaurant_tables')
            ->select([
                'zone',
                'table_id',
                'table_code',
                'status',
            ])
            ->orderByRaw("COALESCE(zone, '') ASC")
            ->orderBy('table_code');

        if (! $includeDeleted) {
            $query->where('is_deleted', false);
        }

        $rows = $query->get();

        $zones = [];
        foreach ($rows as $row) {
            $zone = $this->normalizeZoneInput($row->zone);
            if ($zone === null && ! $includeUnzoned) {
                continue;
            }

            $key = $zone ?? '__UNZONED__';
            if (! isset($zones[$key])) {
                $zones[$key] = [
                    'zone' => $zone,
                    'zone_label' => $zone ?? 'Unzoned',
                    'is_unzoned' => $zone === null,
                    'table_count' => 0,
                    'status_breakdown' => [
                        'available' => 0,
                        'reserved' => 0,
                        'occupied' => 0,
                        'blocked' => 0,
                        'maintenance' => 0,
                    ],
                    'table_ids' => [],
                    'table_codes' => [],
                    'usage' => [
                        'active_reservation_count' => 0,
                        'active_hold_count' => 0,
                        'active_order_count' => 0,
                        'has_active_operational_links' => false,
                    ],
                    'guards' => [
                        'can_rename' => true,
                    ],
                ];
            }

            $zones[$key]['table_count']++;
            $zones[$key]['table_ids'][] = (int) $row->table_id;
            $zones[$key]['table_codes'][] = (string) $row->table_code;

            $tableStatus = $row->status instanceof RestaurantTableStatus ? $row->status->value : ($row->status !== null ? (string) $row->status : null);

            $statusKey = match ($tableStatus) {
                'Available' => 'available',
                'Reserved' => 'reserved',
                'Occupied' => 'occupied',
                'Blocked' => 'blocked',
                'Maintenance' => 'maintenance',
                default => null,
            };
            if ($statusKey !== null) {
                $zones[$key]['status_breakdown'][$statusKey]++;
            }
        }

        $allTableIds = [];
        foreach ($zones as $zoneRow) {
            foreach ((array) ($zoneRow['table_ids'] ?? []) as $tableId) {
                $allTableIds[] = (int) $tableId;
            }
        }
        $usageByTable = $this->loadUsageSnapshot(array_values(array_unique($allTableIds)));

        foreach ($zones as &$zoneRow) {
            foreach ((array) ($zoneRow['table_ids'] ?? []) as $tableId) {
                $usage = $usageByTable[(int) $tableId] ?? $this->emptyUsage();
                $zoneRow['usage']['active_reservation_count'] += (int) ($usage['active_reservation_count'] ?? 0);
                $zoneRow['usage']['active_hold_count'] += (int) ($usage['active_hold_count'] ?? 0);
                $zoneRow['usage']['active_order_count'] += (int) ($usage['active_order_count'] ?? 0);
            }

            $zoneRow['usage']['has_active_operational_links'] =
                $zoneRow['usage']['active_reservation_count'] > 0
                || $zoneRow['usage']['active_hold_count'] > 0
                || $zoneRow['usage']['active_order_count'] > 0;
            $zoneRow['guards']['can_rename'] = ! $zoneRow['usage']['has_active_operational_links'];
        }
        unset($zoneRow);

        $zoneList = array_values($zones);
        usort($zoneList, static fn (array $a, array $b): int => [$a['is_unzoned'] ? 1 : 0, $a['zone_label']] <=> [$b['is_unzoned'] ? 1 : 0, $b['zone_label']]);

        return [
            'zones' => $zoneList,
            'meta' => [
                'action' => 'admin_restaurant_zones',
                'summary' => [
                    'total_zones' => count($zoneList),
                    'total_tables' => array_sum(array_map(static fn (array $zone): int => (int) $zone['table_count'], $zoneList)),
                    'include_unzoned' => $includeUnzoned,
                    'include_deleted' => $includeDeleted,
                ],
            ],
        ];
    }

    /**
     * @return array{from_zone:?string,to_zone:?string,affected_table_count:int}
     */
    public function renameZone(mixed $fromZoneInput, mixed $toZoneInput): array
    {
        $fromZone = $this->normalizeZoneInput($fromZoneInput);
        $toZone = $this->normalizeZoneInput($toZoneInput);

        if ($fromZone === $toZone) {
            throw ValidationException::withMessages([
                'to_zone' => ['Target zone must be different from the source zone.'],
            ]);
        }

        return DB::transaction(function () use ($fromZone, $toZone): array {
            $query = RestaurantTable::query();
            if ($fromZone === null) {
                $query->where(function ($builder): void {
                    $builder->whereNull('zone')->orWhere('zone', '');
                });
            } else {
                $query->where('zone', $fromZone);
            }

            $tables = $query->lockForUpdate()->get();
            $tableIds = $tables->modelKeys();
            $usageByTable = $this->loadUsageSnapshot($tableIds);

            foreach ($tables as $table) {
                $usage = $usageByTable[(int) $table->table_id] ?? $this->emptyUsage();
                $currentStatus = (string) ($table->status?->value ?? $table->status);
                $runtimeOwnedStatus = in_array($currentStatus, [RestaurantTableStatus::Occupied->value, RestaurantTableStatus::Reserved->value], true);

                if ($runtimeOwnedStatus || (bool) ($usage['has_active_operational_links'] ?? false)) {
                    throw ValidationException::withMessages([
                        'from_zone' => ['Cannot rename a zone while any table in that zone is operationally linked.'],
                    ]);
                }
            }

            foreach ($tables as $table) {
                $table->zone = $toZone;
                $table->save();
            }

            return [
                'from_zone' => $fromZone,
                'to_zone' => $toZone,
                'affected_table_count' => $tables->count(),
            ];
        });
    }


    /**
     * @param list<int> $tableIds
     * @return array<int, array{active_reservation_count:int,active_hold_count:int,active_order_count:int,has_active_operational_links:bool}>
     */
    private function loadUsageSnapshot(array $tableIds): array
    {
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        if ($tableIds === []) {
            return [];
        }

        $now = now('UTC');

        $reservationCounts = DB::table('reservation_tables')
            ->join('reservations', 'reservations.reservation_id', '=', 'reservation_tables.reservation_id')
            ->whereIn('reservation_tables.table_id', $tableIds)
            ->whereIn('reservations.status', ReservationStatus::activeDbValues())
            ->where('reservations.end_time', '>', $now)
            ->selectRaw('reservation_tables.table_id, COUNT(*) as aggregate_count')
            ->groupBy('reservation_tables.table_id')
            ->pluck('aggregate_count', 'reservation_tables.table_id');

        $holdCounts = collect();
        if (DB::getSchemaBuilder()->hasTable('table_holds') && DB::getSchemaBuilder()->hasTable('table_hold_details')) {
            $holdCountsQuery = DB::table('table_hold_details')
                ->join('table_holds', 'table_holds.hold_id', '=', 'table_hold_details.hold_id')
                ->whereIn('table_hold_details.table_id', $tableIds)
                ->where('table_holds.end_time', '>', $now);

            HoldConflictScope::apply($holdCountsQuery, 'table_holds', $now);

            $holdCounts = $holdCountsQuery
                ->selectRaw('table_hold_details.table_id, COUNT(*) as aggregate_count')
                ->groupBy('table_hold_details.table_id')
                ->pluck('aggregate_count', 'table_hold_details.table_id');
        }

        $orderCounts = collect();
        if (DB::getSchemaBuilder()->hasTable('reservation_orders') && DB::getSchemaBuilder()->hasTable('reservation_tables')) {
            $orderCounts = DB::table('reservation_tables')
                ->join('reservation_orders', 'reservation_orders.reservation_id', '=', 'reservation_tables.reservation_id')
                ->whereIn('reservation_tables.table_id', $tableIds)
                ->where('reservation_orders.status', ReservationOrderStatus::Active->value)
                ->selectRaw('reservation_tables.table_id, COUNT(*) as aggregate_count')
                ->groupBy('reservation_tables.table_id')
                ->pluck('aggregate_count', 'reservation_tables.table_id');
        }

        $snapshot = [];
        foreach ($tableIds as $tableId) {
            $reservationCount = (int) ($reservationCounts[$tableId] ?? 0);
            $holdCount = (int) ($holdCounts[$tableId] ?? 0);
            $orderCount = (int) ($orderCounts[$tableId] ?? 0);
            $snapshot[$tableId] = [
                'active_reservation_count' => $reservationCount,
                'active_hold_count' => $holdCount,
                'active_order_count' => $orderCount,
                'has_active_operational_links' => $reservationCount > 0 || $holdCount > 0 || $orderCount > 0,
            ];
        }

        return $snapshot;
    }

    /**
     * @return array{active_reservation_count:int,active_hold_count:int,active_order_count:int,has_active_operational_links:bool}
     */
    private function emptyUsage(): array
    {
        return [
            'active_reservation_count' => 0,
            'active_hold_count' => 0,
            'active_order_count' => 0,
            'has_active_operational_links' => false,
        ];
    }

    private function normalizeZoneInput(mixed $zone): ?string
    {
        if ($zone === null) {
            return null;
        }

        $value = trim((string) $zone);

        return $value === '' ? null : $value;
    }
}

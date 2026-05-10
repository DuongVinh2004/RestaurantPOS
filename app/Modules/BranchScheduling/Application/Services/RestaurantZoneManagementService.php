<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Enums\TableHoldStatus;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RestaurantZoneManagementService
{
    // --- BƯỚC 1: LIỆT KÊ VÀ TỔNG HỢP KHU VỰC BÀN (LIST ZONES) ---
    // Nghiệp vụ: Chuyển đổi danh sách các bàn riêng lẻ thành một bản báo cáo tổng hợp theo từng Khu vực (Zone).
    // Phục vụ cho màn hình quản trị Sơ đồ nhà hàng (Ví dụ: Tầng 1 có bao nhiêu bàn trống, Sân vườn có bao nhiêu bàn bận).
    /**
     * @param  array<string,mixed>  $filters
     * @return array{zones: array<int, array<string,mixed>>, meta: array<string,mixed>}
     */
    public function listZones(array $filters = []): array
    {
        $includeDeleted = (bool) ($filters['include_deleted'] ?? false);
        $includeUnzoned = array_key_exists('include_unzoned', $filters)
            ? (bool) $filters['include_unzoned']
            : true;

        // [BEST PRACTICE]: Optimized Query for Aggregation
        // Thay vì truy vấn SELECT * tốn bộ nhớ, chỉ SELECT đúng 4 cột cần thiết để tổng hợp.
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

        // --- BƯỚC 2: PHÂN NHÓM VÀ KHỞI TẠO CẤU TRÚC (GROUPING & STRUCTURING) ---
        foreach ($rows as $row) {
            $zone = $this->normalizeZoneInput($row->zone);
            if ($zone === null && ! $includeUnzoned) {
                continue;
            }

            // Gộp các bàn chưa được gán khu vực vào chung một nhóm '__UNZONED__'
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

            // Đếm số lượng bàn và thống kê trạng thái
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

        // --- BƯỚC 3: ĐÍNH KÈM THỐNG KÊ SỬ DỤNG (ATTACH USAGE SNAPSHOT) ---
        // [BEST PRACTICE]: N+1 Query Prevention (Chống lỗi N+1 Query)
        // Gom toàn bộ ID của TẤT CẢ các bàn trong mọi khu vực thành 1 mảng duy nhất ($allTableIds).
        // Sau đó gọi hàm loadUsageSnapshot() đúng MỘT lần để lấy thống kê đặt chỗ/đang ăn.
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

            // Nếu trong nguyên một khu vực (ví dụ Tầng 1) có ÍT NHẤT 1 bàn đang có khách ngồi / đang đặt trước,
            // Khu vực đó sẽ bị khóa (has_active_operational_links = true) để ngăn Admin đổi tên bừa bãi.
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

    // --- BƯỚC 4: ĐỔI TÊN KHU VỰC HÀNG LOẠT (BULK RENAME ZONE) ---
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

            // [BEST PRACTICE]: Bulk Pessimistic Locking
            // Khóa toàn bộ các bàn thuộc khu vực cũ lại để không ai có thể xếp thêm khách vào
            // trong quá trình Admin đang đổi tên khu vực.
            $tables = $query->lockForUpdate()->get();
            $tableIds = $tables->modelKeys();
            $usageByTable = $this->loadUsageSnapshot($tableIds);

            foreach ($tables as $table) {
                $usage = $usageByTable[(int) $table->table_id] ?? $this->emptyUsage();
                $currentStatus = (string) ($table->status?->value ?? $table->status);
                $runtimeOwnedStatus = in_array($currentStatus, [RestaurantTableStatus::Occupied->value, RestaurantTableStatus::Reserved->value], true);

                // [BEST PRACTICE]: Pre-flight Dependency Check (Kiểm tra phụ thuộc trước khi chạy)
                // Nghiệp vụ: Cấm đổi tên "Tầng 1" thành "Sảnh ngoài" nếu ở Tầng 1 đang có bàn có khách ngồi,
                // hoặc đang có đơn gọi món chưa tính tiền. Điều này tránh làm hỏng các báo cáo doanh thu theo khu vực.
                if ($runtimeOwnedStatus || (bool) ($usage['has_active_operational_links'] ?? false)) {
                    throw ValidationException::withMessages([
                        'from_zone' => ['Cannot rename a zone while any table in that zone is operationally linked.'],
                    ]);
                }
            }

            // Đổi tên hàng loạt
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

    // --- BƯỚC 5: TIỆN ÍCH KIỂM TRA TRẠNG THÁI (USAGE SNAPSHOT) ---
    /**
     * @param  list<int>  $tableIds
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
                ->where('table_holds.end_time', '>', $now)
                ->where(function ($query) use ($now): void {
                    $query
                        ->where(function ($holdQuery) use ($now): void {
                            $holdQuery
                                ->whereIn('table_holds.hold_status', [
                                    TableHoldStatus::Holding->value,
                                    TableHoldStatus::Pending->value,
                                ])
                                ->where('table_holds.expire_at', '>', $now);
                        })
                        ->orWhere(function ($holdQuery): void {
                            $holdQuery
                                ->where('table_holds.hold_status', TableHoldStatus::Confirmed->value)
                                ->whereNotNull('table_holds.confirmed_reservation_id');
                        });
                });

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

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
    // --- BƯỚC 1: KIỂM TRA ĐIỀU KIỆN TRẠNG THÁI (STATE GUARDS) ---
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

    // --- BƯỚC 2: CHUYỂN TRẠNG THÁI "ĐANG SỬ DỤNG" (OCCUPY TABLES) ---
    // Nghiệp vụ: Khi khách hàng bước vào quán và ngồi xuống bàn (Check-in),
    // hoặc khi nhân viên bắt đầu mở bill gọi món, hệ thống cần đánh dấu bàn đó
    // chuyển sang màu đỏ (Occupied) trên sơ đồ để không ai được phép xếp thêm khách vào nữa.
    public function occupyTables(array $tableIds, ?Carbon $now = null, ?int $actorUserId = null, array $context = []): void
    {
        // Occupy chi danh dau nhung ban thuc su co the nhan khach tai thoi diem hien tai.
        $tableIds = $this->normalizeTableIds($tableIds);
        if ($tableIds === []) {
            return;
        }

        $now ??= Carbon::now('UTC');

        // [BEST PRACTICE]: Batch Pessimistic Locking (Khóa bi quan theo lô)
        // Pha 1: lock tap table hien tai, chup before-snapshot roi moi mutate tung row.
        // Ngăn chặn Race Condition: Giả sử 2 nhân viên (Lễ tân ở cửa và Phục vụ ở trong)
        // cùng lúc bấm xếp khách vào Bàn số 5. Lệnh lockForUpdate() sẽ bắt 1 người phải đứng đợi
        // cho đến khi người kia hoàn tất quá trình lưu dữ liệu.
        $tables = RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            ->lockForUpdate()
            ->get()
            ->sortBy('table_id')
            ->values();

        // [BEST PRACTICE]: Audit Trail (Truy vết thay đổi)
        // Chụp lại bức ảnh (snapshot) của toàn bộ các bàn TRƯỚC KHI chúng bị đổi trạng thái.
        $beforeRows = $tables->map(fn (RestaurantTable $table): array => $this->tableSnapshot($table))->all();

        $updated = 0;
        foreach ($tables as $table) {
            // Ban dang blocked/maintenance/occupied thi bo qua, tranh overwrite state van hanh dac biet.
            // Domain Invariant: Nếu bàn đang hỏng (Maintenance) thì không được phép đè trạng thái thành Có khách (Occupied).
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
            // Chụp lại bức ảnh SAU KHI đã đổi trạng thái thành công
            $afterRows = RestaurantTable::query()
                ->whereIn('table_id', $tableIds)
                ->get()
                ->sortBy('table_id')
                ->values()
                ->map(fn (RestaurantTable $table): array => $this->tableSnapshot($table))
                ->all();

            // Lưu cả "Before" và "After" vào sổ nhật ký hệ thống.
            TableStateAuditLogger::insertTransitions(
                beforeRows: $beforeRows,
                afterRows: $afterRows,
                action: (string) ($context['audit_action'] ?? 'table_state_occupied'),
                actorUserId: $actorUserId,
                context: $context,
                occurredAt: $now,
            );

            // [BEST PRACTICE]: Cache Invalidation (Xóa cache diện rộng)
            // AvailabilityCacheVersion::bump() đóng vai trò như việc "kéo còi báo động".
            // Nó báo cho tất cả các luồng tính toán (như luồng check xem khách hàng có đặt được bàn online hay không)
            // rằng "Sơ đồ bàn vừa thay đổi, hãy xóa hết kết quả tính toán cũ đi và tính lại từ đầu!".
            AvailabilityCacheVersion::bump();
        }
    }

    // --- BƯỚC 3: GIẢI PHÓNG BÀN (RELEASE TABLES) ---
    // Nghiệp vụ: Khi khách thanh toán xong và rời đi, hoặc khi khách hủy đặt chỗ,
    // bàn cần được làm sạch (chuyển về màu xanh - Available) để đón lượt khách tiếp theo.
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
            // Rất quan trọng: Nếu một bàn bị hỏng chân ghế (Maintenance), nhưng trên hệ thống vẫn đang kẹt 1 cái bill chưa đóng.
            // Khi thu ngân đóng bill (Release), hệ thống KHÔNG ĐƯỢC biến cái bàn hỏng đó thành Bàn Trống (Available) để xếp khách vào.
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
        // [BEST PRACTICE]: Deadlock Prevention via Sorting (Chống Deadlock bằng cách sắp xếp)
        // BẮT BUỘC phải sắp xếp ID của các bàn từ nhỏ đến lớn trước khi gọi khóa DB (lockForUpdate).
        // Nếu không sort, nhân viên A đang xử lý [Bàn 1, Bàn 2], nhân viên B đang xử lý [Bàn 2, Bàn 1]
        // sẽ tạo ra vòng lặp chết (Deadlock), làm treo cứng cơ sở dữ liệu.
        $normalized = array_values(array_unique(array_map('intval', $tableIds)));
        sort($normalized);

        return array_values(array_filter($normalized, static fn (int $id) => $id > 0));
    }
}

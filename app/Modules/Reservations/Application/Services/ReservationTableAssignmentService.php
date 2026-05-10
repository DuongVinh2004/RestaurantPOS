<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\TableHoldStatus;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use App\Modules\BranchScheduling\Domain\Models\TableHold;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nội bộ giúp xử lý vòng đời của một "Phiên giữ bàn tạm thời" (Table Hold).
 * Liên kết chặt chẽ giữa module Reservations (Đặt bàn chính thức)
 * và module BranchScheduling (Sắp xếp sơ đồ chi nhánh).
 */
class ReservationTableAssignmentService
{
    public function __construct(
        private readonly TableHoldService $tableHoldService,
    ) {}

    /**
     * --- BƯỚC 1: GIẢI MÃ ID BÀN (TABLE RESOLUTION) ---
     * Lấy mảng ID Bàn. Nếu Request truyền Hold ID thì tra từ DB,
     * nếu không có Hold ID thì lấy mảng Table ID khách truyền lên.
     *
     * @param  array<string,mixed>  $payload
     * @return list<int>
     */
    public function resolveTableIdsFromPayloadOrHold(array $payload, ?string $holdId, ?string $sessionId, Carbon $start, Carbon $end): array
    {
        // 1.1: Luồng nhân viên (Staff) tự xếp bàn trực tiếp không qua bước Hold
        if (! is_string($holdId) || $holdId === '') {
            return array_values(array_map('intval', $payload['table_ids']));
        }

        // 1.2: Luồng Khách hàng tự đặt online (Có Hold)
        // Bắt buộc phải có Session ID để chống hack (hacker đoán bừa Hold ID của người khác).
        if (! is_string($sessionId) || $sessionId === '') {
            throw ValidationException::withMessages([
                'session_id' => ['session_id là bắt buộc khi dùng hold_id.'],
            ]);
        }

        // Kỹ thuật Garbage Collection: Dọn dẹp ngay các phiên Hold đã quá hạn (VD: > 10 phút)
        // để thu hồi bàn lại cho nhà hàng trước khi check.
        $this->tableHoldService->expireStaleHolds();

        // 1.3: Thẩm định Phiên Giữ Chỗ (Hold Validation)
        $hold = DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->where('session_id', $sessionId)
            ->first();

        if (! $hold) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không tồn tại hoặc không thuộc session_id.'],
            ]);
        }

        // Bàn phải đang ở trạng thái Holding (Đang giữ) hoặc Pending (Chờ xác nhận)
        if (! in_array((string) $hold->hold_status, ['Holding', 'Pending'], true)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không ở trạng thái Holding/Pending.'],
            ]);
        }

        // Kiểm tra xem khách có nhập thông tin quá lâu (ngâm form) đến mức hết giờ Hold không?
        if (Carbon::parse((string) $hold->expire_at)->utc()->lte(Carbon::now('UTC'))) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã hết hạn.'],
            ]);
        }

        // Kỹ thuật Time Range Validation: Đảm bảo thời gian khách đặt bàn (start - end)
        // phải nằm TRỌN VẸN (bao phủ) bên trong khung giờ mà họ đã Hold trước đó.
        // Tránh việc khách lúc đầu hold bàn từ 19:00 - 20:00, nhưng lúc bấm chốt đơn lại sửa Request
        // thành 19:00 - 22:00 (vượt quá quyền hạn đã giữ).
        $holdStart = Carbon::parse((string) $hold->start_time)->utc();
        $holdEnd = isset($hold->end_time)
            ? Carbon::parse((string) $hold->end_time)->utc()
            : $holdStart->copy()->addMinutes((int) ($hold->duration_minutes ?? 0));

        if ($holdStart->gt($start) || $holdEnd->lt($end)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không bao phủ đủ khoảng thời gian reservation.'],
            ]);
        }

        // Lấy danh sách ID các bàn mà Hold này đang giữ từ bảng chi tiết (table_hold_details)
        $tableIds = DB::table('table_hold_details')
            ->where('hold_id', $holdId)
            ->pluck('table_id')
            ->map(fn ($x) => (int) $x)
            ->values()
            ->all();

        if (empty($tableIds)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không có table_ids.'],
            ]);
        }

        // 1.4 Chống Tampering (Chỉnh sửa dữ liệu Payload bẩn)
        // Nếu UI vừa gửi `hold_id` lại vừa gửi `table_ids`, ta phải verify 2 mảng này y chang nhau.
        // Hacker không thể gửi `hold_id` của bàn số 1 nhưng lại sửa `table_ids` thành bàn VIP số 9.
        if (isset($payload['table_ids']) && is_array($payload['table_ids'])) {
            $client = array_values(array_map('intval', $payload['table_ids']));
            sort($client);
            $fromHold = $tableIds;
            sort($fromHold);

            if ($client !== $fromHold) {
                throw ValidationException::withMessages([
                    'table_ids' => ['table_ids không khớp với hold_id.'],
                ]);
            }
        }

        return $tableIds;
    }

    /**
     * --- BƯỚC 2: KHÓA BẢN GHI GIỮ CHỖ (PESSIMISTIC LOCKING FOR HOLDS) ---
     * Phải chạy hàm này NAY BÊN TRONG Database Transaction của lớp ngoài.
     */
    public function lockAndAssertActiveHoldForReservation(string $holdId, string $sessionId, Carbon $start, Carbon $end): void
    {
        // Khóa riêng cái Hold này lại (lockForUpdate) để tránh trường hợp:
        // Cùng 1 SessionID, khách mở 2 tab duyệt web rồi bấm Submit cùng lúc để tạo ra 2 Đơn Đặt Bàn trùng nhau
        // từ 1 cái Hold.
        $hold = DB::table('table_holds')
            ->where('hold_id', $holdId)
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->first();

        if (! $hold) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không tồn tại hoặc không thuộc session_id.'],
            ]);
        }

        // Kiểm tra lại lần 2 các trạng thái (Double-check Pattern) sau khi Lock thành công
        if (! in_array((string) $hold->hold_status, [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value], true)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã thay đổi trạng thái trong lúc tạo reservation.'],
            ]);
        }

        if (Carbon::parse((string) $hold->expire_at)->utc()->lte(Carbon::now('UTC'))) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã hết hạn trong lúc tạo reservation.'],
            ]);
        }

        $holdStart = Carbon::parse((string) $hold->start_time)->utc();
        $holdEnd = isset($hold->end_time)
            ? Carbon::parse((string) $hold->end_time)->utc()
            : $holdStart->copy()->addMinutes((int) ($hold->duration_minutes ?? 0));

        if ($holdStart->gt($start) || $holdEnd->lt($end)) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold không còn bao phủ đủ khoảng thời gian reservation.'],
            ]);
        }
    }

    /**
     * --- BƯỚC 3: XÁC NHẬN VÀ LIÊN KẾT (CONFIRM & BIND) ---
     * Đổi trạng thái Hold thành Confirmed để giữ lịch sử, và gắn ID của Đơn Đặt Bàn (Reservation ID)
     * chính thức vào record Hold này.
     */
    public function confirmHoldForReservation(string $holdId, string $sessionId, int $reservationId, ?int $actorUserId, Carbon $now): void
    {
        /** @var TableHold|null $hold */
        $hold = TableHold::query()
            ->whereKey($holdId)
            ->where('session_id', $sessionId)
            ->whereIn('hold_status', [TableHoldStatus::Holding->value, TableHoldStatus::Pending->value])
            ->lockForUpdate()
            ->first();

        // Đoạn này là một chốt chặn siêu an toàn. Nếu trước đó không gọi `lockAndAssertActiveHoldForReservation`,
        // có thể giữa lúc save DB bị gián đoạn và cái Hold này đã bị hủy.
        if ($hold === null) {
            throw ValidationException::withMessages([
                'hold_id' => ['Hold đã thay đổi trạng thái trong lúc tạo reservation. Hãy reload rồi thử lại.'],
            ]);
        }

        $hold->hold_status = TableHoldStatus::Confirmed;
        $hold->confirmed_reservation_id = $reservationId;
        // Cho hết hạn luôn lập tức vì đã convert thành công sang Reservation
        $hold->expire_at = $now;
        $hold->updated_at = $now;
        $hold->updated_by = $actorUserId;
        $hold->save();
    }
}

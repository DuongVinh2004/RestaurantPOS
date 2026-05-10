<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Application\UseCases\Realtime;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SettlementRealtimeEventService
{
    public function __construct(
        private readonly OperationalRealtimeService $realtimeService,
    ) {}

    // --- BƯỚC 1: XÂY DỰNG GÓI TIN THANH TOÁN THÀNH CÔNG (BUILD SETTLEMENT PAYLOAD) ---
    /**
     * @return array<string,mixed>
     */
    public function buildSettlementCompletedPayload(Reservation $reservation, int $orderId): array
    {
        $reservationId = (int) $reservation->reservation_id;

        // [BEST PRACTICE]: Timezone Normalization (Chuẩn hóa múi giờ gốc)
        // Khi phát tín hiệu (broadcast) cho nhiều thiết bị khác nhau (iPad của thu ngân, màn hình bếp, điện thoại của khách),
        // tất cả dữ liệu thời gian BẮT BUỘC phải được đưa về múi giờ chuẩn UTC (ISO8601).
        // Thiết bị nhận tự chịu trách nhiệm dịch ngược về giờ địa phương của nó để hiển thị.
        $checkedOutAt = $reservation->checked_out_at instanceof Carbon
            ? $reservation->checked_out_at->copy()->setTimezone('UTC')->toIso8601String()
            : null;

        // Nghiệp vụ: Đóng gói thông tin để thông báo cho toàn nhà hàng biết "Bàn này đã trả tiền xong!".
        return [
            'reservation_id' => $reservationId,
            'order_id' => $orderId,
            // Kéo danh sách ID của các bàn đang được phục vụ bởi đơn đặt bàn này để làm mới giao diện
            'table_ids' => DB::table('reservation_tables')
                ->where('reservation_id', $reservationId)
                ->orderBy('table_id')
                ->pluck('table_id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'checked_out_at' => $checkedOutAt,
        ];
    }

    // --- BƯỚC 2: PHÁT SÓNG SỰ KIỆN THANH TOÁN (PUBLISH SETTLEMENT EVENT) ---
    /**
     * @param  array<string,mixed>|null  $payload
     */
    public function publishSettlementCompleted(?array $payload): void
    {
        if ($payload === null || $payload === []) {
            return;
        }

        // [BEST PRACTICE]: Pub/Sub Pattern (Event-Driven Architecture)
        // Đẩy gói tin đã tạo ở bước 1 vào hệ thống Pub/Sub (ví dụ Redis, Pusher, Soketi).
        // Khai báo rõ 2 kênh (channels) nhận tín hiệu là: 'board' (Sơ đồ bàn) và 'timeline' (Trục thời gian).
        // Nhờ vậy, ngay khi thu ngân in bill, màn hình sơ đồ bàn tự động xóa màu bàn đó mà không cần tải lại trang.
        $this->realtimeService->publishBoardEvent(
            'reservation.settlement_completed',
            $payload,
            ['board', 'timeline']
        );
    }

    // --- BƯỚC 3: XÂY DỰNG GÓI TIN HOÀN TIỀN VÀ HỦY BÀN (BUILD REFUND PAYLOAD) ---
    /**
     * @param  array<int,int>  $tableIds
     * @param  array<int,int>  $refundPaymentIds
     * @return array<string,mixed>
     */
    public function buildRefundCancelledPayload(
        Reservation $reservation,
        array $tableIds,
        array $refundPaymentIds,
        ?string $cancelReason
    ): array {
        $cancelledAt = $reservation->cancelled_at instanceof Carbon
            ? $reservation->cancelled_at->copy()->setTimezone('UTC')->toIso8601String()
            : null;

        // Nghiệp vụ: Đóng gói thông tin cho trường hợp "Khách đòi hủy bàn và lấy lại tiền".
        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'table_ids' => array_values(array_map('intval', $tableIds)),
            // Danh sách các giao dịch (Refund IDs) đã dùng để thối tiền lại cho khách
            'refund_payment_ids' => array_values(array_map('intval', $refundPaymentIds)),
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'cancelled_at' => $cancelledAt,
            // Lý do hủy (ví dụ: Khách không đến, Quán mất điện...)
            'cancel_reason' => $cancelReason !== null && trim($cancelReason) !== ''
                ? trim($cancelReason)
                : (string) ($reservation->cancel_reason ?? ''),
        ];
    }

    // --- BƯỚC 4: PHÁT SÓNG SỰ KIỆN HỦY BÀN (PUBLISH REFUND EVENT) ---
    /**
     * @param  array<string,mixed>|null  $payload
     */
    public function publishRefundCancelled(?array $payload): void
    {
        if ($payload === null || $payload === []) {
            return;
        }

        $this->realtimeService->publishBoardEvent(
            'reservation.refund_cancelled',
            $payload,
            ['board', 'timeline']
        );
    }
}

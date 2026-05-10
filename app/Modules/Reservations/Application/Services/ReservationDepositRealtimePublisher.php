<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\SharedKernel\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service đóng vai trò là "Nhà xuất bản" (Publisher) trong mô hình Pub/Sub.
 * Nó không làm nhiệm vụ tính toán tiền, cũng không ghi DB, mà chỉ chuyên
 * đóng gói dữ liệu và bắn qua hệ thống WebSocket/Realtime.
 */
class ReservationDepositRealtimePublisher
{
    public function __construct(
        // Inject Core Service phụ trách giao tiếp với Websocket (Pusher, Soketi, Reverb...)
        private readonly OperationalRealtimeService $realtime,
    ) {}

    /**
     * --- BƯỚC 1: PHÁT SÓNG SỰ KIỆN (BROADCAST EVENT) ---
     * Hàm này được gọi TỪ MỘT NƠI KHÁC (thường là sau khi Transaction thanh toán đã Commit thành công).
     *
     * @param  array<string,mixed>  $paymentSummary
     */
    public function publishDepositPaid(Reservation $reservation, Payment $payment, array $paymentSummary): void
    {
        $this->realtime->publishBoardEvent(
            'reservation.deposit_paid', // Tên sự kiện (Event Name) để Frontend (React) lắng nghe
            $this->buildDepositPaidPayload($reservation, $payment, $paymentSummary), // Gói dữ liệu
            ['board', 'timeline'] // Kênh (Channels): Bắn cho màn hình Sơ đồ bàn (board) và màn hình Lịch sử (timeline)
        );
    }

    /**
     * --- BƯỚC 2: ĐÓNG GÓI DỮ LIỆU (PAYLOAD BUILDER) ---
     * Trích xuất các dữ liệu cốt lõi nhất để gửi đi. Chú ý: Không gửi toàn bộ Object Reservation
     * vì nó quá to, làm nghẽn băng thông mạng (Websocket payload limit).
     *
     * * @param  array<string,mixed>  $paymentSummary
     * @return array<string,mixed>
     */
    private function buildDepositPaidPayload(Reservation $reservation, Payment $payment, array $paymentSummary): array
    {
        // Chuẩn hóa thời gian về UTC chuẩn quốc tế
        $paidAt = $payment->paid_at instanceof Carbon
            ? $payment->paid_at->copy()->setTimezone('UTC')->toIso8601String()
            : null;

        // Tuân thủ Money Pattern (Tính toán bằng số nguyên Minor Units)
        $paidMinor = Money::minorUnits($paymentSummary['deposit_net_amount'] ?? $reservation->deposit_paid_amount ?? 0, true);
        $requiredMinor = Money::minorUnits($reservation->deposit_required_amount ?? 0, true);

        return [
            'reservation_id' => (int) $reservation->reservation_id,

            // --- BƯỚC 3: TỐI ƯU TRUY VẤN N+1 (PERFORMANCE OPTIMIZATION) ---
            // ĐÂY LÀ ĐOẠN CODE ĐỈNH CAO CỦA FILE NÀY:
            // Hàm này có thể được gọi ở nhiều luồng khác nhau. Đôi khi Reservation đã được
            // Eager Load sẵn danh sách bàn (tables), đôi khi lại chưa.
            'table_ids' => $reservation->relationLoaded('tables')
                // Kịch bản A: Đã load sẵn trong RAM -> Chỉ việc map ra array (Không tốn câu query nào).
                ? $reservation->tables->pluck('table_id')->map(static fn ($id): int => (int) $id)->values()->all()

                // Kịch bản B: Chưa load sẵn -> Tuyệt đối KHÔNG gọi `$reservation->tables` vì nó sẽ sinh ra query N+1
                // kéo toàn bộ object Model Table khổng lồ lên RAM.
                // Giải pháp: Quét thẳng vào bảng trung gian (pivot table) bằng Query Builder thuần (DB::table) để lấy ID nhanh nhất.
                : DB::table('reservation_tables')
                    ->where('reservation_id', $reservation->reservation_id)
                    ->orderBy('table_id')
                    ->pluck('table_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all(),

            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'payment_id' => (int) $payment->payment_id,
            'payment_status' => (string) ($payment->status?->value ?? $payment->status),
            'deposit_status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? ''),

            // Ép ngược lại Minor Units ra số thực (Float) để Frontend dễ đọc và render
            'deposit_paid_amount' => Money::minorToFloat($paidMinor),
            'deposit_outstanding_amount' => Money::minorToFloat(max(0, $requiredMinor - $paidMinor)), // Còn nợ bao nhiêu?

            'currency' => (string) ($payment->currency ?? $reservation->bill_currency ?? 'VND'),
            'paid_at' => $paidAt,
        ];
    }
}

<?php

namespace App\Modules\Reservations\Application\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service chuyên biệt chịu trách nhiệm cấp phát Mã Đặt Bàn.
 * Áp dụng nguyên tắc Single Responsibility Principle (SRP) - Chỉ làm đúng 1 việc là sinh mã.
 */
class ReservationCodeGenerator
{
    public function generate(Carbon $startTimeUtc): string
    {
        // --- BƯỚC 1: LẤY CẤU HÌNH AN TOÀN (SAFE CONFIGURATION) ---
        // Best Practice: Luôn có giá trị mặc định (Fallback) và rào chắn (Min/Max).
        // Nếu file config/booking.php bị lỗi hoặc thiếu, hệ thống vẫn chạy bình thường.
        // max(4, ...) đảm bảo độ dài ngẫu nhiên không bao giờ bị set quá ngắn gây trùng lặp liên tục.
        $prefix = (string) config('booking.reservation_code_prefix', 'RSV');
        $randLen = max(4, (int) config('booking.reservation_code_random_len', 6));
        $attempts = max(5, (int) config('booking.reservation_code_max_attempts', 12));

        // --- BƯỚC 2: PHÂN VÙNG DỮ LIỆU BẰNG THỜI GIAN (TIME-PARTITIONING) ---
        // Domain Logic: Đưa ngày tháng (YYMMDD) vào trong mã code mang lại 2 lợi ích khổng lồ:
        // 1. Cho Vận hành: Thu ngân nhìn vào mã `RSV-231025-...` là biết ngay đơn này của ngày 25/10/2023.
        // 2. Cho Kỹ thuật: Việc ghép thêm ngày sẽ thu hẹp phạm vi rủi ro trùng lặp (Collision Domain).
        // Thay vì phải đảm bảo chuỗi ngẫu nhiên không bao giờ trùng từ khi mở quán đến lúc đóng cửa,
        // ta chỉ cần đảm bảo nó không trùng VỚI CÁC ĐƠN KHÁC TRONG CÙNG 1 NGÀY.
        $datePart = $startTimeUtc->copy()->utc()->format('ymd'); // YYMMDD

        // --- BƯỚC 3: VÒNG LẶP CHỐNG TRÙNG LẶP (COLLISION RESOLUTION LOOP) ---
        // Đây là kỹ thuật Retry Pattern. Do chuỗi ngẫu nhiên luôn có tỷ lệ trùng (dù rất nhỏ),
        // hệ thống sẽ thử tạo lại tối đa $attempts lần (mặc định 12 lần) trước khi bỏ cuộc.
        for ($i = 0; $i < $attempts; $i++) {

            // Tạo chuỗi ngẫu nhiên (chỉ dùng chữ hoa và số để thu ngân dễ đọc, tránh nhầm l - 1, O - 0)
            $random = strtoupper(Str::random($randLen));
            $code = "{$prefix}-{$datePart}-{$random}";

            // --- BƯỚC 4: THẨM ĐỊNH TÍNH DUY NHẤT VỚI DATABASE ---
            // Dùng hàm `exists()` thay vì `count()` hay `first()` để tối ưu hiệu suất truy vấn SQL.
            // Cột `reservation_code` ở database nên được đánh Index (Unique Constraint) để query này chạy siêu tốc (O(1)).
            $exists = Reservation::query()
                ->where('reservation_code', $code)
                ->exists();

            // Nếu DB trả về false (mã này chưa ai xài) -> Lập tức thoát vòng lặp và trả về mã.
            if (! $exists) {
                return $code;
            }
        }

        // --- BƯỚC 5: CHỐT CHẶN AN TOÀN (CIRCUIT BREAKER) ---
        // Nếu xui xẻo đến mức thử 12 lần vẫn trùng (hoặc do DB đang bị lỗi lock),
        // quăng lỗi RuntimeException để hủy toàn bộ Transaction đang chạy ở lớp ngoài.
        // Tuyệt đối KHÔNG ĐƯỢC dùng vòng lặp `while(true)` vì nó có thể treo máy chủ (Infinite Loop) nếu cạn kiệt tổ hợp.
        throw new RuntimeException('Cannot generate unique reservation_code after retries.');
    }
}

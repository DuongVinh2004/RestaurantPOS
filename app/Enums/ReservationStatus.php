<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ReservationStatus - Trạng thái vòng đời của một đơn đặt bàn (Reservation).
 *
 * Mô tả luồng trạng thái:
 * 1. Khách tạo đặt bàn hoặc staff tạo cho khách → Confirmed
 * 2. Khách/Staff check-in → Reserved (lịch sử: tên không chính xác, nhưng giữ lại vì đã có dữ liệu DB)
 * 3. Kết thúc phục vụ → Completed (thanh toán xong, khách về)
 * 4. Hoặc: Hủy trước khi check-in → Cancelled
 * 5. Hoặc: Khách không tới → NoShow
 * 6. Hoặc: Hết thời gian chờ (nếu pending) → Expired
 *
 * **Trạng thái hoạt động**: Confirmed, Reserved (được xem là "active" khi tính availability)
 * **Trạng thái cuối cùng**: Cancelled, Expired, Completed, NoShow (không thay đổi được nữa)
 */
enum ReservationStatus: string
{
    /**
     * Đặt bàn đã được xác nhận nhưng khách chưa check-in.
     * - Khách có thể: hủy, reschedule, thay đổi số lượng khách
     * - Bàn sẽ được block/giữ cho thời gian này
     * - Có thể liên kết với TableHold để giữ bàn sẵn
     */
    case Confirmed = 'Confirmed';
    /**
     * Khách đã check-in và đang ngồi ở bàn (hoặc các bàn nếu multi-table).
     *
     * **LƯỚI Ý: Tên "Reserved" là di sản từ cũ (không được đặt tên sai), tức "ReservedTable" chứ không phải trạng thái đặt bàn.**
     * Trong codebase, khi nói "checked-in" hay "actively occupying", nó chính là Reserved.
     *
     * - Bàn bị khóa/block cho khách này
     * - Có thể tạo order và tính tiền
     * - Không thể reschedule hoặc move khách sang bàn khác (phải release + move)
     * - Khi check-in, set thêm checked_in timestamp trên reservation
     *
     * Các method helper: ::checkedIn(), ::checkedInDbValue() để làm code rõ nghĩa
     */
    case Reserved = 'Reserved';
    /**
     * Khách hủy đặt bàn hoặc staff hủy cho khách.
     * - Có thể hủy ở trạng thái Confirmed hoặc Reserved (checked-in)
     * - Nếu hủy sau khi thanh toán: tạo refund transaction
     * - Bàn được giải phóng
     * - Trạng thái cuối (không đổi được nữa)
     */
    case Cancelled = 'Cancelled';

    /**
     * Thời gian giữ bàn (nếu pending hoặc có expiry) hết hạn mà khách không action.
     * - Thường xảy ra với waiting list khi khách không accept trong notify window
     * - Hoặc khi table hold hết hạn mà chưa tạo reservation
     * - Bàn được giải phóng
     * - Trạng thái cuối (không đổi được nữa)
     */
    case Expired = 'Expired';

    /**
     * Đơn đặt bàn hoàn thành (khách ăn xong, thanh toán, rời quán).
     * - Tuần tự: Confirmed → Reserved (check-in) → Completed
     * - Lúc này hóa đơn đã locked/settled (không thêm món được)
     * - Có thể refund toàn bộ hoặc từng dòng nếu cần
     * - Bàn được giải phóng
     * - Trạng thái cuối (không đổi được nữa)
     */
    case Completed = 'Completed';

    /**
     * Khách không tới theo lịch (no-show).
     * - Thường được set manual bởi staff hoặc tự động sau thời gian chờ
     * - Có thể áp dụng chính sách huỷ/phí no-show nếu có
     * - Bàn được giải phóng
     * - Trạng thái cuối (không đổi được nữa)
     */
    case NoShow = 'NoShow';

    /**
     * Trả về trạng thái "checked-in" (Reserved).
     * Dùng để làm code rõ ràng: thay vì so sánh với Reserved, dùng $status === ReservationStatus::checkedIn()
     */
    public static function checkedIn(): self
    {
        return self::Reserved;
    }

    /**
     * Trả về giá trị DB của trạng thái "checked-in".
     *
     * @return string Luôn trả về 'Reserved'
     */
    public static function checkedInDbValue(): string
    {
        return self::checkedIn()->value;
    }

    /**
     * Danh sách trạng thái "hoạt động" (active/live): các trạng thái khi đơn vẫn có hiệu lực.
     * Dùng để kiểm tra xem đơn có còn chiếm bàn hay không.
     *
     * @return list<string> ['Confirmed', 'Reserved']
     */
    public static function activeDbValues(): array
    {
        return [
            self::Confirmed->value,
            self::checkedInDbValue(),
        ];
    }

    /**
     * Danh sách trạng thái có thể hủy được.
     * Khi đơn ở các trạng thái này, staff/khách có thể hủy + xử lý refund nếu cần.
     *
     * @return list<string> ['Confirmed', 'Reserved']
     */
    public static function cancellableDbValues(): array
    {
        return [
            self::Confirmed->value,
            self::checkedInDbValue(),
        ];
    }

    /**
     * Kiểm tra xem giá trị string có phải là trạng thái "checked-in" hay không.
     *
     * @param  string  $value  Giá trị cần kiểm tra (ví dụ: 'Reserved')
     * @return bool true nếu là trạng thái checked-in
     */
    public static function isCheckedInDbValue(string $value): bool
    {
        return $value === self::checkedInDbValue();
    }

    /**
     * Kiểm tra xem giá trị string có phải là trạng thái "hoạt động" (active) hay không.
     *
     * @param  string  $value  Giá trị cần kiểm tra
     * @return bool true nếu là Confirmed hoặc Reserved
     */
    public static function isActiveDbValue(string $value): bool
    {
        return in_array($value, self::activeDbValues(), true);
    }
}

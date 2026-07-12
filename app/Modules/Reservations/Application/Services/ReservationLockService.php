<?php

namespace App\Modules\Reservations\Application\Services;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Service quản lý Khóa Phân Tán (Distributed Locking).
 * Nó đảm bảo rằng tại một thời điểm, chỉ có ĐÚNG MỘT tiến trình (Process)
 * được phép thao tác (sửa/xóa/đặt) trên một Đơn hàng hoặc một Bàn cụ thể.
 */
class ReservationLockService
{
    private int $lockTtlSeconds;

    private int $lockWaitSeconds;

    private string $tablePrefix;

    private string $reservationPrefix;

    /**
     * --- BƯỚC 1: KHỞI TẠO CẤU HÌNH AN TOÀN ---
     */
    public function __construct()
    {
        // TTL (Time To Live): Thời gian tối đa giữ khóa.
        // Domain Logic: Đề phòng trường hợp Server đang giữ khóa bàn tự nhiên bị cúp điện,
        // khóa sẽ tự động nhả ra sau 20s để không làm "chết đứng" cái bàn đó vĩnh viễn.
        $this->lockTtlSeconds = max(1, (int) config('booking.reservation_lock_ttl_seconds', 20));

        // Wait Seconds: Thời gian kiên nhẫn đứng đợi.
        // Domain Logic: Khách B muốn đặt bàn 5, nhưng Khách A đang thao tác. Hệ thống của Khách B
        // sẽ "đứng chờ" tối đa 5s xem Khách A có nhả khóa không. Nếu quá 5s, báo lỗi "Hệ thống bận".
        $this->lockWaitSeconds = max(0, (int) config('booking.reservation_lock_wait_seconds', 5));

        $base = (string) config('booking.reservation_lock_prefix', 'booking:lock:table');
        $this->tablePrefix = rtrim($base, ':');

        $resBase = (string) config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation');
        $this->reservationPrefix = rtrim($resBase, ':');
    }

    /**
     * --- BƯỚC 2: CÁC HÀM FACADE (TIỆN ÍCH) ---
     * Bọc (wrap) một đoạn logic ($callback) bằng khóa của Đơn đặt bàn.
     */
    public function withReservationLock(int $reservationId, Closure $callback): mixed
    {
        return $this->withLockKeys([
            $this->reservationPrefix.':'.$reservationId,
        ], $callback);
    }

    /**
     * Bọc (wrap) một đoạn logic ($callback) bằng khóa của MỘT HOẶC NHIỀU Bàn.
     */
    public function withTableLocks(array $tableIds, Closure $callback): mixed
    {
        $keys = array_map(fn (int $id) => $this->tablePrefix.':'.$id, $tableIds);

        return $this->withLockKeys($keys, $callback);
    }

    /**
     * --- BƯỚC 3: ĐỘNG CƠ KHÓA (CORE LOCKING ENGINE) ---
     
     */
    public function withLockKeys(array $keys, Closure $callback): mixed
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($k) => is_string($k) && $k !== '')));

        // KỸ THUẬT ĐỈNH CAO (DEADLOCK PREVENTION): Sắp xếp các Key theo thứ tự từ điển (Lexicographical order).
        // Giải thích: Nếu Nhân viên A thao tác "Gộp bàn 1 và 2" (Hệ thống khóa 1 rồi khóa 2).
        // Cùng lúc, Nhân viên B thao tác "Gộp bàn 2 và 1" (Hệ thống khóa 2 rồi khóa 1).
        // Nếu không có hàm sort() này -> Nhân viên A cầm khóa 1 chờ khóa 2, Nhân viên B cầm khóa 2 chờ khóa 1.
        // => Cả 2 hệ thống treo cứng vĩnh viễn (Deadlock). Hàm sort() ép buộc tất cả các request đều phải xin khóa theo đúng 1 chiều (VD: luôn xin 1 rồi mới xin 2).
        sort($keys); // Keep lock ordering stable to reduce deadlock risk.

        /** @var Repository $redis */
        // Sử dụng Redis (In-memory store) vì nó cực nhanh và hỗ trợ atomic locks rát tốt.
        $redis = Cache::store('redis');

        $locks = [];
        try {
            // Bước 3.1: Xin cấp phép khóa (Acquire Locks)
            foreach ($keys as $key) {
                // Khởi tạo đối tượng khóa với tuổi thọ $lockTtlSeconds
                $lock = $redis->lock($key, $this->lockTtlSeconds);

                // Hàm block() sẽ liên tục ping xuống Redis trong vòng $lockWaitSeconds giây để xin khóa.
                // Nếu vượt quá thời gian chờ mà vẫn có người khác đang giữ -> Văng Exception chặn đứng luồng.
                if (! $lock->block($this->lockWaitSeconds)) {
                    throw new RuntimeException("Could not acquire lock: {$key}");
                }
                $locks[] = $lock; // Cất khóa vào mảng để lát nữa nhả ra
            }

            // Bước 3.2: Khóa đã nắm trong tay, môi trường đã an toàn 100%.
            // Giờ thì thực thi đoạn Code nghiệp vụ (Sửa tiền, xếp bàn...)
            return $callback();

        } finally {
            // --- BƯỚC 4: GIẢI PHÓNG TÀI NGUYÊN (SAFE RELEASE) ---
            // Kỹ thuật Safe Release: Dùng khối `finally`. Khối này ĐẢM BẢO LUÔN CHẠY kể cả khi
            // cái $callback() ở trên bị crash (văng Exception hay Error).
            // Nếu không có `finally`, khi code bị lỗi, cái khóa sẽ bị "ngâm" trên Redis suốt 20 giây làm tê liệt quán.

            // Best Practice: Xin khóa chiều nào (1 -> 2) thì nhả khóa theo chiều ngược lại (2 -> 1).
            // Release in reverse order.
            for ($i = count($locks) - 1; $i >= 0; $i--) {
                try {
                    $locks[$i]->release();
                } catch (\Throwable) {
                    // Cố tình bọc try/catch rỗng ở đây: Khi nhả khóa, nếu có 1 khóa bị lỗi mạng không nhả được,
                    // thì vòng lặp vẫn tiếp tục đi nhả các khóa tiếp theo, không bị đứng hình.
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\CustomerAccessSessionPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Internal\PasswordLoginAuthenticator;
use App\Modules\IdentityAccess\Infrastructure\Persistence\CustomerAccessSessionStore;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;

// Vai trò: Xử lý Use Case khách hàng (Customer) đăng nhập bằng mật khẩu. Nó xác thực tài khoản, tạo phiên làm việc (Access Session) lưu giữ ngữ cảnh thiết bị, và trả về token cho ứng dụng Frontend (Customer Web/App).

/**
 * Dieu phoi login customer theo model access session:
 * xac thuc tai khoan, issue session token tu phuc vu,
 * va tra payload dung contract ma FE/customer app can.
 */
class LoginCustomerHandler
{
    public function __construct(
        private readonly PasswordLoginAuthenticator $passwordLoginAuthenticator,
        private readonly CustomerAccessSessionStore $customerAccessSessionStore,
        private readonly CustomerAccessSessionPayloadBuilder $customerAccessSessionPayloadBuilder,
    ) {}

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function handle(string $identifier, string $password, array $context = []): array
    {
        // --- BƯỚC 1: Xác thực thông tin đăng nhập và kiểm duyệt Role (Gate) ---
        // Nghiệp vụ: Khách hàng nhập email/số điện thoại và mật khẩu. Hệ thống phải chắc chắn người này đúng là khách (Role ID = 3 theo cấu hình mặc định), không cho phép nhân viên (Staff) dùng tài khoản nhân viên để đăng nhập trái phép vào luồng của khách hàng.
        // Best Practice: Ủy quyền (delegate) thuật toán băm (hashing) và so sánh mật khẩu cho PasswordLoginAuthenticator. Use Case không quan tâm pass được mã hóa bằng Bcrypt hay Argon2, tuân thủ chặt chẽ SRP (Single Responsibility Principle).
        $user = $this->passwordLoginAuthenticator->authenticate(
            $identifier,
            $password,
            (array) config('customer_auth.allowed_role_ids', [3]),
            'customer',
        );

        // --- BƯỚC 2: Khởi tạo Phiên truy cập (Access Session) và gắn Ngữ cảnh (Context) ---
        // Nghiệp vụ: Cấp cho khách hàng một Token. Kèm theo đó, "chụp" (snapshot) lại thông tin của khách và thiết bị ngay tại thời điểm đăng nhập để hỗ trợ tính năng tự phục vụ (self-service) như tự đặt bàn, xem điểm thưởng.
        $issued = $this->customerAccessSessionStore->issueForUser(
            $user,
            $this->customerSessionExpiry(),
            $this->customerSessionContext($user, $context),
        );

        // --- BƯỚC 3: Ghi nhận Dấu vết Kiểm toán (Audit Trail) ---
        // Best Practice: Chỉ gọi AuditEvent để ghi log SAU KHI phiên truy cập đã được lưu thành công vào Database (đã có access_session_id rõ ràng). Tránh việc ghi log ảo nếu database bị lỗi ở bước issueForUser.
        AuditEvent::info('customer_password_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'access_session_id' => (int) $issued['access_session']->getKey(),
        ]);

        // --- BƯỚC 4: Đóng gói dữ liệu trả về (Payload Building) ---
        // Nghiệp vụ: Trả dữ liệu về cho Frontend theo đúng chuẩn.
        // Best Practice: Sử dụng PayloadBuilder (dạng Presenter) thay vì trả thẳng Entity/Model ra ngoài. Việc này giúp Frontend (React) nhận được cấu trúc JSON thống nhất, không bị vỡ giao diện nếu sau này Database thay đổi tên cột.
        return $this->customerAccessSessionPayloadBuilder->build(
            $issued['access_session'],
            (string) $issued['plain_text_token'],
        );
    }

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    private function customerSessionContext(User $user, array $context): array
    {
        // --- BƯỚC 5: Xử lý Ngữ cảnh Phiên (Session Context Builder) ---
        // Context nay la cau noi giua auth session va reservation self-service sau khi customer dang nhap.
        // Nghiệp vụ: Thu thập thông tin định danh tĩnh (guest_name, phone) và định danh động (IP, User Agent, Device ID).
        // Phục vụ bài toán: Khi khách ấn "Đặt bàn", hệ thống lấy sẵn số điện thoại từ Context điền vào form mà không cần truy vấn lại bảng Users. Đồng thời giúp phát hiện nếu tài khoản bị truy cập từ một IP/Thiết bị lạ.
        return [
            'session_id' => isset($context['session_id']) ? trim((string) $context['session_id']) : null,
            'guest_name' => trim((string) ($context['guest_name'] ?? $user->full_name ?? '')) ?: null,
            'phone' => trim((string) ($context['phone'] ?? $user->phone ?? '')) ?: null,
            // session_meta_json giu thong tin nguon goc va thiet bi, dong thoi loai cac field rong.
            'session_meta_json' => array_filter([
                'session_label' => trim((string) ($context['session_label'] ?? 'customer_password_login')) ?: null,
                'source' => 'customer_password_login',
                'device_id' => trim((string) ($context['device_id'] ?? '')) ?: null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'created_ip' => trim((string) ($context['ip'] ?? '')) ?: null,
            'user_agent' => trim((string) ($context['user_agent'] ?? '')) ?: null,
            'source' => 'customer_password_login',
        ];
    }

    private function customerSessionExpiry(): Carbon
    {
        // --- BƯỚC 6: Tính toán Hạn sử dụng của Phiên ---
        // Nghiệp vụ: Đọc cấu hình để xem token của khách hàng sống được bao lâu (mặc định là 14 ngày). App của khách hàng (B2C) thường giữ đăng nhập rất lâu để tăng UX, khác với hệ thống của nhân viên (B2B) thường bắt đăng nhập lại sau mỗi ca làm việc (8-12 tiếng).
        return now('UTC')->addMinutes(max(1, (int) config('customer_auth.access_session_ttl_minutes', 60 * 24 * 14)));
    }
}

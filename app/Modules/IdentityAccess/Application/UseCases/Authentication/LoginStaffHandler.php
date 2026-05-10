<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\Authentication;

use App\Modules\IdentityAccess\Application\UseCases\ApiKeys\IssueStaffApiKeyHandler;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\IdentityAccess\Infrastructure\Internal\AuthenticatedStaffPayloadBuilder;
use App\Modules\IdentityAccess\Infrastructure\Internal\PasswordLoginAuthenticator;
use App\Support\AuditEvent;
use Illuminate\Support\Carbon;

// Vai trò: Điều phối nghiệp vụ đăng nhập của nhân viên (Staff), bao gồm xác thực mật khẩu, cấp phát Token và phân tách luồng bảo mật riêng biệt cho App/API và Trình duyệt Web (Browser).

/**
 * Điều phối đăng nhập staff: xác thực mật khẩu, cấp khóa phiên,
 * và trả payload đã đủ thông tin actor cho client.
 */
class LoginStaffHandler
{
    public function __construct(
        private readonly PasswordLoginAuthenticator $passwordLoginAuthenticator,
        private readonly IssueStaffApiKeyHandler $issueStaffApiKeyHandler,
        private readonly AuthenticatedStaffPayloadBuilder $authenticatedStaffPayloadBuilder,
    ) {}

    /**
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function handle(string $identifier, string $password, array $context = []): array
    {
        // --- BƯỚC 1: Xác thực danh tính ---
        // Bước 1: chỉ staff hợp lệ mới được đi tiếp sang khâu cấp phiên.
        // Pha 1: gate danh tinh. Neu mat khau sai hoac user khong thuoc nhom staff thi flow dung tai day.
        // Nghiệp vụ: Chặn đứng những nỗ lực đăng nhập bằng tài khoản của Khách hàng (Customer) vào hệ thống quản lý của Staff.
        $user = $this->authenticateStaff($identifier, $password);

        // --- BƯỚC 2: Cấp khóa phiên (API Key) ---
        // Bước 2: cấp API key đại diện cho phiên đăng nhập trên app/API.
        // Pha 2: issue mot API key dai dien cho phien app/API hien tai.
        $issued = $this->issueStaffApiKeyHandler->handle(
            (int) $user->user_id,
            $this->staffSessionLabel($context),
            $this->staffSessionExpiry(),
        );

        // Best Practice: Eager Loading (N+1 Query Prevention). Tải sẵn Role của User ngay tại đây để khi truyền xuống PayloadBuilder, hệ thống không phải tự động query DB thêm lần nào nữa.
        // Load role som de payload builder co du actor context ma khong query le tung field.
        $record = $issued['record'];
        $record->loadMissing('user.role');

        // Audit chi ghi sau khi session moi da ton tai va co id on dinh.
        AuditEvent::info('staff_password_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'staff_api_key_id' => (int) $record->getKey(),
        ]);

        // --- BƯỚC 3: Đóng gói dữ liệu trả về ---
        // Bước 3: gói actor + quyền thành payload để client dùng ngay sau login.
        // Pha 3: dong goi actor, role, capability va plaintext key cho client.
        return $this->authenticatedStaffPayloadBuilder->build(
            $record,
            (string) $issued['plaintext_key'],
        );
    }

    //

    /**
     * @param  array<string,mixed>  $context
     * @return array{payload:array<string,mixed>,refresh_token:string}
     */
    public function handleBrowserSession(string $identifier, string $password, array $context = []): array
    {
        // --- BƯỚC 1: Xử lý Đăng nhập luồng Web (Bảo mật kép) ---
        // Staff web tách refresh/access token để xoay phiên an toàn hơn.
        // Browser session tach refresh/access token de FE co the refresh an toan ma khong giu access token qua lau.
        // Best Practice: Đối với môi trường Browser dễ bị tấn công XSS, việc trả về 1 token sống lâu là rủi ro chí mạng. Hệ thống chia làm 2 Token giống hệt kiến trúc OAuth2.
        $user = $this->authenticateStaff($identifier, $password);

        // --- BƯỚC 2: Cấp Refresh Token (Sống lâu - Nằm trong HttpOnly Cookie) ---
        // Refresh token song lau hon va duoc lop cookie/session quan ly.
        $refresh = $this->issueStaffApiKeyHandler->handle(
            (int) $user->user_id,
            $this->browserRefreshSessionLabel($context),
            $this->staffSessionExpiry(), // Sống 12 tiếng
        );

        // --- BƯỚC 3: Cấp Access Token (Chết yểu - Nằm trong RAM của React) ---
        // Access token ngan han duoc tra ve ngay cho request authenticated tiep theo.
        $access = $this->issueStaffApiKeyHandler->handle(
            (int) $user->user_id,
            $this->browserAccessTokenLabel($context),
            $this->browserAccessExpiry(), // Sống 5 phút
        );

        // Load role truoc de payload browser access day du actor context ngay lap tuc.
        $accessRecord = $access['record'];
        $accessRecord->loadMissing('user.role');

        // Audit tach rieng refresh/access ids de khi review incident co du dau vet xoay phien browser.
        AuditEvent::info('staff_browser_session_login_succeeded', [
            'user_id' => (int) $user->user_id,
            'refresh_staff_api_key_id' => (int) $refresh['record']->getKey(),
            'access_staff_api_key_id' => (int) $accessRecord->getKey(),
        ]);

        // --- BƯỚC 4: Trả về kết quả phân mảnh ---
        // Access payload trả về ngay, còn refresh token dành cho lớp cookie/session.
        $payload = $this->authenticatedStaffPayloadBuilder->build(
            $accessRecord,
            (string) $access['plaintext_key'],
        );
        $payload['auth_mode'] = 'staff_browser_session';
        $payload['session_transport'] = 'refresh_cookie';

        return [
            'payload' => $payload, // Cục này đẩy lên bộ nhớ của React (Zustand/Redux)
            'refresh_token' => (string) $refresh['plaintext_key'], // Cục này thường Controller sẽ set vào Set-Cookie header
        ];
    }

    private function authenticateStaff(string $identifier, string $password): User
    {
        // Nghiệp vụ: Chốt chặn Role. Định nghĩa cứng trong config rằng chỉ Role ID 1 (Admin) và 2 (Staff) mới được phép sử dụng luồng này.
        return $this->passwordLoginAuthenticator->authenticate(
            $identifier,
            $password,
            (array) config('staff_auth.allowed_role_ids', [1, 2]),
            'staff',
        );
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function staffSessionLabel(array $context): string
    {
        // Thu tu fallback giup label session van de nhan ra du client co gui ten thiet bi hay khong.
        $label = trim((string) ($context['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $device = trim((string) ($context['device_name'] ?? ''));
        if ($device !== '') {
            return 'Auth Session - '.$device;
        }

        return 'Auth Session';
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function browserRefreshSessionLabel(array $context): string
    {
        return $this->prefixedLabel(
            (string) config('staff_auth.browser_session.refresh_label_prefix', 'Staff Browser Refresh Session'),
            $context,
        );
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function browserAccessTokenLabel(array $context): string
    {
        return $this->prefixedLabel(
            (string) config('staff_auth.browser_session.access_label_prefix', 'Staff Browser Access Token'),
            $context,
        );
    }

    /**
     * @param  array<string,mixed>  $context
     */
    private function prefixedLabel(string $prefix, array $context): string
    {
        // Browser token uu tien nhan dien theo device, roi moi den label tu do, roi moi den prefix chung.
        $prefix = trim($prefix) !== '' ? trim($prefix) : 'Staff Browser Session';
        $device = trim((string) ($context['device_name'] ?? ''));
        if ($device !== '') {
            return $prefix.' - '.$device;
        }

        $label = trim((string) ($context['label'] ?? ''));
        if ($label !== '') {
            return $prefix.' - '.$label;
        }

        return $prefix;
    }

    private function staffSessionExpiry(): Carbon
    {
        // Thời gian sống của phiên nhân viên (Mặc định: 12 tiếng = 1 ca làm việc)
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.session_ttl_minutes', 720)));
    }

    private function browserAccessExpiry(): Carbon
    {
        // Thời gian sống của Access Token trên trình duyệt (Mặc định: 5 phút để cực tiểu hóa sát thương nếu bị lộ token)
        return now('UTC')->addMinutes(max(1, (int) config('staff_auth.browser_session.access_ttl_minutes', 5)));
    }
}

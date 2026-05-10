<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\ApiKeys;

use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use Carbon\CarbonInterface;

// Vai trò: Cung cấp một cổng giao tiếp duy nhất (Application Use Case) để cấp phát API Key cho nhân viên, đảm bảo tính nhất quán của dữ liệu trả về cho mọi luồng xác thực (login, refresh token, browser session).


class IssueStaffApiKeyHandler
{
    public function __construct(
        // --- BƯỚC 1: Tiêm phụ thuộc (Dependency Injection) ---
        // Best Practice: Use Case ở tầng Application chỉ giao tiếp với tầng Persistence (StaffApiKeyStore) thông qua DI. Từ khóa `readonly` đảm bảo immutability (không bị thay đổi trạng thái trong quá trình chạy).
        private readonly StaffApiKeyStore $staffApiKeyStore,
    ) {}

    /**
     * @return array{record:StaffApiKey, plaintext_key:string}
     */
    public function handle(int $userId, string $label, ?CarbonInterface $expiresAt = null): array
    {
        // --- BƯỚC 2: Ủy quyền cấp phát Key (Delegation) ---
        // Khong them rule nghiep vu tai day; muc tieu la giu call-site dong nhat va de mock/test.

        // Nghiệp vụ: Chuyển tiếp yêu cầu (User ID, Tên định danh thiết bị, Thời gian hết hạn) xuống tầng Store để xử lý sinh chuỗi mã hóa và lưu vào database.

        // Best Practice: Anti-Corruption Layer / Wrapper Pattern. Bằng cách cố tình giữ Use Case này "ngu ngốc" (không chứa logic validate hay tính toán thời gian), bạn tạo ra một điểm duy nhất (Single Point of Contact) cực kỳ dễ mock/stub trong Unit Test của các tính năng Auth khác mà không cần đụng đến logic DB.
        return $this->staffApiKeyStore->issueKey($userId, $label, $expiresAt);
    }
}

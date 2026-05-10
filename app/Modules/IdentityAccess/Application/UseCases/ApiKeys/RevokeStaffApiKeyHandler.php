<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\ApiKeys;

use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;

// Vai trò: Xử lý nghiệp vụ thu hồi (vô hiệu hóa) API Key của nhân viên, dùng cho các luồng như đăng xuất, ép buộc đăng xuất từ xa hoặc khi phát hiện thiết bị rò rỉ bảo mật.

class RevokeStaffApiKeyHandler
{
    public function __construct(
        // --- BƯỚC 1: Tiêm phụ thuộc (Dependency Injection) ---
        // Best Practice: Giữ Use Case phụ thuộc vào Store ở tầng Persistence thay vì gọi thẳng Model Eloquent (như StaffApiKey::find(...)). Từ khóa readonly ngăn chặn việc vô tình thay đổi instance của store trong quá trình chạy.
        private readonly StaffApiKeyStore $staffApiKeyStore,
    ) {}

    public function handle(int $staffApiKeyId, ?string $reason = null): StaffApiKey
    {
        // --- BƯỚC 2: Ủy quyền thực thi thu hồi (Delegation) ---
        // Nghiệp vụ: Chuyển tiếp yêu cầu thu hồi (gồm ID của key và lý do thu hồi - dùng để audit/trace sau này) xuống tầng Store để xử lý cập nhật Database.
        // Best Practice: Giống như IssueStaffApiKeyHandler, class này đóng vai trò Wrapper mỏng. Việc tách rời hành động "Revoke" thành một Use Case riêng biệt (Command Pattern) thay vì gộp chung vào một class ApiKeyService khổng lồ giúp hệ thống tuân thủ chặt chẽ nguyên tắc Single Responsibility Principle (SRP).
        return $this->staffApiKeyStore->revokeKey($staffApiKeyId, $reason);
    }
}

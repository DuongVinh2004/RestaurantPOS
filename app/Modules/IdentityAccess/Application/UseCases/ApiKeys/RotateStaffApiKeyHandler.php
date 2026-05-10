<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\UseCases\ApiKeys;

use App\Modules\IdentityAccess\Domain\Models\StaffApiKey;
use App\Modules\IdentityAccess\Infrastructure\Persistence\StaffApiKeyStore;
use Carbon\CarbonInterface;

// Vai trò: Xử lý nghiệp vụ xoay vòng (rotate) API Key của nhân viên. Hành động này kết hợp việc thu hồi (revoke) key cũ và cấp phát (issue) key mới ngay lập tức, thường dùng cho cơ chế refresh token hoặc đổi key định kỳ để đảm bảo an toàn bảo mật.

class RotateStaffApiKeyHandler
{
    public function __construct(
        // --- BƯỚC 1: Tiêm phụ thuộc (Dependency Injection) ---
        // Best Practice: Tương tự các handler Issue và Revoke, class này nhận phụ thuộc là StaffApiKeyStore thông qua interface/class trung gian ở tầng Persistence. Khai báo readonly giúp giữ state của use case không bị thay đổi.
        private readonly StaffApiKeyStore $staffApiKeyStore,
    ) {}

    /**
     * @return array{
     * revoked:StaffApiKey,
     * record:StaffApiKey,
     * plaintext_key:string
     * }
     */
    public function handle(int $staffApiKeyId, ?string $replacementLabel = null, ?CarbonInterface $expiresAt = null): array
    {
        // --- BƯỚC 2: Ủy quyền thực thi xoay vòng (Delegation) ---
        // Nghiệp vụ: Chuyển yêu cầu đổi key (gồm ID của key cũ, tên nhãn mới nếu có, và thời gian hết hạn mới) xuống tầng Store. Tầng Store sẽ chịu trách nhiệm bọc logic "khóa key cũ + sinh key mới" vào trong một Database Transaction duy nhất (Atomic operation).
        // Best Practice: Việc gom 2 hành động Revoke và Issue vào chung một hàm Rotate ở Store giúp tránh tình trạng Race Condition hoặc dữ liệu không nhất quán (ví dụ: key cũ bị xóa mất nhưng DB lỗi nên key mới chưa được sinh ra, khiến user bị văng khỏi hệ thống đột ngột). Return type trả về đầy đủ context (key cũ đã hủy, key mới, chuỗi plaintext mới) để client xử lý mượt mà.
        return $this->staffApiKeyStore->rotateKey($staffApiKeyId, $replacementLabel, $expiresAt);
    }
}

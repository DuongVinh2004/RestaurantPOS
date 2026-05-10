<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Application\Queries;

use Illuminate\Http\Request;

// Vai trò: Phân giải và chuẩn hóa danh sách quyền hạn (capabilities) của nhân viên (Staff) dựa trên Role ID hoặc Role Name, có hỗ trợ tự động mở rộng các quyền gộp (aliases).

class StaffCapabilityResolver
{
    /**
     * @return array{
     * role_id:int,
     * role_name:string,
     * capabilities:list<string>,
     * source:string,
     * known_capabilities:list<string>
     * }
     */
    public function resolveForRequest(Request $request): array
    {
        // --- BƯỚC 1: Lấy thông tin định danh từ Request ---
        // Nghiệp vụ: Lấy Role ID và Role Name của nhân viên thực hiện request.
        // Best Practice: Dữ liệu này thường được gán vào attributes của request thông qua một Auth Middleware trước đó, đảm bảo Controller hoặc Service không phải tự đi parse token lại.
        return $this->resolveForActor(
            (int) $request->attributes->get('staff_actor_role_id', 0),
            (string) $request->attributes->get('staff_actor_role_name', '')
        );
    }

    /**
     * @return array{
     * role_id:int,
     * role_name:string,
     * capabilities:list<string>,
     * source:string,
     * known_capabilities:list<string>
     * }
     */
    public function resolveForActor(int $roleId = 0, string $roleName = ''): array
    {
        // --- BƯỚC 2: Khởi tạo trạng thái mặc định (Deny by Default) ---
        // Best Practice: Default-Deny. Nếu không map được quyền nào, mặc định là không có quyền gì cả.
        $knownCapabilities = $this->normalizeCapabilities(config('staff_capabilities.known_capabilities', []));
        $capabilities = [];
        $source = 'deny_by_default';

        $roleIdCapabilities = (array) config('staff_capabilities.role_id_capabilities', []);

        // --- BƯỚC 3: Ưu tiên map quyền theo Role ID ---
        // Nghiệp vụ: Nếu có Role ID (thường là định danh cứng trong DB), lấy danh sách quyền theo ID này trước.
        if ($roleId > 0 && array_key_exists($roleId, $roleIdCapabilities)) {
            $capabilities = $this->normalizeCapabilities($roleIdCapabilities[$roleId]);
            $source = 'role_id_capabilities';
        } elseif ($roleId > 0 && array_key_exists((string) $roleId, $roleIdCapabilities)) {
            $capabilities = $this->normalizeCapabilities($roleIdCapabilities[(string) $roleId]);
            $source = 'role_id_capabilities';
        } else {
            // --- BƯỚC 4: Fallback map quyền theo Role Name ---
            // Nghiệp vụ: Nếu không tìm thấy cấu hình cho ID, thử tìm theo Tên (ví dụ: 'Manager', 'Cashier'). Hữu ích cho các hệ thống có vai trò linh động nhưng tên gọi mang tính quy chuẩn.
            $normalizedRoleName = mb_strtolower(trim($roleName));
            $roleCapabilities = (array) config('staff_capabilities.role_capabilities', []);
            foreach ($roleCapabilities as $configuredRoleName => $configuredCapabilities) {
                if (mb_strtolower(trim((string) $configuredRoleName)) !== $normalizedRoleName) {
                    continue;
                }

                $capabilities = $this->normalizeCapabilities($configuredCapabilities);
                $source = 'role_capabilities';
                break;
            }
        }

        return [
            'role_id' => max(0, $roleId),
            'role_name' => trim($roleName),
            'capabilities' => $capabilities,
            'source' => $source,
            'known_capabilities' => $knownCapabilities,
        ];
    }

    public function isKnownCapability(string $capability): bool
    {
        $capability = trim($capability);
        if ($capability === '') {
            return false;
        }

        return in_array($capability, $this->normalizeCapabilities(config('staff_capabilities.known_capabilities', [])), true);
    }

    /**
     * @return list<string>
     */
    private function normalizeCapabilities(mixed $values): array
    {
        // --- BƯỚC 5: Chuẩn hóa danh sách quyền ---
        $aliases = $this->capabilityAliases();
        $normalized = [];

        foreach ((array) $values as $value) {
            $capability = trim((string) $value);
            if ($capability === '') {
                continue;
            }

            // Gọi hàm mở rộng quyền (bung các quyền gộp)
            $normalized = array_merge($normalized, $this->expandCapability($capability, $aliases));
        }

        // Best Practice: Loại bỏ trùng lặp và sắp xếp lại để đảm bảo tính nhất quán (Idempotent) khi so sánh quyền ở các tầng khác nhau.
        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, list<string>>  $aliases
     * @return list<string>
     */
    private function expandCapability(string $capability, array $aliases): array
    {
        // --- BƯỚC 6: Thuật toán bung quyền (Role Expansion) ---
        // Nghiệp vụ: Quản lý nhà hàng cấu hình quyền theo cụm (VD: "manage_orders" sẽ tự động bao gồm "create_order", "cancel_order", "view_order").
        // Best Practice: Sử dụng hàng đợi (Queue - BFS) để xử lý mảng lồng nhau (tránh đệ quy sâu gây tràn bộ nhớ nếu lỡ cấu hình alias lặp vòng tròn).
        $expanded = [];
        $queue = [$capability];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (! is_string($current)) {
                continue;
            }

            $current = trim($current);
            if ($current === '' || in_array($current, $expanded, true)) {
                continue; // Tránh lặp vô hạn (Infinite loop protection)
            }

            $expanded[] = $current;

            foreach ($aliases[$current] ?? [] as $alias) {
                if (! in_array($alias, $expanded, true)) {
                    $queue[] = $alias;
                }
            }
        }

        return $expanded;
    }

    /**
     * @return array<string, list<string>>
     */
    private function capabilityAliases(): array
    {
        // --- BƯỚC 7: Nạp danh sách Alias từ config ---
        $configured = (array) config('staff_capabilities.capability_aliases', []);
        $aliases = [];

        foreach ($configured as $legacy => $canonical) {
            $legacyCapability = trim((string) $legacy);
            if ($legacyCapability === '') {
                continue;
            }

            $canonicalCapabilities = [];
            foreach ((array) $canonical as $candidate) {
                $canonicalCapability = trim((string) $candidate);
                if ($canonicalCapability === '') {
                    continue;
                }

                $canonicalCapabilities[] = $canonicalCapability;
            }

            $canonicalCapabilities = array_values(array_unique($canonicalCapabilities));
            if ($canonicalCapabilities === []) {
                continue;
            }

            sort($canonicalCapabilities);
            $aliases[$legacyCapability] = $canonicalCapabilities;
        }

        ksort($aliases);

        return $aliases;
    }
}

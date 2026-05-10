<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Application\Queries;

use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Domain\Models\Branch;
use App\Modules\IdentityAccess\Application\Queries\StaffCapabilityResolver;
use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffBranchContextService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
        private readonly StaffCapabilityResolver $staffCapabilityResolver,
    ) {}

    /**
     * @return list<int>
     */
    public function accessibleBranchIds(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        // --- BƯỚC 1: PHÂN GIẢI QUYỀN TRUY CẬP CHI NHÁNH (RESOLUTION STRATEGY) ---
        // Nghiệp vụ: Đầu tiên, luôn kiểm tra xem nhân viên này có được gán ĐÍCH DANH vào chi nhánh nào không (Assigned Branches).
        // Ví dụ: Nhân viên thời vụ được điều động gấp sang hỗ trợ chi nhánh B trong 1 ngày.
        $assignedBranchIds = $this->assignedBranchIds($staffActorUserId);
        if ($assignedBranchIds !== null) {
            return $assignedBranchIds;
        }

        // Nếu không có gán đích danh, tiếp tục rà soát dựa trên chức vụ (Role) và Quyền (Capabilities).
        // Ví dụ: Role "Giám đốc" mặc định có token là `*` (All branches).
        return $this->resolveConfiguredBranchScope(
            $this->configuredBranchScopeTokens($staffActorUserId, $staffActorRoleId, $staffActorRoleName),
        );
    }

    /**
     * @return Collection<int, Branch>
     */
    public function accessibleBranches(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): Collection {
        $branchIds = $this->accessibleBranchIds($staffActorUserId, $staffActorRoleId, $staffActorRoleName);
        if ($branchIds === []) {
            return new Collection;
        }

        // Lấy thông tin chi tiết các chi nhánh, ưu tiên Chi nhánh Mặc định (is_default) xếp lên đầu danh sách.
        return Branch::query()
            ->where('is_active', true)
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('is_default')
            ->orderBy('branch_name')
            ->orderBy('branch_id')
            ->get();
    }

    /**
     * @return array{
     * accessible_branch_ids:list<int>,
     * default_branch_id:int|null,
     * current_branch_id:int|null,
     * has_default_branch_access:bool,
     * has_multi_branch_access:bool,
     * branch_selector_enabled:bool,
     * access_source:string,
     * branches_uri:string
     * }
     */
    public function branchAccessContext(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        // --- BƯỚC 2: XÂY DỰNG NGỮ CẢNH (CONTEXT) CHO FRONTEND ---
        // Nghiệp vụ: Cung cấp đầy đủ Dữ liệu Ngữ cảnh để Frontend (Staff Web) quyết định UI.
        // VD: Nếu `branch_selector_enabled` = false, thì Frontend sẽ ẨN cái menu thả xuống chọn Chi nhánh đi,
        // không cho nhân viên có cơ hội tò mò ngó sang chi nhánh khác.
        $branchIds = $this->accessibleBranchIds($staffActorUserId, $staffActorRoleId, $staffActorRoleName);
        $defaultBranchId = null;

        try {
            $defaultBranchId = (int) $this->branchContextService->defaultBranch()->branch_id;
        } catch (ModelNotFoundException) {
            $defaultBranchId = null;
        }

        $hasDefaultBranchAccess = $defaultBranchId !== null && in_array($defaultBranchId, $branchIds, true);
        $accessSource = $this->branchAccessSource($staffActorUserId, $staffActorRoleId, $staffActorRoleName);

        // Cố gắng tìm ra chi nhánh hiện tại (Current Branch) hợp lý nhất cho nhân viên ngay khi họ vừa đăng nhập
        $assignedPrimaryBranchId = $accessSource === 'staff_branch_assignments'
            ? $this->primaryAssignedBranchId($staffActorUserId)
            : null;
        $currentBranchId = $assignedPrimaryBranchId !== null && in_array($assignedPrimaryBranchId, $branchIds, true)
            ? $assignedPrimaryBranchId
            : ($hasDefaultBranchAccess ? $defaultBranchId : ($branchIds[0] ?? null));

        return [
            'accessible_branch_ids' => $branchIds,
            'default_branch_id' => $defaultBranchId,
            'current_branch_id' => $currentBranchId,
            'has_default_branch_access' => $hasDefaultBranchAccess,
            'has_multi_branch_access' => count($branchIds) > 1,
            'branch_selector_enabled' => count($branchIds) > 1,
            'access_source' => $accessSource, // Rất hữu ích cho việc Debug xem quyền này được thừa kế từ đâu
            'branches_uri' => '/api/v1/staff/branches',
        ];
    }

    /**
     * @return list<int>
     */
    public function branchScopeOrAccessible(
        ?int $staffActorUserId = null,
        ?int $requestedBranchId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        // Nếu Request API có truyền lên ID chi nhánh cụ thể, kiểm tra nghiêm ngặt xem user có quyền với chi nhánh đó không.
        if ($requestedBranchId !== null && $requestedBranchId > 0) {
            return [$this->assertAccessibleBranch($staffActorUserId, $requestedBranchId)];
        }

        return $this->accessibleBranchIds($staffActorUserId, $staffActorRoleId, $staffActorRoleName);
    }

    public function assertAccessibleBranch(?int $staffActorUserId = null, ?int $branchId = null): int
    {
        $branchIds = $this->accessibleBranchIds($staffActorUserId);
        if ($branchId !== null && $branchId > 0 && in_array($branchId, $branchIds, true)) {
            return $branchId;
        }

        // Best Practice: Ném thẳng ModelNotFound thay vì AccessDenied (403)
        // để tránh lộ thông tin (Information Disclosure) rằng chi nhánh này CÓ TỒN TẠI nhưng user không có quyền.
        // Hacker quét API sẽ chỉ nhận được lỗi 404 Not Found.
        throw (new ModelNotFoundException)->setModel(Branch::class, $branchId !== null ? [$branchId] : []);
    }

    public function assertCashierShiftBranchEligible(?int $staffActorUserId = null, mixed $branchId = null): int
    {
        // --- BƯỚC 3: KIỂM SOÁT BẢO MẬT GIAO DỊCH (FINANCIAL GUARD) ---
        // Nghiệp vụ: Chặn đứng hành vi mở ca (Shift) hoặc thanh toán hộ chi nhánh khác.
        // Tránh tình trạng Thu ngân ở HN lại xuất hóa đơn lấy tiền cho khách ở HCM.
        $resolvedBranchId = $this->branchContextService->resolveBranchId($branchId);
        if (in_array($resolvedBranchId, $this->accessibleBranchIds($staffActorUserId), true)) {
            return $resolvedBranchId;
        }

        throw ValidationException::withMessages([
            'branch_id' => [
                (string) config(
                    'staff_capabilities.messages.branch_scope_denied',
                    'Resolved staff actor is not allowed to operate on the selected branch.',
                ),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function configuredBranchScopeTokens(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): array {
        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return $this->fallbackBranchScopeTokens();
        }

        [$roleId, $roleName, $resolvedFromActorContext] = $this->resolveActorRoleContext(
            $staffActorUserId,
            $staffActorRoleId,
            $staffActorRoleName,
        );
        if (! $resolvedFromActorContext && $roleId <= 0 && $roleName === '') {
            return $this->fallbackBranchScopeTokens();
        }

        // CẢNH BÁO: Ràng buộc Vận hành Cứng (Hard Operational Constraint)
        // Nếu role này bắt buộc phải gán chi nhánh rõ ràng (như Bếp, Phục vụ) nhưng lại không có,
        // thì trả về mảng rỗng -> Coi như không có quyền vào bất kỳ chi nhánh nào, không cho làm việc!
        if ($this->operationalRoleRequiresExplicitBranchAssignment($roleId, $roleName)) {
            return [];
        }

        $explicitScopes = $this->roleScopedBranchTokens($roleId, $roleName);
        if ($explicitScopes !== []) {
            return $explicitScopes;
        }

        // Kiểm tra quyền "Thiên thần" (Wildcard '*')
        $capabilities = (array) ($this->staffCapabilityResolver->resolveForActor($roleId, $roleName)['capabilities'] ?? []);
        if (in_array('*', $capabilities, true)) {
            return ['*'];
        }

        return $this->fallbackBranchScopeTokens();
    }

    private function branchAccessSource(
        ?int $staffActorUserId = null,
        ?int $staffActorRoleId = null,
        ?string $staffActorRoleName = null,
    ): string {
        // Hàm này có cấu trúc giống hệt configuredBranchScopeTokens,
        // nhưng mục đích là trả về "Tên định danh" của nguồn cấp quyền (dùng cho Audit Log và Debug).
        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return 'fallback_branch_scopes';
        }

        if ($this->assignedBranchIds($staffActorUserId) !== null) {
            return 'staff_branch_assignments';
        }

        [$roleId, $roleName, $resolvedFromActorContext] = $this->resolveActorRoleContext(
            $staffActorUserId,
            $staffActorRoleId,
            $staffActorRoleName,
        );
        if (! $resolvedFromActorContext && $roleId <= 0 && $roleName === '') {
            return 'fallback_branch_scopes';
        }
        $roleIdScopes = (array) config('staff_capabilities.role_id_branch_scopes', []);

        if ($this->operationalRoleRequiresExplicitBranchAssignment($roleId, $roleName)) {
            return 'explicit_branch_assignment_required';
        }

        if ($roleId > 0 && (array_key_exists($roleId, $roleIdScopes) || array_key_exists((string) $roleId, $roleIdScopes))) {
            return 'role_id_branch_scopes';
        }

        $normalizedRoleName = mb_strtolower(trim($roleName));
        foreach ((array) config('staff_capabilities.role_branch_scopes', []) as $configuredRoleName => $scopeTokens) {
            if (mb_strtolower(trim((string) $configuredRoleName)) === $normalizedRoleName) {
                return 'role_branch_scopes';
            }
        }

        $capabilities = (array) ($this->staffCapabilityResolver->resolveForActor($roleId, $roleName)['capabilities'] ?? []);
        if (in_array('*', $capabilities, true)) {
            return 'capability_wildcard';
        }

        return 'fallback_branch_scopes';
    }

    /**
     * @return list<string>
     */
    private function fallbackBranchScopeTokens(): array
    {
        return $this->normalizeBranchScopeTokens(
            config('staff_capabilities.fallback_branch_scopes', ['default']),
        );
    }

    // --- BƯỚC 4: RÀNG BUỘC MÔI TRƯỜNG & CHỨC VỤ (OPERATIONAL SAFETY) ---
    private function operationalRoleRequiresExplicitBranchAssignment(int $roleId, string $roleName): bool
    {
        if ($roleId <= 0 || ! $this->deniesOperationalRoleBranchFallback()) {
            return false;
        }

        $normalizedRoleName = mb_strtolower(trim($roleName));
        if ($normalizedRoleName === '') {
            return false;
        }

        // Nghiệp vụ: Những Role trực tiếp tạo ra sai lệch số liệu vật lý (Phục vụ bưng nhầm mâm, Thu ngân thu nhầm tiền, Bếp nấu nhầm món)
        // thì tuyệt đối phải được ấn định làm việc ở 1 chi nhánh cụ thể. Không có chuyện "Fallback" hay "Wildcard" ở đây.
        return in_array($normalizedRoleName, $this->operationalBranchAssignmentRoleNames(), true);
    }

    /**
     * @return array{0:int,1:string,2:bool}
     */
    private function resolveActorRoleContext(
        ?int $staffActorUserId,
        ?int $staffActorRoleId,
        ?string $staffActorRoleName,
    ): array {
        $roleId = max(0, (int) ($staffActorRoleId ?? 0));
        $roleName = trim((string) ($staffActorRoleName ?? ''));

        if ($roleId > 0 || $roleName !== '') {
            return [$roleId, $roleName, true];
        }

        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return [0, '', false];
        }

        /** @var User|null $user */
        $user = User::query()->with('role')->find($staffActorUserId);
        if (! $user instanceof User) {
            return [0, '', false];
        }

        return [
            (int) ($user->role_id ?? 0),
            (string) ($user->role?->role_name ?? ''),
            false,
        ];
    }

    private function deniesOperationalRoleBranchFallback(): bool
    {
        // Tính năng an toàn: Chỉ kích hoạt khóa bảo vệ này ở môi trường Production/Staging.
        // Ở môi trường Dev, có thể cho phép Fallback để Lập trình viên dễ dàng Test giao diện mà không cần setup Database phức tạp.
        if (! (bool) config('staff_capabilities.deny_operational_role_branch_fallback_in_production_like', true)) {
            return false;
        }

        return $this->isProductionLikeEnvironment();
    }

    private function isProductionLikeEnvironment(): bool
    {
        $environment = mb_strtolower(trim((string) config('app.env', 'production')));
        if ($environment === '') {
            return false;
        }

        $productionLikeEnvironments = config(
            'staff_capabilities.production_like_environments',
            config('staff_auth.production_like_environments', ['production']),
        );

        $normalizedEnvironments = array_values(array_filter(array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) $productionLikeEnvironments,
        )));

        return in_array($environment, $normalizedEnvironments, true);
    }

    /**
     * @return list<string>
     */
    private function operationalBranchAssignmentRoleNames(): array
    {
        return config('staff_capabilities.operational_branch_assignment_roles', [
            'Staff',
            'Server',
            'Waiter',
            'Cashier',
            'Kitchen',
        ]);

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => mb_strtolower(trim((string) $value)),
            (array) $roles,
        )));
    }

    /**
     * Returns null when no per-staff assignment contract exists for the actor.
     *
     * @return list<int>|null
     */
    private function assignedBranchIds(?int $staffActorUserId = null): ?array
    {
        if ($staffActorUserId === null || $staffActorUserId <= 0) {
            return null;
        }

        // --- BƯỚC 5: TRUY VẤN DỮ LIỆU TẦNG THẤP (RAW QUERY OPTIMIZATION) ---
        // Best Practice: Thay vì dùng Eloquent Relationship (User->branches) gây tốn RAM để tạo các PHP Objects,
        // sử dụng Query Builder (DB::table) để Query trực tiếp bảng trung gian `staff_branch_assignments`.
        // Việc này tối ưu hoá Tốc độ cực tốt vì hàm này được gọi trong RẤT NHIỀU middleware và API.
        try {
            $assignmentQuery = DB::table('staff_branch_assignments')
                ->where('user_id', $staffActorUserId);

            if (! $assignmentQuery->exists()) {
                return null;
            }

            $branchIds = DB::table('staff_branch_assignments as sba')
                ->join('branches as b', 'b.branch_id', '=', 'sba.branch_id')
                ->where('sba.user_id', $staffActorUserId)
                ->where('b.is_active', true)
                ->whereNull('sba.revoked_at') // Không lấy các lịch sử gán quyền đã bị thu hồi
                ->orderByDesc('sba.is_primary') // Đưa chi nhánh Primary lên đầu
                ->orderBy('sba.branch_id')
                ->pluck('sba.branch_id')
                ->map(static fn ($value): int => (int) $value)
                ->filter(static fn (int $branchId): bool => $branchId > 0)
                ->values()
                ->all();
        } catch (QueryException) {
            return null;
        }

        return array_values(array_unique($branchIds));
    }

    private function primaryAssignedBranchId(?int $staffActorUserId = null): ?int
    {
        $branchIds = $this->assignedBranchIds($staffActorUserId);
        if ($branchIds === null || $branchIds === []) {
            return null;
        }

        // Do đã order by sba.is_primary giảm dần ở trên, nên phần tử [0] chắc chắn là chi nhánh chính.
        return $branchIds[0];
    }

    /**
     * @return list<string>
     */
    private function roleScopedBranchTokens(int $roleId, string $roleName): array
    {
        $roleIdScopes = (array) config('staff_capabilities.role_id_branch_scopes', []);
        if ($roleId > 0 && array_key_exists($roleId, $roleIdScopes)) {
            return $this->normalizeBranchScopeTokens($roleIdScopes[$roleId]);
        }

        if ($roleId > 0 && array_key_exists((string) $roleId, $roleIdScopes)) {
            return $this->normalizeBranchScopeTokens($roleIdScopes[(string) $roleId]);
        }

        $normalizedRoleName = mb_strtolower(trim($roleName));
        foreach ((array) config('staff_capabilities.role_branch_scopes', []) as $configuredRoleName => $scopeTokens) {
            if (mb_strtolower(trim((string) $configuredRoleName)) !== $normalizedRoleName) {
                continue;
            }

            return $this->normalizeBranchScopeTokens($scopeTokens);
        }

        return [];
    }

    /**
     * @param  list<string>  $scopeTokens
     * @return list<int>
     */
    private function resolveConfiguredBranchScope(array $scopeTokens): array
    {
        // --- BƯỚC 6: PHÂN GIẢI TOKEN THÀNH ID (TOKEN PARSING) ---
        // Biến các chuỗi cấu hình như `*`, `default`, `HN-01` thành các con số ID thực tế trong Database.
        if ($scopeTokens === []) {
            return [];
        }

        if (in_array('*', $scopeTokens, true)) {
            return Branch::query()
                ->where('is_active', true)
                ->pluck('branch_id')
                ->map(static fn ($value): int => (int) $value)
                ->values()
                ->all();
        }

        if ($scopeTokens === ['default']) {
            return $this->resolveDefaultBranchScope();
        }

        $candidateIds = [];
        $branchCodes = [];

        foreach ($scopeTokens as $scopeToken) {
            if ($scopeToken === 'default') {
                try {
                    $candidateIds[] = (int) $this->branchContextService->defaultBranch()->branch_id;
                } catch (ModelNotFoundException) {
                    continue;
                }

                continue;
            }

            if (ctype_digit($scopeToken)) {
                $candidateIds[] = (int) $scopeToken;

                continue;
            }

            $branchCodes[] = $scopeToken;
        }

        if ($branchCodes !== []) {
            $candidateIds = array_merge(
                $candidateIds,
                Branch::query()
                    ->where('is_active', true)
                    ->whereIn('branch_code', $branchCodes)
                    ->pluck('branch_id')
                    ->map(static fn ($value): int => (int) $value)
                    ->all(),
            );
        }

        // Lọc lại một lần cuối: Chỉ trả về những chi nhánh nào đang CÒN HOẠT ĐỘNG (is_active)
        $activeBranchIds = Branch::query()
            ->where('is_active', true)
            ->whereIn('branch_id', array_values(array_unique($candidateIds)))
            ->pluck('branch_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $activeBranchIds = array_values(array_unique($activeBranchIds));
        sort($activeBranchIds);

        return $activeBranchIds;
    }

    /**
     * @return list<int>
     */
    private function resolveDefaultBranchScope(): array
    {
        /** @var Branch|null $defaultBranch */
        $defaultBranch = Branch::query()
            ->where('is_default', true)
            ->orderBy('branch_id')
            ->first(['branch_id', 'is_active']);

        if ($defaultBranch instanceof Branch) {
            return (bool) $defaultBranch->is_active ? [(int) $defaultBranch->branch_id] : [];
        }

        $activeBranchId = Branch::query()
            ->where('is_active', true)
            ->orderBy('branch_id')
            ->value('branch_id');

        return $activeBranchId !== null ? [(int) $activeBranchId] : [];
    }

    /**
     * @return list<string>
     */
    private function normalizeBranchScopeTokens(mixed $values): array
    {
        $tokens = [];

        foreach ((array) $values as $value) {
            $token = trim((string) $value);
            if ($token === '') {
                continue;
            }

            $tokens[] = $token === '*' ? '*' : $token;
        }

        $tokens = array_values(array_unique($tokens));
        sort($tokens);

        return $tokens;
    }
}

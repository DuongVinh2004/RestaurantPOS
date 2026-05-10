<?php

declare(strict_types=1);

namespace App\Modules\BranchScheduling\Application\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Validation\ValidationException;

/**
 * Giu reservation va cac ban duoc gan cho no luon nam trong cung mot branch hop le.
 */
class ReservationBranchScopeService
{
    public function __construct(
        private readonly BranchContextService $branchContextService,
    ) {}

    // --- BƯỚC 1: KIỂM TRA TÍNH ĐỒNG NHẤT CỦA CÁC BÀN (TABLE BRANCH ISOLATION) ---
    /**
     * @param  iterable<mixed>  $tableBranchIds
     */
    public function resolveTableBranchId(
        iterable $tableBranchIds,
        string $singleBranchMessage = 'Assigned tables must belong to a single branch.',
        string $field = 'reservation_id',
    ): int {
        // Nhieu ban cung phuc vu mot reservation, nhung chi duoc thuoc mot chi nhanh duy nhat.
        // [BEST PRACTICE]: Cross-Tenant Validation (Kiểm tra chéo phân vùng dữ liệu)
        // Nghiệp vụ: Khách hàng có thể ghép nhiều bàn lại với nhau cho một bữa tiệc lớn (ví dụ Bàn 1 + Bàn 2),
        // nhưng hệ thống phải rào chặn tuyệt đối việc ghép Bàn 1 (ở Hà Nội) với Bàn 2 (ở TP.HCM).
        return $this->branchContextService->assertSingleBranch(
            $tableBranchIds,
            $singleBranchMessage,
            $field,
            false,
        );
    }

    // --- BƯỚC 2: XÁC ĐỊNH CHI NHÁNH CHỦ QUẢN CỦA ĐƠN ĐẶT BÀN ---
    public function resolveEffectiveReservationBranchId(mixed $reservationBranchId = null, ?int $tableBranchId = null): int
    {
        // Neu reservation da co branch ro rang thi uu tien dung no; neu chua co moi fallback sang branch cua table/default.
        // Nghiệp vụ: Đơn đặt bàn online (đặt qua Web) ban đầu có thể chưa được gán chi nhánh cụ thể nào nếu khách chưa chọn.
        // Hàm này cung cấp cơ chế Fallback: Lấy chi nhánh đã chốt -> Nếu không có thì lấy chi nhánh của Bàn -> Nếu vẫn không có thì lấy chi nhánh Mặc định.
        if ($reservationBranchId !== null && $reservationBranchId !== '') {
            return $this->branchContextService->resolveBranchId($reservationBranchId, false);
        }

        if ($tableBranchId !== null) {
            return $tableBranchId;
        }

        return $this->branchContextService->resolveBranchId(null, false);
    }

    // --- BƯỚC 3: ĐỐI CHIẾU CHI NHÁNH GIỮA ĐƠN ĐẶT BÀN VÀ BÀN VẬT LÝ ---
    public function assertReservationMatchesTableBranch(
        mixed $reservationBranchId,
        int $tableBranchId,
        string $mismatchMessage = 'Reservation branch does not match the assigned table branch.',
        string $field = 'reservation_id',
    ): int {
        // [BEST PRACTICE]: Strict Domain Boundaries (Biên giới nghiệp vụ nghiêm ngặt)
        // Rào chắn bảo vệ: Đảm bảo Đơn đặt bàn của chi nhánh A không thể "chiếm" Bàn của chi nhánh B.
        return $this->branchContextService->assertSameBranch(
            $this->resolveEffectiveReservationBranchId($reservationBranchId, $tableBranchId),
            $tableBranchId,
            $mismatchMessage,
            $field,
            false,
        );
    }

    /**
     * @param  iterable<mixed>  $tableBranchIds
     */
    public function assertReservationMatchesTableBranches(
        mixed $reservationBranchId,
        iterable $tableBranchIds,
        string $singleBranchMessage = 'Assigned tables must belong to a single branch.',
        string $mismatchMessage = 'Reservation branch does not match the assigned table branch.',
        string $field = 'reservation_id',
    ): int {
        // [BEST PRACTICE]: Iterable Materialization (Vật chất hóa vòng lặp)
        // Materialize iterable mot lan de co the vua normalize vua kiem tra "co nhieu branch hay khong".
        // Kỹ thuật này chuyển đổi các Generator/Iterable lười biếng (lazy) thành mảng bộ nhớ (RAM) một lần duy nhất.
        // Tránh việc lặp lại Generator nhiều lần gây tốn tài nguyên hoặc lỗi con trỏ dữ liệu.
        $tableBranchIds = array_values(iterator_to_array((function () use ($tableBranchIds) {
            foreach ($tableBranchIds as $tableBranchId) {
                yield $tableBranchId;
            }
        })(), false));

        if ($tableBranchIds === []) {
            return $this->resolveEffectiveReservationBranchId($reservationBranchId);
        }

        // Bước 3.1: Đảm bảo tập hợp các bàn đều chung 1 chi nhánh
        $tableBranchId = $this->resolveTableBranchId($tableBranchIds, $singleBranchMessage, $field);

        // Bước 3.2: Đảm bảo chi nhánh của các bàn đó KHỚP với chi nhánh của đơn đặt bàn
        return $this->assertReservationMatchesTableBranch(
            $reservationBranchId,
            $tableBranchId,
            $mismatchMessage,
            $field,
        );
    }

    // --- BƯỚC 4: TỐI ƯU KIỂM TRA CHI NHÁNH TRONG RAM (IN-MEMORY) ---
    // [BEST PRACTICE]: Zero-Query Validation (Kiểm chứng không chạm Database)
    // Các hàm InMemory này được thiết kế để chạy hàng ngàn lần trong các vòng lặp xử lý logic lớn
    // mà không hề gửi bất kỳ câu query SQL nào xuống Database, giúp hệ thống không bị thắt cổ chai.
    public function reservationMatchesTableBranchInMemory(mixed $reservationBranchId, mixed $tableBranchId): bool
    {
        $normalizedTableBranchId = $this->normalizeBranchId($tableBranchId);
        if ($normalizedTableBranchId === null) {
            return false;
        }

        $normalizedReservationBranchId = $this->normalizeBranchId($reservationBranchId);

        return $normalizedReservationBranchId === null || $normalizedReservationBranchId === $normalizedTableBranchId;
    }

    /**
     * @param  iterable<mixed>  $tableBranchIds
     */
    public function assertReservationMatchesTableBranchesInMemory(
        mixed $reservationBranchId,
        iterable $tableBranchIds,
        string $singleBranchMessage = 'Assigned tables must belong to a single branch.',
        string $mismatchMessage = 'Reservation branch does not match the assigned table branch.',
        string $field = 'reservation_id',
    ): ?int {
        $normalizedTableBranchIds = [];

        // Lọc và lưu ID các chi nhánh vào mảng Hash (Key-Value) để tự động loại bỏ trùng lặp
        foreach ($tableBranchIds as $tableBranchId) {
            $normalized = $this->normalizeBranchId($tableBranchId);
            if ($normalized !== null) {
                $normalizedTableBranchIds[$normalized] = true;
            }
        }

        $resolvedTableBranchIds = array_keys($normalizedTableBranchIds);
        sort($resolvedTableBranchIds);

        // Ném lỗi ngay nếu phát hiện các bàn thuộc nhiều chi nhánh khác nhau
        if (count($resolvedTableBranchIds) > 1) {
            throw ValidationException::withMessages([
                $field => [$singleBranchMessage],
            ]);
        }

        $tableBranchId = $resolvedTableBranchIds[0] ?? null;
        if ($tableBranchId === null) {
            return $this->normalizeBranchId($reservationBranchId);
        }

        if (! $this->reservationMatchesTableBranchInMemory($reservationBranchId, $tableBranchId)) {
            throw ValidationException::withMessages([
                $field => [$mismatchMessage],
            ]);
        }

        return $tableBranchId;
    }

    // --- BƯỚC 5: ĐỒNG BỘ HÓA DỮ LIỆU ĐỊNH TUYẾN ---
    /**
     * @param  iterable<mixed>  $tableBranchIds
     */
    public function syncReservationBranchOrAssert(
        Reservation $reservation,
        iterable $tableBranchIds,
        ?int $updatedBy = null,
        string $singleBranchMessage = 'Assigned tables must belong to a single branch.',
        string $mismatchMessage = 'Reservation branch does not match the assigned table branch.',
        string $field = 'reservation_id',
    ): int {
        // Neu reservation chua co branch thi dong bo tu ban; neu da co thi bat buoc phai khop.
        $tableBranchId = $this->resolveTableBranchId($tableBranchIds, $singleBranchMessage, $field);

        // Reservation branch chi duoc "day tu table" khi no con rong; neu da co thi bat buoc phai match.
        // Nghiệp vụ: Khi khách hàng đặt bàn online qua tổng đài, đơn đặt bàn lúc đầu ở dạng "Chờ xếp chỗ" (chưa thuộc chi nhánh nào).
        // Ngay khi nhân viên kéo thả một cái Bàn vật lý vào đơn này, hệ thống sẽ chốt chặt đơn đặt bàn vào chi nhánh của cái bàn đó.
        if ($reservation->branch_id === null || $reservation->branch_id === '') {
            $reservation->branch_id = $tableBranchId;
            $reservation->updated_by = $updatedBy;
            $reservation->save();

            return $tableBranchId;
        }

        return $this->assertReservationMatchesTableBranch(
            $reservation->branch_id,
            $tableBranchId,
            $mismatchMessage,
            $field,
        );
    }

    // Tiện ích làm sạch ID chi nhánh an toàn
    private function normalizeBranchId(mixed $branchId): ?int
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        if (! is_int($branchId) && ! is_float($branchId) && ! is_string($branchId)) {
            return null;
        }

        $normalized = trim((string) $branchId);
        if ($normalized === '' || ! preg_match('/^[0-9]+$/', $normalized)) {
            return null;
        }

        $resolved = (int) $normalized;

        return $resolved > 0 ? $resolved : null;
    }
}

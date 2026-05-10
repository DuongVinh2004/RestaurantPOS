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

    /**
     * @param  iterable<mixed>  $tableBranchIds
     */
    public function resolveTableBranchId(
        iterable $tableBranchIds,
        string $singleBranchMessage = 'Assigned tables must belong to a single branch.',
        string $field = 'reservation_id',
    ): int {
        // Nhieu ban cung phuc vu mot reservation, nhung chi duoc thuoc mot chi nhanh duy nhat.
        return $this->branchContextService->assertSingleBranch(
            $tableBranchIds,
            $singleBranchMessage,
            $field,
            false,
        );
    }

    public function resolveEffectiveReservationBranchId(mixed $reservationBranchId = null, ?int $tableBranchId = null): int
    {
        // Neu reservation da co branch ro rang thi uu tien dung no; neu chua co moi fallback sang branch cua table/default.
        if ($reservationBranchId !== null && $reservationBranchId !== '') {
            return $this->branchContextService->resolveBranchId($reservationBranchId, false);
        }

        if ($tableBranchId !== null) {
            return $tableBranchId;
        }

        return $this->branchContextService->resolveBranchId(null, false);
    }

    public function assertReservationMatchesTableBranch(
        mixed $reservationBranchId,
        int $tableBranchId,
        string $mismatchMessage = 'Reservation branch does not match the assigned table branch.',
        string $field = 'reservation_id',
    ): int {
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
        // Materialize iterable mot lan de co the vua normalize vua kiem tra "co nhieu branch hay khong".
        $tableBranchIds = array_values(iterator_to_array((function () use ($tableBranchIds) {
            foreach ($tableBranchIds as $tableBranchId) {
                yield $tableBranchId;
            }
        })(), false));

        if ($tableBranchIds === []) {
            return $this->resolveEffectiveReservationBranchId($reservationBranchId);
        }

        $tableBranchId = $this->resolveTableBranchId($tableBranchIds, $singleBranchMessage, $field);

        return $this->assertReservationMatchesTableBranch(
            $reservationBranchId,
            $tableBranchId,
            $mismatchMessage,
            $field,
        );
    }

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

        foreach ($tableBranchIds as $tableBranchId) {
            $normalized = $this->normalizeBranchId($tableBranchId);
            if ($normalized !== null) {
                $normalizedTableBranchIds[$normalized] = true;
            }
        }

        $resolvedTableBranchIds = array_keys($normalizedTableBranchIds);
        sort($resolvedTableBranchIds);

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

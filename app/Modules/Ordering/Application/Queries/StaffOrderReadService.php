<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Application\Queries;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Modules\Billing\Application\UseCases\Previews\SettlementAmountCalculator;
use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class StaffOrderReadService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    public function __construct(
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
        private readonly SettlementAmountCalculator $settlementAmountCalculator,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
        private readonly ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
    }

    public function findOrder(int $orderId, ?int $staffUserId = null): ?ReservationOrder
    {
        $query = $this->baseOrderQuery()
            ->where('order_id', $orderId);

        $this->constrainOrderQueryToStaffBranchScope($query, $staffUserId);

        $order = $query->first();

        if (! $order instanceof ReservationOrder) {
            return null;
        }

        return $this->attachReservationSettlementTotals($order);
    }

    public function findActiveOrderByTable(int $tableId, ?int $staffUserId = null): ?ReservationOrder
    {
        $query = $this->baseOrderQuery()
            ->where('reservation_orders.status', ReservationOrderStatus::Active->value)
            ->whereExists(function ($query) use ($tableId): void {
                $query
                    ->selectRaw('1')
                    ->from('reservation_tables')
                    ->whereColumn('reservation_tables.reservation_id', 'reservation_orders.reservation_id')
                    ->where('reservation_tables.table_id', $tableId);
            })
            ->orderByRaw($this->activeOrderPrioritySql())
            ->orderByDesc('reservation_orders.order_id');

        $this->constrainOrderQueryToStaffBranchScope($query, $staffUserId);

        $order = $query->first();

        if (! $order instanceof ReservationOrder) {
            return null;
        }

        return $this->attachReservationSettlementTotals($order);
    }

    public function findActiveOrderByReservation(int $reservationId, ?int $staffUserId = null): ?ReservationOrder
    {
        $query = $this->baseOrderQuery()
            ->where('reservation_id', $reservationId)
            ->where('status', ReservationOrderStatus::Active->value)
            ->orderByRaw($this->activeOrderPrioritySql())
            ->orderByDesc('order_id');

        $this->constrainOrderQueryToStaffBranchScope($query, $staffUserId);

        $order = $query->first();

        if (! $order instanceof ReservationOrder) {
            return null;
        }

        return $this->attachReservationSettlementTotals($order);
    }

    /**
     * @return Collection<int, ReservationOrder>
     */
    public function listOrdersByReservation(int $reservationId, ?int $staffUserId = null): Collection
    {
        $reservationQuery = Reservation::query()->where('reservation_id', $reservationId);
        if ($staffUserId !== null && $staffUserId > 0) {
            $accessibleBranchIds = $this->staffBranchContextService()->accessibleBranchIds($staffUserId);
            if ($accessibleBranchIds === []) {
                throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
            }

            $reservationQuery->whereIn('branch_id', $accessibleBranchIds);
        }

        $reservationQuery->firstOrFail();

        $query = $this->baseOrderQuery()
            ->where('reservation_id', $reservationId)
            ->orderBy('order_id');

        $this->constrainOrderQueryToStaffBranchScope($query, $staffUserId);

        return $query
            ->get()
            ->map(fn (ReservationOrder $order): ReservationOrder => $this->attachReservationSettlementTotals($order));
    }

    /**
     * @param  array{subtotal:float,discount:float,total_due:float,currency:string}|null  $billSnapshot
     */
    public function findActiveOrderForReservationModel(Reservation $reservation, ?array $billSnapshot = null): ?ReservationOrder
    {
        $order = ReservationOrder::query()
            ->with(['items.item'])
            ->where('reservation_id', (int) $reservation->reservation_id)
            ->where('status', ReservationOrderStatus::Active->value)
            ->orderByRaw($this->activeOrderPrioritySql())
            ->orderByDesc('order_id')
            ->first();

        if (! $order instanceof ReservationOrder) {
            return null;
        }

        $order->setRelation('reservation', $reservation);

        return $this->attachReservationSettlementTotals($order, $billSnapshot);
    }

    private function baseOrderQuery(): Builder
    {
        return ReservationOrder::query()->with([
            'items.item',
            'reservation.user.points',
            'reservation.user.currentTier',
            'reservation.tables',
            'reservation.payments.refundOfPayment',
            'reservation.appliedUserVoucher.voucher',
        ]);
    }

    /**
     * @param  Builder<ReservationOrder>  $query
     */
    private function constrainOrderQueryToStaffBranchScope(Builder $query, ?int $staffUserId): void
    {
        if ($staffUserId === null || $staffUserId <= 0) {
            return;
        }

        $accessibleBranchIds = $this->staffBranchContextService()->accessibleBranchIds($staffUserId);
        if ($accessibleBranchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('reservation', static function (Builder $reservationQuery) use ($accessibleBranchIds): void {
            $reservationQuery->whereIn('branch_id', $accessibleBranchIds);
        });
    }

    private function staffBranchContextService(): StaffBranchContextService
    {
        return $this->staffBranchContextService ?? app(StaffBranchContextService::class);
    }

    /**
     * @param  array{subtotal:float,discount:float,total_due:float,currency:string}|null  $snapshot
     */
    private function attachReservationSettlementTotals(ReservationOrder $order, ?array $snapshot = null): ReservationOrder
    {
        $reservation = $order->reservation;
        if ($reservation?->relationLoaded('tables') === true) {
            $this->reservationBranchScopeService->assertReservationMatchesTableBranchesInMemory(
                $reservation?->branch_id,
                $reservation->tables->pluck('branch_id')->all(),
                'Assigned tables must belong to a single branch.',
                'Reservation branch does not match the assigned table branch.',
                'reservation_id',
            );
        } else {
            $tableBranchIds = $reservation?->tables()->pluck('branch_id')->all() ?? [];

            $this->reservationBranchScopeService->assertReservationMatchesTableBranches(
                $reservation?->branch_id,
                $tableBranchIds,
                'Assigned tables must belong to a single branch.',
                'Reservation branch does not match the assigned table branch.',
                'reservation_id',
            );
        }

        $discountAmount = (float) ($reservation?->discount_amount ?? 0.0);
        $reservationId = (int) ($reservation?->reservation_id ?? $order->reservation_id ?? 0);

        if ($reservationId <= 0) {
            return $this->settlementAmountCalculator->attachTotals($order);
        }

        $snapshot ??= $this->reservationFinancialSyncService->computeReservationBillSnapshot(
            reservationId: $reservationId,
            discountAmount: $discountAmount,
            lockOrders: false,
        );

        return $this->settlementAmountCalculator->attachTotals(
            order: $order,
            subtotal: (float) ($snapshot['subtotal'] ?? 0.0),
            discount: (float) ($snapshot['discount'] ?? 0.0),
            totalDue: (float) ($snapshot['total_due'] ?? 0.0),
            currency: (string) ($snapshot['currency'] ?? ($reservation?->bill_currency ?? 'VND')),
        );
    }

    private function activeOrderPrioritySql(): string
    {
        return sprintf(
            "CASE WHEN %s = '%s' THEN 0 ELSE 1 END ASC",
            'order_type',
            ReservationOrderType::OnSpot->value,
        );
    }
}

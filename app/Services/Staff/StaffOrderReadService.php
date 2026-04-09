<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Services\Branch\ReservationBranchScopeService;
use App\Services\ReservationFinancialSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StaffOrderReadService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    public function __construct(
        private readonly ReservationFinancialSyncService $reservationFinancialSyncService,
        private readonly SettlementAmountCalculator $settlementAmountCalculator,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
    }

    public function findOrder(int $orderId): ?ReservationOrder
    {
        $order = $this->baseOrderQuery()
            ->where('order_id', $orderId)
            ->first();

        if (! $order instanceof ReservationOrder) {
            return null;
        }

        return $this->attachReservationSettlementTotals($order);
    }

    public function findActiveOrderByTable(int $tableId): ?ReservationOrder
    {
        $order = $this->baseOrderQuery()
            ->where('reservation_orders.status', ReservationOrderStatus::Active->value)
            ->whereExists(function ($query) use ($tableId): void {
                $query
                    ->selectRaw('1')
                    ->from('reservation_tables')
                    ->whereColumn('reservation_tables.reservation_id', 'reservation_orders.reservation_id')
                    ->where('reservation_tables.table_id', $tableId);
            })
            ->orderByRaw($this->activeOrderPrioritySql())
            ->orderByDesc('reservation_orders.order_id')
            ->first();

        if (! $order instanceof ReservationOrder) {
            return null;
        }

        return $this->attachReservationSettlementTotals($order);
    }

    public function findActiveOrderByReservation(int $reservationId): ?ReservationOrder
    {
        $order = $this->baseOrderQuery()
            ->where('reservation_id', $reservationId)
            ->where('status', ReservationOrderStatus::Active->value)
            ->orderByRaw($this->activeOrderPrioritySql())
            ->orderByDesc('order_id')
            ->first();

        if (! $order instanceof ReservationOrder) {
            return null;
        }

        return $this->attachReservationSettlementTotals($order);
    }

    /**
     * @return Collection<int, ReservationOrder>
     */
    public function listOrdersByReservation(int $reservationId): Collection
    {
        Reservation::query()->findOrFail($reservationId);

        return $this->baseOrderQuery()
            ->where('reservation_id', $reservationId)
            ->orderBy('order_id')
            ->get()
            ->map(fn (ReservationOrder $order): ReservationOrder => $this->attachReservationSettlementTotals($order));
    }

    /**
     * @param array{subtotal:float,discount:float,total_due:float,currency:string}|null $billSnapshot
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
     * @param array{subtotal:float,discount:float,total_due:float,currency:string}|null $snapshot
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

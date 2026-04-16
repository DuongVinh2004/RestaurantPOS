<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Application\Services;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\KitchenDispatch\Application\Services\KitchenRoutingService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Ordering\Domain\Policies\ReservationOrderItemStatusTransitionPolicy;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Services\Inventory\OrderItemInventoryConsumptionService;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\FloorOps\Application\Services\StaffBranchContextService;
use App\Support\AuditEvent;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffOrderItemLifecycleService
{
    private readonly ReservationBranchScopeService $reservationBranchScopeService;

    private readonly StaffBranchContextService $staffBranchContextService;

    public function __construct(
        private readonly ReservationLockService $locks,
        private readonly OrderItemInventoryConsumptionService $orderItemInventoryConsumptionService,
        private readonly KitchenRoutingService $kitchenRoutingService,
        ?ReservationBranchScopeService $reservationBranchScopeService = null,
        ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->reservationBranchScopeService = $reservationBranchScopeService ?? app(ReservationBranchScopeService::class);
        $this->staffBranchContextService = $staffBranchContextService ?? app(StaffBranchContextService::class);
    }

    public function updateItem(
        int $orderId,
        int $orderItemId,
        array $attributes,
        ?int $staffUserId = null,
        ?int $expectedOrderRowVersion = null,
        ?int $expectedItemRowVersion = null,
    ): ReservationOrder {
        [$reservationId, $tableIds] = $this->resolveLockContext($orderId);

        try {
            return $this->locks->withLockKeys(
                $this->buildLockKeys($reservationId, $tableIds),
                function () use ($orderId, $orderItemId, $attributes, $staffUserId, $expectedOrderRowVersion, $expectedItemRowVersion) {
                    return DB::transaction(function () use ($orderId, $orderItemId, $attributes, $staffUserId, $expectedOrderRowVersion, $expectedItemRowVersion) {
                        [$order, $item] = $this->loadWritableContext(
                            orderId: $orderId,
                            orderItemId: $orderItemId,
                            staffUserId: $staffUserId,
                            expectedOrderRowVersion: $expectedOrderRowVersion,
                            expectedItemRowVersion: $expectedItemRowVersion,
                        );

                        $currentStatus = $this->normalizeItemStatus($item);
                        if (in_array($currentStatus, [ReservationOrderItemStatus::Served->value, ReservationOrderItemStatus::Cancelled->value], true)) {
                            throw ValidationException::withMessages([
                                'order_item_id' => 'Served or cancelled items can no longer be edited.',
                            ]);
                        }

                        $qtyProvided = array_key_exists('qty', $attributes) || array_key_exists('quantity', $attributes);
                        $noteProvided = array_key_exists('note', $attributes) || array_key_exists('notes', $attributes);

                        $newQuantity = $qtyProvided
                            ? (int) ($attributes['qty'] ?? $attributes['quantity'])
                            : (int) $item->quantity;
                        $newNote = $noteProvided
                            ? $this->normalizeNote((string) ($attributes['note'] ?? $attributes['notes'] ?? ''))
                            : $this->normalizeNote((string) ($item->notes ?? ''));

                        $currentNote = $this->normalizeNote((string) ($item->notes ?? ''));
                        if ($newQuantity === (int) $item->quantity && $newNote === $currentNote) {
                            AuditEvent::info('staff.order_item.update_noop', [
                                'order_id' => $orderId,
                                'order_item_id' => $orderItemId,
                                'actor_user_id' => $staffUserId,
                            ]);

                            return $this->freshOrder($orderId);
                        }

                        $item->quantity = $newQuantity;
                        $item->notes = $newNote !== '' ? $newNote : null;
                        $item->updated_by = $staffUserId;
                        $item->save();

                        $order->updated_by = $staffUserId;
                        $order->save();

                        AuditEvent::info('staff.order_item.updated', [
                            'order_id' => $orderId,
                            'order_item_id' => $orderItemId,
                            'actor_user_id' => $staffUserId,
                            'quantity' => $newQuantity,
                            'status' => $currentStatus,
                        ]);

                        return $this->freshOrder($orderId);
                    });
                }
            );
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    public function transitionItemStatus(
        int $orderId,
        int $orderItemId,
        string|ReservationOrderItemStatus $targetStatus,
        ?int $staffUserId = null,
        ?int $expectedOrderRowVersion = null,
        ?int $expectedItemRowVersion = null,
    ): ReservationOrder {
        [$reservationId, $tableIds] = $this->resolveLockContext($orderId);
        $target = $targetStatus instanceof ReservationOrderItemStatus
            ? $targetStatus
            : ReservationOrderItemStatus::from((string) $targetStatus);

        try {
            return $this->locks->withLockKeys(
                $this->buildLockKeys($reservationId, $tableIds),
                function () use ($orderId, $orderItemId, $target, $staffUserId, $expectedOrderRowVersion, $expectedItemRowVersion) {
                    return DB::transaction(function () use ($orderId, $orderItemId, $target, $staffUserId, $expectedOrderRowVersion, $expectedItemRowVersion) {
                        [$order, $item] = $this->loadWritableContext(
                            orderId: $orderId,
                            orderItemId: $orderItemId,
                            staffUserId: $staffUserId,
                            expectedOrderRowVersion: $expectedOrderRowVersion,
                            expectedItemRowVersion: $expectedItemRowVersion,
                        );

                        $current = $item->status instanceof ReservationOrderItemStatus
                            ? $item->status
                            : ReservationOrderItemStatus::from((string) $item->status);

                        if ($current === $target) {
                            AuditEvent::info('staff.order_item.status_noop', [
                                'order_id' => $orderId,
                                'order_item_id' => $orderItemId,
                                'actor_user_id' => $staffUserId,
                                'status' => $target->value,
                            ]);

                            return $this->freshOrder($orderId);
                        }

                        ReservationOrderItemStatusTransitionPolicy::assertTransitionAllowed($current, $target);

                        $item->status = $target;
                        $item->updated_by = $staffUserId;
                        $item->save();

                        $order->updated_by = $staffUserId;
                        $order->save();

                        AuditEvent::info('staff.order_item.status_changed', [
                            'order_id' => $orderId,
                            'order_item_id' => $orderItemId,
                            'actor_user_id' => $staffUserId,
                            'from_status' => $current->value,
                            'to_status' => $target->value,
                        ]);

                        $this->orderItemInventoryConsumptionService->consumeIfServed(
                            reservation: $order->reservation,
                            order: $order,
                            item: $item,
                            previousStatus: $current,
                            targetStatus: $target,
                            actorUserId: $staffUserId,
                        );
                        $this->kitchenRoutingService->syncTicketForOrderItem((int) $item->order_item_id, $staffUserId);

                        return $this->freshOrder($orderId);
                    });
                }
            );
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    /**
     * @return array{0:int,1:array<int,int>}
     */
    private function resolveLockContext(int $orderId): array
    {
        $reservationId = (int) ReservationOrder::query()->where('order_id', $orderId)->value('reservation_id');
        if ($reservationId <= 0) {
            throw ValidationException::withMessages(['order_id' => 'Order not found.']);
        }

        $tableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservationId)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return [$reservationId, $tableIds];
    }

    /**
     * @return array{0:ReservationOrder,1:ReservationOrderItem}
     */
    private function loadWritableContext(
        int $orderId,
        int $orderItemId,
        ?int $staffUserId,
        ?int $expectedOrderRowVersion,
        ?int $expectedItemRowVersion,
    ): array {
        /** @var ReservationOrder $order */
        $order = ReservationOrder::query()->where('order_id', $orderId)->lockForUpdate()->firstOrFail();
        $this->assertExpectedOrderRowVersion($order, $expectedOrderRowVersion);

        if (($order->status?->value ?? (string) $order->status) !== ReservationOrderStatus::Active->value) {
            throw ValidationException::withMessages(['order_id' => 'Order is not active.']);
        }

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->where('reservation_id', $order->reservation_id)->lockForUpdate()->firstOrFail();
        if (($reservation->status?->value ?? (string) $reservation->status) !== ReservationStatus::Reserved->value) {
            throw ValidationException::withMessages([
                'reservation_id' => 'Reservation is not currently in service.',
            ]);
        }
        $this->assertReservationBillEditable($reservation);

        $tableIds = DB::table('reservation_tables')
            ->where('reservation_id', $reservation->reservation_id)
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($tableIds !== []) {
            $tables = RestaurantTable::query()
                ->whereIn('table_id', $tableIds)
                ->lockForUpdate()
                ->get(['table_id', 'branch_id', 'status']);

            $occupiedCount = $tables
                ->filter(fn (RestaurantTable $table): bool => ($table->status?->value ?? (string) $table->status) === RestaurantTableStatus::Occupied->value)
                ->count();

            if ($tables->count() !== count($tableIds) || $occupiedCount !== count($tableIds)) {
                throw ValidationException::withMessages([
                    'reservation_id' => 'Assigned tables are not currently occupied.',
                ]);
            }

            $branchId = $this->reservationBranchScopeService->syncReservationBranchOrAssert(
                $reservation,
                $tables->pluck('branch_id')->all(),
                $staffUserId,
            );
            $this->assertOperationalBranchAccessible($branchId, $staffUserId);
        } else {
            $branchId = $this->reservationBranchScopeService->resolveEffectiveReservationBranchId($reservation->branch_id);
            $this->assertOperationalBranchAccessible($branchId, $staffUserId);
        }

        /** @var ReservationOrderItem $item */
        $item = ReservationOrderItem::query()
            ->where('order_item_id', $orderItemId)
            ->where('order_id', $orderId)
            ->lockForUpdate()
            ->firstOrFail();

        $this->assertExpectedItemRowVersion($item, $expectedItemRowVersion);

        return [$order, $item];
    }

    /**
     * @param  array<int,int>  $tableIds
     * @return array<int,string>
     */
    private function buildLockKeys(int $reservationId, array $tableIds): array
    {
        return array_merge(
            [config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId],
            array_map(fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$id, $tableIds),
        );
    }

    private function assertReservationBillEditable(Reservation $reservation): void
    {
        if ($reservation->billed_at !== null || $reservation->final_bill_amount !== null) {
            throw ValidationException::withMessages([
                'reservation_id' => 'Reservation bill has already been closed for payment. Reopen the bill before modifying order items.',
            ]);
        }
    }

    private function assertOperationalBranchAccessible(int $branchId, ?int $staffUserId): void
    {
        if ($staffUserId === null || $staffUserId <= 0) {
            return;
        }

        $this->staffBranchContextService->assertAccessibleBranch($staffUserId, $branchId);
    }

    private function assertExpectedOrderRowVersion(ReservationOrder $order, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($order->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'order_row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }

    private function assertExpectedItemRowVersion(ReservationOrderItem $item, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($item->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }

    private function normalizeItemStatus(ReservationOrderItem $item): string
    {
        return $item->status instanceof ReservationOrderItemStatus
            ? $item->status->value
            : (string) $item->status;
    }

    private function normalizeNote(string $note): string
    {
        return trim($note);
    }

    private function freshOrder(int $orderId): ReservationOrder
    {
        return ReservationOrder::query()
            ->with(['items.item'])
            ->where('order_id', $orderId)
            ->firstOrFail();
    }
}

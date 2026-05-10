<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Application\UseCases\OrderItems;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Application\Services\ReservationBranchScopeService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\InventoryProcurement\Application\UseCases\Inventory\OrderItemInventoryConsumptionService;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenRoutingService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Ordering\Domain\Policies\ReservationOrderItemStatusTransitionPolicy;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\Auth\StaffActorGuard;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Điều khiển vòng đời của từng món trong order:
 * sửa thông tin món, đổi trạng thái, và đồng bộ tồn kho/KDS.
 */
class StaffOrderItemLifecycleService
{
    private const STALE_ROW_VERSION_MESSAGE = 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.';

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
        // Pha 1: resolve lock context tu order de item update va bill state khong lech nhau.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);
        [$reservationId, $tableIds] = $this->resolveLockContext($orderId);

        // Khóa cùng lúc reservation, order và bàn để sửa item nhất quán với bill hiện tại.
        try {
            return $this->locks->withLockKeys(
                $this->buildLockKeys($reservationId, $tableIds),
                function () use ($orderId, $orderItemId, $attributes, $staffUserId, $expectedOrderRowVersion, $expectedItemRowVersion) {
                    return DB::transaction(function () use ($orderId, $orderItemId, $attributes, $staffUserId, $expectedOrderRowVersion, $expectedItemRowVersion) {
                        // Pha 2: lock order, item, reservation va assigned tables trong mot writable context.
                        [$order, $item, $branchId] = $this->loadWritableContext(
                            orderId: $orderId,
                            orderItemId: $orderItemId,
                            staffUserId: $staffUserId,
                            expectedOrderRowVersion: $expectedOrderRowVersion,
                            expectedItemRowVersion: $expectedItemRowVersion,
                        );

                        $currentStatus = $this->normalizeItemStatus($item);
                        // Món đã served/cancelled thì khóa lại để giữ lịch sử và tổng tiền ổn định.
                        if (in_array($currentStatus, [ReservationOrderItemStatus::Served->value, ReservationOrderItemStatus::Cancelled->value], true)) {
                            throw ValidationException::withMessages([
                                'order_item_id' => 'Served or cancelled items can no longer be edited.',
                            ]);
                        }

                        $qtyProvided = array_key_exists('qty', $attributes) || array_key_exists('quantity', $attributes);
                        $noteProvided = array_key_exists('note', $attributes) || array_key_exists('notes', $attributes);
                        $oldQuantity = (int) $item->quantity;
                        $unitPrice = Money::format($item->unit_price ?? 0);
                        $oldLineTotal = Money::format($item->line_total ?? 0);
                        $currency = $this->normalizeCurrency((string) ($item->currency ?? 'VND'));

                        $newQuantity = $qtyProvided
                            ? (int) ($attributes['qty'] ?? $attributes['quantity'])
                            : $oldQuantity;
                        $newNote = $noteProvided
                            ? $this->normalizeNote((string) ($attributes['note'] ?? $attributes['notes'] ?? ''))
                            : $this->normalizeNote((string) ($item->notes ?? ''));

                        $currentNote = $this->normalizeNote((string) ($item->notes ?? ''));
                        // Noop branch nay giu idempotent semantics cho UI edit form save lai ma khong doi du lieu.
                        if ($newQuantity === (int) $item->quantity && $newNote === $currentNote) {
                            AuditEvent::info('staff.order_item.update_noop', [
                                'order_id' => $orderId,
                                'order_item_id' => $orderItemId,
                                'actor_user_id' => $staffUserId,
                            ]);

                            return $this->freshOrder($orderId);
                        }

                        $newLineTotal = $oldLineTotal;
                        // line_total chi tinh lai khi quantity doi; note edit khong duoc cham vao tong tien.
                        if ($newQuantity !== $oldQuantity) {
                            $newLineTotal = $this->lineTotalForQuantity($unitPrice, $newQuantity);
                            $item->line_total = $newLineTotal;
                        }

                        $item->quantity = $newQuantity;
                        $item->notes = $newNote !== '' ? $newNote : null;
                        $item->updated_by = $staffUserId;
                        $item->save();

                        // Chạm vào order để row_version và audit của order phản ánh lần sửa item này.
                        $order->updated_by = $staffUserId;
                        $order->save();

                        // Audit after luu before/after quantity-line_total de doi soat bill mutation.
                        AuditEvent::info('staff.order_item.updated', [
                            'order_id' => $orderId,
                            'order_item_id' => $orderItemId,
                            'old_quantity' => $oldQuantity,
                            'new_quantity' => $newQuantity,
                            'unit_price' => $unitPrice,
                            'old_line_total' => $oldLineTotal,
                            'new_line_total' => $newLineTotal,
                            'currency' => $currency,
                            'actor_user_id' => $staffUserId,
                            'branch_id' => $branchId > 0 ? $branchId : null,
                            'status' => $currentStatus,
                            '_audit' => [
                                'action' => 'order_item.updated',
                                'entity_type' => 'reservation_order_item',
                                'entity_id' => (string) $orderItemId,
                                'subjects' => array_values(array_filter([
                                    [
                                        'type' => 'reservation_order',
                                        'id' => (string) $orderId,
                                        'role' => 'order',
                                    ],
                                    $branchId > 0 ? [
                                        'type' => 'branch',
                                        'id' => (string) $branchId,
                                        'role' => 'branch',
                                    ] : null,
                                ])),
                                'before' => [
                                    'quantity' => $oldQuantity,
                                    'line_total' => $oldLineTotal,
                                ],
                                'after' => [
                                    'quantity' => $newQuantity,
                                    'line_total' => $newLineTotal,
                                    'unit_price' => $unitPrice,
                                    'currency' => $currency,
                                ],
                                'summary' => [
                                    'order_id' => $orderId,
                                    'order_item_id' => $orderItemId,
                                    'old_quantity' => $oldQuantity,
                                    'new_quantity' => $newQuantity,
                                    'unit_price' => $unitPrice,
                                    'old_line_total' => $oldLineTotal,
                                    'new_line_total' => $newLineTotal,
                                    'currency' => $currency,
                                    'actor_user_id' => $staffUserId,
                                    'branch_id' => $branchId > 0 ? $branchId : null,
                                ],
                                'meta' => [
                                    'branch_id' => $branchId > 0 ? $branchId : null,
                                ],
                                'actor' => [
                                    'type' => 'staff_user',
                                    'user_id' => $staffUserId,
                                    'key' => 'staff_user:'.$staffUserId,
                                ],
                            ],
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
        // Status transition can cung lock scope voi updateItem vi no co side-effect ton kho/KDS.
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);
        [$reservationId, $tableIds] = $this->resolveLockContext($orderId);
        $target = $targetStatus instanceof ReservationOrderItemStatus
            ? $targetStatus
            : ReservationOrderItemStatus::from((string) $targetStatus);

        // Mọi đổi trạng thái đều đi qua lock + policy trước khi chạm tồn kho và ticket bếp.
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

                        // Policy la state machine duy nhat quy dinh item duoc di tu trang thai nao sang nao.
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

                        // Side-effect ton kho chi xay ra sau khi DB status da duoc ghi trong transaction hien tai.
                        $this->orderItemInventoryConsumptionService->consumeIfServed(
                            reservation: $order->reservation,
                            order: $order,
                            item: $item,
                            previousStatus: $current,
                            targetStatus: $target,
                            actorUserId: $staffUserId,
                        );
                        // Đồng bộ KDS sau cùng để màn hình bếp phản ánh đúng trạng thái mới.
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
        // Lock context duoc resolve mot lan de build distributed lock keys cho moi mutation item.
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
     * @return array{0:ReservationOrder,1:ReservationOrderItem,2:int}
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

        return [$order, $item, $branchId];
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
        $staffUserId = StaffActorGuard::requireStaffUserId($staffUserId);

        $this->staffBranchContextService->assertAccessibleBranch($staffUserId, $branchId);
    }

    private function assertExpectedOrderRowVersion(ReservationOrder $order, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($order->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'order_row_version' => [self::STALE_ROW_VERSION_MESSAGE],
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
                'row_version' => [self::STALE_ROW_VERSION_MESSAGE],
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

    private function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        return $normalized !== '' ? $normalized : 'VND';
    }

    private function lineTotalForQuantity(string $unitPrice, int $quantity): string
    {
        return Money::formatMinor(Money::minorUnits($unitPrice) * max(0, $quantity));
    }

    private function freshOrder(int $orderId): ReservationOrder
    {
        return ReservationOrder::query()
            ->with(['items.item'])
            ->where('order_id', $orderId)
            ->firstOrFail();
    }
}

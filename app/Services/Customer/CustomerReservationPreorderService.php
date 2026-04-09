<?php

declare(strict_types=1);

namespace App\Services\Customer;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Models\MenuItem;
use App\Models\MenuItemPrice;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Models\ReservationOrderItem;
use App\Services\CustomerReservationSessionAccessService;
use App\Services\MenuPreorderPolicyService;
use App\Services\ReservationLockService;
use App\Support\AuditEvent;
use App\Support\ValidationExceptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerReservationPreorderService
{
    public function __construct(
        private readonly CustomerReservationSessionAccessService $customerSessionAccessService,
        private readonly MenuPreorderPolicyService $menuPreorderPolicyService,
        private readonly ReservationLockService $locks,
    ) {
    }

    /**
     * @return array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}
     */
    public function showAccessiblePreorder(int $reservationId, ?int $customerUserId, ?string $sessionId): array
    {
        $reservation = $this->loadAccessibleReservation(
            reservationId: $reservationId,
            customerUserId: $customerUserId,
            sessionId: $sessionId,
            lockForUpdate: false,
        );

        return $this->buildResponse($reservation, $this->findCurrentPreorderOrder($reservationId));
    }

    /**
     * @param array<int, array<string,mixed>> $requestedItems
     * @return array{reservation:Reservation,current_pre_order:array<string,mixed>,management_policy:array<string,mixed>,preview:array<string,mixed>}
     */
    public function previewAccessiblePreorderUpdate(int $reservationId, ?int $customerUserId, ?string $sessionId, array $requestedItems): array
    {
        $reservation = $this->loadAccessibleReservation(
            reservationId: $reservationId,
            customerUserId: $customerUserId,
            sessionId: $sessionId,
            lockForUpdate: false,
        );

        $this->assertReservationPreorderMutable($reservation);

        $preview = $this->buildRequestedPreorderPreview(
            requestedItems: $requestedItems,
            serviceStart: Carbon::parse((string) $reservation->start_time)->utc(),
            ignoreReservationId: (int) $reservation->reservation_id,
        );

        $currentOrder = $this->findCurrentPreorderOrder((int) $reservation->reservation_id);

        return [
            'reservation' => $reservation,
            'current_pre_order' => $this->buildCurrentPreorderSnapshot($reservation, $currentOrder),
            'management_policy' => $this->buildManagementPolicy($reservation),
            'preview' => $preview,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}
     */
    public function replaceAccessiblePreorder(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload): array
    {
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $customerUserId, $sessionId, $payload) {
            return DB::transaction(function () use ($reservationId, $customerUserId, $sessionId, $payload) {
                $reservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: true,
                );

                $this->assertReservationRowVersion($reservation, (int) $payload['row_version']);
                $this->assertReservationPreorderMutable($reservation);

                $prepared = $this->menuPreorderPolicyService->prepareRequestedItems(
                    (array) $payload['pre_order_items'],
                    Carbon::parse((string) $reservation->start_time)->utc(),
                    (int) $reservation->reservation_id,
                );

                $order = $this->findActivePreorderOrderForUpdate((int) $reservation->reservation_id);
                if ($order instanceof ReservationOrder) {
                    $this->assertPreorderRowVersion($order, $payload['pre_order_row_version'] ?? null);
                    $this->cancelExistingPreorderItems($order, $customerUserId);
                    $this->incrementOrderRowVersion($order);
                    $order->status = ReservationOrderStatus::Active;
                    $order->updated_by = $customerUserId;
                    $order->save();
                } else {
                    $order = new ReservationOrder();
                    $order->reservation_id = (int) $reservation->reservation_id;
                    $order->order_type = ReservationOrderType::PreOrder;
                    $order->status = ReservationOrderStatus::Active;
                    $order->notes = 'Customer managed pre-order';
                    $order->created_by = $customerUserId;
                    $order->updated_by = $customerUserId;
                    $order->save();
                }

                $this->persistPreparedRows($order, $prepared, $customerUserId);

                AuditEvent::info('customer.reservation.preorder.replaced', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'preorder_order_id' => (int) $order->order_id,
                    'customer_user_id' => $customerUserId,
                    'customer_session_id' => $customerUserId === null ? trim((string) $sessionId) : null,
                    'line_count' => count((array) $prepared['rows']),
                ]);

                $freshReservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: false,
                );

                return $this->buildResponse($freshReservation, $this->findCurrentPreorderOrder($reservationId));
            });
        });
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}
     */
    public function clearAccessiblePreorder(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload): array
    {
        return $this->locks->withReservationLock($reservationId, function () use ($reservationId, $customerUserId, $sessionId, $payload) {
            return DB::transaction(function () use ($reservationId, $customerUserId, $sessionId, $payload) {
                $reservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: true,
                );

                $this->assertReservationRowVersion($reservation, (int) $payload['row_version']);
                $this->assertReservationPreorderMutable($reservation);

                $order = $this->findActivePreorderOrderForUpdate((int) $reservation->reservation_id);
                if ($order instanceof ReservationOrder) {
                    $this->assertPreorderRowVersion($order, $payload['pre_order_row_version'] ?? null);
                    $this->cancelExistingPreorderItems($order, $customerUserId);
                    $this->incrementOrderRowVersion($order);
                    $order->status = ReservationOrderStatus::Cancelled;
                    $order->updated_by = $customerUserId;
                    $order->save();

                    AuditEvent::info('customer.reservation.preorder.cleared', [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'preorder_order_id' => (int) $order->order_id,
                        'customer_user_id' => $customerUserId,
                        'customer_session_id' => $customerUserId === null ? trim((string) $sessionId) : null,
                    ]);
                }

                $freshReservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: false,
                );

                return $this->buildResponse($freshReservation, $this->findCurrentPreorderOrder($reservationId));
            });
        });
    }

    /**
     * @return array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}
     */
    private function buildResponse(Reservation $reservation, ?ReservationOrder $currentOrder): array
    {
        return [
            'reservation' => $reservation,
            'pre_order' => $this->buildCurrentPreorderSnapshot($reservation, $currentOrder),
            'management_policy' => $this->buildManagementPolicy($reservation),
        ];
    }

    private function loadAccessibleReservation(int $reservationId, ?int $customerUserId, ?string $sessionId, bool $lockForUpdate): Reservation
    {
        if ($customerUserId !== null) {
            $query = Reservation::query()
                ->with(['orders.items.item'])
                ->whereKey($reservationId)
                ->where('user_id', $customerUserId);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $reservation = $query->first();
            if ($reservation instanceof Reservation) {
                return $reservation;
            }

            throw (new ModelNotFoundException())->setModel(Reservation::class, [$reservationId]);
        }

        $trimmedSessionId = trim((string) $sessionId);
        if ($trimmedSessionId === '') {
            throw (new ModelNotFoundException())->setModel(Reservation::class, [$reservationId]);
        }

        $query = Reservation::query()
            ->with(['orders.items.item'])
            ->whereKey($reservationId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $reservation = $query->first();
        if (! $reservation instanceof Reservation || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $trimmedSessionId)) {
            throw (new ModelNotFoundException())->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
    }

    private function findCurrentPreorderOrder(int $reservationId): ?ReservationOrder
    {
        return ReservationOrder::query()
            ->with(['items.item'])
            ->where('reservation_id', $reservationId)
            ->where('order_type', ReservationOrderType::PreOrder->value)
            ->where('status', ReservationOrderStatus::Active->value)
            ->orderByDesc('order_id')
            ->first();
    }

    private function findActivePreorderOrderForUpdate(int $reservationId): ?ReservationOrder
    {
        return ReservationOrder::query()
            ->where('reservation_id', $reservationId)
            ->where('order_type', ReservationOrderType::PreOrder->value)
            ->where('status', ReservationOrderStatus::Active->value)
            ->orderByDesc('order_id')
            ->lockForUpdate()
            ->first();
    }

    private function assertReservationPreorderMutable(Reservation $reservation): void
    {
        $policy = $this->buildManagementPolicy($reservation);
        if ((bool) ($policy['can_manage'] ?? false)) {
            return;
        }

        throw ValidationExceptionFactory::make([
            'reservation' => (array) ($policy['reasons'] ?? ['Reservation pre-order is not currently mutable.']),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function buildManagementPolicy(Reservation $reservation): array
    {
        $reservationStatus = (string) ($reservation->status?->value ?? $reservation->status ?? '');
        $cutoffMinutes = max(0, (int) config('booking.customer_preorder_management_cutoff_minutes', 60));
        $serviceStart = Carbon::parse((string) $reservation->start_time)->utc();
        $manageUntil = $serviceStart->copy()->subMinutes($cutoffMinutes);
        $now = Carbon::now('UTC');

        $reasons = [];
        if ($reservationStatus !== ReservationStatus::Confirmed->value) {
            $reasons[] = 'Pre-order chỉ có thể được chỉnh sửa khi reservation còn ở trạng thái Confirmed.';
        }

        if ($reservation->checked_in_at !== null || ReservationStatus::isCheckedInDbValue($reservationStatus)) {
            $reasons[] = 'Reservation đã check-in nên không còn được chỉnh sửa pre-order từ self-service.';
        }

        if ($now->gte($manageUntil)) {
            $reasons[] = sprintf('Pre-order chỉ có thể chỉnh sửa trước giờ đến ít nhất %d phút.', $cutoffMinutes);
        }

        return [
            'can_manage' => $reasons === [],
            'reservation_status' => $reservationStatus,
            'cutoff_minutes' => $cutoffMinutes,
            'service_start' => $serviceStart->toIso8601String(),
            'manage_until' => $manageUntil->toIso8601String(),
            'reasons' => $reasons,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCurrentPreorderSnapshot(Reservation $reservation, ?ReservationOrder $order): array
    {
        $serviceTime = Carbon::parse((string) $reservation->start_time)->utc();
        if (! $order instanceof ReservationOrder) {
            return [
                'present' => false,
                'order_id' => null,
                'order_row_version' => null,
                'order_status' => null,
                'service_time' => $serviceTime->toIso8601String(),
                'currency' => (string) ($reservation->bill_currency ?? 'VND'),
                'lines' => [],
                'totals' => [
                    'item_count' => 0,
                    'quantity' => 0,
                    'subtotal' => number_format(0, 2, '.', ''),
                ],
                'normalized_pre_order_items' => [],
            ];
        }

        $activeItems = $order->relationLoaded('items')
            ? $order->items->filter(static fn (ReservationOrderItem $item): bool => (string) ($item->status?->value ?? $item->status) !== ReservationOrderItemStatus::Cancelled->value)->values()
            : collect();

        $currency = (string) ($reservation->bill_currency ?? 'VND');
        $subtotal = 0.0;
        $quantityTotal = 0;
        $lines = [];

        foreach ($activeItems as $item) {
            $unitPrice = round((float) ($item->unit_price ?? 0.0), 2);
            $lineTotal = round((float) ($item->line_total ?? ($unitPrice * (int) $item->quantity)), 2);
            $subtotal += $lineTotal;
            $quantityTotal += (int) $item->quantity;
            $currency = (string) ($item->currency ?: $currency);

            /** @var MenuItem|null $menuItem */
            $menuItem = $item->relationLoaded('item') ? $item->item : null;

            $lines[] = [
                'order_item_id' => (int) $item->order_item_id,
                'item_id' => (int) $item->item_id,
                'quantity' => (int) $item->quantity,
                'status' => $item->status?->value ?? (string) $item->status,
                'name' => (string) ($item->item_name_snapshot ?: ($menuItem?->name ?? '')),
                'code' => $menuItem?->code,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'currency' => (string) ($item->currency ?: $currency),
                'notes' => $item->notes,
                'updated_at' => optional($item->updated_at)->utc()->toIso8601String(),
            ];
        }

        return [
            'present' => $activeItems->isNotEmpty(),
            'order_id' => (int) $order->order_id,
            'order_row_version' => (int) ($order->row_version ?? 1),
            'order_status' => $order->status?->value ?? (string) $order->status,
            'service_time' => $serviceTime->toIso8601String(),
            'currency' => $currency,
            'lines' => $lines,
            'totals' => [
                'item_count' => count($lines),
                'quantity' => $quantityTotal,
                'subtotal' => number_format($subtotal, 2, '.', ''),
            ],
            'normalized_pre_order_items' => array_map(static fn (array $line): array => [
                'item_id' => (int) $line['item_id'],
                'quantity' => (int) $line['quantity'],
            ], $lines),
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $requestedItems
     * @return array<string,mixed>
     */
    private function buildRequestedPreorderPreview(array $requestedItems, Carbon $serviceStart, ?int $ignoreReservationId = null): array
    {
        $prepared = $this->menuPreorderPolicyService->prepareRequestedItems(
            requestedItems: $requestedItems,
            serviceStart: $serviceStart,
            ignoreReservationId: $ignoreReservationId,
        );

        /** @var Collection<int, MenuItem> $menuItems */
        $menuItems = $prepared['menu_items'];
        /** @var Collection<int, MenuItemPrice> $priceRows */
        $priceRows = $prepared['price_rows'];
        $rows = $prepared['rows'];

        $currency = 'VND';
        $subtotal = 0.0;
        $quantityTotal = 0;
        $lines = [];

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $quantity = (int) $row['quantity'];
            /** @var MenuItem $menuItem */
            $menuItem = $menuItems->get($itemId);
            /** @var MenuItemPrice $priceRow */
            $priceRow = $priceRows->get($itemId);

            $unitPrice = round((float) $priceRow->price, 2);
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;
            $quantityTotal += $quantity;
            $currency = (string) ($priceRow->currency ?: $currency);

            $lines[] = [
                'item_id' => $itemId,
                'code' => (string) ($menuItem->code ?? ''),
                'name' => (string) $menuItem->name,
                'quantity' => $quantity,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'currency' => (string) ($priceRow->currency ?: $currency),
                'preorder_cutoff_minutes' => (int) ($menuItem->preorder_cutoff_minutes ?? 0),
                'preorder_quota_per_day' => $menuItem->preorder_quota_per_day !== null
                    ? (int) $menuItem->preorder_quota_per_day
                    : null,
            ];
        }

        return [
            'service_time' => $serviceStart->toIso8601String(),
            'currency' => $currency,
            'lines' => $lines,
            'totals' => [
                'item_count' => count($lines),
                'quantity' => $quantityTotal,
                'subtotal' => number_format($subtotal, 2, '.', ''),
            ],
            'normalized_pre_order_items' => array_map(static fn (array $row): array => [
                'item_id' => (int) $row['item_id'],
                'quantity' => (int) $row['quantity'],
            ], $rows),
        ];
    }

    /**
     * @param array{rows:array<int, array{item_id:int, quantity:int}>,menu_items:Collection<int, MenuItem>,price_rows:Collection<int, MenuItemPrice>} $prepared
     */
    private function persistPreparedRows(ReservationOrder $order, array $prepared, ?int $customerUserId): void
    {
        /** @var Collection<int, MenuItem> $menuItems */
        $menuItems = $prepared['menu_items'];
        /** @var Collection<int, MenuItemPrice> $priceRows */
        $priceRows = $prepared['price_rows'];

        foreach ($prepared['rows'] as $row) {
            $itemId = (int) $row['item_id'];
            $quantity = (int) $row['quantity'];
            /** @var MenuItem $menuItem */
            $menuItem = $menuItems->get($itemId);
            /** @var MenuItemPrice $priceRow */
            $priceRow = $priceRows->get($itemId);

            $unitPrice = round((float) $priceRow->price, 2);
            $item = new ReservationOrderItem();
            $item->order_id = (int) $order->order_id;
            $item->item_id = $itemId;
            $item->quantity = $quantity;
            $item->unit_price = $unitPrice;
            $item->line_total = round($unitPrice * $quantity, 2);
            $item->currency = (string) ($priceRow->currency ?: 'VND');
            $item->item_name_snapshot = (string) $menuItem->name;
            $item->status = ReservationOrderItemStatus::Ordered;
            $item->updated_by = $customerUserId;
            $item->save();
        }
    }

    private function cancelExistingPreorderItems(ReservationOrder $order, ?int $customerUserId): void
    {
        $items = ReservationOrderItem::query()
            ->where('order_id', (int) $order->order_id)
            ->where('status', '!=', ReservationOrderItemStatus::Cancelled->value)
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            $item->status = ReservationOrderItemStatus::Cancelled;
            $item->updated_by = $customerUserId;
            $item->row_version = max(1, (int) ($item->row_version ?? 1)) + 1;
            $item->save();
        }
    }

    private function assertReservationRowVersion(Reservation $reservation, int $expectedRowVersion): void
    {
        if ((int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'row_version' => ['Reservation row version does not match the latest state.'],
            ]);
        }
    }

    private function assertPreorderRowVersion(ReservationOrder $order, mixed $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            throw ValidationExceptionFactory::make([
                'pre_order_row_version' => ['Pre-order row version is required when an existing pre-order is being updated.'],
            ]);
        }

        if ((int) ($order->row_version ?? 1) !== (int) $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'pre_order_row_version' => ['Pre-order row version does not match the latest state.'],
            ]);
        }
    }

    private function incrementOrderRowVersion(ReservationOrder $order): void
    {
        $order->row_version = max(1, (int) ($order->row_version ?? 1)) + 1;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases\PolicyPreview;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MenuPreorderPolicyService
{
    /**
     * @param array<int, array{item_id:int, quantity:int}> $requestedItems
     * @return array{
     *   rows: array<int, array{item_id:int, quantity:int}>,
     *   menu_items: Collection<int, MenuItem>,
     *   price_rows: Collection<int, MenuItemPrice>
     * }
     */
    public function prepareRequestedItems(array $requestedItems, Carbon $serviceStart, ?int $ignoreReservationId = null): array
    {
        $rows = $this->normalizeRequestedItems($requestedItems);
        if ($rows === []) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Danh sách pre-order không hợp lệ.'],
            ]);
        }

        $itemIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['item_id'],
            $rows,
        )));

        /** @var Collection<int, MenuItem> $menuItems */
        $menuItems = MenuItem::query()
            ->whereIn('item_id', $itemIds)
            ->where('is_available', 1)
            ->get([
                'item_id',
                'code',
                'name',
                'is_available',
                'is_preorder_enabled',
                'preorder_quota_per_day',
                'preorder_cutoff_minutes',
            ])
            ->keyBy('item_id');

        if ($menuItems->count() !== count($itemIds)) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Có món không tồn tại hoặc đang unavailable.'],
            ]);
        }

        $nowUtc = Carbon::now('UTC');
        $requestedQuantityByItemId = [];
        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $requestedQuantityByItemId[$itemId] = ($requestedQuantityByItemId[$itemId] ?? 0) + (int) $row['quantity'];
        }

        $existingQuantityByItemId = $this->existingDailyPreorderQuantities(
            itemIds: array_keys($requestedQuantityByItemId),
            serviceStart: $serviceStart,
            ignoreReservationId: $ignoreReservationId,
        );

        foreach ($requestedQuantityByItemId as $itemId => $requestedQuantity) {
            /** @var MenuItem|null $menuItem */
            $menuItem = $menuItems->get($itemId);
            if (! $menuItem) {
                throw ValidationException::withMessages([
                    'pre_order_items' => ['Có món không tồn tại hoặc đang unavailable.'],
                ]);
            }

            if (! (bool) $menuItem->is_preorder_enabled) {
                throw ValidationException::withMessages([
                    'pre_order_items' => [sprintf('Món "%s" hiện không cho phép pre-order.', (string) $menuItem->name)],
                ]);
            }

            $cutoffMinutes = max(0, (int) ($menuItem->preorder_cutoff_minutes ?? 0));
            if ($cutoffMinutes > 0 && $nowUtc->copy()->addMinutes($cutoffMinutes)->greaterThan($serviceStart)) {
                throw ValidationException::withMessages([
                    'pre_order_items' => [sprintf('Món "%s" đã quá thời hạn pre-order trước giờ phục vụ.', (string) $menuItem->name)],
                ]);
            }

            $quotaPerDay = $menuItem->preorder_quota_per_day;
            if ($quotaPerDay !== null) {
                $quotaPerDay = (int) $quotaPerDay;
                $alreadyReserved = (int) ($existingQuantityByItemId[$itemId] ?? 0);
                if ($alreadyReserved + $requestedQuantity > $quotaPerDay) {
                    throw ValidationException::withMessages([
                        'pre_order_items' => [sprintf(
                            'Món "%s" vượt quota pre-order trong ngày (%d/%d trước khi thêm yêu cầu mới).',
                            (string) $menuItem->name,
                            $alreadyReserved,
                            $quotaPerDay,
                        )],
                    ]);
                }
            }
        }

        /** @var Collection<int, MenuItemPrice> $priceRows */
        $priceRows = MenuItemPrice::query()
            ->whereIn('item_id', $itemIds)
            ->effectiveAt($serviceStart)
            ->orderBy('item_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->get()
            ->groupBy('item_id')
            ->map(static fn (Collection $rows): MenuItemPrice => $rows->first());

        if ($priceRows->count() !== count($itemIds)) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Có món chưa có giá hiệu lực tại thời điểm phục vụ.'],
            ]);
        }

        return [
            'rows' => $rows,
            'menu_items' => $menuItems,
            'price_rows' => $priceRows,
        ];
    }

    public function assertReservationPreordersRemainValid(int $reservationId, Carbon $serviceStart): void
    {
        $rows = ReservationOrderItem::query()
            ->join('reservation_orders as ro', 'ro.order_id', '=', 'reservation_order_items.order_id')
            ->where('ro.reservation_id', $reservationId)
            ->where('ro.order_type', ReservationOrderType::PreOrder->value)
            ->whereIn('ro.status', [
                ReservationOrderStatus::Active->value,
                ReservationOrderStatus::Completed->value,
            ])
            ->where('reservation_order_items.status', '!=', 'Cancelled')
            ->selectRaw('reservation_order_items.item_id as item_id, SUM(reservation_order_items.quantity) as quantity')
            ->groupBy('reservation_order_items.item_id')
            ->get()
            ->map(static fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'quantity' => (int) $row->quantity,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return;
        }

        try {
            $this->prepareRequestedItems($rows, $serviceStart, $reservationId);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'start_time' => $e->errors()['pre_order_items'] ?? ['Existing pre-order items are no longer valid for the new service time.'],
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $requestedItems
     * @return array<int, array{item_id:int, quantity:int}>
     */
    public function normalizeRequestedItems(array $requestedItems): array
    {
        $normalized = [];

        foreach ($requestedItems as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($itemId <= 0 || $quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'item_id' => $itemId,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, int> $itemIds
     * @return array<int, int>
     */
    private function existingDailyPreorderQuantities(array $itemIds, Carbon $serviceStart, ?int $ignoreReservationId = null): array
    {
        if ($itemIds === []) {
            return [];
        }

        $dayStart = $serviceStart->copy()->startOfDay();
        $dayEnd = $serviceStart->copy()->endOfDay();

        return ReservationOrderItem::query()
            ->join('reservation_orders as ro', 'ro.order_id', '=', 'reservation_order_items.order_id')
            ->join('reservations as r', 'r.reservation_id', '=', 'ro.reservation_id')
            ->whereIn('reservation_order_items.item_id', $itemIds)
            ->where('ro.order_type', ReservationOrderType::PreOrder->value)
            ->whereIn('ro.status', [
                ReservationOrderStatus::Active->value,
                ReservationOrderStatus::Completed->value,
            ])
            ->whereIn('r.status', [
                ReservationStatus::Confirmed->value,
                ReservationStatus::checkedInDbValue(),
                ReservationStatus::Completed->value,
            ])
            ->whereBetween('r.start_time', [$dayStart, $dayEnd])
            ->when($ignoreReservationId !== null, static function ($query) use ($ignoreReservationId) {
                $query->where('r.reservation_id', '!=', $ignoreReservationId);
            })
            ->selectRaw('reservation_order_items.item_id as item_id, COALESCE(SUM(reservation_order_items.quantity), 0) as total_quantity')
            ->groupBy('reservation_order_items.item_id')
            ->pluck('total_quantity', 'item_id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }
}

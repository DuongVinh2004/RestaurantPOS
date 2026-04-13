<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Models\MenuItem;
use App\Models\MenuItemPrice;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Models\ReservationOrderItem;
use App\Models\RestaurantTable;
use App\Services\Branch\BranchContextService;
use App\Services\ReservationLockService;
use App\Support\AuditEvent;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffTableOrderService
{
    private readonly BranchContextService $branchContextService;

    public function __construct(
        private readonly ReservationLockService $locks,
        ?BranchContextService $branchContextService = null,
        private readonly ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
    }

    /**
     * Tạo (hoặc reuse) order OnSpot cho bàn đang phục vụ.
     * - reservationId optional: nếu 0, server tự resolve reservation Reserved của bàn
     * - items optional: có thể tạo order rỗng rồi addItems sau
     */
    public function createOnSpotOrder(
        int $tableId,
        int $reservationId,
        array $items,
        ?int $staffUserId = null,
        string $idempotencyKey = '',
        string $notes = '',
        ?int $expectedRowVersion = null,
    ): ReservationOrder {
        $idempotencyKey = trim($idempotencyKey);

        // Idempotency (optional)
        if ($idempotencyKey !== '') {
            $cache = Cache::store('redis');
            $hit = $cache->get('booking:idem:staff_order:' . $this->buildIdempotencyScopeSuffix($idempotencyKey, [
                'table_id' => $tableId,
                'reservation_id' => $reservationId,
                'staff_user_id' => $staffUserId,
            ]));
            if ($hit) {
                $existing = ReservationOrder::query()->where('order_id', (int) $hit)->first();
                if ($existing) {
                    return $existing;
                }
            }
        }

        try {
            return $this->locks->withTableLocks([$tableId], function () use ($tableId, $reservationId, $items, $staffUserId, $idempotencyKey, $notes, $expectedRowVersion) {
                return DB::transaction(function () use ($tableId, $reservationId, $items, $staffUserId, $idempotencyKey, $notes, $expectedRowVersion) {
                    /** @var RestaurantTable $table */
                $table = RestaurantTable::query()->where('table_id', $tableId)->lockForUpdate()->firstOrFail();
                if (($table->status?->value ?? (string) $table->status) !== RestaurantTableStatus::Occupied->value) {
                    throw ValidationException::withMessages([
                        'table_id' => 'Table is not occupied (service not started).',
                    ]);
                }

                // Resolve active reservation if not provided
                if ($reservationId <= 0) {
                    $reservationId = $this->resolveActiveReservationIdForTable($tableId);
                }

                /** @var Reservation $reservation */
                $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
                if ($reservation->status !== ReservationStatus::Reserved) {
                    throw ValidationException::withMessages([
                        'reservation_id' => 'Reservation must be checked-in (Reserved) to create on-spot orders.',
                    ]);
                }
                $this->assertReservationBillEditable($reservation);

                // ensure reservation includes this table
                $hasTable = DB::table('reservation_tables')
                    ->where('reservation_id', $reservationId)
                    ->where('table_id', $tableId)
                    ->exists();
                if (! $hasTable) {
                    throw ValidationException::withMessages([
                        'reservation_id' => 'Reservation is not assigned to this table.',
                    ]);
                }

                $tableBranchId = $this->branchContextService->resolveBranchId($table->branch_id ?? null, false);
                $this->assertOperationalBranchAccessible($tableBranchId, $staffUserId);
                $this->ensureReservationBranchAligned($reservation, $tableBranchId, $staffUserId);

                // Reuse active order if exists
                $existing = ReservationOrder::query()
                    ->where('reservation_id', $reservationId)
                    ->where('order_type', ReservationOrderType::OnSpot)
                    ->where('status', ReservationOrderStatus::Active)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $this->assertExpectedOrderRowVersion($existing, $expectedRowVersion);
                }

                $order = $existing ?: new ReservationOrder();
                $trimmedNotes = trim($notes);
                $hasItems = is_array($items) && count($items) > 0;
                if ($existing && ! $hasItems && ($trimmedNotes === '' || $trimmedNotes === (string) ($existing->notes ?? ''))) {
                    AuditEvent::info('staff.table_order.create_noop', [
                        'table_id' => $tableId,
                        'reservation_id' => $reservationId,
                        'order_id' => (int) $existing->order_id,
                        'actor_user_id' => $staffUserId,
                        'reason' => $trimmedNotes === '' ? 'reuse_active_order_without_changes' : 'notes_unchanged',
                    ]);

                    if ($idempotencyKey !== '') {
                        Cache::store('redis')->set(
                            'booking:idem:staff_order:' . $this->buildIdempotencyScopeSuffix($idempotencyKey, [
                                'table_id' => $tableId,
                                'reservation_id' => $reservationId,
                                'staff_user_id' => $staffUserId,
                            ]),
                            (string) $existing->order_id,
                            3600
                        );
                    }

                    return $existing;
                }

                if (! $existing) {
                    $order->reservation_id = $reservationId;
                    $order->order_type = ReservationOrderType::OnSpot;
                    $order->status = ReservationOrderStatus::Active;
                    $order->created_by = $staffUserId;
                }
                $order->notes = $trimmedNotes !== '' ? $trimmedNotes : $order->notes;
                $order->updated_by = $staffUserId;
                $order->save();

                // Append items if provided
                if ($hasItems) {
                    $this->appendItems($order->order_id, $items, $staffUserId);
                }

                if ($idempotencyKey !== '') {
                    Cache::store('redis')->set(
                        'booking:idem:staff_order:' . $this->buildIdempotencyScopeSuffix($idempotencyKey, [
                            'table_id' => $tableId,
                            'reservation_id' => $reservationId,
                            'staff_user_id' => $staffUserId,
                        ]),
                        (string) $order->order_id,
                        3600
                    );
                }

                return $order;
                });
            });
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }
    }

    public function addItems(
        int $orderId,
        array $items,
        ?int $staffUserId = null,
        string $idempotencyKey = '',
        ?int $expectedRowVersion = null,
    ): ReservationOrder {
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey !== '') {
            $hit = Cache::store('redis')->get('booking:idem:staff_order_items:' . $this->buildIdempotencyScopeSuffix($idempotencyKey, [
                'order_id' => $orderId,
                'staff_user_id' => $staffUserId,
            ]));
            if ($hit) {
                $existing = ReservationOrder::query()->where('order_id', (int) $hit)->first();
                if ($existing) {
                    return $existing;
                }
            }
        }

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

        try {
            return $this->locks->withLockKeys(
                array_merge(
                    [config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation') . ':' . $reservationId],
                    array_map(fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table') . ':' . $id, $tableIds),
                ),
                function () use ($orderId, $items, $staffUserId, $idempotencyKey, $reservationId, $tableIds, $expectedRowVersion) {
                    return DB::transaction(function () use ($orderId, $items, $staffUserId, $idempotencyKey, $reservationId, $tableIds, $expectedRowVersion) {
                        /** @var ReservationOrder $order */
                    $order = ReservationOrder::query()->where('order_id', $orderId)->lockForUpdate()->firstOrFail();
                    $this->assertExpectedOrderRowVersion($order, $expectedRowVersion);

                    if (($order->status?->value ?? (string) $order->status) !== ReservationOrderStatus::Active->value) {
                        throw ValidationException::withMessages(['order_id' => 'Order is not active.']);
                    }

                    /** @var Reservation $reservation */
                    $reservation = Reservation::query()->where('reservation_id', $reservationId)->lockForUpdate()->firstOrFail();
                    if (($reservation->status?->value ?? (string) $reservation->status) !== ReservationStatus::Reserved->value) {
                        throw ValidationException::withMessages([
                            'reservation_id' => 'Reservation is not currently in service.',
                        ]);
                    }
                    $this->assertReservationBillEditable($reservation);

                    if (! empty($tableIds)) {
                        $occupiedTables = RestaurantTable::query()
                            ->whereIn('table_id', $tableIds)
                            ->lockForUpdate()
                            ->get(['table_id', 'branch_id', 'status']);

                        $occupiedCount = $occupiedTables
                            ->filter(fn (RestaurantTable $table) => ($table->status?->value ?? (string) $table->status) === RestaurantTableStatus::Occupied->value)
                            ->count();

                        if ($occupiedTables->count() !== count($tableIds) || $occupiedCount !== count($tableIds)) {
                            throw ValidationException::withMessages([
                                'reservation_id' => 'Assigned tables are not currently occupied.',
                            ]);
                        }

                        $tableBranchId = $this->branchContextService->assertSingleBranch(
                            $occupiedTables->pluck('branch_id')->all(),
                            'Assigned tables must belong to a single branch.',
                            'reservation_id',
                            false
                        );
                        $this->assertOperationalBranchAccessible($tableBranchId, $staffUserId);
                        $this->ensureReservationBranchAligned($reservation, $tableBranchId, $staffUserId);
                    } else {
                        $this->assertOperationalBranchAccessible(
                            $this->branchContextService->resolveBranchId($reservation->branch_id ?? null, false),
                            $staffUserId,
                        );
                    }

                    $this->appendItems($order->order_id, $items, $staffUserId);

                    $order->updated_by = $staffUserId;
                    $order->save();

                    if ($idempotencyKey !== '') {
                        Cache::store('redis')->set(
                            'booking:idem:staff_order_items:' . $this->buildIdempotencyScopeSuffix($idempotencyKey, [
                                'order_id' => $orderId,
                                'staff_user_id' => $staffUserId,
                            ]),
                            (string) $order->order_id,
                            3600
                        );
                    }

                    return $order;
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

        $this->staffBranchContextService()->assertAccessibleBranch($staffUserId, $branchId);
    }

    private function staffBranchContextService(): StaffBranchContextService
    {
        return $this->staffBranchContextService ?? app(StaffBranchContextService::class);
    }

    private function ensureReservationBranchAligned(Reservation $reservation, int $tableBranchId, ?int $staffUserId = null): int
    {
        if ($reservation->branch_id === null || $reservation->branch_id === '') {
            $reservation->branch_id = $tableBranchId;
            $reservation->updated_by = $staffUserId;
            $reservation->save();

            return $tableBranchId;
        }

        return $this->branchContextService->assertSameBranch(
            $reservation->branch_id,
            $tableBranchId,
            'Reservation branch does not match the assigned table branch.',
            'reservation_id',
            false
        );
    }

    private function buildIdempotencyScopeSuffix(string $idempotencyKey, array $context = []): string
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        ksort($normalized);

        if ($normalized === []) {
            return $idempotencyKey;
        }

        return $idempotencyKey . ':' . hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function resolveActiveReservationIdForTable(int $tableId): int
    {
        $rid = DB::table('reservation_tables as rt')
            ->join('reservations as r', 'r.reservation_id', '=', 'rt.reservation_id')
            ->where('rt.table_id', $tableId)
            ->where('r.status', ReservationStatus::Reserved->value)
            ->orderByDesc('r.checked_in_at')
            ->value('r.reservation_id');

        if (! $rid) {
            throw ValidationException::withMessages(['reservation_id' => 'No active reservation in service for this table.']);
        }
        return (int) $rid;
    }

    private function appendItems(int $orderId, array $items, ?int $staffUserId = null): void
    {
        $normalized = [];
        foreach ($items as $row) {
            $mid = (int) ($row['menu_item_id'] ?? $row['item_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? $row['quantity'] ?? 0);
            if ($mid <= 0 || $qty <= 0) {
                continue;
            }
            $normalized[] = ['item_id' => $mid, 'qty' => $qty, 'note' => (string) ($row['note'] ?? $row['notes'] ?? '')];
        }
        if (count($normalized) === 0) {
            throw ValidationException::withMessages(['items' => 'No valid items.']);
        }

        $itemIds = array_values(array_unique(array_map(fn ($x) => (int) $x['item_id'], $normalized)));
        $menuItems = MenuItem::query()
            ->whereIn('item_id', $itemIds)
            ->where('is_available', 1)
            ->get(['item_id', 'name'])
            ->keyBy('item_id');

        if ($menuItems->count() !== count($itemIds)) {
            throw ValidationException::withMessages(['items' => 'Some menu items are not available.']);
        }

        $now = now();
        $priceRows = MenuItemPrice::query()
            ->whereIn('item_id', $itemIds)
            ->effectiveAt($now)
            ->orderBy('item_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->get()
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->first());

        $incomingCurrencies = [];
        foreach ($normalized as $row) {
            $priceRow = $priceRows->get((int) $row['item_id']);
            $currency = $this->normalizeCurrency($priceRow ? (string) $priceRow->currency : 'VND');
            $incomingCurrencies[$currency] = true;
        }

        $existingCurrencies = ReservationOrderItem::query()
            ->where('order_id', $orderId)
            ->whereNotNull('currency')
            ->distinct()
            ->pluck('currency')
            ->map(fn ($currency) => $this->normalizeCurrency((string) $currency))
            ->filter(fn (string $currency) => $currency !== '')
            ->unique()
            ->values()
            ->all();

        if (count($existingCurrencies) > 1) {
            throw ValidationException::withMessages([
                'items' => 'Order already contains multiple currencies and cannot accept more items safely.',
            ]);
        }

        $expectedCurrency = null;
        if ($existingCurrencies !== []) {
            $expectedCurrency = (string) $existingCurrencies[0];
        } elseif (count($incomingCurrencies) === 1) {
            $expectedCurrency = (string) array_key_first($incomingCurrencies);
        } elseif (count($incomingCurrencies) > 1) {
            throw ValidationException::withMessages([
                'items' => 'Items in the same order must use the same currency.',
            ]);
        }

        foreach ($normalized as $row) {
            $itemId = (int) $row['item_id'];
            $qty = (int) $row['qty'];
            $note = trim((string) $row['note']);

            $mi = $menuItems->get($itemId);
            $itemName = (string) ($mi?->name ?? '');

            $priceRow = $priceRows->get($itemId);
            $unitPrice = $priceRow ? (float) $priceRow->price : 0.0;
            $currency = $this->normalizeCurrency($priceRow ? (string) $priceRow->currency : 'VND');

            if ($expectedCurrency !== null && $currency !== $expectedCurrency) {
                throw ValidationException::withMessages([
                    'items' => 'All items in an order must share the same currency.',
                ]);
            }

            $oi = new ReservationOrderItem();
            $oi->order_id = $orderId;
            $oi->item_id = $itemId;
            $oi->quantity = $qty;
            $oi->unit_price = $unitPrice;
            $oi->currency = $currency;
            $oi->line_total = $unitPrice * $qty;
            $oi->item_name_snapshot = $itemName !== '' ? $itemName : null;
            $oi->notes = $note !== '' ? $note : null;
            $oi->status = 'Ordered';
            $oi->updated_by = $staffUserId;
            $oi->save();
        }
    }

    private function assertExpectedOrderRowVersion(ReservationOrder $order, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($order->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => ['Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.'],
            ]);
        }
    }

    private function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        return $normalized !== '' ? $normalized : 'VND';
    }
}

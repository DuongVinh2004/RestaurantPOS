<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Application\UseCases\Orders;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantTableStatus;
use App\Modules\BranchScheduling\Application\Services\BranchContextService;
use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Application\Services\ReservationLockService;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\AuditEvent;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffTableOrderService
{
    private const STALE_ROW_VERSION_MESSAGE = 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.';

    private const IDEMPOTENCY_KEY_REUSE_MESSAGE = 'Idempotency key has already been used with a different request payload. Retry with a new Idempotency-Key.';

    private readonly BranchContextService $branchContextService;

    public function __construct(
        private readonly ReservationLockService $locks,
        ?BranchContextService $branchContextService = null,
        private readonly ?StaffBranchContextService $staffBranchContextService = null,
    ) {
        $this->branchContextService = $branchContextService ?? app(BranchContextService::class);
    }

    /**
     * Táº¡o (hoáº·c reuse) order OnSpot cho bÃ n Ä‘ang phá»¥c vá»¥.
     * - reservationId optional: náº¿u 0, server tá»± resolve reservation Reserved cá»§a bÃ n
     * - items optional: cÃ³ thá»ƒ táº¡o order rá»—ng rá»“i addItems sau
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
        $createReplayPayload = $this->buildCreateOnSpotReplayPayload($items, $notes);

        if ($idempotencyKey !== '' && $reservationId > 0) {
            $this->assertCreateReplayScopeAccessible($tableId, $reservationId, $staffUserId);

            $existing = $this->loadReplayedOrder(
                cachePrefix: 'booking:idem:staff_order',
                idempotencyKey: $idempotencyKey,
                context: [
                    'table_id' => $tableId,
                    'reservation_id' => $reservationId,
                    'staff_user_id' => $staffUserId,
                ],
                payload: $createReplayPayload,
            );
            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return $this->locks->withTableLocks([$tableId], function () use ($tableId, $reservationId, $items, $staffUserId, $idempotencyKey, $notes, $expectedRowVersion, $createReplayPayload) {
                return DB::transaction(function () use ($tableId, $reservationId, $items, $staffUserId, $idempotencyKey, $notes, $expectedRowVersion, $createReplayPayload) {
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

                    if ($idempotencyKey !== '') {
                        $existing = $this->loadReplayedOrder(
                            cachePrefix: 'booking:idem:staff_order',
                            idempotencyKey: $idempotencyKey,
                            context: [
                                'table_id' => $tableId,
                                'reservation_id' => $reservationId,
                                'staff_user_id' => $staffUserId,
                            ],
                            payload: $createReplayPayload,
                        );
                        if ($existing !== null) {
                            return $existing;
                        }
                    }

                    // Reuse active order if exists
                    $existing = ReservationOrder::query()
                        ->where('reservation_id', $reservationId)
                        ->where('order_type', ReservationOrderType::OnSpot)
                        ->where('status', ReservationOrderStatus::Active)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $this->assertExpectedOrderRowVersion($existing, $expectedRowVersion);
                    } else {
                        $this->assertExpectedReservationRowVersion($reservation, $expectedRowVersion);
                    }

                    $order = $existing ?: new ReservationOrder;
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
                            $this->storeReplayedOrder(
                                cachePrefix: 'booking:idem:staff_order',
                                idempotencyKey: $idempotencyKey,
                                context: [
                                    'table_id' => $tableId,
                                    'reservation_id' => $reservationId,
                                    'staff_user_id' => $staffUserId,
                                ],
                                payload: $createReplayPayload,
                                orderId: (int) $existing->order_id,
                            );
                        }

                        return $existing;
                    }

                    if (! $existing) {
                        $order->reservation_id = $reservationId;
                        $order->setAttribute('order_type', ReservationOrderType::OnSpot);
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
                        $this->storeReplayedOrder(
                            cachePrefix: 'booking:idem:staff_order',
                            idempotencyKey: $idempotencyKey,
                            context: [
                                'table_id' => $tableId,
                                'reservation_id' => $reservationId,
                                'staff_user_id' => $staffUserId,
                            ],
                            payload: $createReplayPayload,
                            orderId: (int) $order->order_id,
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
        $addItemsReplayPayload = $this->buildAddItemsReplayPayload($items);
        if ($idempotencyKey !== '') {
            $this->assertOrderReplayScopeAccessible($orderId, $staffUserId);

            $existing = $this->loadReplayedOrder(
                cachePrefix: 'booking:idem:staff_order_items',
                idempotencyKey: $idempotencyKey,
                context: [
                    'order_id' => $orderId,
                    'staff_user_id' => $staffUserId,
                ],
                payload: $addItemsReplayPayload,
            );
            if ($existing !== null) {
                return $existing;
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
                    [config('booking.reservation_lock_reservation_prefix', 'booking:lock:reservation').':'.$reservationId],
                    array_map(fn (int $id) => config('booking.reservation_lock_prefix', 'booking:lock:table').':'.$id, $tableIds),
                ),
                function () use ($orderId, $items, $staffUserId, $idempotencyKey, $reservationId, $tableIds, $expectedRowVersion, $addItemsReplayPayload) {
                    return DB::transaction(function () use ($orderId, $items, $staffUserId, $idempotencyKey, $reservationId, $tableIds, $expectedRowVersion, $addItemsReplayPayload) {
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
                            $this->storeReplayedOrder(
                                cachePrefix: 'booking:idem:staff_order_items',
                                idempotencyKey: $idempotencyKey,
                                context: [
                                    'order_id' => $orderId,
                                    'staff_user_id' => $staffUserId,
                                ],
                                payload: $addItemsReplayPayload,
                                orderId: (int) $order->order_id,
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

    private function assertCreateReplayScopeAccessible(int $tableId, int $reservationId, ?int $staffUserId): void
    {
        /** @var RestaurantTable $table */
        $table = RestaurantTable::query()->where('table_id', $tableId)->firstOrFail();

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->where('reservation_id', $reservationId)->firstOrFail();

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
        $this->assertReplayReservationBranchAligned($reservation, $tableBranchId);
        $this->assertOperationalBranchAccessible($tableBranchId, $staffUserId);
    }

    private function assertOrderReplayScopeAccessible(int $orderId, ?int $staffUserId): void
    {
        /** @var ReservationOrder|null $order */
        $order = ReservationOrder::query()->where('order_id', $orderId)->first();
        if (! $order instanceof ReservationOrder) {
            return;
        }

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->where('reservation_id', $order->reservation_id)->firstOrFail();
        $tableIds = DB::table('reservation_tables')
            ->where('reservation_id', (int) $order->reservation_id)
            ->orderBy('table_id')
            ->pluck('table_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($tableIds === []) {
            $this->assertOperationalBranchAccessible(
                $this->branchContextService->resolveBranchId($reservation->branch_id ?? null, false),
                $staffUserId,
            );

            return;
        }

        $tables = RestaurantTable::query()
            ->whereIn('table_id', $tableIds)
            ->get(['table_id', 'branch_id']);

        if ($tables->count() !== count($tableIds)) {
            throw ValidationException::withMessages([
                'reservation_id' => 'Reservation is assigned to unknown tables.',
            ]);
        }

        $tableBranchId = $this->branchContextService->assertSingleBranch(
            $tables->pluck('branch_id')->all(),
            'Assigned tables must belong to a single branch.',
            'reservation_id',
            false
        );
        $branchId = $this->assertReplayReservationBranchAligned($reservation, $tableBranchId);
        $this->assertOperationalBranchAccessible($branchId, $staffUserId);
    }

    private function assertReplayReservationBranchAligned(Reservation $reservation, int $tableBranchId): int
    {
        if ($reservation->branch_id === null || $reservation->branch_id === '') {
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

    /**
     * @param  array<string,mixed>  $context
     * @param  array<string,mixed>  $payload
     */
    private function loadReplayedOrder(string $cachePrefix, string $idempotencyKey, array $context, array $payload): ?ReservationOrder
    {
        $entry = Cache::store('redis')->get($cachePrefix.':'.$this->buildIdempotencyScopeSuffix($idempotencyKey, $context));
        if (! is_string($entry) && ! is_int($entry)) {
            return null;
        }

        $cachedPayloadHash = null;
        $orderId = 0;

        if (is_int($entry) || ctype_digit((string) $entry)) {
            $orderId = (int) $entry;
        } else {
            $decoded = json_decode((string) $entry, true);
            if (! is_array($decoded)) {
                return null;
            }

            $orderId = (int) ($decoded['order_id'] ?? 0);
            $cachedPayloadHash = is_string($decoded['payload_hash'] ?? null)
                ? (string) $decoded['payload_hash']
                : null;
        }

        if ($orderId <= 0) {
            return null;
        }

        $existing = ReservationOrder::query()->where('order_id', $orderId)->first();
        if (! $existing instanceof ReservationOrder) {
            return null;
        }

        if ($cachedPayloadHash !== null && $cachedPayloadHash !== $this->hashIdempotencyPayload($payload)) {
            throw ValidationException::withMessages([
                'idempotency_key' => [self::IDEMPOTENCY_KEY_REUSE_MESSAGE],
            ]);
        }

        return $existing;
    }

    /**
     * @param  array<string,mixed>  $context
     * @param  array<string,mixed>  $payload
     */
    private function storeReplayedOrder(string $cachePrefix, string $idempotencyKey, array $context, array $payload, int $orderId): void
    {
        Cache::store('redis')->set(
            $cachePrefix.':'.$this->buildIdempotencyScopeSuffix($idempotencyKey, $context),
            json_encode([
                'order_id' => $orderId,
                'payload_hash' => $this->hashIdempotencyPayload($payload),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            3600
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

        return $idempotencyKey.':'.hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return array{items:array<int,array{item_id:int,qty:int,note:string}>,notes:string}
     */
    private function buildCreateOnSpotReplayPayload(array $items, string $notes): array
    {
        return [
            'items' => $this->normalizeIdempotencyItems($items),
            'notes' => trim($notes),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return array{items:array<int,array{item_id:int,qty:int,note:string}>}
     */
    private function buildAddItemsReplayPayload(array $items): array
    {
        return [
            'items' => $this->normalizeIdempotencyItems($items),
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array{item_id:int,qty:int,note:string}>
     */
    private function normalizeIdempotencyItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $row) {
            $itemId = (int) ($row['menu_item_id'] ?? $row['item_id'] ?? 0);
            $quantity = (int) ($row['qty'] ?? $row['quantity'] ?? 0);
            if ($itemId <= 0 || $quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'item_id' => $itemId,
                'qty' => $quantity,
                'note' => trim((string) ($row['note'] ?? $row['notes'] ?? '')),
            ];
        }

        usort($normalized, static fn (array $left, array $right): int => [$left['item_id'], $left['qty'], $left['note']] <=> [$right['item_id'], $right['qty'], $right['note']]);

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function hashIdempotencyPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

        if ($priceRows->count() !== count($itemIds)) {
            throw ValidationException::withMessages([
                'items' => 'Some menu items do not have an effective price.',
            ]);
        }

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

            $oi = new ReservationOrderItem;
            $oi->order_id = $orderId;
            $oi->item_id = $itemId;
            $oi->quantity = $qty;
            $oi->unit_price = number_format($unitPrice, 2, '.', '');
            $oi->currency = $currency;
            $oi->line_total = number_format($unitPrice * $qty, 2, '.', '');
            $oi->item_name_snapshot = $itemName !== '' ? $itemName : null;
            $oi->notes = $note !== '' ? $note : null;
            $oi->status = ReservationOrderItemStatus::Ordered;
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
                'row_version' => [self::STALE_ROW_VERSION_MESSAGE],
            ]);
        }
    }

    private function assertExpectedReservationRowVersion(Reservation $reservation, ?int $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            return;
        }

        if ((int) ($reservation->row_version ?? 1) !== $expectedRowVersion) {
            throw ValidationException::withMessages([
                'row_version' => [self::STALE_ROW_VERSION_MESSAGE],
            ]);
        }
    }

    private function normalizeCurrency(string $currency): string
    {
        $normalized = strtoupper(trim($currency));

        return $normalized !== '' ? $normalized : 'VND';
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Reservation;

use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Enums\ReservationOrderType;
use App\Enums\ReservationStatus;
use App\Models\MenuItem;
use App\Models\MenuItemPrice;
use App\Models\Reservation;
use App\Models\ReservationOrder;
use App\Models\ReservationOrderItem;
use App\Models\User;
use App\Services\NotificationOutboxService;
use App\Services\ReservationCodeGenerator;
use App\Services\ReservationLockService;
use App\Services\TableHoldService;
use App\Support\AuditEvent;
use App\Support\AvailabilityCacheVersion;
use App\Support\DatabaseWriteConflictMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationCreateService
{
    public function __construct(
        private readonly TableHoldService $tableHoldService,
        private readonly ReservationLockService $lockService,
        private readonly ReservationCodeGenerator $codeGenerator,
        private readonly NotificationOutboxService $notificationOutboxService,
        private readonly ReservationConflictValidator $conflictValidator,
        private readonly ReservationTableAssignmentService $tableAssignmentService,
    ) {
    }

    public function createReservation(array $payload, ?int $actorUserId = null, array $options = []): Reservation
    {
        $startUtc = Carbon::parse((string) $payload['start_time'])->utc();
        $endUtc = Carbon::parse((string) $payload['end_time'])->utc();

        $holdId = isset($payload['hold_id']) ? (string) $payload['hold_id'] : null;
        $sessionId = isset($payload['session_id']) ? (string) $payload['session_id'] : null;
        $skipLocking = (bool) ($options['skip_locking'] ?? false);
        $trustedHoldIds = array_values(array_unique(array_filter(
            array_map('strval', (array) ($options['trusted_hold_ids'] ?? [])),
            static fn (string $value) => $value !== ''
        )));

        $tableIds = $this->tableAssignmentService->resolveTableIdsFromPayloadOrHold($payload, $holdId, $sessionId, $startUtc, $endUtc);
        $tableIds = array_values(array_unique(array_map('intval', $tableIds)));
        sort($tableIds);

        if (is_string($holdId) && $holdId !== '') {
            $trustedHoldIds[] = $holdId;
            $trustedHoldIds = array_values(array_unique($trustedHoldIds));
        }

        $runner = function () use ($payload, $actorUserId, $startUtc, $endUtc, $tableIds, $holdId, $sessionId, $trustedHoldIds) {
            return DB::transaction(function () use ($payload, $actorUserId, $startUtc, $endUtc, $tableIds, $holdId, $sessionId, $trustedHoldIds) {
                $this->tableHoldService->expireStaleHolds();

                $userId = $this->resolveReservationUserId($payload, $actorUserId);
                $guestSnapshot = $this->resolveGuestSnapshot($payload, $userId);

                $user = User::query()
                    ->where('user_id', $userId)
                    ->where('is_deleted', 0)
                    ->first();
                if ($userId !== null && ! $user) {
                    throw ValidationException::withMessages([
                        'user_id' => ['User không tồn tại hoặc đã bị xoá.'],
                    ]);
                }

                if (is_string($holdId) && $holdId !== '' && is_string($sessionId) && $sessionId !== '') {
                    $this->tableAssignmentService->lockAndAssertActiveHoldForReservation($holdId, $sessionId, $startUtc, $endUtc);
                }

                $guestCount = (int) $payload['guest_count'];
                $tables = $this->conflictValidator->lockAndLoadTables($tableIds);
                $this->conflictValidator->assertTablesAllocatableAndCapacity($tables, $tableIds, $guestCount);
                $this->conflictValidator->assertNoCreateConflicts($tableIds, $startUtc, $endUtc, $trustedHoldIds);

                $reservation = new Reservation();
                $reservation->user_id = $userId;
                $reservation->guest_name = $guestSnapshot['guest_name'];
                $reservation->guest_phone = $guestSnapshot['guest_phone'];
                $reservation->guest_email = $guestSnapshot['guest_email'];
                $reservation->reservation_code = $this->codeGenerator->generate($startUtc);
                $now = Carbon::now('UTC');
                $reservation->reserved_at = $now;
                $reservation->start_time = $startUtc;
                $reservation->end_time = $endUtc;
                $reservation->guest_count = $guestCount;
                $reservation->status = ReservationStatus::Confirmed;
                $reservation->source = $actorUserId !== null
                    && $actorUserId > 0
                    && ($userId === null || $actorUserId !== $userId)
                    ? 'Offline'
                    : 'Online';
                $reservation->notes = $payload['notes'] ?? null;
                $reservation->created_by = $actorUserId;
                $reservation->updated_by = $actorUserId;
                $reservation->save();
                $reservation->tables()->attach($tableIds);

                $this->createPreorderIfPresent($reservation, $payload['pre_order_items'] ?? null, $startUtc, $actorUserId);

                if (is_string($holdId) && $holdId !== '' && is_string($sessionId) && $sessionId !== '') {
                    $this->tableAssignmentService->confirmHoldForReservation($holdId, $sessionId, (int) $reservation->reservation_id, $actorUserId, $now);
                }

                AuditEvent::info('reservation_created', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'reservation_code' => (string) $reservation->reservation_code,
                    'user_id' => $reservation->user_id !== null ? (int) $reservation->user_id : null,
                    'guest_name' => $reservation->guest_name,
                    'guest_phone' => $reservation->guest_phone,
                    'guest_email' => $reservation->guest_email,
                    'source' => (string) $reservation->source,
                    'actor_user_id' => $actorUserId,
                    'start_time_utc' => $startUtc->toIso8601String(),
                    'end_time_utc' => $endUtc->toIso8601String(),
                    'table_ids' => $tableIds,
                    'hold_id' => $holdId ?: null,
                ]);

                $this->notificationOutboxService->enqueueReservationCreated($reservation);

                return (int) $reservation->reservation_id;
            });
        };

        try {
            $reservationId = (int) ($skipLocking
                ? $runner()
                : $this->lockService->withTableLocks($tableIds, $runner));
        } catch (QueryException $e) {
            $mapped = DatabaseWriteConflictMapper::toValidationException($e);
            if ($mapped !== null) {
                throw $mapped;
            }

            throw $e;
        }

        AvailabilityCacheVersion::bump();

        return Reservation::query()
            ->with(['user', 'tables', 'orders.items.item', 'payments'])
            ->where('reservation_id', $reservationId)
            ->firstOrFail();
    }

    private function createPreorderIfPresent(Reservation $reservation, mixed $preOrderItems, Carbon $startUtc, ?int $actorUserId): void
    {
        if (! is_array($preOrderItems) || count($preOrderItems) === 0) {
            return;
        }

        $normalizedPreOrderItems = [];
        foreach ($preOrderItems as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }
            $normalizedPreOrderItems[] = [
                'item_id' => $itemId,
                'quantity' => $qty,
            ];
        }

        if (count($normalizedPreOrderItems) === 0) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Danh sách pre-order không hợp lệ.'],
            ]);
        }

        $itemIds = array_values(array_unique(array_map(
            fn ($x) => (int) $x['item_id'],
            $normalizedPreOrderItems
        )));

        if (! MenuItem::supportsPreorderColumns()) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Hệ thống chưa được đồng bộ contract pre-order. Vui lòng áp dụng patch database mới nhất rồi thử lại.'],
            ]);
        }

        $menuItems = MenuItem::query()
            ->whereIn('item_id', $itemIds)
            ->where('is_available', 1)
            ->get(['item_id', 'name', 'is_preorder_enabled', 'preorder_quota_per_day', 'preorder_cutoff_minutes'])
            ->keyBy('item_id');

        if ($menuItems->count() !== count($itemIds)) {
            throw ValidationException::withMessages([
                'pre_order_items' => ['Có món không tồn tại hoặc đang unavailable.'],
            ]);
        }

        $reservationDate = $startUtc->copy()->toDateString();
        $priceRows = MenuItemPrice::query()
            ->whereIn('item_id', $itemIds)
            ->effectiveAt($startUtc)
            ->orderBy('item_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->get()
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->first());

        foreach ($normalizedPreOrderItems as $row) {
            $menuItem = $menuItems->get((int) $row['item_id']);
            if (! $menuItem) {
                throw ValidationException::withMessages([
                    'pre_order_items' => ['Có món không tồn tại hoặc đang unavailable.'],
                ]);
            }

            if (! (bool) ($menuItem->is_preorder_enabled ?? false)) {
                throw ValidationException::withMessages([
                    'pre_order_items' => [sprintf('Món %s không cho phép pre-order.', (string) $menuItem->name)],
                ]);
            }

            $cutoffMinutes = (int) ($menuItem->preorder_cutoff_minutes ?? 0);
            if ($cutoffMinutes > 0 && Carbon::now('UTC')->addMinutes($cutoffMinutes)->gt($startUtc)) {
                throw ValidationException::withMessages([
                    'pre_order_items' => [sprintf('Món %s đã quá hạn pre-order.', (string) $menuItem->name)],
                ]);
            }

            $quotaPerDay = (int) ($menuItem->preorder_quota_per_day ?? 0);
            if ($quotaPerDay > 0) {
                $existingQty = (int) DB::table('reservation_order_items as roi')
                    ->join('reservation_orders as ro', 'ro.order_id', '=', 'roi.order_id')
                    ->join('reservations as r', 'r.reservation_id', '=', 'ro.reservation_id')
                    ->where('ro.order_type', ReservationOrderType::PreOrder->value)
                    ->where('roi.item_id', (int) $row['item_id'])
                    ->whereDate('r.start_time', '=', $reservationDate)
                    ->whereIn('r.status', [
                        ReservationStatus::Confirmed->value,
                        ReservationStatus::checkedInDbValue(),
                        ReservationStatus::Completed->value,
                    ])
                    ->sum('roi.quantity');

                if ($existingQty + (int) $row['quantity'] > $quotaPerDay) {
                    throw ValidationException::withMessages([
                        'pre_order_items' => [sprintf('Món %s đã vượt quota pre-order trong ngày.', (string) $menuItem->name)],
                    ]);
                }
            }
        }

        $order = new ReservationOrder();
        $order->reservation_id = $reservation->reservation_id;
        $order->order_type = ReservationOrderType::PreOrder;
        $order->status = ReservationOrderStatus::Active;
        $order->created_by = $actorUserId;
        $order->updated_by = $actorUserId;
        $order->notes = null;
        $order->save();

        foreach ($normalizedPreOrderItems as $row) {
            $menuItem = $menuItems->get((int) $row['item_id']);
            $priceRow = $priceRows->get((int) $row['item_id']);
            $unitPrice = $priceRow ? (float) $priceRow->price : 0.0;
            $currency = $priceRow ? (string) $priceRow->currency : 'VND';
            $quantity = (int) $row['quantity'];

            $item = new ReservationOrderItem();
            $item->order_id = $order->order_id;
            $item->item_id = (int) $row['item_id'];
            $item->quantity = $quantity;
            $item->unit_price = $unitPrice;
            $item->currency = $currency;
            $item->line_total = $unitPrice * $quantity;
            $item->item_name_snapshot = $menuItem ? (string) $menuItem->name : null;
            $item->status = ReservationOrderItemStatus::Ordered;
            $item->notes = null;
            $item->updated_by = $actorUserId;
            $item->save();
        }
    }

    private function resolveReservationUserId(array $payload, ?int $actorUserId): ?int
    {
        $userId = isset($payload['user_id']) && $payload['user_id'] !== null
            ? (int) $payload['user_id']
            : null;

        if ($userId === null || $userId <= 0) {
            return null;
        }

        $user = User::query()
            ->where('user_id', $userId)
            ->where('is_deleted', 0)
            ->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'user_id' => ['User khong ton tai hoac da bi xoa.'],
            ]);
        }

        return $userId;
    }

    /**
     * @return array{guest_name:?string,guest_phone:?string,guest_email:?string}
     */
    private function resolveGuestSnapshot(array $payload, ?int $userId): array
    {
        $guestName = $this->normalizeGuestField($payload['guest_name'] ?? null);
        $guestPhone = $this->normalizeGuestField($payload['guest_phone'] ?? null);
        $guestEmail = $this->normalizeGuestField($payload['guest_email'] ?? null);

        if ($userId !== null) {
            return [
                'guest_name' => null,
                'guest_phone' => null,
                'guest_email' => null,
            ];
        }

        if ($guestName === null || $guestPhone === null) {
            throw ValidationException::withMessages([
                'guest_name' => ['guest_name is required when user_id is omitted.'],
                'guest_phone' => ['guest_phone is required when user_id is omitted.'],
            ]);
        }

        return [
            'guest_name' => $guestName,
            'guest_phone' => $guestPhone,
            'guest_email' => $guestEmail,
        ];
    }

    private function normalizeGuestField(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}

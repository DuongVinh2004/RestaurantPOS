<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Enums\PreorderStatus;
use App\Enums\ReservationStatus;
use App\Modules\Catalog\Application\UseCases\PolicyPreview\MenuPreorderPolicyService;
use App\Modules\Catalog\Domain\Models\MenuItem;
use App\Modules\Catalog\Domain\Models\MenuItemPrice;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Ordering\Domain\Models\Preorder;
use App\Modules\Ordering\Domain\Models\PreorderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\SharedKernel\Money\Money;
use App\Support\AuditEvent;
use App\Support\ValidationExceptionFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerReservationPreorderService
{
    public function __construct(
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
        private readonly MenuPreorderPolicyService $menuPreorderPolicyService,
        private readonly ReservationLockService $locks,
    ) {}

    public function showAccessiblePreorder(int $reservationId, ?int $customerUserId, ?string $sessionId): array
    {
        $reservation = $this->loadAccessibleReservation(
            reservationId: $reservationId,
            customerUserId: $customerUserId,
            sessionId: $sessionId,
            lockForUpdate: false,
        );

        return $this->buildResponse($reservation, $reservation->preorder);
    }

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

        return [
            'reservation' => $reservation,
            'current_pre_order' => $this->buildCurrentPreorderSnapshot($reservation, $reservation->preorder),
            'management_policy' => $this->buildManagementPolicy($reservation),
            'preview' => $preview,
        ];
    }

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

                $preorder = $reservation->preorder()->lockForUpdate()->first();
                if ($preorder instanceof Preorder) {
                    $this->assertPreorderRowVersion($preorder, $payload['pre_order_row_version'] ?? null);
                    // Drop old items. Customer is replacing the cart.
                    $preorder->items()->delete();
                    $this->incrementPreorderRowVersion($preorder);
                    $preorder->status = PreorderStatus::Draft;
                    $preorder->customer_user_id = $customerUserId;
                    $preorder->save();
                } else {
                    $preorder = new Preorder;
                    $preorder->reservation_id = (int) $reservation->reservation_id;
                    $preorder->status = PreorderStatus::Draft;
                    $preorder->notes = 'Customer managed pre-order';
                    $preorder->customer_user_id = $customerUserId;
                    $preorder->save();
                }

                $this->persistPreparedRows($preorder, $prepared);

                AuditEvent::info('customer.reservation.preorder.replaced', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'preorder_id' => (int) $preorder->preorder_id,
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

                return $this->buildResponse($freshReservation, $freshReservation->preorder);
            });
        });
    }

    public function submitAccessiblePreorder(int $reservationId, ?int $customerUserId, ?string $sessionId, array $payload): array
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

                $preorder = $reservation->preorder()->lockForUpdate()->first();
                if (! $preorder instanceof Preorder) {
                    throw ValidationExceptionFactory::make([
                        'pre_order' => ['No pre-order exists to submit.'],
                    ]);
                }

                $this->assertPreorderRowVersion($preorder, $payload['pre_order_row_version'] ?? null);

                $preorder->status = PreorderStatus::Submitted;
                $preorder->submitted_at = Carbon::now('UTC');
                $preorder->customer_user_id = $customerUserId;
                $this->incrementPreorderRowVersion($preorder);
                $preorder->save();

                AuditEvent::info('customer.reservation.preorder.submitted', [
                    'reservation_id' => (int) $reservation->reservation_id,
                    'preorder_id' => (int) $preorder->preorder_id,
                    'customer_user_id' => $customerUserId,
                    'customer_session_id' => $customerUserId === null ? trim((string) $sessionId) : null,
                ]);

                $freshReservation = $this->loadAccessibleReservation(
                    reservationId: $reservationId,
                    customerUserId: $customerUserId,
                    sessionId: $sessionId,
                    lockForUpdate: false,
                );

                return $this->buildResponse($freshReservation, $freshReservation->preorder);
            });
        });
    }

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

                $preorder = $reservation->preorder()->lockForUpdate()->first();
                if ($preorder instanceof Preorder) {
                    $this->assertPreorderRowVersion($preorder, $payload['pre_order_row_version'] ?? null);

                    $preorder->status = PreorderStatus::Cancelled;
                    $preorder->cancelled_at = Carbon::now('UTC');
                    $preorder->customer_user_id = $customerUserId;
                    $this->incrementPreorderRowVersion($preorder);
                    $preorder->save();

                    AuditEvent::info('customer.reservation.preorder.cleared', [
                        'reservation_id' => (int) $reservation->reservation_id,
                        'preorder_id' => (int) $preorder->preorder_id,
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

                return $this->buildResponse($freshReservation, $freshReservation->preorder);
            });
        });
    }

    private function buildResponse(Reservation $reservation, ?Preorder $currentPreorder): array
    {
        return [
            'reservation' => $reservation,
            'pre_order' => $this->buildCurrentPreorderSnapshot($reservation, $currentPreorder),
            'management_policy' => $this->buildManagementPolicy($reservation),
        ];
    }

    private function loadAccessibleReservation(int $reservationId, ?int $customerUserId, ?string $sessionId, bool $lockForUpdate): Reservation
    {
        if ($customerUserId !== null) {
            $query = Reservation::query()
                ->with(['preorder.items.item'])
                ->whereKey($reservationId)
                ->where('user_id', $customerUserId);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $reservation = $query->first();
            if ($reservation instanceof Reservation) {
                return $reservation;
            }

            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        $trimmedSessionId = trim((string) $sessionId);
        if ($trimmedSessionId === '') {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        $query = Reservation::query()
            ->with(['preorder.items.item'])
            ->whereKey($reservationId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $reservation = $query->first();
        if (! $reservation instanceof Reservation || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $trimmedSessionId)) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return $reservation;
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

    private function buildCurrentPreorderSnapshot(Reservation $reservation, ?Preorder $preorder): array
    {
        $serviceTime = Carbon::parse((string) $reservation->start_time)->utc();
        // If cancelled, we still return the structure but with present=false or we can return present=true and status=cancelled.
        // The original code filtered out Cancelled orders. Let's do the same if preorder is cancelled or null.
        if (! $preorder instanceof Preorder || $preorder->status === PreorderStatus::Cancelled) {
            return [
                'present' => false,
                'order_id' => null, // Maintaining contract key `order_id` for frontend mapping
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

        $activeItems = $preorder->relationLoaded('items') ? $preorder->items : collect();

        $currency = (string) ($reservation->bill_currency ?? 'VND');
        $subtotalMinor = 0;
        $quantityTotal = 0;
        $lines = [];

        foreach ($activeItems as $item) {
            $unitPriceMinor = Money::minorUnits($item->unit_price_snapshot ?? 0, true);
            $lineTotalMinor = $item->line_total_snapshot !== null
                ? Money::minorUnits($item->line_total_snapshot, true)
                : $unitPriceMinor * (int) $item->quantity;
            $subtotalMinor += $lineTotalMinor;
            $quantityTotal += (int) $item->quantity;
            $currency = (string) ($item->currency ?: $currency);

            /** @var MenuItem|null $menuItem */
            $menuItem = $item->relationLoaded('item') ? $item->item : null;

            $lines[] = [
                'order_item_id' => (int) $item->preorder_item_id, // Map preorder_item_id to order_item_id
                'item_id' => (int) $item->menu_item_id,
                'quantity' => (int) $item->quantity,
                'status' => 'Ordered', // Fixed status for lines
                'name' => (string) ($item->item_name_snapshot ?: ($menuItem?->name ?? '')),
                'code' => $menuItem?->code,
                'unit_price' => Money::formatMinor($unitPriceMinor),
                'line_total' => Money::formatMinor($lineTotalMinor),
                'currency' => (string) ($item->currency ?: $currency),
                'notes' => $item->notes,
                'updated_at' => optional($item->updated_at)->utc()->toIso8601String(),
            ];
        }

        return [
            'present' => $activeItems->isNotEmpty(),
            'order_id' => (int) $preorder->preorder_id, // Map preorder_id
            'order_row_version' => (int) ($preorder->row_version ?? 1),
            'order_status' => $preorder->status?->value ?? (string) $preorder->status,
            'service_time' => $serviceTime->toIso8601String(),
            'currency' => $currency,
            'lines' => $lines,
            'totals' => [
                'item_count' => count($lines),
                'quantity' => $quantityTotal,
                'subtotal' => Money::formatMinor($subtotalMinor),
            ],
            'normalized_pre_order_items' => array_map(static fn (array $line): array => [
                'item_id' => (int) $line['item_id'],
                'quantity' => (int) $line['quantity'],
            ], $lines),
        ];
    }

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
        $subtotalMinor = 0;
        $quantityTotal = 0;
        $lines = [];

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $quantity = (int) $row['quantity'];
            /** @var MenuItem $menuItem */
            $menuItem = $menuItems->get($itemId);
            /** @var MenuItemPrice $priceRow */
            $priceRow = $priceRows->get($itemId);

            $unitPriceMinor = Money::minorUnits($priceRow->price, true);
            $lineTotalMinor = $unitPriceMinor * $quantity;
            $subtotalMinor += $lineTotalMinor;
            $quantityTotal += $quantity;
            $currency = (string) ($priceRow->currency ?: $currency);

            $lines[] = [
                'item_id' => $itemId,
                'code' => (string) ($menuItem->code ?? ''),
                'name' => (string) $menuItem->name,
                'quantity' => $quantity,
                'unit_price' => Money::formatMinor($unitPriceMinor),
                'line_total' => Money::formatMinor($lineTotalMinor),
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
                'subtotal' => Money::formatMinor($subtotalMinor),
            ],
            'normalized_pre_order_items' => array_map(static fn (array $row): array => [
                'item_id' => (int) $row['item_id'],
                'quantity' => (int) $row['quantity'],
            ], $rows),
        ];
    }

    private function persistPreparedRows(Preorder $preorder, array $prepared): void
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

            $unitPriceMinor = Money::minorUnits($priceRow->price, true);
            $item = new PreorderItem;
            $item->preorder_id = (int) $preorder->preorder_id;
            $item->menu_item_id = $itemId;
            $item->quantity = $quantity;
            $item->unit_price_snapshot = Money::formatMinor($unitPriceMinor);
            $item->line_total_snapshot = Money::formatMinor($unitPriceMinor * $quantity);
            $item->currency = (string) ($priceRow->currency ?: 'VND');

            $item->item_name_snapshot = (string) $menuItem->name;
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

    private function assertPreorderRowVersion(Preorder $preorder, mixed $expectedRowVersion): void
    {
        if ($expectedRowVersion === null) {
            throw ValidationExceptionFactory::make([
                'pre_order_row_version' => ['Pre-order row version is required when an existing pre-order is being updated.'],
            ]);
        }

        if ((int) ($preorder->row_version ?? 1) !== (int) $expectedRowVersion) {
            throw ValidationExceptionFactory::make([
                'pre_order_row_version' => ['Pre-order row version does not match the latest state.'],
            ]);
        }
    }

    private function incrementPreorderRowVersion(Preorder $preorder): void
    {
        $preorder->row_version = max(1, (int) ($preorder->row_version ?? 1)) + 1;
    }
}

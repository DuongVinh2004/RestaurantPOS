<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Application\Actions;

use App\Enums\KitchenStationOutputMode;
use App\Enums\KitchenTicketStatus;
use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenTicketConsistencyInspector;
use App\Modules\KitchenDispatch\Domain\Models\KitchenOrderItemTicket;
use App\Modules\KitchenDispatch\Domain\Models\KitchenStation;
use App\Modules\KitchenDispatch\Domain\Models\KitchenStationCategoryRoute;
use App\Modules\KitchenDispatch\Domain\Policies\KitchenTicketTransitionPolicy;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\AuditEvent;
use App\Support\Auth\StaffActorGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchKitchenOrderAction
{
    private const STALE_ROW_VERSION_MESSAGE = 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.';

    public function __construct(
        private readonly OperationalRealtimeService $realtimeService,
        private readonly FeatureFlagService $featureFlags,
        private readonly KitchenTicketConsistencyInspector $ticketConsistencyInspector,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    /**
     * @return array{order: ReservationOrder, tickets: Collection<int, KitchenOrderItemTicket>, created_count:int, reused_count:int, unrouted_count:int, pinned_route_count:int}
     */
    public function execute(int $orderId, ?int $expectedOrderRowVersion = null, ?int $actorUserId = null): array
    {
        $actorUserId = StaffActorGuard::requireStaffUserId($actorUserId);

        // Dispatch la thao tac thay doi ticket theo snapshot order hien tai, nen bat buoc client gui row_version.
        if ($expectedOrderRowVersion === null) {
            throw ValidationException::withMessages([
                'row_version' => ['The row version field is required.'],
            ]);
        }

        return DB::transaction(function () use ($orderId, $expectedOrderRowVersion, $actorUserId): array {
            // Khoa order trong pham vi branch staff duoc phep de tranh dispatch vao order ngoai scope.
            $accessibleBranchIds = $this->branchContextService->accessibleBranchIds($actorUserId);

            /** @var ReservationOrder $order */
            $orderQuery = ReservationOrder::query()
                ->with(['items.item', 'reservation:reservation_id,branch_id'])
                ->lockForUpdate();

            $this->constrainReservationLookupToAccessibleBranches($orderQuery, $accessibleBranchIds);
            $order = $orderQuery->findOrFail($orderId);
            $orderBranchId = (int) $order->reservation->branch_id;

            $this->assertDispatchEnabledForReservation($order->reservation);

            if (($order->status?->value ?? (string) $order->status) !== ReservationOrderStatus::Active->value) {
                throw ValidationException::withMessages([
                    'order_id' => 'Only active orders can be dispatched to kitchen.',
                ]);
            }

            if ((int) ($order->row_version ?? 0) !== $expectedOrderRowVersion) {
                throw ValidationException::withMessages([
                    'row_version' => self::STALE_ROW_VERSION_MESSAGE,
                ]);
            }

            // Moi order item hop le se co mot ticket KDS, tao moi neu chua co hoac tai su dung ticket cu khi duoc phep.
            $createdCount = 0;
            $reusedCount = 0;
            $unroutedCount = 0;
            $pinnedRouteCount = 0;
            $ticketIds = [];

            foreach ($order->items as $orderItem) {
                $itemStatus = $this->normalizeOrderItemStatus($orderItem);
                // Item da huy khong duoc dua vao kitchen queue.
                if ($itemStatus === ReservationOrderItemStatus::Cancelled) {
                    continue;
                }

                // Combo parent items do not go to the kitchen; their components will be dispatched instead.
                if ($orderItem->item && $orderItem->item->is_combo) {
                    continue;
                }

                /** @var KitchenOrderItemTicket|null $ticket */
                $ticket = KitchenOrderItemTicket::query()
                    ->where('order_item_id', $orderItem->order_item_id)
                    ->lockForUpdate()
                    ->first();

                $isNew = false;
                $currentTicketStatus = null;
                if (! $ticket instanceof KitchenOrderItemTicket) {
                    // Ticket moi chi khoi tao metadata co ban; status cuoi cung van duoc suy ra tu trang thai order item.
                    $ticket = new KitchenOrderItemTicket;
                    $ticket->order_item_id = (int) $orderItem->order_item_id;
                    $ticket->first_dispatched_at = Carbon::now('UTC');
                    $ticket->dispatch_count = 0;
                    $ticket->recall_count = 0;
                    $ticket->row_version = 1;
                    $ticket->created_by = $actorUserId;
                    $isNew = true;
                } else {
                    $currentTicketStatus = $this->normalizeTicketStatus($ticket);
                    $ticket->loadMissing(['station', 'route', 'orderItem']);

                    // Active ticket bi lech order item hoac routing phai duoc reconcile truoc, tranh redispatch len drift cu.
                    if (! $this->isTerminalTicketStatus($currentTicketStatus)
                        && ! $this->ticketConsistencyInspector->canRedispatchActiveTicket($ticket)) {
                        $inspection = $this->ticketConsistencyInspector->describe($ticket);

                        AuditEvent::warning('staff.kitchen.redispatch_blocked_for_drifted_ticket', [
                            'order_id' => $orderId,
                            'ticket_id' => (int) $ticket->ticket_id,
                            'order_item_id' => (int) $ticket->order_item_id,
                            'actor_user_id' => $actorUserId,
                            'sync_status' => (string) ($inspection['reconciliation']['sync_status'] ?? 'unknown'),
                            'routing_status' => (string) ($inspection['reconciliation']['routing_status'] ?? 'unknown'),
                            'drift_reasons' => (array) ($inspection['reconciliation']['drift_reasons'] ?? []),
                        ]);

                        throw ValidationException::withMessages([
                            'ticket_id' => [
                                'Active kitchen tickets with drifted routing or item state cannot be redispatched until reconciled.',
                            ],
                        ]);
                    }
                }

                $route = $this->resolveRouteForOrderItem($orderItem, $orderBranchId);
                // Ticket moi hoac ticket da ket thuc ma khong tim thay route thi chi ghi nhan unrouted, khong tao ticket mo co.
                if ($route === null && ($isNew || $this->isTerminalTicketStatus($currentTicketStatus))) {
                    $unroutedCount++;

                    continue;
                }

                // Route category la nguon mac dinh; active ticket se co quyen "pin" route cu de tranh nhay station giua service.
                $effectiveStation = $route?->station;
                $effectiveRouteId = $route?->route_id !== null ? (int) $route->route_id : null;
                $effectiveRouteSource = 'Category';
                $effectiveOutputMode = $effectiveStation instanceof KitchenStation
                    ? ($effectiveStation->output_mode instanceof KitchenStationOutputMode
                        ? $effectiveStation->output_mode
                        : KitchenStationOutputMode::from((string) $effectiveStation->output_mode))
                    : null;
                $effectivePrinterTarget = $effectiveStation instanceof KitchenStation
                    ? $effectiveStation->printer_target
                    : null;

                if (! $isNew) {
                    if (! $this->isTerminalTicketStatus($currentTicketStatus)) {
                        // Active ticket tiep tuc giu route/station dang phuc vu de bep khong mat ngu canh.
                        $pinnedRouteCount++;
                    }

                    $effectiveRouteId = $ticket->route_id !== null ? (int) $ticket->route_id : $effectiveRouteId;
                    $effectiveRouteSource = (string) ($ticket->route_source ?? 'Category');
                    $effectiveOutputMode = $ticket->output_mode instanceof KitchenStationOutputMode
                        ? $ticket->output_mode
                        : KitchenStationOutputMode::from((string) $ticket->output_mode);
                    $effectivePrinterTarget = $ticket->printer_target;
                    $effectiveStation = $ticket->relationLoaded('station') && $ticket->station instanceof KitchenStation
                        ? $ticket->station
                        : KitchenStation::query()->findOrFail((int) $ticket->station_id);
                }

                // Neu khong xac dinh duoc dich den cuoi cung thi bo qua item nay va de reconciliation xu ly sau.
                if ($effectiveStation === null || $effectiveRouteId === null || ! ($effectiveOutputMode instanceof KitchenStationOutputMode)) {
                    $unroutedCount++;

                    continue;
                }

                // Dong bo snapshot ticket tu order item hien tai truoc khi tinh status va timestamp lifecycle.
                $ticket->station_id = (int) $effectiveStation->station_id;
                $ticket->order_id = (int) $order->order_id;
                $ticket->reservation_id = (int) $order->reservation_id;
                $ticket->item_id = (int) $orderItem->item_id;
                $ticket->category_id = $orderItem->item?->category_id !== null ? (int) $orderItem->item->category_id : null;
                $ticket->route_id = $effectiveRouteId;
                $ticket->route_source = $effectiveRouteSource;
                $ticket->output_mode = $effectiveOutputMode;
                $ticket->printer_target = $effectivePrinterTarget;
                $ticket->ticket_notes = $orderItem->notes;
                $ticket->ticket_status = $isNew
                    ? KitchenTicketTransitionPolicy::statusFromOrderItemStatus($itemStatus)
                    : KitchenTicketTransitionPolicy::resolveRedispatchStatus(
                        $currentTicketStatus,
                        KitchenTicketTransitionPolicy::statusFromOrderItemStatus($itemStatus)
                    );
                $this->applyTicketLifecycleTimestamps($ticket, $ticket->ticket_status, $currentTicketStatus);
                $ticket->updated_by = $actorUserId;
                $ticket->dispatch_count = ((int) $ticket->dispatch_count) + 1;

                if (! $isNew) {
                    $this->bumpTicketRowVersion($ticket);
                }

                $ticket->save();

                // Luu lai ticket vua tao/cap nhat de tra ve mot snapshot on dinh sau transaction.
                $ticketIds[] = (int) $ticket->ticket_id;

                if ($isNew) {
                    $createdCount++;
                } else {
                    $reusedCount++;
                }
            }

            /** @var Collection<int, KitchenOrderItemTicket> $tickets */
            $tickets = KitchenOrderItemTicket::query()
                ->with(['station', 'route', 'orderItem', 'item.category'])
                ->whereIn('ticket_id', $ticketIds)
                ->orderBy('ticket_id')
                ->get();

            // Audit ghi nhan tong quan, realtime danh thuc board KDS va dashboard order.
            AuditEvent::info('staff.kitchen.order_dispatched', [
                'order_id' => $orderId,
                'actor_user_id' => $actorUserId,
                'created_count' => $createdCount,
                'reused_count' => $reusedCount,
                'unrouted_count' => $unroutedCount,
            ]);

            if ($ticketIds !== [] || $unroutedCount > 0) {
                $this->realtimeService->publishKitchenEvent('kitchen.order_dispatched', [
                    'order_id' => $orderId,
                    'reservation_id' => (int) $order->reservation_id,
                    'ticket_ids' => $ticketIds,
                    'created_count' => $createdCount,
                    'reused_count' => $reusedCount,
                    'unrouted_count' => $unroutedCount,
                    'pinned_route_count' => $pinnedRouteCount,
                ], ['kitchen']);
            }

            return [
                'order' => $order->fresh(['items.item']) ?? $order,
                'tickets' => $tickets,
                'created_count' => $createdCount,
                'reused_count' => $reusedCount,
                'unrouted_count' => $unroutedCount,
                'pinned_route_count' => $pinnedRouteCount,
            ];
        }, 3);
    }

    /**
     * @param  Builder<ReservationOrder>  $query
     * @param  list<int>  $accessibleBranchIds
     */
    private function constrainReservationLookupToAccessibleBranches(Builder $query, array $accessibleBranchIds): void
    {
        // Staff khong co branch hop le thi query bi khoa rong thay vi vo tinh mo toan bo du lieu.
        if ($accessibleBranchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('reservation', function (Builder $reservationQuery) use ($accessibleBranchIds): void {
            $reservationQuery->whereIn('branch_id', $accessibleBranchIds);
        });
    }

    private function assertDispatchEnabledForReservation(?Reservation $reservation): void
    {
        // Kitchen dispatch duoc gate theo feature flag branch de rollout an toan.
        $this->featureFlags->assertEnabled(
            'staff.kitchen_dispatch',
            $reservation?->branch_id !== null ? (int) $reservation->branch_id : null,
            field: 'feature_flag',
        );
    }

    private function normalizeOrderItemStatus(ReservationOrderItem $orderItem): ReservationOrderItemStatus
    {
        return $orderItem->status instanceof ReservationOrderItemStatus
            ? $orderItem->status
            : ReservationOrderItemStatus::from((string) $orderItem->status);
    }

    private function normalizeTicketStatus(KitchenOrderItemTicket $ticket): KitchenTicketStatus
    {
        return $ticket->ticket_status instanceof KitchenTicketStatus
            ? $ticket->ticket_status
            : KitchenTicketStatus::from((string) $ticket->ticket_status);
    }

    private function isTerminalTicketStatus(?KitchenTicketStatus $status): bool
    {
        return $status?->isTerminal() ?? false;
    }

    private function resolveRouteForOrderItem(ReservationOrderItem $orderItem, int $branchId): ?KitchenStationCategoryRoute
    {
        // Route hien tai duoc map tu category cua mon trong dung branch dang phuc vu.
        $categoryId = $orderItem->item?->category_id;
        if ($categoryId === null) {
            return null;
        }

        /** @var KitchenStationCategoryRoute|null $route */
        $route = KitchenStationCategoryRoute::query()
            ->with(['station'])
            ->where('branch_id', $branchId)
            ->where('category_id', (int) $categoryId)
            ->where('is_active', true)
            ->whereHas('station', static fn ($query) => $query
                ->where('branch_id', $branchId)
                ->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('route_id')
            ->first();

        return $route;
    }

    private function applyTicketLifecycleTimestamps(
        KitchenOrderItemTicket $ticket,
        KitchenTicketStatus $nextStatus,
        ?KitchenTicketStatus $currentStatus,
    ): void {
        // Chi cham vao moc thoi gian khi ticket that su doi state; comment nay giup doc flow KDS de hinh dung hon.
        if ($currentStatus === $nextStatus) {
            return;
        }

        $now = Carbon::now('UTC');

        if ($nextStatus === KitchenTicketStatus::Queued) {
            if ($currentStatus === null) {
                $ticket->fired_at = null;
                $ticket->ready_at = null;
                $ticket->completed_at = null;
                $ticket->cancelled_at = null;
            }

            return;
        }

        if ($nextStatus === KitchenTicketStatus::Fired) {
            $ticket->fired_at = $ticket->fired_at ?? $now;

            if ($currentStatus === null || $currentStatus === KitchenTicketStatus::Queued) {
                $ticket->ready_at = null;
            }

            return;
        }

        if ($nextStatus === KitchenTicketStatus::Ready) {
            $ticket->fired_at = $ticket->fired_at ?? $now;
            $ticket->ready_at = $ticket->ready_at ?? $now;

            return;
        }

        if ($nextStatus === KitchenTicketStatus::Completed) {
            $ticket->completed_at = $ticket->completed_at ?? $now;

            return;
        }

        if ($nextStatus === KitchenTicketStatus::Cancelled) {
            $ticket->cancelled_at = $ticket->cancelled_at ?? $now;
        }
    }

    private function bumpTicketRowVersion(KitchenOrderItemTicket $ticket): void
    {
        // Ticket duoc optimistic-lock rieng de fire/bump/recall khong de len nhau.
        $ticket->row_version = max(1, (int) ($ticket->row_version ?? 1)) + 1;
    }
}

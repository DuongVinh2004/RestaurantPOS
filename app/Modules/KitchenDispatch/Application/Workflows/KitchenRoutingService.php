<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Application\Workflows;

use App\Enums\KitchenStationOutputMode;
use App\Enums\KitchenTicketStatus;
use App\Enums\ReservationOrderItemStatus;
use App\Enums\ReservationOrderStatus;
use App\Modules\Catalog\Domain\Models\MenuCategory;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\KitchenDispatch\Domain\Models\KitchenOrderItemTicket;
use App\Modules\KitchenDispatch\Domain\Models\KitchenStation;
use App\Modules\KitchenDispatch\Domain\Models\KitchenStationCategoryRoute;
use App\Modules\KitchenDispatch\Domain\Policies\KitchenTicketTransitionPolicy;
use App\Modules\Ordering\Domain\Models\ReservationOrder;
use App\Modules\Ordering\Domain\Models\ReservationOrderItem;
use App\Modules\Ordering\Domain\Policies\ReservationOrderItemStatusTransitionPolicy;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\FeatureFlags\Services\FeatureFlagService;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenRoutingService
{
    private const STALE_ROW_VERSION_MESSAGE = 'Dữ liệu đã thay đổi (row_version mismatch). Hãy reload rồi thử lại.';

    public function __construct(
        private readonly OperationalRealtimeService $realtimeService,
        private readonly FeatureFlagService $featureFlags,
        private readonly KitchenTicketConsistencyInspector $ticketConsistencyInspector,
        private readonly StaffBranchContextService $branchContextService,
    ) {}

    /**
     * @param  array<string,mixed>  $filters
     * @return Collection<int, KitchenStation>
     */
    public function listStations(array $filters = []): Collection
    {
        $branchId = isset($filters['branch_id']) && $filters['branch_id'] !== null
            ? (int) $filters['branch_id']
            : null;
        $accessibleBranchIds = $this->accessibleBranchIdsFromFilters($filters);
        $enforceAccessibleBranchScope = array_key_exists('accessible_branch_ids', $filters);

        $query = KitchenStation::query()
            ->withCount([
                'categoryRoutes as route_count' => static fn ($query) => $query->where('is_active', true),
                'tickets as queued_ticket_count' => function (Builder $query) use ($branchId, $accessibleBranchIds, $enforceAccessibleBranchScope): void {
                    $query->where('ticket_status', KitchenTicketStatus::Queued->value);
                    $this->constrainTicketQueryToReservationScope($query, $branchId, $accessibleBranchIds, $enforceAccessibleBranchScope);
                },
                'tickets as fired_ticket_count' => function (Builder $query) use ($branchId, $accessibleBranchIds, $enforceAccessibleBranchScope): void {
                    $query->where('ticket_status', KitchenTicketStatus::Fired->value);
                    $this->constrainTicketQueryToReservationScope($query, $branchId, $accessibleBranchIds, $enforceAccessibleBranchScope);
                },
                'tickets as ready_ticket_count' => function (Builder $query) use ($branchId, $accessibleBranchIds, $enforceAccessibleBranchScope): void {
                    $query->where('ticket_status', KitchenTicketStatus::Ready->value);
                    $this->constrainTicketQueryToReservationScope($query, $branchId, $accessibleBranchIds, $enforceAccessibleBranchScope);
                },
            ])
            ->orderBy('name')
            ->orderBy('station_id');

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if ($branchId !== null || $enforceAccessibleBranchScope) {
            $this->constrainStationQueryToBranchScope($query, $branchId, $accessibleBranchIds, $enforceAccessibleBranchScope);
        }

        /** @var Collection<int, KitchenStation> $stations */
        $stations = $query->get();

        return $stations;
    }

    public function findStation(int $stationId): KitchenStation
    {
        /** @var KitchenStation|null $station */
        $station = KitchenStation::query()
            ->withCount([
                'categoryRoutes as route_count' => static fn ($query) => $query->where('is_active', true),
            ])
            ->find($stationId);

        if (! $station instanceof KitchenStation) {
            throw (new ModelNotFoundException)->setModel(KitchenStation::class, [$stationId]);
        }

        return $station;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function createStation(array $payload): KitchenStation
    {
        $station = new KitchenStation;
        $station->fill($this->normalizeStationPayload($payload, true));
        $station->save();

        AuditEvent::info('admin.kitchen_station.created', [
            'station_id' => (int) $station->station_id,
            'code' => (string) $station->code,
        ]);

        return $this->findStation((int) $station->station_id);
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    public function updateStation(int $stationId, array $payload): KitchenStation
    {
        /** @var KitchenStation $station */
        $station = KitchenStation::query()->findOrFail($stationId);
        $normalized = $this->normalizeStationPayload($payload, false);
        $this->assertStationCanBeDeactivated($station, $normalized);
        $station->fill($normalized);
        $station->save();

        AuditEvent::info('admin.kitchen_station.updated', [
            'station_id' => (int) $station->station_id,
            'code' => (string) $station->code,
        ]);

        return $this->findStation($stationId);
    }

    /**
     * @return array{station: KitchenStation, routes: Collection<int, KitchenStationCategoryRoute>}
     */
    public function getStationRoutes(int $stationId): array
    {
        $station = $this->findStation($stationId);

        /** @var Collection<int, KitchenStationCategoryRoute> $routes */
        $routes = KitchenStationCategoryRoute::query()
            ->with(['category' => static fn ($query) => $query->select('category_id', 'name')])
            ->where('station_id', $stationId)
            ->orderBy('sort_order')
            ->orderBy('route_id')
            ->get();

        return [
            'station' => $station,
            'routes' => $routes,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $routes
     * @return array{station: KitchenStation, routes: Collection<int, KitchenStationCategoryRoute>}
     */
    public function syncStationRoutes(int $stationId, array $routes): array
    {
        return DB::transaction(function () use ($stationId, $routes): array {
            /** @var KitchenStation $station */
            $station = KitchenStation::query()->lockForUpdate()->findOrFail($stationId);

            $categoryIds = collect($routes)
                ->pluck('category_id')
                ->map(static fn ($value): int => (int) $value)
                ->values()
                ->all();

            $knownCategoryIds = MenuCategory::query()
                ->whereIn('category_id', $categoryIds)
                ->pluck('category_id')
                ->map(static fn ($value): int => (int) $value)
                ->all();

            if (count(array_unique($categoryIds)) !== count($knownCategoryIds)) {
                throw (new ModelNotFoundException)->setModel(MenuCategory::class, $categoryIds);
            }

            /** @var Collection<int,KitchenStationCategoryRoute> $existingRoutes */
            $existingRoutes = KitchenStationCategoryRoute::query()
                ->where('station_id', $stationId)
                ->lockForUpdate()
                ->get()
                ->keyBy('category_id');

            $conflictingRoutes = KitchenStationCategoryRoute::query()
                ->whereIn('category_id', $categoryIds)
                ->where('station_id', '!=', $stationId)
                ->lockForUpdate()
                ->get();

            if ($conflictingRoutes->isNotEmpty()) {
                $conflictCategoryIds = $conflictingRoutes
                    ->pluck('category_id')
                    ->map(static fn ($value): int => (int) $value)
                    ->values()
                    ->all();

                throw ValidationException::withMessages([
                    'routes' => [
                        'One or more menu categories are already mapped to another kitchen station. Remove the old route before reassigning it.',
                    ],
                    'category_ids' => $conflictCategoryIds,
                ]);
            }

            $incomingByCategory = collect($routes)
                ->keyBy(static fn (array $route): int => (int) $route['category_id']);

            foreach ($existingRoutes as $categoryId => $existingRoute) {
                if ($incomingByCategory->has($categoryId)) {
                    continue;
                }

                if ($this->routeHasActiveTickets((int) $existingRoute->route_id)) {
                    throw ValidationException::withMessages([
                        'routes' => ['Kitchen category routes with active tickets cannot be removed. Resolve the active kitchen tickets first.'],
                        'category_id' => [(int) $categoryId],
                    ]);
                }
            }

            foreach ($routes as $index => $route) {
                $categoryId = (int) $route['category_id'];
                /** @var KitchenStationCategoryRoute $categoryRoute */
                $categoryRoute = $existingRoutes->get($categoryId) ?? new KitchenStationCategoryRoute;
                $this->assertRouteCanBeDeactivated($categoryRoute, (bool) ($route['is_active'] ?? true));
                $categoryRoute->station_id = $stationId;
                $categoryRoute->category_id = $categoryId;
                $categoryRoute->sort_order = (int) ($route['sort_order'] ?? (($index + 1) * 10));
                $categoryRoute->is_active = (bool) ($route['is_active'] ?? true);
                $categoryRoute->save();
            }

            foreach ($existingRoutes as $categoryId => $existingRoute) {
                if ($incomingByCategory->has($categoryId)) {
                    continue;
                }

                $existingRoute->delete();
            }

            AuditEvent::info('admin.kitchen_station.routes_synced', [
                'station_id' => $stationId,
                'route_count' => count($routes),
            ]);

            return $this->getStationRoutes((int) $station->station_id);
        }, 3);
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return Collection<int, KitchenOrderItemTicket>
     */
    public function listStationTickets(int $stationId, array $filters = []): Collection
    {
        $branchId = isset($filters['branch_id']) && $filters['branch_id'] !== null
            ? (int) $filters['branch_id']
            : null;
        $accessibleBranchIds = $this->accessibleBranchIdsFromFilters($filters);
        $enforceAccessibleBranchScope = array_key_exists('accessible_branch_ids', $filters);

        $stationQuery = KitchenStation::query()->where('station_id', $stationId);
        if ($branchId !== null || $enforceAccessibleBranchScope) {
            $this->constrainStationQueryToBranchScope($stationQuery, $branchId, $accessibleBranchIds, $enforceAccessibleBranchScope);
        }
        $stationQuery->firstOrFail();

        $query = KitchenOrderItemTicket::query()
            ->with(['station', 'route', 'orderItem', 'item.category'])
            ->where('station_id', $stationId)
            ->orderByRaw("CASE `ticket_status` WHEN 'Fired' THEN 1 WHEN 'Queued' THEN 2 WHEN 'Ready' THEN 3 ELSE 4 END")
            ->orderBy('ticket_id');

        $this->constrainTicketQueryToReservationScope($query, $branchId, $accessibleBranchIds, $enforceAccessibleBranchScope);

        if (! empty($filters['status'])) {
            $query->where('ticket_status', (string) $filters['status']);
        } elseif (! (bool) ($filters['include_terminal'] ?? false)) {
            $query->whereNotIn('ticket_status', [KitchenTicketStatus::Completed->value, KitchenTicketStatus::Cancelled->value]);
        }

        /** @var Collection<int, KitchenOrderItemTicket> $tickets */
        $tickets = $query->get();

        return $tickets;
    }

    /**
     * @return array{order: ReservationOrder, tickets: Collection<int, KitchenOrderItemTicket>, created_count:int, reused_count:int, unrouted_count:int}
     */
    public function dispatchOrder(int $orderId, ?int $expectedOrderRowVersion = null, ?int $actorUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $expectedOrderRowVersion, $actorUserId): array {
            $accessibleBranchIds = $actorUserId !== null && $actorUserId > 0
                ? $this->branchContextService->accessibleBranchIds($actorUserId)
                : [];

            /** @var ReservationOrder $order */
            $orderQuery = ReservationOrder::query()
                ->with(['items.item', 'reservation:reservation_id,branch_id'])
                ->lockForUpdate();
            if ($actorUserId !== null && $actorUserId > 0) {
                $this->constrainReservationLookupToAccessibleBranches($orderQuery, $accessibleBranchIds);
            }
            $order = $orderQuery->findOrFail($orderId);

            $this->assertDispatchEnabledForReservation($order->reservation);

            if (($order->status?->value ?? (string) $order->status) !== ReservationOrderStatus::Active->value) {
                throw ValidationException::withMessages([
                    'order_id' => 'Only active orders can be dispatched to kitchen.',
                ]);
            }

            if ($expectedOrderRowVersion !== null && (int) ($order->row_version ?? 0) !== $expectedOrderRowVersion) {
                throw ValidationException::withMessages([
                    'row_version' => self::STALE_ROW_VERSION_MESSAGE,
                ]);
            }

            $createdCount = 0;
            $reusedCount = 0;
            $unroutedCount = 0;
            $pinnedRouteCount = 0;
            $ticketIds = [];

            foreach ($order->items as $orderItem) {
                $itemStatus = $this->normalizeOrderItemStatus($orderItem);
                if ($itemStatus === ReservationOrderItemStatus::Cancelled) {
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
                    $ticket = new KitchenOrderItemTicket;
                    $ticket->order_item_id = (int) $orderItem->order_item_id;
                    $ticket->first_dispatched_at = Carbon::now('UTC');
                    $ticket->dispatch_count = 0;
                    $ticket->recall_count = 0;
                    $ticket->created_by = $actorUserId;
                    $isNew = true;
                } else {
                    $currentTicketStatus = $this->normalizeTicketStatus($ticket);
                    $ticket->loadMissing(['station', 'route', 'orderItem']);

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

                $route = $this->resolveRouteForOrderItem($orderItem);
                if ($route === null && ($isNew || $this->isTerminalTicketStatus($currentTicketStatus))) {
                    $unroutedCount++;

                    continue;
                }

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

                if ($effectiveStation === null || $effectiveRouteId === null || ! ($effectiveOutputMode instanceof KitchenStationOutputMode)) {
                    $unroutedCount++;

                    continue;
                }

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
                $ticket->save();

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

    public function fireTicket(int $ticketId, ?int $actorUserId = null): KitchenOrderItemTicket
    {
        return DB::transaction(function () use ($ticketId, $actorUserId): KitchenOrderItemTicket {
            $accessibleBranchIds = $actorUserId !== null && $actorUserId > 0
                ? $this->branchContextService->accessibleBranchIds($actorUserId)
                : [];

            /** @var KitchenOrderItemTicket $ticket */
            $ticketQuery = KitchenOrderItemTicket::query()
                ->with(['orderItem', 'reservation:reservation_id,branch_id'])
                ->lockForUpdate();
            if ($actorUserId !== null && $actorUserId > 0) {
                $this->constrainReservationLookupToAccessibleBranches($ticketQuery, $accessibleBranchIds);
            }
            $ticket = $ticketQuery->findOrFail($ticketId);

            $current = $this->normalizeTicketStatus($ticket);
            KitchenTicketTransitionPolicy::assertActionAllowed($current, 'fire');

            $this->assertTicketOrderItemAllowsAction($ticket, 'fire');

            $now = Carbon::now('UTC');
            $ticket->ticket_status = KitchenTicketTransitionPolicy::nextStatusForAction($current, 'fire');
            $ticket->fired_at = $ticket->fired_at ?? $now;
            $ticket->ready_at = null;
            $ticket->updated_by = $actorUserId;
            $ticket->save();

            $this->syncOrderItemStatusOnFire($ticket, $actorUserId);
            $this->publishTicketEvent('kitchen.ticket_fired', $ticket, $actorUserId, ['kitchen']);

            return $this->freshTicket($ticketId);
        }, 3);
    }

    public function bumpTicket(int $ticketId, ?int $actorUserId = null): KitchenOrderItemTicket
    {
        return DB::transaction(function () use ($ticketId, $actorUserId): KitchenOrderItemTicket {
            $accessibleBranchIds = $actorUserId !== null && $actorUserId > 0
                ? $this->branchContextService->accessibleBranchIds($actorUserId)
                : [];

            /** @var KitchenOrderItemTicket $ticket */
            $ticketQuery = KitchenOrderItemTicket::query()
                ->with(['orderItem', 'reservation:reservation_id,branch_id'])
                ->lockForUpdate();
            if ($actorUserId !== null && $actorUserId > 0) {
                $this->constrainReservationLookupToAccessibleBranches($ticketQuery, $accessibleBranchIds);
            }
            $ticket = $ticketQuery->findOrFail($ticketId);

            $current = $this->normalizeTicketStatus($ticket);
            KitchenTicketTransitionPolicy::assertActionAllowed($current, 'bump');

            $this->assertTicketOrderItemAllowsAction($ticket, 'bump');

            $ticket->ticket_status = KitchenTicketTransitionPolicy::nextStatusForAction($current, 'bump');
            $ticket->ready_at = Carbon::now('UTC');
            $ticket->updated_by = $actorUserId;
            $ticket->save();

            $this->publishTicketEvent('kitchen.ticket_bumped', $ticket, $actorUserId, ['kitchen']);

            return $this->freshTicket($ticketId);
        }, 3);
    }

    public function recallTicket(int $ticketId, ?int $actorUserId = null): KitchenOrderItemTicket
    {
        return DB::transaction(function () use ($ticketId, $actorUserId): KitchenOrderItemTicket {
            $accessibleBranchIds = $actorUserId !== null && $actorUserId > 0
                ? $this->branchContextService->accessibleBranchIds($actorUserId)
                : [];

            /** @var KitchenOrderItemTicket $ticket */
            $ticketQuery = KitchenOrderItemTicket::query()
                ->with(['orderItem', 'reservation:reservation_id,branch_id'])
                ->lockForUpdate();
            if ($actorUserId !== null && $actorUserId > 0) {
                $this->constrainReservationLookupToAccessibleBranches($ticketQuery, $accessibleBranchIds);
            }
            $ticket = $ticketQuery->findOrFail($ticketId);

            $current = $this->normalizeTicketStatus($ticket);
            KitchenTicketTransitionPolicy::assertActionAllowed($current, 'recall');

            $this->assertTicketOrderItemAllowsAction($ticket, 'recall');

            $ticket->ticket_status = KitchenTicketTransitionPolicy::nextStatusForAction($current, 'recall');
            $ticket->last_recalled_at = Carbon::now('UTC');
            $ticket->recall_count = ((int) $ticket->recall_count) + 1;
            $ticket->updated_by = $actorUserId;
            $ticket->save();

            $this->publishTicketEvent('kitchen.ticket_recalled', $ticket, $actorUserId, ['kitchen']);

            return $this->freshTicket($ticketId);
        }, 3);
    }

    public function syncTicketForOrderItem(int $orderItemId, ?int $actorUserId = null): ?KitchenOrderItemTicket
    {
        return DB::transaction(function () use ($orderItemId, $actorUserId): ?KitchenOrderItemTicket {
            $accessibleBranchIds = $actorUserId !== null && $actorUserId > 0
                ? $this->branchContextService->accessibleBranchIds($actorUserId)
                : [];

            /** @var ReservationOrderItem $orderItem */
            $orderItemQuery = ReservationOrderItem::query()->lockForUpdate();
            if ($actorUserId !== null && $actorUserId > 0) {
                $this->constrainOrderItemLookupToAccessibleBranches($orderItemQuery, $accessibleBranchIds);
            }
            $orderItem = $orderItemQuery->findOrFail($orderItemId);

            /** @var KitchenOrderItemTicket|null $ticket */
            $ticket = KitchenOrderItemTicket::query()->lockForUpdate()->where('order_item_id', $orderItemId)->first();
            if (! $ticket instanceof KitchenOrderItemTicket) {
                return null;
            }

            $currentStatus = $this->normalizeTicketStatus($ticket);
            $targetStatus = KitchenTicketTransitionPolicy::statusFromOrderItemStatus($this->normalizeOrderItemStatus($orderItem));
            $nextStatus = KitchenTicketTransitionPolicy::resolveSynchronizedStatus($currentStatus, $targetStatus);
            if ($currentStatus === $nextStatus) {
                $ticket->setRelation('orderItem', $orderItem);
                $ticket->loadMissing(['route', 'station']);
                $inspection = $this->ticketConsistencyInspector->describe($ticket);
                $this->recordTicketDriftWarning('kitchen.ticket_sync_drift_detected', $ticket, $inspection, $actorUserId);

                return $this->freshTicket((int) $ticket->ticket_id);
            }

            $now = Carbon::now('UTC');
            $ticket->ticket_status = $nextStatus;
            $ticket->updated_by = $actorUserId;
            $this->applyTicketLifecycleTimestamps($ticket, $nextStatus, $currentStatus);

            if ($nextStatus === KitchenTicketStatus::Fired) {
                $eventType = 'kitchen.ticket_fired';
            } elseif ($nextStatus === KitchenTicketStatus::Completed) {
                $ticket->completed_at = $ticket->completed_at ?? $now;
                $eventType = 'kitchen.ticket_completed';
            } elseif ($nextStatus === KitchenTicketStatus::Cancelled) {
                $ticket->cancelled_at = $ticket->cancelled_at ?? $now;
                $eventType = 'kitchen.ticket_cancelled';
            } else {
                $eventType = 'kitchen.ticket_synced';
            }

            $ticket->save();
            $this->publishTicketEvent($eventType, $ticket, $actorUserId, ['kitchen']);

            return $this->freshTicket((int) $ticket->ticket_id);
        }, 3);
    }

    private function routeHasActiveTickets(int $routeId): bool
    {
        return KitchenOrderItemTicket::query()
            ->where('route_id', $routeId)
            ->whereNotIn('ticket_status', [KitchenTicketStatus::Completed->value, KitchenTicketStatus::Cancelled->value])
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    private function accessibleBranchIdsFromFilters(array $filters): array
    {
        $branchIds = [];

        foreach ((array) ($filters['accessible_branch_ids'] ?? []) as $branchId) {
            $normalizedBranchId = (int) $branchId;
            if ($normalizedBranchId > 0) {
                $branchIds[] = $normalizedBranchId;
            }
        }

        $branchIds = array_values(array_unique($branchIds));
        sort($branchIds);

        return $branchIds;
    }

    /**
     * @param  Builder<Reservation>  $query
     * @param  list<int>  $accessibleBranchIds
     */
    private function applyReservationBranchScope(
        Builder $query,
        ?int $branchId,
        array $accessibleBranchIds,
        bool $enforceAccessibleBranchScope = false,
    ): void {
        if ($branchId !== null && $branchId > 0) {
            $query->where('branch_id', $branchId);

            return;
        }

        if ($accessibleBranchIds !== []) {
            $query->whereIn('branch_id', $accessibleBranchIds);

            return;
        }

        if ($enforceAccessibleBranchScope) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @param  Builder<KitchenOrderItemTicket>  $query
     * @param  list<int>  $accessibleBranchIds
     */
    private function constrainTicketQueryToReservationScope(
        Builder $query,
        ?int $branchId,
        array $accessibleBranchIds,
        bool $enforceAccessibleBranchScope = false,
    ): void {
        if ($branchId === null && $accessibleBranchIds === [] && ! $enforceAccessibleBranchScope) {
            return;
        }

        $query->whereHas('reservation', function (Builder $reservationQuery) use ($branchId, $accessibleBranchIds, $enforceAccessibleBranchScope): void {
            $this->applyReservationBranchScope($reservationQuery, $branchId, $accessibleBranchIds, $enforceAccessibleBranchScope);
        });
    }

    /**
     * @param  Builder<KitchenStation>  $query
     * @param  list<int>  $accessibleBranchIds
     */
    private function constrainStationQueryToBranchScope(
        Builder $query,
        ?int $branchId,
        array $accessibleBranchIds,
        bool $enforceAccessibleBranchScope = false,
    ): void {
        $query->whereHas('tickets.reservation', function (Builder $reservationQuery) use ($branchId, $accessibleBranchIds, $enforceAccessibleBranchScope): void {
            $this->applyReservationBranchScope($reservationQuery, $branchId, $accessibleBranchIds, $enforceAccessibleBranchScope);
        });
    }

    /**
     * @param  Builder<ReservationOrder|KitchenOrderItemTicket>  $query
     * @param  list<int>  $accessibleBranchIds
     */
    private function constrainReservationLookupToAccessibleBranches(Builder $query, array $accessibleBranchIds): void
    {
        if ($accessibleBranchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('reservation', function (Builder $reservationQuery) use ($accessibleBranchIds): void {
            $reservationQuery->whereIn('branch_id', $accessibleBranchIds);
        });
    }

    /**
     * @param  Builder<ReservationOrderItem>  $query
     * @param  list<int>  $accessibleBranchIds
     */
    private function constrainOrderItemLookupToAccessibleBranches(Builder $query, array $accessibleBranchIds): void
    {
        if ($accessibleBranchIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('order.reservation', function (Builder $reservationQuery) use ($accessibleBranchIds): void {
            $reservationQuery->whereIn('branch_id', $accessibleBranchIds);
        });
    }

    private function stationHasActiveTickets(int $stationId): bool
    {
        return KitchenOrderItemTicket::query()
            ->where('station_id', $stationId)
            ->whereNotIn('ticket_status', [KitchenTicketStatus::Completed->value, KitchenTicketStatus::Cancelled->value])
            ->exists();
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function normalizeStationPayload(array $payload, bool $isCreate): array
    {
        $normalized = [];

        foreach (['code', 'name', 'description', 'output_mode', 'printer_target'] as $key) {
            if ($isCreate || array_key_exists($key, $payload)) {
                $normalized[$key] = $payload[$key] ?? null;
            }
        }

        if ($isCreate || array_key_exists('is_active', $payload)) {
            $normalized['is_active'] = (bool) ($payload['is_active'] ?? true);
        }

        if (array_key_exists('code', $normalized) && $normalized['code'] !== null) {
            $normalized['code'] = trim((string) $normalized['code']);
        }
        if (array_key_exists('name', $normalized) && $normalized['name'] !== null) {
            $normalized['name'] = trim((string) $normalized['name']);
        }
        foreach (['description', 'printer_target'] as $field) {
            if (array_key_exists($field, $normalized)) {
                $value = $normalized[$field];
                $normalized[$field] = $value !== null && trim((string) $value) !== '' ? trim((string) $value) : null;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function assertStationCanBeDeactivated(KitchenStation $station, array $payload): void
    {
        if (! array_key_exists('is_active', $payload) || (bool) $payload['is_active'] || ! (bool) $station->is_active) {
            return;
        }

        if (! $this->stationHasActiveTickets((int) $station->station_id)) {
            return;
        }

        throw ValidationException::withMessages([
            'is_active' => ['Kitchen stations with active tickets cannot be deactivated. Resolve the active kitchen tickets first.'],
        ]);
    }

    private function assertRouteCanBeDeactivated(KitchenStationCategoryRoute $route, bool $nextIsActive): void
    {
        if ($route->exists === false || $nextIsActive || ! (bool) $route->is_active) {
            return;
        }

        if (! $this->routeHasActiveTickets((int) $route->route_id)) {
            return;
        }

        throw ValidationException::withMessages([
            'routes' => ['Kitchen category routes with active tickets cannot be deactivated. Resolve the active kitchen tickets first.'],
            'category_id' => [(int) $route->category_id],
        ]);
    }

    private function resolveRouteForOrderItem(ReservationOrderItem $orderItem): ?KitchenStationCategoryRoute
    {
        $categoryId = $orderItem->item?->category_id;
        if ($categoryId === null) {
            return null;
        }

        /** @var KitchenStationCategoryRoute|null $route */
        $route = KitchenStationCategoryRoute::query()
            ->with(['station'])
            ->where('category_id', (int) $categoryId)
            ->where('is_active', true)
            ->whereHas('station', static fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('route_id')
            ->first();

        return $route;
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

    private function applyTicketLifecycleTimestamps(
        KitchenOrderItemTicket $ticket,
        KitchenTicketStatus $nextStatus,
        ?KitchenTicketStatus $currentStatus,
    ): void {
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

    private function syncOrderItemStatusOnFire(KitchenOrderItemTicket $ticket, ?int $actorUserId = null): void
    {
        /** @var ReservationOrderItem|null $orderItem */
        $orderItem = ReservationOrderItem::query()->lockForUpdate()->find($ticket->order_item_id);
        if (! $orderItem instanceof ReservationOrderItem) {
            return;
        }

        $currentStatus = $this->normalizeOrderItemStatus($orderItem);
        if (! ReservationOrderItemStatusTransitionPolicy::canTransition($currentStatus, ReservationOrderItemStatus::InProgress)) {
            return;
        }

        $orderItem->status = ReservationOrderItemStatus::InProgress;
        $orderItem->updated_by = $actorUserId;
        $orderItem->save();
    }

    private function assertTicketOrderItemAllowsAction(KitchenOrderItemTicket $ticket, string $action): void
    {
        $orderItem = $ticket->relationLoaded('orderItem') ? $ticket->orderItem : null;
        if (! $orderItem instanceof ReservationOrderItem) {
            throw ValidationException::withMessages([
                'ticket_id' => ['Kitchen ticket is missing its linked order item. Run kitchen reconciliation before retrying.'],
            ]);
        }

        $status = $this->normalizeOrderItemStatus($orderItem);
        $allowedStatuses = match ($action) {
            'fire' => [ReservationOrderItemStatus::Ordered, ReservationOrderItemStatus::InProgress],
            'bump', 'recall' => [ReservationOrderItemStatus::InProgress],
            default => [],
        };

        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        $actionVerb = match ($action) {
            'fire' => 'fired',
            'bump' => 'bumped',
            'recall' => 'recalled',
            default => $action,
        };

        throw ValidationException::withMessages([
            'ticket_id' => [sprintf('Kitchen ticket cannot be %s while the linked order item is %s.', $actionVerb, $status->value)],
        ]);
    }

    /**
     * @param  array{
     *     lifecycle: array<string,mixed>,
     *     reconciliation: array<string,mixed>
     * }  $inspection
     */
    private function recordTicketDriftWarning(
        string $eventType,
        KitchenOrderItemTicket $ticket,
        array $inspection,
        ?int $actorUserId,
    ): void {
        $reconciliation = $inspection['reconciliation'] ?? [];
        $syncStatus = (string) ($reconciliation['sync_status'] ?? 'in_sync');
        $routingStatus = (string) ($reconciliation['routing_status'] ?? 'active_route');

        if ($syncStatus === 'in_sync' && $routingStatus === 'active_route') {
            return;
        }

        AuditEvent::warning($eventType, [
            'ticket_id' => (int) $ticket->ticket_id,
            'order_id' => (int) $ticket->order_id,
            'order_item_id' => (int) $ticket->order_item_id,
            'actor_user_id' => $actorUserId,
            'ticket_status' => (string) ($inspection['lifecycle']['status'] ?? ''),
            'sync_status' => $syncStatus,
            'routing_status' => $routingStatus,
            'drift_reasons' => (array) ($reconciliation['drift_reasons'] ?? []),
            'next_actions' => (array) ($reconciliation['next_actions'] ?? []),
        ]);
    }

    /**
     * @param  list<string>  $refreshTargets
     */
    private function publishTicketEvent(string $eventType, KitchenOrderItemTicket $ticket, ?int $actorUserId, array $refreshTargets = ['kitchen']): void
    {
        AuditEvent::info($eventType, [
            'ticket_id' => (int) $ticket->ticket_id,
            'order_id' => (int) $ticket->order_id,
            'order_item_id' => (int) $ticket->order_item_id,
            'actor_user_id' => $actorUserId,
            'ticket_status' => $this->normalizeTicketStatus($ticket)->value,
            'ticket_state_reason' => $this->normalizeTicketStatus($ticket)->stateReason(),
        ]);

        $this->realtimeService->publishKitchenEvent($eventType, [
            'ticket_id' => (int) $ticket->ticket_id,
            'station_id' => (int) $ticket->station_id,
            'order_id' => (int) $ticket->order_id,
            'reservation_id' => (int) $ticket->reservation_id,
            'order_item_id' => (int) $ticket->order_item_id,
            'ticket_status' => $this->normalizeTicketStatus($ticket)->value,
            'ticket_state_reason' => $this->normalizeTicketStatus($ticket)->stateReason(),
        ], $refreshTargets);
    }

    private function freshTicket(int $ticketId): KitchenOrderItemTicket
    {
        /** @var KitchenOrderItemTicket $ticket */
        $ticket = KitchenOrderItemTicket::query()
            ->with(['station', 'route', 'orderItem', 'item.category'])
            ->findOrFail($ticketId);

        return $ticket;
    }

    private function assertDispatchEnabledForReservation(?Reservation $reservation): void
    {
        $this->featureFlags->assertEnabled(
            'staff.kitchen_dispatch',
            $reservation?->branch_id !== null ? (int) $reservation->branch_id : null,
            field: 'feature_flag',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\KitchenDispatch\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Application\Queries\StaffBranchContextService;
use App\Modules\FloorOperations\Http\Requests\Staff\BranchScopeRequest;
use App\Modules\KitchenDispatch\Application\Workflows\KitchenRoutingService;
use App\Modules\KitchenDispatch\Http\Requests\Staff\DispatchKitchenTicketRequest;
use App\Modules\KitchenDispatch\Http\Requests\Staff\KitchenTicketActionRequest;
use App\Modules\KitchenDispatch\Http\Requests\Staff\ListKitchenStationTicketsRequest;
use App\Modules\KitchenDispatch\Http\Resources\KitchenStationResource;
use App\Modules\KitchenDispatch\Http\Resources\Staff\KitchenTicketResource;
use App\Platform\Realtime\Http\Requests\ListOperationalChangeFeedRequest;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenDispatchController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly KitchenRoutingService $kitchenRoutingService,
        private readonly StaffBranchContextService $branchContextService,
        private readonly OperationalRealtimeService $realtimeService,
    ) {}

    public function stations(BranchScopeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $actorUserId = $this->resolveStaffActorUserId($request);

        try {
            $accessibleBranchIds = $branchId !== null
                ? [$this->branchContextService->assertAccessibleBranch($actorUserId, $branchId)]
                : $this->branchContextService->accessibleBranchIds($actorUserId);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Branch not found.');
        }

        $stations = $this->kitchenRoutingService->listStations([
            'is_active' => true,
            'branch_id' => $branchId,
            'accessible_branch_ids' => $accessibleBranchIds,
        ]);

        return response()->json([
            'data' => KitchenStationResource::collection($stations)->toArray($request),
            'meta' => [
                'count' => $stations->count(),
                'branch_id' => $branchId,
                'branch_scope' => $this->branchScopeMeta($branchId, $accessibleBranchIds),
                'realtime' => $this->realtimeService->describeTopic(
                    OperationalRealtimeService::TOPIC_KITCHEN,
                    '/api/v1/staff/kitchen/changes',
                    ['kitchen']
                ),
            ],
        ]);
    }

    public function stationTickets(int $station_id, ListKitchenStationTicketsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $actorUserId = $this->resolveStaffActorUserId($request);

        try {
            $accessibleBranchIds = $branchId !== null
                ? [$this->branchContextService->assertAccessibleBranch($actorUserId, $branchId)]
                : $this->branchContextService->accessibleBranchIds($actorUserId);
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Branch not found.');
        }

        try {
            $tickets = $this->kitchenRoutingService->listStationTickets($station_id, array_merge($validated, [
                'accessible_branch_ids' => $accessibleBranchIds,
            ]));
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Kitchen station not found.');
        }

        return response()->json([
            'data' => KitchenTicketResource::collection($tickets)->toArray($request),
            'meta' => [
                'station_id' => $station_id,
                'branch_id' => $branchId,
                'count' => $tickets->count(),
                'branch_scope' => $this->branchScopeMeta($branchId, $accessibleBranchIds),
            ],
        ]);
    }

    public function dispatchOrder(int $order_id, DispatchKitchenTicketRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->kitchenRoutingService->dispatchOrder(
                $order_id,
                (int) $validated['row_version'],
                $this->resolveStaffActorUserId($request),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Order not found.');
        }

        return response()->json([
            'data' => KitchenTicketResource::collection($result['tickets'])->toArray($request),
            'meta' => [
                'action' => 'kitchen_order_dispatched',
                'order_id' => $order_id,
                'created_count' => $result['created_count'],
                'reused_count' => $result['reused_count'],
                'unrouted_count' => $result['unrouted_count'],
                'pinned_route_count' => $result['pinned_route_count'],
            ],
        ]);
    }

    public function fire(int $ticket_id, KitchenTicketActionRequest $request): JsonResponse
    {
        return $this->ticketActionResponse(
            $request,
            'kitchen_ticket_fired',
            fn () => $this->kitchenRoutingService->fireTicket(
                $ticket_id,
                (int) $request->input('row_version'),
                $this->resolveStaffActorUserId($request),
            )
        );
    }

    public function bump(int $ticket_id, KitchenTicketActionRequest $request): JsonResponse
    {
        return $this->ticketActionResponse(
            $request,
            'kitchen_ticket_bumped',
            fn () => $this->kitchenRoutingService->bumpTicket(
                $ticket_id,
                (int) $request->input('row_version'),
                $this->resolveStaffActorUserId($request),
            )
        );
    }

    public function recall(int $ticket_id, KitchenTicketActionRequest $request): JsonResponse
    {
        return $this->ticketActionResponse(
            $request,
            'kitchen_ticket_recalled',
            fn () => $this->kitchenRoutingService->recallTicket(
                $ticket_id,
                (int) $request->input('row_version'),
                $this->resolveStaffActorUserId($request),
            )
        );
    }

    public function changes(ListOperationalChangeFeedRequest $request): JsonResponse
    {
        $branchId = $request->integer('branch_id') ?: null;

        try {
            $accessibleBranchIds = $this->branchContextService->branchScopeOrAccessible(
                $this->resolveStaffActorUserId($request),
                $branchId,
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Branch not found.');
        }

        return response()->json([
            'data' => $this->realtimeService->readTopic(
                OperationalRealtimeService::TOPIC_KITCHEN,
                (int) $request->input('after_version', 0),
                (int) $request->input('limit', 20),
                $accessibleBranchIds,
            ),
        ]);
    }

    private function ticketActionResponse(Request $request, string $action, callable $callback): JsonResponse
    {
        try {
            $ticket = $callback();
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Kitchen ticket not found.');
        }

        return response()->json([
            'data' => (new KitchenTicketResource($ticket))->toArray($request),
            'meta' => [
                'action' => $action,
            ],
        ]);
    }

    private function notFoundResponse(Request $request, string $message): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            $message,
        );
    }

    /**
     * @param  list<int>  $accessibleBranchIds
     * @return array{requested_branch_id:int|null,accessible_branch_ids:list<int>,uses_explicit_entitlement:bool}
     */
    private function branchScopeMeta(?int $branchId, array $accessibleBranchIds): array
    {
        return [
            'requested_branch_id' => $branchId,
            'accessible_branch_ids' => array_values(array_map('intval', $accessibleBranchIds)),
            'uses_explicit_entitlement' => true,
        ];
    }
}

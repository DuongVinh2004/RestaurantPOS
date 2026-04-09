<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\DispatchKitchenTicketsRequest;
use App\Http\Requests\Staff\ListKitchenStationTicketsRequest;
use App\Http\Requests\Staff\ListOperationalRealtimeChangesRequest;
use App\Http\Resources\KitchenOrderItemTicketResource;
use App\Http\Resources\KitchenStationResource;
use App\Services\Kitchen\KitchenRoutingService;
use App\Services\Staff\StaffOperationalRealtimeService;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffKitchenController extends Controller
{
    public function __construct(
        private readonly KitchenRoutingService $kitchenRoutingService,
        private readonly StaffOperationalRealtimeService $realtimeService,
    ) {}

    public function stations(Request $request): JsonResponse
    {
        $stations = $this->kitchenRoutingService->listStations(['is_active' => true]);

        return response()->json([
            'data' => KitchenStationResource::collection($stations)->toArray($request),
            'meta' => [
                'count' => $stations->count(),
                'realtime' => $this->realtimeService->describeTopic(
                    StaffOperationalRealtimeService::TOPIC_KITCHEN,
                    '/api/v1/staff/kitchen/changes',
                    ['kitchen']
                ),
            ],
        ]);
    }

    public function stationTickets(int $station_id, ListKitchenStationTicketsRequest $request): JsonResponse
    {
        try {
            $tickets = $this->kitchenRoutingService->listStationTickets($station_id, $request->validated());
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Kitchen station not found.');
        }

        return response()->json([
            'data' => KitchenOrderItemTicketResource::collection($tickets)->toArray($request),
            'meta' => [
                'station_id' => $station_id,
                'count' => $tickets->count(),
            ],
        ]);
    }

    public function dispatchOrder(int $order_id, DispatchKitchenTicketsRequest $request): JsonResponse
    {
        try {
            $result = $this->kitchenRoutingService->dispatchOrder(
                $order_id,
                $request->filled('row_version') ? (int) $request->input('row_version') : null,
                $this->resolveStaffActorUserId($request),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundResponse($request, 'Order not found.');
        }

        return response()->json([
            'data' => KitchenOrderItemTicketResource::collection($result['tickets'])->toArray($request),
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

    public function fire(int $ticket_id, Request $request): JsonResponse
    {
        return $this->ticketActionResponse($request, 'kitchen_ticket_fired', fn () => $this->kitchenRoutingService->fireTicket($ticket_id, $this->resolveStaffActorUserId($request)));
    }

    public function bump(int $ticket_id, Request $request): JsonResponse
    {
        return $this->ticketActionResponse($request, 'kitchen_ticket_bumped', fn () => $this->kitchenRoutingService->bumpTicket($ticket_id, $this->resolveStaffActorUserId($request)));
    }

    public function recall(int $ticket_id, Request $request): JsonResponse
    {
        return $this->ticketActionResponse($request, 'kitchen_ticket_recalled', fn () => $this->kitchenRoutingService->recallTicket($ticket_id, $this->resolveStaffActorUserId($request)));
    }

    public function changes(ListOperationalRealtimeChangesRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->realtimeService->readTopic(
                StaffOperationalRealtimeService::TOPIC_KITCHEN,
                (int) $request->input('after_version', 0),
                (int) $request->input('limit', 20),
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
            'data' => (new KitchenOrderItemTicketResource($ticket))->toArray($request),
            'meta' => [
                'action' => $action,
            ],
        ]);
    }

    private function resolveStaffActorUserId(mixed $request): ?int
    {
        $actor = $request->attributes->get('staff_actor_user_id');

        return is_numeric($actor) ? (int) $actor : null;
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
}

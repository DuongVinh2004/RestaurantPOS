<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Ordering\Application\Queries\StaffOrderReadService;
use App\Modules\Ordering\Http\Resources\Staff\OrderReadResource;
use App\Support\ApiErrorResponse;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderReadController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffOrderReadService $orderReadService,
    ) {}

    public function show(int $order_id, Request $request): JsonResponse
    {
        $order = $this->orderReadService->findOrder($order_id, $this->resolveStaffActorUserId($request));
        if ($order === null) {
            return $this->notFoundOrderResponse($request);
        }

        return $this->orderResponse($request, $order, 'order_detail');
    }

    public function showActiveByTable(int $table_id, Request $request): JsonResponse
    {
        $order = $this->orderReadService->findActiveOrderByTable($table_id, $this->resolveStaffActorUserId($request));
        if ($order === null) {
            return $this->notFoundOrderResponse($request);
        }

        $request->attributes->set('staff_order_read_context_table_id', $table_id);

        return $this->orderResponse($request, $order, 'active_order_by_table');
    }

    public function showActiveByReservation(int $reservation_id, Request $request): JsonResponse
    {
        $order = $this->orderReadService->findActiveOrderByReservation($reservation_id, $this->resolveStaffActorUserId($request));
        if ($order === null) {
            return $this->notFoundOrderResponse($request);
        }

        return $this->orderResponse($request, $order, 'active_order_by_reservation');
    }

    private function orderResponse(Request $request, mixed $order, string $action): JsonResponse
    {
        $request->attributes->set('reservation_access_scope', ReservationAccessScope::STAFF);

        return response()->json([
            'data' => new OrderReadResource($order),
            'meta' => [
                'action' => $action,
                'selection_policy' => 'Returns the active OnSpot order when present, otherwise the latest active reservation-linked order.',
            ],
        ]);
    }

    private function notFoundOrderResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            'Order not found.',
        );
    }
}



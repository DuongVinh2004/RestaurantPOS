<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffOrderReadResource;
use App\Services\Staff\StaffOrderReadService;
use App\Support\ApiErrorResponse;
use App\Support\ReservationAccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffOrderReadController extends Controller
{
    public function __construct(
        private readonly StaffOrderReadService $orderReadService,
    ) {}

    public function show(int $order_id, Request $request): JsonResponse
    {
        $order = $this->orderReadService->findOrder($order_id);
        if ($order === null) {
            return $this->notFoundOrderResponse($request);
        }

        return $this->orderResponse($request, $order, 'order_detail');
    }

    public function showActiveByTable(int $table_id, Request $request): JsonResponse
    {
        $order = $this->orderReadService->findActiveOrderByTable($table_id);
        if ($order === null) {
            return $this->notFoundOrderResponse($request);
        }

        $request->attributes->set('staff_order_read_context_table_id', $table_id);

        return $this->orderResponse($request, $order, 'active_order_by_table');
    }

    public function showActiveByReservation(int $reservation_id, Request $request): JsonResponse
    {
        $order = $this->orderReadService->findActiveOrderByReservation($reservation_id);
        if ($order === null) {
            return $this->notFoundOrderResponse($request);
        }

        return $this->orderResponse($request, $order, 'active_order_by_reservation');
    }

    private function orderResponse(Request $request, mixed $order, string $action): JsonResponse
    {
        $request->attributes->set('reservation_access_scope', ReservationAccessScope::STAFF);

        return response()->json([
            'data' => new StaffOrderReadResource($order),
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

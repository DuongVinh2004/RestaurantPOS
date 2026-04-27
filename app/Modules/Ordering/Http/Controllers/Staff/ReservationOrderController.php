<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Ordering\Application\Queries\StaffOrderReadService;
use App\Modules\Ordering\Application\UseCases\Orders\StaffTableOrderService;
use App\Modules\Ordering\Http\Requests\Staff\AddOrderItemsRequest;
use App\Modules\Ordering\Http\Requests\Staff\CreateTableOrderRequest;
use App\Modules\Ordering\Http\Resources\ReservationOrderResource;
use App\Support\Listing\ListingMetaFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationOrderController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffTableOrderService $orderService,
        private readonly StaffOrderReadService $orderReadService,
    ) {}

    public function indexByReservation(int $reservation_id, Request $request): JsonResponse
    {
        $orders = $this->orderReadService->listOrdersByReservation(
            $reservation_id,
            $this->resolveStaffActorUserId($request),
        );

        return response()->json([
            'data' => ReservationOrderResource::collection($orders),
            'meta' => [
                'action' => 'reservation_orders_lookup',
                'reservation_id' => $reservation_id,
                'count' => $orders->count(),
                'sort' => [
                    'supported' => false,
                    'value' => 'order_id',
                    'by' => 'order_id',
                    'dir' => 'asc',
                ],
                'pagination' => [
                    'mode' => 'none',
                    'supported' => false,
                ],
                'query_contract' => ListingMetaFactory::contract([], ['order_id'], 'order_id', false),
            ],
        ]);
    }

    public function store(int $table_id, CreateTableOrderRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $order = $this->orderService->createOnSpotOrder(
            tableId: $table_id,
            reservationId: (int) ($request->input('reservation_id') ?? 0),
            items: $request->input('items', []),
            staffUserId: $staffUserId,
            idempotencyKey: (string) ($request->header('Idempotency-Key') ?? ''),
            notes: (string) ($request->input('notes') ?? ''),
            expectedRowVersion: (int) $request->input('row_version'),
        );

        return response()->json([
            'data' => new ReservationOrderResource($order->load('items.item')),
        ], 201);
    }

    public function addItems(int $order_id, AddOrderItemsRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $order = $this->orderService->addItems(
            orderId: $order_id,
            items: $request->input('items', []),
            staffUserId: $staffUserId,
            idempotencyKey: (string) ($request->header('Idempotency-Key') ?? ''),
            expectedRowVersion: (int) $request->input('row_version'),
        );

        return response()->json([
            'data' => new ReservationOrderResource($order->load('items.item')),
        ]);
    }
}

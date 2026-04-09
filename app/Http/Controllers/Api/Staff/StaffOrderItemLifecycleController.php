<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpdateOrderItemRequest;
use App\Http\Requests\Staff\UpdateOrderItemStatusRequest;
use App\Http\Resources\ReservationOrderResource;
use App\Services\Staff\StaffOrderItemLifecycleService;
use Illuminate\Http\JsonResponse;

class StaffOrderItemLifecycleController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffOrderItemLifecycleService $itemLifecycleService,
    ) {}

    public function update(int $order_id, int $order_item_id, UpdateOrderItemRequest $request): JsonResponse
    {
        $order = $this->itemLifecycleService->updateItem(
            orderId: $order_id,
            orderItemId: $order_item_id,
            attributes: $request->validated(),
            staffUserId: $this->resolveStaffActorUserId($request),
            expectedOrderRowVersion: (int) $request->input('order_row_version'),
            expectedItemRowVersion: (int) $request->input('row_version'),
        )->loadMissing(['items.item']);

        return response()->json([
            'data' => new ReservationOrderResource($order),
            'meta' => [
                'action' => 'order_item_updated',
            ],
        ]);
    }

    public function updateStatus(int $order_id, int $order_item_id, UpdateOrderItemStatusRequest $request): JsonResponse
    {
        $targetStatus = (string) $request->input('status');

        $order = $this->itemLifecycleService->transitionItemStatus(
            orderId: $order_id,
            orderItemId: $order_item_id,
            targetStatus: $targetStatus,
            staffUserId: $this->resolveStaffActorUserId($request),
            expectedOrderRowVersion: (int) $request->input('order_row_version'),
            expectedItemRowVersion: (int) $request->input('row_version'),
        )->loadMissing(['items.item']);

        return response()->json([
            'data' => new ReservationOrderResource($order),
            'meta' => [
                'action' => 'order_item_status_updated',
                'status' => $targetStatus,
            ],
        ]);
    }
}

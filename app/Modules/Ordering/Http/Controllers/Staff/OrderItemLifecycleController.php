<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Ordering\Application\UseCases\OrderItems\StaffOrderItemLifecycleService;
use App\Modules\Ordering\Http\Requests\Staff\UpdateOrderItemComponentSwapRequest;
use App\Modules\Ordering\Http\Requests\Staff\UpdateOrderItemRequest;
use App\Modules\Ordering\Http\Requests\Staff\UpdateOrderItemStatusRequest;
use App\Modules\Ordering\Http\Resources\ReservationOrderResource;
use Illuminate\Http\JsonResponse;

class OrderItemLifecycleController extends Controller
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

    public function swapComponent(int $order_id, int $order_item_id, UpdateOrderItemComponentSwapRequest $request): JsonResponse
    {
        $order = $this->itemLifecycleService->swapComponent(
            orderId: $order_id,
            orderItemId: $order_item_id,
            newItemId: (int) $request->input('new_item_id'),
            unitPriceOverride: $request->has('unit_price') ? (float) $request->input('unit_price') : null,
            staffUserId: $this->resolveStaffActorUserId($request),
            expectedOrderRowVersion: $request->has('order_row_version') ? (int) $request->input('order_row_version') : null,
            expectedItemRowVersion: $request->has('row_version') ? (int) $request->input('row_version') : null,
        )->loadMissing(['items.item']);

        return response()->json([
            'data' => new ReservationOrderResource($order),
            'meta' => [
                'action' => 'order_item_component_swapped',
            ],
        ]);
    }
}

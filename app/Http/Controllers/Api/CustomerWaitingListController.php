<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\ListOwnerWaitingListRequest;
use App\Http\Requests\Customer\RespondOwnerWaitingListRequest;
use App\Http\Requests\Customer\StoreOwnerWaitingListRequest;
use App\Http\Resources\CustomerWaitingListResource;
use App\Services\CustomerWaitingListService;
use App\Support\RequestActorContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerWaitingListController extends Controller
{
    public function __construct(
        private readonly CustomerWaitingListService $waitingListService,
    ) {}

    public function index(ListOwnerWaitingListRequest $request): JsonResponse
    {
        $ownerUserId = $this->authenticatedOwnerId($request);

        $entries = $this->waitingListService->listOwnerEntries($ownerUserId, $request->validated());

        return response()->json([
            'data' => CustomerWaitingListResource::collection($entries),
        ]);
    }

    public function store(StoreOwnerWaitingListRequest $request): JsonResponse
    {
        $ownerUserId = $this->authenticatedOwnerId($request);
        $entry = $this->waitingListService->createEntry($ownerUserId, $request->validated());

        return response()->json([
            'data' => new CustomerWaitingListResource($entry),
        ], 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $ownerUserId = $this->authenticatedOwnerId($request);
        $entry = $this->waitingListService->getOwnerEntryOrFail($id, $ownerUserId);

        return response()->json([
            'data' => new CustomerWaitingListResource($entry),
        ]);
    }

    public function accept(int $id, RespondOwnerWaitingListRequest $request): JsonResponse
    {
        $ownerUserId = $this->authenticatedOwnerId($request);
        $entry = $this->waitingListService->acceptEntry(
            waitingId: $id,
            ownerUserId: $ownerUserId,
            expectedRowVersion: (int) $request->validated('row_version'),
        );

        return response()->json([
            'data' => new CustomerWaitingListResource($entry),
        ]);
    }

    public function confirmArrival(int $id, RespondOwnerWaitingListRequest $request): JsonResponse
    {
        $ownerUserId = $this->authenticatedOwnerId($request);
        $entry = $this->waitingListService->confirmArrivalEntry(
            waitingId: $id,
            ownerUserId: $ownerUserId,
            expectedRowVersion: (int) $request->validated('row_version'),
        );

        return response()->json([
            'data' => new CustomerWaitingListResource($entry),
            'meta' => [
                'action' => 'await_staff_seating',
                'staff_seat_required' => true,
                'message' => 'Đã xác nhận tới nơi. Nhân viên sẽ thực hiện seat khi sẵn sàng.',
            ],
        ]);
    }

    public function decline(int $id, RespondOwnerWaitingListRequest $request): JsonResponse
    {
        $ownerUserId = $this->authenticatedOwnerId($request);
        $entry = $this->waitingListService->declineEntry(
            waitingId: $id,
            ownerUserId: $ownerUserId,
            expectedRowVersion: (int) $request->validated('row_version'),
        );

        return response()->json([
            'data' => new CustomerWaitingListResource($entry),
        ]);
    }

    public function cancel(int $id, RespondOwnerWaitingListRequest $request): JsonResponse
    {
        $ownerUserId = $this->authenticatedOwnerId($request);
        $entry = $this->waitingListService->cancelEntry(
            waitingId: $id,
            ownerUserId: $ownerUserId,
            expectedRowVersion: (int) $request->validated('row_version'),
            cancelReason: $request->validated('cancel_reason'),
        );

        return response()->json([
            'data' => new CustomerWaitingListResource($entry),
        ]);
    }

    private function authenticatedOwnerId(Request $request): int
    {
        $actor = RequestActorContext::fromRequest($request);
        $ownerUserId = $actor->customerUserId();

        if ($actor->isCustomerOwner() && $ownerUserId !== null && $ownerUserId > 0) {
            return $ownerUserId;
        }

        throw new HttpException(401, 'Customer authentication is required for waiting-list owner actions.');
    }
}

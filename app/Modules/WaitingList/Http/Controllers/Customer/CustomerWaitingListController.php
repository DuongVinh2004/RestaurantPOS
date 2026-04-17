<?php

declare(strict_types=1);

namespace App\Modules\WaitingList\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\WaitingList\Application\Services\CustomerWaitingListService;
use App\Modules\WaitingList\Http\Requests\Customer\ListOwnerWaitingListRequest;
use App\Modules\WaitingList\Http\Requests\Customer\RespondOwnerWaitingListRequest;
use App\Modules\WaitingList\Http\Requests\Customer\StoreOwnerWaitingListRequest;
use App\Modules\WaitingList\Http\Resources\CustomerWaitingListResource;
use App\Support\ApiErrorResponse;
use App\Support\RequestActorContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        if ($request->user() !== null) {
            throw new HttpResponseException(ApiErrorResponse::ownerScopeDenied(
                $request,
                'Waiting-list owner actions require an authenticated customer owner.',
                [
                    'state_reason' => 'owner_scope_required',
                    'next_actions' => [
                        'sign_in_as_owner',
                    ],
                ],
            ));
        }

        if ($this->hasProvidedCustomerToken($request) && (int) $request->attributes->get('customer_access_session_id', 0) <= 0) {
            throw new HttpResponseException(ApiErrorResponse::authenticationRequired(
                $request,
                'Customer authentication is required for waiting-list owner actions.',
            ));
        }

        if ($actor->isCustomerSession() || $actor->isStaff()) {
            throw new HttpResponseException(ApiErrorResponse::ownerScopeDenied(
                $request,
                'Waiting-list owner actions require an authenticated customer owner.',
                [
                    'state_reason' => 'owner_scope_required',
                    'next_actions' => [
                        'sign_in_as_owner',
                    ],
                ],
            ));
        }

        throw new HttpResponseException(ApiErrorResponse::authenticationRequired(
            $request,
            'Customer authentication is required for waiting-list owner actions.',
        ));
    }

    private function hasProvidedCustomerToken(Request $request): bool
    {
        $headerName = (string) config('customer_auth.header', 'X-Customer-Token');
        $providedToken = trim((string) ($request->header($headerName) ?? ''));

        if ($providedToken !== '') {
            return true;
        }

        if (! (bool) config('customer_auth.allow_bearer', false)) {
            return false;
        }

        $authorization = trim((string) ($request->header('Authorization') ?? ''));

        return str_starts_with($authorization, 'Bearer ') && trim(substr($authorization, 7)) !== '';
    }
}

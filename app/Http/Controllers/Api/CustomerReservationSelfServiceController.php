<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\CancelReservationRequest;
use App\Http\Requests\Reservation\CustomerRescheduleReservationRequest;
use App\Http\Requests\Reservation\ListReservationsRequest;
use App\Http\Resources\ReservationResource;
use App\Services\Reservation\CustomerReservationSelfService;
use App\Support\ApiErrorResponse;
use App\Support\RequestActorContext;
use Illuminate\Http\JsonResponse;

class CustomerReservationSelfServiceController extends Controller
{
    public function __construct(
        private readonly CustomerReservationSelfService $selfService,
    ) {
    }

    public function index(ListReservationsRequest $request): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            return ApiErrorResponse::json(
                $request,
                403,
                'forbidden',
                'Staff actors must use staff reservation endpoints.',
            );
        }

        $validated = $request->validated();
        $customerUserId = $actor->customerUserId();
        $sessionId = $actor->isCustomerSession() ? $actor->sessionId() : null;

        $result = $this->selfService->listAccessibleReservations($customerUserId, $sessionId, $validated);
        $scope = (string) $result['scope'];
        $paginator = $result['paginator'];

        $request->attributes->set('reservation_access_scope', $scope);

        return response()->json([
            'data' => ReservationResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'access_scope' => $scope,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'count' => $paginator->count(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ],
        ]);
    }

    public function cancel(CancelReservationRequest $request, int $id): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            return ApiErrorResponse::json(
                $request,
                403,
                'forbidden',
                'Staff actors must use staff reservation endpoints.',
            );
        }

        $validated = $request->validated();
        $customerUserId = $actor->customerUserId();
        $sessionId = $actor->isCustomerSession() ? $actor->sessionId() : null;
        $scope = $actor->accessScope();

        $reservation = $this->selfService->cancelAccessibleReservation($id, $customerUserId, $sessionId, $validated);

        $request->attributes->set('reservation_access_scope', $scope);

        return response()->json([
            'data' => new ReservationResource($reservation),
            'meta' => [
                'action' => 'reservation.cancelled',
                'access_scope' => $scope,
            ],
        ]);
    }

    public function reschedule(CustomerRescheduleReservationRequest $request, int $id): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            return ApiErrorResponse::json(
                $request,
                403,
                'forbidden',
                'Staff actors must use staff reservation endpoints.',
            );
        }

        $validated = $request->validated();
        $customerUserId = $actor->customerUserId();
        $sessionId = $actor->isCustomerSession() ? $actor->sessionId() : null;
        $scope = $actor->accessScope();

        $reservation = $this->selfService->rescheduleAccessibleReservation($id, $customerUserId, $sessionId, $validated);

        $request->attributes->set('reservation_access_scope', $scope);

        return response()->json([
            'data' => new ReservationResource($reservation),
            'meta' => [
                'action' => 'reservation.rescheduled',
                'access_scope' => $scope,
            ],
        ]);
    }
}

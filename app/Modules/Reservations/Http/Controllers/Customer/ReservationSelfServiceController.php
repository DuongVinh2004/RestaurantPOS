<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\Reservations\Application\Services\CustomerReservationSelfService;
use App\Modules\Reservations\Http\Requests\Customer\CancelReservationRequest;
use App\Modules\Reservations\Http\Requests\Customer\RescheduleReservationRequest;
use App\Modules\Reservations\Http\Requests\ListReservationsRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Illuminate\Http\JsonResponse;

class ReservationSelfServiceController extends Controller
{
    public function __construct(
        private readonly CustomerReservationSelfService $selfService,
    ) {
    }

    public function index(ListReservationsRequest $request): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            return ApiErrorResponse::policyDenied(
                $request,
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
            'data' => ReservationResource::collection($paginator)->resolve($request),
            'meta' => [
                'access_scope' => $scope,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'count' => count($paginator->items()),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ],
        ]);
    }

    public function cancel(CancelReservationRequest $request, int $id): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            return ApiErrorResponse::policyDenied(
                $request,
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

    public function reschedule(RescheduleReservationRequest $request, int $id): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            return ApiErrorResponse::policyDenied(
                $request,
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

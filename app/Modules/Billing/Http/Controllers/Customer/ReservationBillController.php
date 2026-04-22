<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Customer;

use App\Http\Concerns\RespondsWithCustomerReservationNotFound;
use App\Http\Controllers\Controller;
use App\Modules\Billing\Application\UseCases\Previews\CustomerReservationBillService;
use App\Modules\Billing\Application\UseCases\Previews\CustomerReservationOrderBillService;
use App\Modules\Billing\Http\Resources\Customer\ReservationBillResource;
use App\Modules\IdentityAccess\Application\Workflows\ReservationSessionAccessWorkflow;
use App\Modules\Ordering\Http\Resources\ReservationOrderResource;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationBillController extends Controller
{
    use RespondsWithCustomerReservationNotFound;

    public function __construct(
        private readonly CustomerReservationBillService $billService,
        private readonly CustomerReservationOrderBillService $orderBillService,
        private readonly ReservationSessionAccessWorkflow $customerSessionAccessService,
    ) {}

    public function show(int $reservation_id, Request $request): JsonResponse
    {
        try {
            [$reservation, $accessScope] = $this->resolveAccessibleReservation($reservation_id, $request);
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => new ReservationBillResource(
                $this->billService->getReservationBill($reservation),
                $accessScope,
                $this->billService,
            ),
        ]);
    }

    public function activeOrder(int $reservation_id, Request $request): JsonResponse
    {
        try {
            [$reservation] = $this->resolveAccessibleReservation($reservation_id, $request);
            $result = $this->orderBillService->showAccessibleActiveOrder($reservation);
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => [
                'reservation_id' => (int) $result['reservation']->reservation_id,
                'active_order' => $result['active_order'] !== null
                    ? (new ReservationOrderResource($result['active_order']))->toArray($request)
                    : null,
            ],
            'meta' => [
                'has_active_order' => $result['active_order'] !== null,
            ],
        ]);
    }

    public function billPreview(int $reservation_id, Request $request): JsonResponse
    {
        try {
            [$reservation] = $this->resolveAccessibleReservation($reservation_id, $request);
            $result = $this->orderBillService->previewAccessibleBill($reservation);
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => [
                'reservation_id' => (int) $result['reservation']->reservation_id,
                'active_order' => $result['active_order'] !== null
                    ? (new ReservationOrderResource($result['active_order']))->toArray($request)
                    : null,
                'bill_preview' => $result['bill_preview'],
            ],
        ]);
    }

    /**
     * @return array{0:Reservation,1:string}
     */
    private function resolveAccessibleReservation(int $reservationId, Request $request): array
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            throw new HttpResponseException(ApiErrorResponse::policyDenied(
                $request,
                'Staff must use staff settlement endpoints for operational actions.',
            ));
        }

        if ($actor->isCustomerOwner() && $actor->customerUserId() !== null) {
            $reservation = Reservation::query()
                ->whereKey($reservationId)
                ->where('user_id', $actor->customerUserId())
                ->first();

            if ($reservation instanceof Reservation) {
                return [$reservation, 'owner'];
            }

            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        $reservation = Reservation::query()->find($reservationId);
        $sessionId = $actor->sessionId() ?? $this->customerSessionAccessService->extractSessionIdFromRequest($request);
        if (! $reservation instanceof Reservation || $sessionId === '' || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $sessionId)) {
            throw (new ModelNotFoundException)->setModel(Reservation::class, [$reservationId]);
        }

        return [$reservation, 'session'];
    }
}

<?php

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesResolvedStaffCapability;
use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Reservations\Http\Requests\ReplaceReservationPreOrderRequest;
use App\Modules\Reservations\Http\Requests\StoreReservationRequest;
use App\Modules\Reservations\Http\Requests\UpdateReservationStatusRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Services\CustomerReservationSessionAccessService;
use App\Modules\Reservations\Application\Services\ReservationPreorderService;
use App\Modules\Reservations\Application\Services\ReservationService;
use App\Support\RequestActorContext;
use App\Support\ApiErrorResponse;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    use AuthorizesResolvedStaffCapability;
    use ResolvesStaffActor;

    public function __construct(
        private readonly ReservationService $service,
        private readonly CustomerReservationSessionAccessService $customerSessionAccessService,
        private readonly ReservationPreorderService $reservationPreorderService,
    ) {
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);
        $isStaff = $actor->isStaff();
        $payload = $request->validated();
        $accessScope = ReservationAccessScope::SESSION;

        if ($isStaff) {
            $this->authorizeResolvedStaffCapability($request, 'reservation.manage');
            $actorUserId = $this->resolveStaffActorUserId($request);
            $accessScope = ReservationAccessScope::STAFF;
        } else {
            $actorUserId = $actor->customerUserId();
            // For hold ownership validation, prefer the request-provided session_id
            // (body or X-Session-Id header) over the actor's access-session session_id,
            // because the hold was created with the request-level session_id.
            $requestSessionId = $this->customerSessionAccessService->extractSessionIdFromRequest($request, $payload);
            $sessionId = $requestSessionId !== '' ? $requestSessionId : ($actor->sessionId() ?? '');
            $holdId = trim((string) ($payload['hold_id'] ?? ''));

            if ($actorUserId !== null) {
                if ($holdId !== '' && $sessionId !== '' && ! $this->customerSessionAccessService->authenticatedCustomerCanUseHold($holdId, $sessionId, (int) $actorUserId)) {
                    return $this->reservationCreateUnauthorizedResponse($request);
                }

                $payload['user_id'] = (int) $actorUserId;
                $accessScope = ReservationAccessScope::OWNER;
            } else {
                $ownedUserId = $holdId !== '' && $sessionId !== ''
                    ? $this->customerSessionAccessService->resolveUserIdFromOwnedHold($holdId, $sessionId)
                    : null;

                if ($ownedUserId === null) {
                    return $this->reservationCreateUnauthorizedResponse($request);
                }

                $payload['user_id'] = $ownedUserId;
            }
        }

        $reservation = $this->service->createReservation($payload, $actorUserId !== null ? (int) $actorUserId : null);
        $request->attributes->set('reservation_access_scope', $accessScope);

        return response()->json([
            'data' => new ReservationResource($reservation),
        ], 201);
    }

    private function reservationCreateUnauthorizedResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::authenticationRequired(
            $request,
            'Customer authentication or a session-owned hold is required to create a reservation.',
        );
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $actor = RequestActorContext::fromRequest($request);
        $isStaff = $actor->isStaff();
        $actorUserId = $isStaff ? $this->resolveStaffActorUserId($request) : $actor->customerUserId();

        $scope = ReservationAccessScope::STAFF;
        $reservation = null;

        if ($isStaff) {
            $this->authorizeResolvedStaffCapability($request, 'reservation.manage');
            /** @var Reservation|null $reservation */
            $reservation = Reservation::query()->find($id);
        } elseif ($actorUserId !== null) {
            $scope = ReservationAccessScope::OWNER;
            /** @var Reservation|null $reservation */
            $reservation = Reservation::query()
                ->whereKey($id)
                ->where('user_id', (int) $actorUserId)
                ->first();
        } else {
            $scope = ReservationAccessScope::SESSION;
            $sessionId = $actor->sessionId() ?? $this->customerSessionAccessService->extractSessionIdFromRequest($request);
            /** @var Reservation|null $reservation */
            $reservation = Reservation::query()->find($id);

            if ($sessionId === '' || ! $reservation instanceof Reservation || ! $this->customerSessionAccessService->canAccessReservationBySession($reservation, $sessionId)) {
                return $this->notFoundReservationResponse($request);
            }
        }

        if (! $reservation instanceof Reservation) {
            return $this->notFoundReservationResponse($request);
        }

        $reservation->load($this->relationsForScope($scope));
        $request->attributes->set('reservation_access_scope', $scope);

        return response()->json([
            'data' => new ReservationResource($reservation),
        ]);
    }

    public function showPreOrder(int $id, Request $request): JsonResponse
    {
        $reservation = $this->resolveReservationForPreOrder($id, $request);
        if (! $reservation instanceof Reservation) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => $this->reservationPreorderService->snapshotForReservation($reservation),
        ]);
    }

    public function replacePreOrder(int $id, ReplaceReservationPreOrderRequest $request): JsonResponse
    {
        $reservation = $this->resolveReservationForPreOrder($id, $request);
        if (! $reservation instanceof Reservation) {
            return $this->notFoundReservationResponse($request);
        }

        $actorUserId = (bool) $request->attributes->get('is_staff', false)
            ? $this->resolveStaffActorUserId($request)
            : ($request->user()?->user_id !== null ? (int) $request->user()->user_id : null);

        return response()->json([
            'data' => $this->reservationPreorderService->replaceForReservation(
                $reservation,
                (array) $request->validated('pre_order_items', []),
                $actorUserId,
            ),
        ]);
    }

    public function updateStatus(int $id, UpdateReservationStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = (string) $validated['status'];
        $expectedRowVersion = (array_key_exists('row_version', $validated) && $validated['row_version'] !== null)
            ? (int) $validated['row_version']
            : null;

        $actorUserId = $this->resolveStaffActorUserId($request);

        $reservation = $this->service->updateReservationStatus(
            reservationId: $id,
            newStatus: $status,
            expectedRowVersion: $expectedRowVersion,
            actorUserId: $actorUserId,
            options: [
                'force' => (bool) ($validated['force'] ?? false),
                'cancel_reason' => isset($validated['cancel_reason']) ? (string) $validated['cancel_reason'] : null,
            ]
        );

        $request->attributes->set('reservation_access_scope', ReservationAccessScope::STAFF);

        return response()->json([
            'data' => new ReservationResource($reservation),
        ]);
    }

    private function resolveReservationForPreOrder(int $id, Request $request): ?Reservation
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            $request->attributes->set('reservation_access_scope', ReservationAccessScope::STAFF);

            return Reservation::query()->find($id);
        }

        $actorUserId = $actor->customerUserId();
        if ($actorUserId === null) {
            return null;
        }

        $request->attributes->set('reservation_access_scope', ReservationAccessScope::OWNER);

        return Reservation::query()
            ->whereKey($id)
            ->where('user_id', (int) $actorUserId)
            ->first();
    }

    private function notFoundReservationResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::notFound($request, 'Reservation not found.');
    }

    /**
     * @return list<string>
     */
    private function relationsForScope(string $scope): array
    {
        return match ($scope) {
            ReservationAccessScope::SESSION => [
                'user',
                'tables',
                'orders.items.item',
            ],
            ReservationAccessScope::OWNER => [
                'user',
                'user.points',
                'user.currentTier',
                'tables',
                'orders.items.item',
                'payments',
                'appliedUserVoucher.voucher',
            ],
            default => [
                'user',
                'user.points',
                'user.currentTier',
                'tables',
                'orders.items.item',
                'payments',
                'appliedUserVoucher.voucher',
            ],
        };
    }
}

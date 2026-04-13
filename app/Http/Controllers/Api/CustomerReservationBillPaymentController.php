<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithCustomerReservationNotFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\CreateCustomerReservationBillPaymentSessionRequest;
use App\Http\Requests\Reservation\MutateCustomerReservationBillPaymentSessionRequest;
use App\Http\Resources\CustomerReservationBillPaymentSessionResource;
use App\Services\Customer\CustomerReservationBillPaymentService;
use App\Support\ApiErrorResponse;
use App\Support\RequestActorContext;
use App\Support\ReservationAccessScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReservationBillPaymentController extends Controller
{
    use RespondsWithCustomerReservationNotFound;

    public function __construct(
        private readonly CustomerReservationBillPaymentService $service,
    ) {}

    public function store(int $id, CreateCustomerReservationBillPaymentSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->resolveCustomerContext($request, $validated);
        $request->attributes->set('reservation_access_scope', $context['scope']);

        try {
            $result = $this->service->createSession(
                reservationId: $id,
                payload: $validated,
                customerUserId: $context['customer_user_id'],
                sessionId: $context['session_id'],
                idempotencyKey: $this->resolveIdempotencyKey($request),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => [
                'reservation_id' => (int) $result['reservation']->reservation_id,
                'bill' => $result['bill'],
                'payment_session' => new CustomerReservationBillPaymentSessionResource($result['payment_session']),
            ],
        ], 201);
    }

    public function show(int $id, int $sessionId, Request $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);
        $request->attributes->set('reservation_access_scope', $context['scope']);

        try {
            $result = $this->service->showSession($id, $sessionId, $context['customer_user_id'], $context['session_id']);
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => [
                'reservation_id' => (int) $result['reservation']->reservation_id,
                'bill' => $result['bill'],
                'payment_session' => new CustomerReservationBillPaymentSessionResource($result['payment_session']),
            ],
        ]);
    }

    public function refresh(int $id, int $sessionId, MutateCustomerReservationBillPaymentSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->resolveCustomerContext($request, $validated);
        $request->attributes->set('reservation_access_scope', $context['scope']);

        try {
            $result = $this->service->refreshSession($id, $sessionId, $validated, $context['customer_user_id'], $context['session_id']);
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => [
                'reservation_id' => (int) $result['reservation']->reservation_id,
                'bill' => $result['bill'],
                'payment_session' => new CustomerReservationBillPaymentSessionResource($result['payment_session']),
            ],
        ]);
    }

    public function confirm(int $id, int $sessionId, MutateCustomerReservationBillPaymentSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $context = $this->resolveCustomerContext($request, $validated);
        $request->attributes->set('reservation_access_scope', $context['scope']);

        try {
            $result = $this->service->confirmSession($id, $sessionId, $validated, $context['customer_user_id'], $context['session_id']);
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => [
                'reservation_id' => (int) $result['reservation']->reservation_id,
                'bill' => $result['bill'],
                'payment_session' => new CustomerReservationBillPaymentSessionResource($result['payment_session']),
            ],
        ]);
    }

    /**
     * @param  array<string,mixed>|null  $validatedPayload
     * @return array{scope:string,customer_user_id:int|null,session_id:string|null}
     */
    private function resolveCustomerContext(Request $request, ?array $validatedPayload = null): array
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            abort(ApiErrorResponse::policyDenied(
                $request,
                'Staff must use staff settlement endpoints for operational actions.',
            ));
        }

        if ($actor->isCustomerOwner() && $actor->customerUserId() !== null) {
            return [
                'scope' => ReservationAccessScope::OWNER,
                'customer_user_id' => $actor->customerUserId(),
                'session_id' => null,
            ];
        }

        if ($actor->isCustomerSession() && $actor->sessionId() !== null) {
            return [
                'scope' => ReservationAccessScope::SESSION,
                'customer_user_id' => null,
                'session_id' => $actor->sessionId(),
            ];
        }

        abort(ApiErrorResponse::authenticationRequired(
            $request,
            'Customer authentication or a valid session_id is required for bill payment.',
        ));
    }

    private function resolveIdempotencyKey(Request $request): string
    {
        return (string) ($request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key')
            ?? '');
    }
}

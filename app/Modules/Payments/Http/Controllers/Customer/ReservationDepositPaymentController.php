<?php

declare(strict_types=1);

namespace App\Modules\Payments\Http\Controllers\Customer;

use App\Http\Concerns\RespondsWithCustomerReservationNotFound;
use App\Http\Controllers\Controller;
use App\Modules\Payments\Application\UseCases\PaymentSessions\CustomerReservationDepositPaymentService;
use App\Modules\Payments\Http\Requests\Customer\MutateReservationDepositPaymentSessionRequest;
use App\Modules\Payments\Http\Requests\Customer\StartReservationDepositPaymentRequest;
use App\Modules\Payments\Http\Resources\Customer\ReservationDepositPaymentSessionResource;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationDepositPaymentController extends Controller
{
    use RespondsWithCustomerReservationNotFound;

    public function __construct(
        private readonly CustomerReservationDepositPaymentService $service,
    ) {}

    public function store(int $id, StartReservationDepositPaymentRequest $request): JsonResponse
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
                'deposit' => $result['deposit'],
                'payment_session' => new ReservationDepositPaymentSessionResource($result['payment_session']),
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
                'deposit' => $result['deposit'],
                'payment_session' => new ReservationDepositPaymentSessionResource($result['payment_session']),
            ],
        ]);
    }

    public function refresh(int $id, int $sessionId, MutateReservationDepositPaymentSessionRequest $request): JsonResponse
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
                'deposit' => $result['deposit'],
                'payment_session' => new ReservationDepositPaymentSessionResource($result['payment_session']),
            ],
        ]);
    }

    public function confirm(int $id, int $sessionId, MutateReservationDepositPaymentSessionRequest $request): JsonResponse
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
                'deposit' => $result['deposit'],
                'payment_session' => new ReservationDepositPaymentSessionResource($result['payment_session']),
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
            throw new HttpResponseException(ApiErrorResponse::policyDenied(
                $request,
                'Staff must use staff reservation deposit endpoints for operational actions.',
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

        throw new HttpResponseException(ApiErrorResponse::authenticationRequired(
            $request,
            'Customer authentication or a valid session_id is required for deposit payment.',
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

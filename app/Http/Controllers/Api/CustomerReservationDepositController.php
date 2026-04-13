<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithCustomerReservationNotFound;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\AcknowledgeCustomerReservationDepositRequest;
use App\Http\Requests\Reservation\RevokeCustomerReservationDepositIntentRequest;
use App\Http\Requests\Reservation\SubmitCustomerReservationDepositIntentRequest;
use App\Http\Resources\CustomerReservationDepositPreviewResource;
use App\Services\Customer\CustomerReservationDepositIntentService;
use App\Services\Customer\CustomerReservationDepositService;
use App\Support\ApiErrorResponse;
use App\Support\RequestActorContext;
use App\Support\ReservationAccessScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReservationDepositController extends Controller
{
    use RespondsWithCustomerReservationNotFound;

    public function __construct(
        private readonly CustomerReservationDepositService $depositService,
        private readonly CustomerReservationDepositIntentService $depositIntentService,
    ) {}

    public function show(int $id, Request $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);

        try {
            $result = $this->depositService->previewAccessibleReservationDeposit(
                reservationId: $id,
                userId: $context['customer_user_id'],
                sessionId: $context['session_id'],
                fallbackCurrency: (string) ($request->query('currency') ?? 'VND'),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        $request->attributes->set('reservation_access_scope', $context['scope']);

        return response()->json([
            'data' => (new CustomerReservationDepositPreviewResource($result))->resolve($request),
            'meta' => [
                'action' => 'customer_reservation_deposit_preview',
                'intent_supported' => true,
            ],
        ]);
    }

    public function acknowledge(int $id, AcknowledgeCustomerReservationDepositRequest $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);

        try {
            $result = $this->depositIntentService->acknowledgeDepositRequirementForAccessibleReservation(
                reservationId: $id,
                userId: $context['customer_user_id'],
                sessionId: $context['session_id'],
                expectedRowVersion: (int) $request->input('row_version'),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        $request->attributes->set('reservation_access_scope', $context['scope']);

        return response()->json([
            'data' => (new CustomerReservationDepositPreviewResource($result))->resolve($request),
            'meta' => [
                'action' => 'customer_reservation_deposit_acknowledge',
                'intent_supported' => true,
            ],
        ]);
    }

    public function submitIntent(int $id, SubmitCustomerReservationDepositIntentRequest $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);

        try {
            $result = $this->depositIntentService->submitDepositIntentForAccessibleReservation(
                reservationId: $id,
                userId: $context['customer_user_id'],
                sessionId: $context['session_id'],
                expectedRowVersion: (int) $request->input('row_version'),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        $request->attributes->set('reservation_access_scope', $context['scope']);

        return response()->json([
            'data' => (new CustomerReservationDepositPreviewResource($result))->resolve($request),
            'meta' => [
                'action' => 'customer_reservation_deposit_submit_intent',
                'intent_supported' => true,
            ],
        ]);
    }

    public function revokeIntent(int $id, RevokeCustomerReservationDepositIntentRequest $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);

        try {
            $result = $this->depositIntentService->revokeDepositIntentForAccessibleReservation(
                reservationId: $id,
                userId: $context['customer_user_id'],
                sessionId: $context['session_id'],
                expectedRowVersion: (int) $request->input('row_version'),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        $request->attributes->set('reservation_access_scope', $context['scope']);

        return response()->json([
            'data' => (new CustomerReservationDepositPreviewResource($result))->resolve($request),
            'meta' => [
                'action' => 'customer_reservation_deposit_revoke_intent',
                'intent_supported' => true,
            ],
        ]);
    }

    /**
     * @return array{scope:string,customer_user_id:int|null,session_id:string|null}
     */
    private function resolveCustomerContext(Request $request): array
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
            'Customer authentication or a valid session_id is required.',
        ));
    }
}

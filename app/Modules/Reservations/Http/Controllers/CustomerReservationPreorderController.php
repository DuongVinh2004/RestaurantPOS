<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Concerns\AppliesDeprecatedRouteHeaders;
use App\Http\Controllers\Controller;
use App\Modules\Reservations\Http\Requests\ClearCustomerReservationPreorderRequest;
use App\Modules\Reservations\Http\Requests\PreviewCustomerReservationPreorderRequest;
use App\Modules\Reservations\Http\Requests\ReplaceCustomerReservationPreorderRequest;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Application\Services\CustomerReservationPreorderService;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReservationPreorderController extends Controller
{
    use AppliesDeprecatedRouteHeaders;

    public function __construct(
        private readonly CustomerReservationPreorderService $service,
    ) {}

    public function show(int $id, Request $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);

        try {
            $result = $this->service->showAccessiblePreorder(
                reservationId: $id,
                customerUserId: $context['customer_user_id'],
                sessionId: $context['session_id'],
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return $this->applyLegacyAliasHeaders($request, response()->json([
            'data' => $this->basePayload($result),
            'meta' => [
                'action' => 'customer_reservation_preorder_show',
                'access_scope' => $context['scope'],
            ],
        ]));
    }

    public function preview(int $id, PreviewCustomerReservationPreorderRequest $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);

        try {
            $result = $this->service->previewAccessiblePreorderUpdate(
                reservationId: $id,
                customerUserId: $context['customer_user_id'],
                sessionId: $context['session_id'],
                requestedItems: (array) $request->validated('pre_order_items', []),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return $this->applyLegacyAliasHeaders($request, response()->json([
            'data' => array_merge($this->basePayload([
                'reservation' => $result['reservation'],
                'pre_order' => $result['current_pre_order'],
                'management_policy' => $result['management_policy'],
            ]), [
                'preview' => $result['preview'],
            ]),
            'meta' => [
                'action' => 'customer_reservation_preorder_preview',
                'access_scope' => $context['scope'],
            ],
        ]));
    }

    public function replace(int $id, ReplaceCustomerReservationPreorderRequest $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);

        try {
            $result = $this->service->replaceAccessiblePreorder(
                reservationId: $id,
                customerUserId: $context['customer_user_id'],
                sessionId: $context['session_id'],
                payload: $request->validated(),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return $this->applyLegacyAliasHeaders($request, response()->json([
            'data' => $this->basePayload($result),
            'meta' => [
                'action' => 'customer_reservation_preorder_replace',
                'access_scope' => $context['scope'],
            ],
        ]));
    }

    public function clear(int $id, ClearCustomerReservationPreorderRequest $request): JsonResponse
    {
        $context = $this->resolveCustomerContext($request);

        try {
            $result = $this->service->clearAccessiblePreorder(
                reservationId: $id,
                customerUserId: $context['customer_user_id'],
                sessionId: $context['session_id'],
                payload: $request->validated(),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return $this->applyLegacyAliasHeaders($request, response()->json([
            'data' => $this->basePayload($result),
            'meta' => [
                'action' => 'customer_reservation_preorder_clear',
                'access_scope' => $context['scope'],
            ],
        ]));
    }

    /**
     * @param  array{reservation:Reservation,pre_order:array<string,mixed>,management_policy:array<string,mixed>}  $result
     * @return array<string,mixed>
     */
    private function basePayload(array $result): array
    {
        return [
            'reservation_id' => (int) $result['reservation']->reservation_id,
            'reservation_code' => (string) $result['reservation']->reservation_code,
            'reservation_status' => $result['reservation']->status?->value ?? (string) $result['reservation']->status,
            'reservation_row_version' => (int) ($result['reservation']->row_version ?? 1),
            'pre_order' => $result['pre_order'],
            'management_policy' => $result['management_policy'],
        ];
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
                'Staff must use dedicated staff reservation endpoints for operational actions.',
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

    private function applyLegacyAliasHeaders(Request $request, JsonResponse $response): JsonResponse
    {
        $path = '/'.trim($request->path(), '/');

        if (! str_contains($path, '/pre-order')) {
            return $response;
        }

        $canonical = preg_replace('#/pre-order(?=/|$)#', '/preorder', $path, 1);
        if (! is_string($canonical) || $canonical === '') {
            return $response;
        }

        return $this->markDeprecatedRouteAlias($response, $path, $canonical);
    }

    private function notFoundReservationResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::notFound($request, 'Reservation data was not found.');
    }
}

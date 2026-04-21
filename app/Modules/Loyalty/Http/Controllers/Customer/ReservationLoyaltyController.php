<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Loyalty\Application\Workflows\CustomerReservationLoyaltyWorkflow;
use App\Modules\Loyalty\Http\Requests\Customer\RedeemReservationPointsRequest;
use App\Modules\Loyalty\Http\Requests\Customer\ReleaseReservationPointsRequest;
use App\Modules\Loyalty\Http\Resources\LoyaltyPointTransactionResource;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationLoyaltyController extends Controller
{
    public function __construct(
        private readonly CustomerReservationLoyaltyWorkflow $loyaltyWorkflow,
    ) {}

    public function redeem(int $id, RedeemReservationPointsRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);

        try {
            $result = $this->loyaltyWorkflow->redeemPointsForOwnedReservation(
                reservationId: $id,
                userId: (int) $user->user_id,
                payload: $request->validated(),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => [
                'reservation' => $result['reservation'] ?? null,
                'transactions' => $this->resolveCustomerLoyaltyTransactions($request, $result['transactions'] ?? []),
            ],
        ]);
    }

    public function release(int $id, ReleaseReservationPointsRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);

        try {
            $result = $this->loyaltyWorkflow->releasePointsForOwnedReservation(
                reservationId: $id,
                userId: (int) $user->user_id,
                payload: $request->validated(),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        return response()->json([
            'data' => [
                'reservation' => $result['reservation'] ?? null,
                'transactions' => $this->resolveCustomerLoyaltyTransactions($request, $result['transactions'] ?? []),
            ],
        ]);
    }

    /**
     * @param  iterable<mixed>  $transactions
     * @return array<int,array<string,mixed>>
     */
    private function resolveCustomerLoyaltyTransactions(Request $request, iterable $transactions): array
    {
        $rows = collect($transactions)
            ->map(function (mixed $row): array {
                $payload = is_array($row) ? $row : (method_exists($row, 'toArray') ? $row->toArray() : (array) $row);

                if ((string) ($payload['txn_type'] ?? '') === 'Redeem' && isset($payload['points'])) {
                    $payload['points'] = abs((int) $payload['points']);
                }

                return $payload;
            })
            ->values();

        return LoyaltyPointTransactionResource::collection($rows)->resolve($request);
    }

    private function requireAuthenticatedCustomer(Request $request): User
    {
        $actor = RequestActorContext::fromRequest($request);

        if ($actor->isStaff()) {
            throw new HttpResponseException(ApiErrorResponse::policyDenied(
                $request,
                'Staff must use staff loyalty endpoints for operational actions.',
            ));
        }

        $user = $request->user();
        if (! $actor->isCustomerOwner() || ! $user instanceof User) {
            throw new HttpResponseException(ApiErrorResponse::authenticationRequired(
                $request,
                'Customer authentication is required.',
            ));
        }

        return $user;
    }

    private function notFoundReservationResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::notFound($request, 'Reservation data was not found.');
    }
}

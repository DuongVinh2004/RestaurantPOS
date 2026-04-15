<?php

declare(strict_types=1);

namespace App\Modules\BenefitsLoyalty\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\BenefitsLoyalty\Application\Services\CustomerReservationBenefitsSelfService;
use App\Modules\BenefitsLoyalty\Http\Requests\Customer\ApplyCustomerReservationVoucherRequest;
use App\Modules\BenefitsLoyalty\Http\Requests\Customer\RedeemCustomerReservationPointsRequest;
use App\Modules\BenefitsLoyalty\Http\Requests\Customer\ReleaseCustomerReservationPointsRequest;
use App\Modules\BenefitsLoyalty\Http\Requests\Customer\RemoveCustomerReservationVoucherRequest;
use App\Modules\BenefitsLoyalty\Http\Resources\CustomerReservationBenefitsPreviewResource;
use App\Modules\BenefitsLoyalty\Http\Resources\CustomerVoucherResource;
use App\Modules\BenefitsLoyalty\Http\Resources\LoyaltyPointTransactionResource;
use App\Support\ApiErrorResponse;
use App\Support\RequestActorContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReservationBenefitsActionController extends Controller
{
    public function __construct(
        private readonly CustomerReservationBenefitsSelfService $benefitsSelfService,
    ) {}

    public function applyVoucher(int $id, ApplyCustomerReservationVoucherRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);

        try {
            $result = $this->benefitsSelfService->applyVoucherForOwnedReservation(
                reservationId: $id,
                userId: (int) $user->user_id,
                payload: $request->validated(),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        $preview = (new CustomerReservationBenefitsPreviewResource($result['preview']))->resolve($request);

        return response()->json([
            'data' => array_merge($preview, [
                'voucher' => isset($result['voucher']) && is_array($result['voucher'])
                    ? (new CustomerVoucherResource($result['voucher']))->resolve($request)
                    : null,
            ]),
        ]);
    }

    public function removeVoucher(int $id, RemoveCustomerReservationVoucherRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);

        try {
            $result = $this->benefitsSelfService->removeVoucherForOwnedReservation(
                reservationId: $id,
                userId: (int) $user->user_id,
                payload: $request->validated(),
            );
        } catch (ModelNotFoundException) {
            return $this->notFoundReservationResponse($request);
        }

        $preview = (new CustomerReservationBenefitsPreviewResource($result['preview']))->resolve($request);

        return response()->json([
            'data' => array_merge($preview, [
                'removed_voucher' => isset($result['removed_voucher']) && is_array($result['removed_voucher'])
                    ? (new CustomerVoucherResource($result['removed_voucher']))->resolve($request)
                    : null,
            ]),
        ]);
    }

    public function redeemLoyalty(int $id, RedeemCustomerReservationPointsRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);

        try {
            $result = $this->benefitsSelfService->redeemPointsForOwnedReservation(
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

    public function releaseLoyalty(int $id, ReleaseCustomerReservationPointsRequest $request): JsonResponse
    {
        $user = $this->requireAuthenticatedCustomer($request);

        try {
            $result = $this->benefitsSelfService->releasePointsForOwnedReservation(
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
                'Staff must use staff reservation benefits endpoints for operational actions.',
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

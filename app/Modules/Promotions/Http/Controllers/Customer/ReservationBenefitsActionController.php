<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Domain\Models\User;
use App\Modules\Promotions\Application\Workflows\CustomerReservationPromotionWorkflow;
use App\Modules\Promotions\Http\Requests\Customer\ApplyReservationVoucherRequest;
use App\Modules\Promotions\Http\Requests\Customer\RemoveReservationVoucherRequest;
use App\Modules\Promotions\Http\Resources\Customer\ReservationBenefitsPreviewResource;
use App\Modules\Promotions\Http\Resources\Customer\VoucherResource;
use App\Support\ApiErrorResponse;
use App\Support\Auth\RequestActorContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationBenefitsActionController extends Controller
{
    public function __construct(
        private readonly CustomerReservationPromotionWorkflow $benefitsSelfService,
    ) {}

    public function applyVoucher(int $id, ApplyReservationVoucherRequest $request): JsonResponse
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

        $preview = (new ReservationBenefitsPreviewResource($result['preview']))->resolve($request);

        return response()->json([
            'data' => array_merge($preview, [
                'voucher' => isset($result['voucher']) && is_array($result['voucher'])
                    ? (new VoucherResource($result['voucher']))->resolve($request)
                    : null,
            ]),
        ]);
    }

    public function removeVoucher(int $id, RemoveReservationVoucherRequest $request): JsonResponse
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

        $preview = (new ReservationBenefitsPreviewResource($result['preview']))->resolve($request);

        return response()->json([
            'data' => array_merge($preview, [
                'removed_voucher' => isset($result['removed_voucher']) && is_array($result['removed_voucher'])
                    ? (new VoucherResource($result['removed_voucher']))->resolve($request)
                    : null,
            ]),
        ]);
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

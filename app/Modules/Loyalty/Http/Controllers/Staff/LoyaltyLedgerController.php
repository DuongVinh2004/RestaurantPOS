<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Http\Controllers\Staff;

use App\Http\Concerns\AppliesDeprecatedRouteHeaders;
use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyPointsService;
use App\Modules\Loyalty\Http\Requests\Staff\AdjustUserLoyaltyPointsRequest;
use App\Modules\Loyalty\Http\Requests\Staff\RedeemReservationPointsRequest;
use App\Modules\Loyalty\Http\Requests\Staff\ReleaseReservationPointsRequest;
use App\Modules\Loyalty\Http\Resources\LoyaltyPointTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyLedgerController extends Controller
{
    use AppliesDeprecatedRouteHeaders;
    use ResolvesStaffActor;

    public function __construct(
        private readonly LoyaltyPointsService $loyaltyPointsService,
    ) {}

    public function showUser(int $user_id, Request $request): JsonResponse
    {
        $result = $this->loyaltyPointsService->getUserLoyaltySummary(
            userId: $user_id,
            limit: (int) ($request->query('limit', 20)),
        );

        return response()->json([
            'data' => [
                'user' => $result['user'],
                'transactions' => LoyaltyPointTransactionResource::collection($result['transactions']),
            ],
        ]);
    }

    public function adjustUser(int $user_id, AdjustUserLoyaltyPointsRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->loyaltyPointsService->adjustUserPoints(
            userId: $user_id,
            points: (int) $request->input('points'),
            reason: (string) $request->input('reason'),
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => [
                'user' => $result['user'],
                'transactions' => LoyaltyPointTransactionResource::collection($result['transactions']),
            ],
        ]);
    }

    public function showReservation(int $reservation_id, Request $request): JsonResponse
    {
        $result = $this->loyaltyPointsService->getReservationLoyaltySummary(
            reservationId: $reservation_id,
            limit: (int) ($request->query('limit', 20)),
        );

        return response()->json([
            'data' => [
                'reservation' => $result['reservation'],
                'transactions' => LoyaltyPointTransactionResource::collection($result['transactions']),
            ],
        ]);
    }

    public function redeemReservation(int $reservation_id, RedeemReservationPointsRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->loyaltyPointsService->redeemReservationPoints(
            reservationId: $reservation_id,
            points: (int) $request->input('points'),
            reason: $request->filled('reason') ? (string) $request->input('reason') : null,
            expectedRowVersion: $request->filled('row_version') ? (int) $request->input('row_version') : null,
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => [
                'reservation' => $result['reservation'],
                'transactions' => LoyaltyPointTransactionResource::collection($result['transactions']),
            ],
        ]);
    }

    public function legacyReleaseReservation(int $reservation_id, ReleaseReservationPointsRequest $request): JsonResponse
    {
        return $this->markDeprecatedRouteAliasForRequest(
            $request,
            $this->releaseReservation($reservation_id, $request),
            '/api/v1/staff/reservations/{reservation_id}/loyalty/release',
            '/api/v1/staff/reservations/{reservation_id}/loyalty/redeem/release',
        );
    }

    public function releaseReservation(int $reservation_id, ReleaseReservationPointsRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->loyaltyPointsService->releaseReservationRedemption(
            reservationId: $reservation_id,
            reason: $request->filled('reason') ? (string) $request->input('reason') : null,
            expectedRowVersion: $request->filled('row_version') ? (int) $request->input('row_version') : null,
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => [
                'reservation' => $result['reservation'],
                'transactions' => LoyaltyPointTransactionResource::collection($result['transactions']),
            ],
        ]);
    }
}

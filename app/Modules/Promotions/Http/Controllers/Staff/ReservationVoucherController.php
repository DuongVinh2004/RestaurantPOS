<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Http\Controllers\Staff;

use App\Http\Concerns\AppliesDeprecatedRouteHeaders;
use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Promotions\Application\Workflows\ReservationVoucherWorkflow;
use App\Modules\Promotions\Http\Requests\Staff\ApplyReservationVoucherRequest;
use App\Modules\Promotions\Http\Requests\Staff\RemoveReservationVoucherRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use Illuminate\Http\JsonResponse;

class ReservationVoucherController extends Controller
{
    use AppliesDeprecatedRouteHeaders;
    use ResolvesStaffActor;

    public function __construct(
        private readonly ReservationVoucherWorkflow $voucherService,
    ) {}

    public function index(int $reservation_id): JsonResponse
    {
        $result = $this->voucherService->listAvailableForReservation($reservation_id);

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($result['reservation']),
                'available_vouchers' => $result['available_vouchers'],
            ],
        ]);
    }

    public function apply(int $reservation_id, ApplyReservationVoucherRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->voucherService->applyVoucher(
            reservationId: $reservation_id,
            userVoucherId: $request->filled('user_voucher_id') ? (int) $request->input('user_voucher_id') : null,
            voucherCode: $request->filled('voucher_code') ? (string) $request->input('voucher_code') : null,
            expectedRowVersion: $request->filled('row_version') ? (int) $request->input('row_version') : null,
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($result['reservation']),
                'voucher' => $result['voucher'],
            ],
        ]);
    }

    public function release(int $reservation_id, RemoveReservationVoucherRequest $request): JsonResponse
    {
        return $this->markDeprecatedRouteAliasForRequest(
            $request,
            $this->remove($reservation_id, $request),
            '/api/v1/staff/reservations/{reservation_id}/voucher/release',
            '/api/v1/staff/reservations/{reservation_id}/voucher/remove',
        );
    }

    public function remove(int $reservation_id, RemoveReservationVoucherRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->voucherService->removeVoucher(
            reservationId: $reservation_id,
            expectedRowVersion: $request->filled('row_version') ? (int) $request->input('row_version') : null,
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => [
                'reservation' => new ReservationResource($result['reservation']),
                'removed_voucher' => $result['removed_voucher'],
            ],
        ]);
    }
}

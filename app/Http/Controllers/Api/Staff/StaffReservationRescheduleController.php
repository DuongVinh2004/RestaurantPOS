<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RescheduleReservationRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use App\Services\Staff\StaffReservationRescheduleService;
use Illuminate\Http\JsonResponse;

class StaffReservationRescheduleController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffReservationRescheduleService $rescheduleService,
    ) {
    }

    public function store(int $id, RescheduleReservationRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $reservation = $this->rescheduleService->reschedule(
            reservationId: $id,
            payload: $request->validated(),
            staffUserId: $staffUserId,
        );

        return response()->json([
            'data' => new ReservationResource($reservation),
        ]);
    }
}

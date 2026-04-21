<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Reservations\Application\Services\ReservationRescheduleService;
use App\Modules\Reservations\Http\Requests\Staff\RescheduleReservationRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use Illuminate\Http\JsonResponse;

class ReservationRescheduleController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly ReservationRescheduleService $rescheduleService,
    ) {
    }

    public function store(int $id, RescheduleReservationRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $reservation = $this->rescheduleService->reschedule(
            $id,
            $request->validated(),
            [
                'type' => 'staff',
                'user_id' => $staffUserId,
            ],
        );

        return response()->json([
            'data' => new ReservationResource($reservation),
        ]);
    }
}

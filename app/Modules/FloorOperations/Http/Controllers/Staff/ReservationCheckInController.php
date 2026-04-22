<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Application\UseCases\CheckIn\StaffCheckInService;
use App\Modules\FloorOperations\Http\Requests\Staff\CheckInReservationRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use Illuminate\Http\JsonResponse;

class ReservationCheckInController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffCheckInService $checkInService,
    ) {}

    public function store(int $id, CheckInReservationRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $reservation = $this->checkInService->checkIn(
            reservationId: $id,
            tableIds: $request->input('table_ids'),
            checkedInAt: $request->date('checked_in_at') ?? now(),
            staffUserId: $staffUserId,
            expectedRowVersion: $request->filled('row_version') ? (int) $request->input('row_version') : null,
        );

        return response()->json([
            'data' => new ReservationResource($reservation->load(['tables', 'user'])),
        ]);
    }
}

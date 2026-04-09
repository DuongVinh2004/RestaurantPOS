<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CheckInReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\Staff\StaffCheckInService;
use Illuminate\Http\JsonResponse;

class StaffReservationCheckInController extends Controller
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

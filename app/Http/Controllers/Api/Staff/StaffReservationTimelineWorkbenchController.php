<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOps\Http\Requests\AssignBestFitTableRequest;
use App\Modules\FloorOps\Http\Requests\AssignSuggestedTableRequest;
use App\Modules\FloorOps\Http\Requests\CheckInReservationRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use App\Modules\FloorOps\Application\Services\StaffCheckInService;
use App\Modules\FloorOps\Application\Services\StaffReservationBoardAssignmentService;
use Illuminate\Http\JsonResponse;

class StaffReservationTimelineWorkbenchController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffReservationBoardAssignmentService $assignmentService,
        private readonly StaffCheckInService $checkInService,
    ) {}

    public function assignSuggested(int $id, AssignSuggestedTableRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->assignmentService->assignSuggestedTable(
            reservationId: $id,
            tableId: $request->integer('table_id'),
            staffUserId: $staffUserId,
            expectedRowVersion: (int) $request->input('row_version'),
            zone: $this->normalizeBoardZone($request->input('zone')),
            boardFrom: $request->date('board_from'),
            boardTo: $request->date('board_to'),
            includeSlotOnlyCandidates: false,
        );

        return response()->json([
            'data' => new ReservationResource($result['reservation']),
            'assignment' => $result['assignment'],
            'meta' => [
                'action' => 'timeline_assign_suggested',
                'source' => 'staff_reservation_timeline_workbench',
            ],
        ]);
    }

    public function assignBestFit(int $id, AssignBestFitTableRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);
        $result = $this->assignmentService->assignBestFit(
            reservationId: $id,
            staffUserId: $staffUserId,
            expectedRowVersion: (int) $request->input('row_version'),
            zone: $this->normalizeBoardZone($request->input('zone')),
            boardFrom: $request->date('board_from'),
            boardTo: $request->date('board_to'),
            includeSlotOnlyCandidates: false,
        );

        return response()->json([
            'data' => new ReservationResource($result['reservation']),
            'assignment' => $result['assignment'],
            'meta' => [
                'action' => 'timeline_assign_best_fit',
                'source' => 'staff_reservation_timeline_workbench',
            ],
        ]);
    }

    public function checkIn(int $id, CheckInReservationRequest $request): JsonResponse
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
            'meta' => [
                'action' => 'timeline_check_in',
                'source' => 'staff_reservation_timeline_workbench',
            ],
        ]);
    }

    private function normalizeBoardZone(mixed $zone): ?string
    {
        $zone = trim((string) ($zone ?? ''));

        return $zone === '' ? null : $zone;
    }
}

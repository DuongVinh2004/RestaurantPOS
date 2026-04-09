<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AssignBestFitTableRequest;
use App\Http\Requests\Staff\AssignSuggestedTableRequest;
use App\Http\Resources\ReservationResource;
use App\Services\Staff\StaffReservationBoardAssignmentService;
use Illuminate\Http\JsonResponse;

class StaffReservationBoardAssignmentController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffReservationBoardAssignmentService $assignmentService,
    ) {
    }

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
            includeSlotOnlyCandidates: true,
        );

        return response()->json([
            'data' => new ReservationResource($result['reservation']),
            'assignment' => $result['assignment'],
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
            includeSlotOnlyCandidates: true,
        );

        return response()->json([
            'data' => new ReservationResource($result['reservation']),
            'assignment' => $result['assignment'],
        ]);
    }

    private function normalizeBoardZone(mixed $zone): ?string
    {
        $zone = trim((string) ($zone ?? ''));

        return $zone === '' ? null : $zone;
    }
}

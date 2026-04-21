<?php

declare(strict_types=1);

namespace App\Modules\FloorOperations\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOperations\Application\UseCases\Boards\StaffMoveTableService;
use App\Modules\FloorOperations\Http\Requests\Staff\MoveTableRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use Illuminate\Http\JsonResponse;

class ReservationMoveTableController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffMoveTableService $moveTableService,
    ) {
    }

    public function store(int $id, MoveTableRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $reservation = $this->moveTableService->move(
            reservationId: $id,
            fromTableId: $request->integer('from_table_id'),
            toTableId: $request->integer('to_table_id'),
            movedAt: $request->date('moved_at') ?? now(),
            staffUserId: $staffUserId,
            expectedRowVersion: $request->filled('row_version') ? (int) $request->input('row_version') : null,
        );

        return response()->json([
            'data' => new ReservationResource($reservation->load(['tables', 'user'])),
        ]);
    }
}



<?php

declare(strict_types=1);

namespace App\Modules\FloorOps\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\FloorOps\Application\Services\StaffServiceSessionService;
use App\Modules\FloorOps\Http\Requests\CreateWalkInServiceSessionRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use App\Support\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffServiceSessionController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly StaffServiceSessionService $serviceSessionService,
    ) {
    }

    public function store(CreateWalkInServiceSessionRequest $request): JsonResponse
    {
        $staffUserId = $this->resolveStaffActorUserId($request);

        $reservation = $this->serviceSessionService->createWalkInSession(
            $request->validated(),
            $staffUserId,
        );

        return response()->json([
            'data' => new ReservationResource($reservation),
        ], 201);
    }

    public function showActiveByTable(int $table_id, Request $request): JsonResponse
    {
        $reservation = $this->serviceSessionService->findActiveSessionByTable($table_id);
        if ($reservation === null) {
            return $this->notFoundResponse($request);
        }

        return response()->json([
            'data' => new ReservationResource($reservation),
            'meta' => [
                'action' => 'active_service_session_by_table',
            ],
        ]);
    }

    private function notFoundResponse(Request $request): JsonResponse
    {
        return ApiErrorResponse::json(
            $request,
            404,
            'not_found',
            'No active service session found for this table.',
        );
    }
}

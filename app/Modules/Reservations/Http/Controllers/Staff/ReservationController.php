<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Http\Controllers\Staff;

use App\Http\Concerns\ResolvesStaffActor;
use App\Http\Controllers\Controller;
use App\Modules\Reservations\Application\Services\ReservationService;
use App\Modules\Reservations\Domain\Policies\ReservationAccessScope;
use App\Modules\Reservations\Http\Requests\Staff\UpdateReservationStatusRequest;
use App\Modules\Reservations\Http\Resources\ReservationResource;
use Illuminate\Http\JsonResponse;

class ReservationController extends Controller
{
    use ResolvesStaffActor;

    public function __construct(
        private readonly ReservationService $service,
    ) {
    }

    public function updateStatus(int $id, UpdateReservationStatusRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = (string) $validated['status'];
        $expectedRowVersion = (array_key_exists('row_version', $validated) && $validated['row_version'] !== null)
            ? (int) $validated['row_version']
            : null;

        $actorUserId = $this->resolveStaffActorUserId($request);

        $reservation = $this->service->updateReservationStatus(
            reservationId: $id,
            newStatus: $status,
            expectedRowVersion: $expectedRowVersion,
            actorUserId: $actorUserId,
            options: [
                'force' => (bool) ($validated['force'] ?? false),
                'cancel_reason' => isset($validated['cancel_reason']) ? (string) $validated['cancel_reason'] : null,
            ]
        );

        $request->attributes->set('reservation_access_scope', ReservationAccessScope::STAFF);

        return response()->json([
            'data' => new ReservationResource($reservation),
        ]);
    }
}

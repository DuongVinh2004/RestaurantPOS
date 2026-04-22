<?php

namespace App\Platform\Health\Services;

use App\Enums\ReservationStatus;
use App\Modules\BranchScheduling\Application\Services\TableHoldService;
use App\Modules\Notifications\Application\Services\NotificationOutboxService;
use App\Modules\Reservations\Application\Services\ReservationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingMaintenanceService
{
    public function __construct(
        private readonly TableHoldService $tableHoldService,
        private readonly ReservationService $reservationService,
        private readonly NotificationOutboxService $notificationOutboxService,
    ) {}

    public function expireHolds(): int
    {
        return $this->tableHoldService->expireStaleHolds();
    }

    public function expireReservations(int $graceMinutes = 0): int
    {
        $cutoff = Carbon::now('UTC')->subMinutes(max(0, $graceMinutes));

        $reservationIds = DB::table('reservations')
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('end_time', '<=', $cutoff)
            ->orderBy('reservation_id')
            ->pluck('reservation_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $count = 0;
        foreach ($reservationIds as $reservationId) {
            try {
                $this->reservationService->updateReservationStatus(
                    reservationId: $reservationId,
                    newStatus: ReservationStatus::Expired->value,
                    expectedRowVersion: null,
                    actorUserId: null,
                    options: ['source' => 'scheduler.expire']
                );
                $count++;
            } catch (\Throwable $e) {
                Log::channel('audit')->warning('scheduler.expire_reservation_failed', [
                    'reservation_id' => $reservationId,
                    'grace_minutes' => $graceMinutes,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        }

        return $count;
    }

    public function markNoShows(int $graceMinutes = 15): int
    {
        return $this->reservationService->markNoShows($graceMinutes);
    }

    public function flushNotificationOutbox(int $limit = 100): array
    {
        return $this->notificationOutboxService->flushPending($limit);
    }
}

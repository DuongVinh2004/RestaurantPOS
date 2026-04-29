<?php

declare(strict_types=1);

namespace App\Modules\Cashiering\Application\UseCases\Realtime;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Platform\Realtime\Services\OperationalRealtimeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SettlementRealtimeEventService
{
    public function __construct(
        private readonly OperationalRealtimeService $realtimeService,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function buildSettlementCompletedPayload(Reservation $reservation, int $orderId): array
    {
        $reservationId = (int) $reservation->reservation_id;
        $checkedOutAt = $reservation->checked_out_at instanceof Carbon
            ? $reservation->checked_out_at->copy()->setTimezone('UTC')->toIso8601String()
            : null;

        return [
            'reservation_id' => $reservationId,
            'order_id' => $orderId,
            'table_ids' => DB::table('reservation_tables')
                ->where('reservation_id', $reservationId)
                ->orderBy('table_id')
                ->pluck('table_id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'checked_out_at' => $checkedOutAt,
        ];
    }

    /**
     * @param  array<string,mixed>|null  $payload
     */
    public function publishSettlementCompleted(?array $payload): void
    {
        if ($payload === null || $payload === []) {
            return;
        }

        $this->realtimeService->publishBoardEvent(
            'reservation.settlement_completed',
            $payload,
            ['board', 'timeline']
        );
    }

    /**
     * @param  array<int,int>  $tableIds
     * @param  array<int,int>  $refundPaymentIds
     * @return array<string,mixed>
     */
    public function buildRefundCancelledPayload(
        Reservation $reservation,
        array $tableIds,
        array $refundPaymentIds,
        ?string $cancelReason
    ): array {
        $cancelledAt = $reservation->cancelled_at instanceof Carbon
            ? $reservation->cancelled_at->copy()->setTimezone('UTC')->toIso8601String()
            : null;

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'table_ids' => array_values(array_map('intval', $tableIds)),
            'refund_payment_ids' => array_values(array_map('intval', $refundPaymentIds)),
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'cancelled_at' => $cancelledAt,
            'cancel_reason' => $cancelReason !== null && trim($cancelReason) !== ''
                ? trim($cancelReason)
                : (string) ($reservation->cancel_reason ?? ''),
        ];
    }

    /**
     * @param  array<string,mixed>|null  $payload
     */
    public function publishRefundCancelled(?array $payload): void
    {
        if ($payload === null || $payload === []) {
            return;
        }

        $this->realtimeService->publishBoardEvent(
            'reservation.refund_cancelled',
            $payload,
            ['board', 'timeline']
        );
    }
}

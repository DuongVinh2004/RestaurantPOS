<?php

declare(strict_types=1);

namespace App\Modules\Reservations\Application\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Domain\Models\Payment;
use App\Modules\Reporting\Application\Services\StaffOperationalRealtimeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationDepositRealtimePublisher
{
    public function __construct(
        private readonly StaffOperationalRealtimeService $realtime,
    ) {}

    /**
     * @param  array<string,mixed>  $paymentSummary
     */
    public function publishDepositPaid(Reservation $reservation, Payment $payment, array $paymentSummary): void
    {
        $this->realtime->publishBoardEvent(
            'reservation.deposit_paid',
            $this->buildDepositPaidPayload($reservation, $payment, $paymentSummary),
            ['board', 'timeline']
        );
    }

    /**
     * @param  array<string,mixed>  $paymentSummary
     * @return array<string,mixed>
     */
    private function buildDepositPaidPayload(Reservation $reservation, Payment $payment, array $paymentSummary): array
    {
        $paidAt = $payment->paid_at instanceof Carbon
            ? $payment->paid_at->copy()->setTimezone('UTC')->toIso8601String()
            : null;

        return [
            'reservation_id' => (int) $reservation->reservation_id,
            'table_ids' => $reservation->relationLoaded('tables')
                ? $reservation->tables->pluck('table_id')->map(static fn ($id): int => (int) $id)->values()->all()
                : DB::table('reservation_tables')
                    ->where('reservation_id', $reservation->reservation_id)
                    ->orderBy('table_id')
                    ->pluck('table_id')
                    ->map(static fn ($id): int => (int) $id)
                    ->all(),
            'reservation_status' => (string) ($reservation->status?->value ?? $reservation->status),
            'payment_id' => (int) $payment->payment_id,
            'payment_status' => (string) ($payment->status?->value ?? $payment->status),
            'deposit_status' => (string) ($reservation->deposit_status?->value ?? $reservation->deposit_status ?? ''),
            'deposit_paid_amount' => round((float) ($paymentSummary['deposit_net_amount'] ?? $reservation->deposit_paid_amount ?? 0.0), 2),
            'deposit_outstanding_amount' => round(max(0.0, (float) ($reservation->deposit_required_amount ?? 0.0) - (float) ($paymentSummary['deposit_net_amount'] ?? $reservation->deposit_paid_amount ?? 0.0)), 2),
            'currency' => (string) ($payment->currency ?? $reservation->bill_currency ?? 'VND'),
            'paid_at' => $paidAt,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Billing\Application\UseCases\Synchronization\ReservationFinancialSyncService;
use App\Modules\Reservations\Domain\Models\Reservation;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class ReservationFinancialSyncServiceHardeningTest extends TestCase
{
    public function test_it_marks_terminal_deposit_as_forfeited_when_cancelled_or_no_show_without_refund(): void
    {
        $service = new ReservationFinancialSyncService;
        $reservation = new Reservation([
            'deposit_required_amount' => 100,
        ]);

        $service->syncDepositSnapshot($reservation, [
            'deposit_captured_amount' => 100.0,
            'deposit_refunded_amount' => 0.0,
            'deposit_net_amount' => 100.0,
            'over_refunded_amount' => 0.0,
            'has_over_refund' => 0.0,
        ], true);

        self::assertSame('Forfeited', $reservation->deposit_status?->value);
        self::assertEquals(100.0, (float) $reservation->deposit_paid_amount);
    }

    public function test_it_rejects_over_refunded_payment_state(): void
    {
        $this->expectException(ValidationException::class);

        $service = new ReservationFinancialSyncService;
        $reservation = new Reservation([
            'deposit_required_amount' => 100,
        ]);

        $service->syncDepositSnapshot($reservation, [
            'deposit_captured_amount' => 100.0,
            'deposit_refunded_amount' => 120.0,
            'deposit_net_amount' => 0.0,
            'over_refunded_amount' => 20.0,
            'has_over_refund' => 1.0,
        ], false);
    }
}

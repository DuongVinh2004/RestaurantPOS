<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Loyalty;

use App\Modules\Loyalty\Application\UseCases\Points\LoyaltyBalanceService;
use App\Modules\Reservations\Domain\Models\Reservation;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class LoyaltyBalanceServiceTest extends TestCase
{
    use BuildsBookingScenario;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_earn_basis_ignores_deposit_net_amount_when_final_payment_exists(): void
    {
        $service = new LoyaltyBalanceService($this->mockRuntimeSettings());
        $reservation = new Reservation;
        $reservation->final_bill_amount = 150000.0;

        $summary = [
            'deposit_net_amount' => 50000.0,
            'final_net_amount' => 100000.0,
            'net_paid_amount' => 150000.0,
        ];

        $this->assertSame(100000.0, $service->earnBasisForReservation($reservation, $summary));
        $this->assertSame(10, $service->desiredEarnPointsForReservation($reservation, $summary));
    }

    public function test_earn_basis_is_capped_by_final_bill_even_if_final_net_is_higher(): void
    {
        $service = new LoyaltyBalanceService($this->mockRuntimeSettings());
        $reservation = new Reservation;
        $reservation->final_bill_amount = 80000.0;

        $summary = [
            'deposit_net_amount' => 50000.0,
            'final_net_amount' => 100000.0,
            'net_paid_amount' => 150000.0,
        ];

        $this->assertSame(80000.0, $service->earnBasisForReservation($reservation, $summary));
        $this->assertSame(8, $service->desiredEarnPointsForReservation($reservation, $summary));
    }

    public function test_deposit_only_capture_does_not_create_earn_basis(): void
    {
        $service = new LoyaltyBalanceService($this->mockRuntimeSettings());
        $reservation = new Reservation;
        $reservation->final_bill_amount = 150000.0;

        $summary = [
            'deposit_net_amount' => 50000.0,
            'final_net_amount' => 0.0,
            'net_paid_amount' => 50000.0,
        ];

        $this->assertSame(0.0, $service->earnBasisForReservation($reservation, $summary));
        $this->assertSame(0, $service->desiredEarnPointsForReservation($reservation, $summary));
    }
}

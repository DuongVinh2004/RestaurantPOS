<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\CheckoutPayments\Application\Services\ReservationFinancialSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class ReservationFinancialSyncServiceTouchMutationFeatureTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_touch_financial_mutation_advances_row_version_even_when_updated_at_would_match_same_second(): void
    {
        $frozenNow = Carbon::create(2026, 3, 25, 0, 0, 0, 'UTC');
        Carbon::setTestNow($frozenNow);

        try {
            $reservationId = $this->createReservation([
                'updated_at' => $frozenNow,
                'row_version' => 1,
            ]);

            $reservation = Reservation::query()->findOrFail($reservationId);
            $beforeVersion = (int) $reservation->row_version;

            $service = new ReservationFinancialSyncService();
            $service->touchFinancialMutation($reservation, null);

            $reservation->refresh();

            self::assertSame($beforeVersion + 1, (int) $reservation->row_version);
            self::assertSame($frozenNow->toDateTimeString(), optional($reservation->updated_at)?->copy()->utc()->toDateTimeString());
        } finally {
            Carbon::setTestNow();
        }
    }
}

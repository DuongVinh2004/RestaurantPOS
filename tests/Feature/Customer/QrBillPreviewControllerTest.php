<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\BranchScheduling\Domain\Models\RestaurantTable;
use App\Modules\Reservations\Domain\Models\Reservation;
use App\Modules\Reservations\Domain\Models\ReservationTable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class QrBillPreviewControllerTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    public function test_it_returns_404_for_invalid_token(): void
    {
        $response = $this->getJson('/api/v1/qr/bill-preview/invalid-token');

        $response->assertStatus(404)
            ->assertSee('Invalid QR token');
    }

    public function test_it_returns_empty_state_for_table_without_active_reservation(): void
    {
        $token = Str::random(32);
        $table = new RestaurantTable([
            'table_code' => 'T1',
            'qr_payment_token' => $token,
            'branch_id' => 1,
            'status' => 'Available',
        ]);
        $table->save();

        $response = $this->getJson('/api/v1/qr/bill-preview/' . $token);

        $response->assertStatus(200)
            ->assertJsonPath('data.table.table_id', $table->table_id)
            ->assertJsonPath('data.reservation_id', null)
            ->assertJsonPath('meta.has_active_session', false);
    }

    public function test_it_returns_bill_preview_for_active_reservation(): void
    {
        $token = Str::random(32);
        $table = new RestaurantTable([
            'table_code' => 'T2',
            'qr_payment_token' => $token,
            'branch_id' => 1,
            'status' => 'Occupied',
        ]);
        $table->save();

        $reservation = new Reservation([
            'reservation_code' => 'RES-1',
            'branch_id' => 1,
            'status' => 'Confirmed',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
            'guest_count' => 2,
            'source' => 'Online',
            'deposit_status' => 'NotRequired',
            'deposit_intent_status' => 'None',
        ]);
        $reservation->save();

        $rt = new ReservationTable();
        $rt->reservation_id = $reservation->reservation_id;
        $rt->table_id = $table->table_id;
        $rt->save();

        $response = $this->getJson('/api/v1/qr/bill-preview/' . $token);

        $response->assertStatus(200)
            ->assertJsonPath('data.table.table_id', $table->table_id)
            ->assertJsonPath('data.reservation_id', $reservation->reservation_id)
            ->assertJsonPath('meta.has_active_session', true)
            ->assertJsonStructure([
                'data' => [
                    'table',
                    'reservation_id',
                    'active_order',
                    'bill_preview',
                ],
            ]);
    }
}

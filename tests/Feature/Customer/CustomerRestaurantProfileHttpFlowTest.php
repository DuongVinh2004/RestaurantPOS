<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerRestaurantProfileHttpFlowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guest_can_read_public_restaurant_profile_with_default_branch_hours(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:15:00', 'Asia/Ho_Chi_Minh'));

        $businessHours = collect(range(0, 6))
            ->map(static fn (int $day): array => [
                'day_of_week' => $day,
                'periods' => [[
                    'start_time' => '09:00',
                    'end_time' => '22:00',
                ]],
            ])
            ->all();

        DB::table('branches')->updateOrInsert(
            ['branch_code' => 'MAIN'],
            [
                'branch_name' => 'RestaurantPOS',
                'description' => 'Customer visible default branch',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'business_hours' => json_encode($businessHours, JSON_THROW_ON_ERROR),
                'closure_windows' => json_encode([], JSON_THROW_ON_ERROR),
                'booking_policy' => null,
                'is_active' => true,
                'is_default' => true,
                'row_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $response = $this->getJson('/api/v1/restaurant/profile');

        $response->assertOk()
            ->assertJsonPath('meta.action', 'restaurant_profile_show')
            ->assertJsonPath('data.branch_name', 'RestaurantPOS')
            ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('data.today_hours.day_of_week', 1)
            ->assertJsonPath('data.today_hours.periods.0.start_time', '09:00')
            ->assertJsonPath('data.today_hours.periods.0.end_time', '22:00')
            ->assertJsonPath('data.today_hours.is_closed', false)
            ->assertJsonPath('data.current_status.is_open', true)
            ->assertJsonPath('data.current_status.timezone', 'Asia/Ho_Chi_Minh');
    }
    public function test_guest_can_read_public_restaurant_branches(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-27 10:15:00', 'Asia/Ho_Chi_Minh'));

        $businessHours = collect(range(0, 6))
            ->map(static fn (int $day): array => [
                'day_of_week' => $day,
                'periods' => [[
                    'start_time' => '09:00',
                    'end_time' => '22:00',
                ]],
            ])
            ->all();

        DB::table('branches')->updateOrInsert(
            ['branch_code' => 'B2'],
            [
                'branch_name' => 'Branch 2',
                'description' => 'Branch 2 description',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'currency' => 'VND',
                'business_hours' => json_encode($businessHours, JSON_THROW_ON_ERROR),
                'closure_windows' => json_encode([], JSON_THROW_ON_ERROR),
                'booking_policy' => null,
                'is_active' => true,
                'is_default' => false,
                'row_version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $response = $this->getJson('/api/v1/restaurant/branches');

        $response->assertOk()
            ->assertJsonPath('meta.action', 'restaurant_branch_index')
            ->assertJsonIsArray('data');
    }
}

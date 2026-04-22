<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class AdminMasterDataValidationEnvelopeAndPriceWindowTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_role_name_fallback', true);
        config()->set('staff_auth.allowed_role_ids', []);
        config()->set('staff_auth.allowed_role_names', ['Admin', 'Staff']);
    }

    public function test_future_admin_price_row_closes_previous_open_ended_price(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        config()->set('staff_auth.api_keys', ['admin-price-window-key' => $adminId]);

        $itemId = $this->createMenuItem([
            'name' => 'Windowed Pasta',
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_quota_per_day' => 5,
            'preorder_cutoff_minutes' => 15,
        ]);

        $activeFrom = Carbon::now('UTC')->subDay()->startOfMinute();
        $futureFrom = Carbon::now('UTC')->addDay()->startOfMinute();

        $activePriceId = $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '165000.00',
            'currency' => 'VND',
            'effective_from' => $activeFrom,
            'effective_to' => null,
        ]);

        $this->withHeaders($this->staffHeaders('admin-price-window-key'))
            ->postJson(sprintf('/api/v1/admin/menu/items/%d/prices', $itemId), [
                'price' => '175000.00',
                'currency' => 'VND',
                'effective_from' => $futureFrom->toIso8601String(),
            ])
            ->assertCreated();

        $closedAt = DB::table('menu_item_prices')->where('price_id', $activePriceId)->value('effective_to');

        self::assertNotNull($closedAt);
        self::assertSame(
            $futureFrom->toIso8601String(),
            Carbon::parse((string) $closedAt, 'UTC')->toIso8601String(),
        );
    }

    public function test_admin_master_data_validation_errors_are_exposed_at_root_and_details_levels(): void
    {
        $adminId = $this->createUser(['role_name' => 'Admin']);
        config()->set('staff_auth.api_keys', ['admin-validation-envelope-key' => $adminId]);

        $itemId = $this->createMenuItem([
            'name' => 'Envelope Soup',
            'is_available' => 1,
            'is_preorder_enabled' => 1,
            'preorder_quota_per_day' => 8,
            'preorder_cutoff_minutes' => 30,
        ]);

        $start = Carbon::now('UTC')->subDay()->startOfMinute();
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '99000.00',
            'currency' => 'VND',
            'effective_from' => $start,
            'effective_to' => null,
        ]);

        $response = $this->withHeaders($this->staffHeaders('admin-validation-envelope-key'))
            ->postJson(sprintf('/api/v1/admin/menu/items/%d/prices', $itemId), [
                'price' => '109000.00',
                'currency' => 'VND',
                'effective_from' => $start->copy()->addHour()->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_error')
            ->assertJsonPath('errors.effective_from.0', 'Price range overlaps another price for the same item.')
            ->assertJsonPath('details.errors.effective_from.0', 'Price range overlaps another price for the same item.');
    }

    /**
     * @return array<string,string>
     */
    private function staffHeaders(string $staffKey): array
    {
        return [
            'X-Staff-Key' => $staffKey,
            'Accept' => 'application/json',
            'Idempotency-Key' => 'idem-'.$staffKey.'-'.bin2hex(random_bytes(6)),
        ];
    }
}

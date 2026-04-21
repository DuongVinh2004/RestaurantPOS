<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\IdentityAccess\Domain\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class CustomerSelfServiceErrorEnvelopeContractTest extends TestCase
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
        config()->set('staff_auth.api_keys', []);
    }

    public function test_staff_misuse_of_customer_benefits_endpoint_returns_standardized_error_envelope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_auth.api_keys', ['customer-benefits-envelope-staff-key' => $staffId]);

        $response = $this->withHeaders([
            'X-Staff-Key' => 'customer-benefits-envelope-staff-key',
            'X-Request-Id' => 'req-customer-benefits-staff',
        ])->getJson('/api/v1/me/loyalty');

        $response->assertStatus(403)
            ->assertHeader('X-Request-Id', 'req-customer-benefits-staff')
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('category_code', 'policy_denied')
            ->assertJsonPath('message', 'Staff must use dedicated staff loyalty and voucher endpoints for operational actions.')
            ->assertJsonPath('request_id', 'req-customer-benefits-staff');
    }

    public function test_staff_misuse_of_customer_privacy_endpoint_returns_standardized_error_envelope(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        config()->set('staff_auth.api_keys', ['customer-privacy-envelope-staff-key' => $staffId]);

        $response = $this->withHeaders([
            'X-Staff-Key' => 'customer-privacy-envelope-staff-key',
            'X-Request-Id' => 'req-customer-privacy-staff',
        ])->getJson('/api/v1/me/data-export');

        $response->assertStatus(403)
            ->assertHeader('X-Request-Id', 'req-customer-privacy-staff')
            ->assertJsonPath('error_code', 'forbidden')
            ->assertJsonPath('category_code', 'policy_denied')
            ->assertJsonPath('message', 'Staff actors must use dedicated admin privacy endpoints.')
            ->assertJsonPath('request_id', 'req-customer-privacy-staff');
    }

    public function test_customer_benefits_mutation_not_found_returns_standardized_error_envelope(): void
    {
        $user = $this->createCustomerUser();
        $voucherId = $this->createVoucher([
            'code' => 'ERR-404-BENEFITS',
            'discount_type' => 'Fixed',
            'discount_value' => '50000.00',
            'min_spend' => '100000.00',
        ]);
        $userVoucherId = $this->assignVoucher([
            'user_id' => (int) $user->user_id,
            'voucher_id' => $voucherId,
            'is_used' => 0,
        ]);

        $response = $this->actingAs($user)
            ->withHeaders([
                'Idempotency-Key' => 'customer-benefits-envelope-not-found',
                'X-Request-Id' => 'req-customer-benefits-404',
            ])
            ->postJson('/api/v1/reservations/999999/voucher/apply', [
                'user_voucher_id' => $userVoucherId,
                'row_version' => 1,
            ]);

        $response->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-customer-benefits-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('category_code', 'not_found')
            ->assertJsonPath('message', 'Reservation data was not found.')
            ->assertJsonPath('request_id', 'req-customer-benefits-404');
    }

    public function test_customer_preorder_not_found_returns_standardized_error_envelope(): void
    {
        $user = $this->createCustomerUser();

        $response = $this->actingAs($user)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Request-Id' => 'req-customer-preorder-404',
            ])
            ->getJson('/api/v1/reservations/999999/pre-order');

        $response->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-customer-preorder-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('category_code', 'not_found')
            ->assertJsonPath('message', 'Reservation data was not found.')
            ->assertJsonPath('request_id', 'req-customer-preorder-404');
    }

    public function test_customer_menu_not_found_returns_standardized_error_envelope(): void
    {
        $serviceTime = $this->nowUtc()->copy()->addHours(3);
        $categoryId = $this->ensureMenuCategory('Envelope Hidden Items');
        $itemId = $this->createMenuItem([
            'category_id' => $categoryId,
            'name' => 'Envelope Hidden Dish',
            'code' => 'ENV-HIDDEN-01',
            'is_available' => 0,
            'is_preorder_enabled' => 1,
            'preorder_cutoff_minutes' => 30,
        ]);
        $this->createMenuItemPrice([
            'item_id' => $itemId,
            'price' => '150000.00',
            'currency' => 'VND',
            'effective_from' => $serviceTime->copy()->subDay(),
        ]);

        $response = $this->withHeaders([
            'X-Request-Id' => 'req-customer-menu-404',
        ])->getJson('/api/v1/menu/items/'.$itemId.'?service_time='.urlencode($serviceTime->toIso8601String()));

        $response->assertNotFound()
            ->assertHeader('X-Request-Id', 'req-customer-menu-404')
            ->assertJsonPath('error_code', 'not_found')
            ->assertJsonPath('category_code', 'not_found')
            ->assertJsonPath('message', 'Menu item is not available for the selected service time.')
            ->assertJsonPath('request_id', 'req-customer-menu-404');
    }

    private function createCustomerUser(): User
    {
        $userId = $this->createUser(['role_name' => 'Customer']);

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        return $user;
    }
}

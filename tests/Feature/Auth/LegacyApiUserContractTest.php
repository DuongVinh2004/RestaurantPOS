<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class LegacyApiUserContractTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('customer_auth.enabled', true);
        config()->set('customer_auth.header', 'X-Customer-Token');
        config()->set('customer_auth.allow_bearer', false);
        config()->set('customer_auth.allow_legacy_user_auth_tokens', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', true);
    }

    public function test_customer_access_token_gets_runtime_top_level_user_payload(): void
    {
        $customerId = $this->createUser([
            'role_name' => 'Customer',
            'full_name' => 'Contract Customer',
            'email' => 'contract.customer@example.test',
            'phone' => '0900001000',
        ]);

        $response = $this->withHeaders($this->customerAuthHeaders($customerId))
            ->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('auth_mode', 'customer')
            ->assertJsonPath('user.user_id', $customerId)
            ->assertJsonPath('user.full_name', 'Contract Customer')
            ->assertJsonPath('user.email', 'contract.customer@example.test')
            ->assertJsonPath('user.phone', '0900001000')
            ->assertJsonMissingPath('data');
    }

    public function test_staff_api_key_gets_runtime_top_level_user_payload(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);

        $response = $this->withHeaders($this->staffAuthHeaders($staffId, 'legacy-api-user-staff-key'))
            ->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('auth_mode', 'staff')
            ->assertJsonPath('user.user_id', $staffId)
            ->assertJsonPath('user.role_name', 'Staff')
            ->assertJsonPath('user.staff_auth_mode', 'mapped_key_fallback')
            ->assertJsonMissingPath('data');
    }

    public function test_session_only_customer_context_is_not_allowed_for_api_user(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Session-Id' => 'sess-api-user-session-only',
        ])->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('category_code', 'authentication_required');
    }
}

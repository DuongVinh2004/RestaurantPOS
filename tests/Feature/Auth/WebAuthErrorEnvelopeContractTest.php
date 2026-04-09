<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\TestCase;

final class WebAuthErrorEnvelopeContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.allow_env_fallback', false);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', false);
        config()->set('staff_auth.api_keys', []);
        config()->set('staff_auth.legacy_key', '');
        config()->set('staff_auth.legacy_user_id', 0);
    }

    public function test_staff_auth_middleware_returns_standardized_error_envelope_with_request_id(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'invalid-staff-key',
            'X-Request-Id' => 'req-staff-auth-envelope',
        ])->getJson('/api/v1/auth/staff/me');

        $response->assertStatus(401)
            ->assertHeader('X-Request-Id', 'req-staff-auth-envelope')
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonPath('request_id', 'req-staff-auth-envelope');
    }

    public function test_customer_or_staff_middleware_returns_standardized_error_envelope_with_request_id(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'invalid-staff-key',
            'X-Request-Id' => 'req-customer-or-staff-envelope',
        ])->getJson('/api/user');

        $response->assertStatus(401)
            ->assertHeader('X-Request-Id', 'req-customer-or-staff-envelope')
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonPath('request_id', 'req-customer-or-staff-envelope');
    }
}

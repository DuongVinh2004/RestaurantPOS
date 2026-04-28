<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

final class StaffApiKeyProductionGuardTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireBookingSchema();
        config()->set('booking.require_redis_for_booking_api', false);
        config()->set('staff_auth.database_store_enabled', false);
        config()->set('staff_auth.api_keys', []);
    }

    public function test_missing_staff_api_key_is_rejected_before_staff_capability_checks(): void
    {
        $this->getJson('/api/v1/staff/branches')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('category_code', 'authentication_required')
            ->assertJsonMissingPath('required_capability');

        $this->postJson('/api/v1/staff/cashier/shifts/open', [
            'opening_float_amount' => 0,
            'currency' => 'VND',
        ], $this->withIdempotencyKey('staff-api-key-missing-shift-open'))
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonMissingPath('required_capability');

        $this->getJson('/api/v1/admin/settings/branches')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonMissingPath('required_capability');
    }

    public function test_production_like_config_disallows_env_fallback_staff_api_key(): void
    {
        $staffId = $this->createUser(['role_name' => 'Admin']);

        config()->set('app.env', 'production');
        config()->set('staff_auth.database_store_enabled', true);
        config()->set('staff_auth.allow_env_fallback', true);
        config()->set('staff_auth.allow_env_fallback_when_database_store_unavailable', true);
        config()->set('staff_auth.api_keys', ['prod-like-fallback-key' => $staffId]);
        config()->set('staff_auth.env_fallback_allowed_environments', ['production']);
        config()->set('staff_auth.production_like_environments', ['production']);
        config()->set('staff_auth.deny_env_fallback_in_production_like', true);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Staff-Key' => 'prod-like-fallback-key',
        ])->getJson('/api/v1/staff/branches')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'unauthorized')
            ->assertJsonPath('category_code', 'authentication_required');
    }
}

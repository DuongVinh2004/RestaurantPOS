<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class CustomerAuthConfigContractTest extends TestCase
{
    public function test_customer_auth_config_declares_foundation_keys_for_dedicated_access_sessions(): void
    {
        $this->assertSame(
            ['local', 'testing'],
            config('customer_auth.legacy_user_auth_tokens_allowed_environments')
        );
        $this->assertSame('customer_access_sessions', config('customer_auth.access_session_table'));
        $this->assertGreaterThan(0, (int) config('customer_auth.access_session_ttl_minutes'));
        $this->assertGreaterThan(0, (int) config('customer_auth.login_throttle_limit'));
        $this->assertGreaterThan(0, (int) config('customer_auth.login_throttle_window_seconds'));
        $this->assertGreaterThan(0, (int) config('customer_auth.password_reset_ttl_minutes'));
        $this->assertIsBool(config('customer_auth.touch_last_used_at'));
        $this->assertSame([3], array_values(config('customer_auth.allowed_role_ids')));

        $contracts = (array) config('customer_auth.session_bound_route_contracts', []);
        $this->assertArrayHasKey('App\Modules\Reservations\Http\Controllers\Customer\ReservationController@store', $contracts);
        $this->assertTrue((bool) ($contracts['App\Modules\Reservations\Http\Controllers\Customer\ReservationController@store']['require_owned_hold'] ?? false));
        $this->assertArrayHasKey('App\Modules\Reservations\Http\Controllers\Customer\ReservationSelfServiceController@index', $contracts);
        $this->assertArrayHasKey('App\Modules\Payments\Http\Controllers\Customer\ReservationBillPaymentController@confirm', $contracts);
    }

    public function test_customer_auth_session_bound_route_contracts_have_no_raw_duplicate_keys(): void
    {
        $source = (string) file_get_contents(config_path('customer_auth.php'));

        preg_match_all("/^\\s*'([^']+@[^']+)'\\s*=>/m", $source, $matches);

        $keys = $matches[1] ?? [];
        $this->assertNotEmpty($keys, 'Expected raw route contract keys in config/customer_auth.php.');

        $duplicates = array_keys(array_filter(
            array_count_values($keys),
            static fn (int $count): bool => $count > 1,
        ));

        $this->assertSame([], $duplicates, 'Duplicate raw customer_auth route contract key(s): '.implode(', ', $duplicates));
    }
}

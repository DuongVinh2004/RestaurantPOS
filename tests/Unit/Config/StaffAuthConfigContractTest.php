<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class StaffAuthConfigContractTest extends TestCase
{
    public function test_staff_auth_config_declares_product_login_session_controls(): void
    {
        $this->assertGreaterThan(0, (int) config('staff_auth.session_ttl_minutes'));
        $this->assertGreaterThan(0, (int) config('staff_auth.login_throttle_limit'));
        $this->assertGreaterThan(0, (int) config('staff_auth.login_throttle_window_seconds'));
        $this->assertSame([1, 2], array_values(config('staff_auth.allowed_role_ids')));
    }
}

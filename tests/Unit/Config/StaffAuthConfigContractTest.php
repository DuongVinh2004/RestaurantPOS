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
        $this->assertSame([1, 2, 4, 5, 6, 7, 8], array_values(config('staff_auth.allowed_role_ids')));
        $this->assertSame(['local', 'testing'], array_values(config('staff_auth.env_fallback_allowed_environments')));
        $this->assertTrue((bool) config('staff_auth.deny_env_fallback_in_production_like'));
        $this->assertTrue((bool) config('staff_auth.deny_role_name_fallback_in_production_like'));
        $this->assertContains('production', array_values(config('staff_auth.production_like_environments')));
        $this->assertFalse((bool) config('staff_auth.browser_session.enabled'));
        $this->assertGreaterThan(0, (int) config('staff_auth.browser_session.access_ttl_minutes'));
        $this->assertSame('staff_web_refresh', config('staff_auth.browser_session.refresh_cookie_name'));
        $this->assertSame('/api/v1/auth/staff', config('staff_auth.browser_session.refresh_cookie_path'));
        $this->assertSame('staff_web_csrf', config('staff_auth.browser_session.csrf_cookie_name'));
        $this->assertSame('X-Staff-CSRF', config('staff_auth.browser_session.csrf_header'));
        $this->assertSame('lax', config('staff_auth.browser_session.same_site'));
        $this->assertTrue((bool) config('staff_auth.browser_session.secure'));
    }
}

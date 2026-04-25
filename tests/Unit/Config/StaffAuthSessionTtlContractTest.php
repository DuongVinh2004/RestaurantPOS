<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class StaffAuthSessionTtlContractTest extends TestCase
{
    public function test_staff_auth_default_session_ttl_is_short_lived_for_browser_staff_web(): void
    {
        $this->assertGreaterThan(0, (int) config('staff_auth.session_ttl_minutes'));
        $this->assertLessThanOrEqual(30, (int) config('staff_auth.session_ttl_minutes'));
    }
}

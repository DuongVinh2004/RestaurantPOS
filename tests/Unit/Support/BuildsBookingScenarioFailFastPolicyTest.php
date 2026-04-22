<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class BuildsBookingScenarioFailFastPolicyTest extends TestCase
{
    public function test_fail_fast_on_missing_schema_defaults_to_enabled(): void
    {
        $probe = new class
        {
            use BuildsBookingScenario;

            public function policy(): bool
            {
                return $this->shouldFailFastOnMissingBookingSchema();
            }
        };

        $this->assertTrue($probe->policy());
    }

    public function test_fail_fast_on_missing_schema_policy_can_be_explicitly_disabled(): void
    {
        config()->set('booking.testing.fail_fast_on_missing_schema', false);

        $probe = new class
        {
            use BuildsBookingScenario;

            public function policy(): bool
            {
                return $this->shouldFailFastOnMissingBookingSchema();
            }
        };

        $this->assertFalse($probe->policy());
    }
}

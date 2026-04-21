<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class StaffCapabilitiesConfigContractTest extends TestCase
{
    public function test_staff_capabilities_config_exposes_known_capability_contract(): void
    {
        $knownCapabilities = config('staff_capabilities.known_capabilities');
        $routeCapabilities = config('staff_capabilities.route_capabilities');

        $this->assertTrue(config('staff_capabilities.enforce_known_capabilities'));
        $this->assertIsArray($knownCapabilities);
        $this->assertIsArray(config('staff_capabilities.role_id_capabilities'));
        $this->assertIsArray(config('staff_capabilities.role_capabilities'));
        $this->assertIsArray($routeCapabilities);
        $this->assertContains('reservation.manage', $knownCapabilities);
        $this->assertContains('menu.manage', $knownCapabilities);
        $this->assertContains('voucher.master_data.manage', $knownCapabilities);

        foreach ($routeCapabilities as $surface => $capability) {
            $this->assertIsString($surface);
            $this->assertContains($capability, $knownCapabilities);
        }
    }
}

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
        $roleBranchScopes = config('staff_capabilities.role_branch_scopes');

        $this->assertTrue(config('staff_capabilities.enforce_known_capabilities'));
        $this->assertIsArray($knownCapabilities);
        $this->assertIsArray(config('staff_capabilities.role_id_capabilities'));
        $this->assertIsArray(config('staff_capabilities.role_capabilities'));
        $this->assertSame([], array_values((array) config('staff_capabilities.fallback_branch_scopes', [])));
        $this->assertTrue((bool) config('staff_capabilities.deny_operational_role_branch_fallback_in_production_like'));
        $this->assertContains('production', array_values((array) config('staff_capabilities.production_like_environments')));
        $this->assertSame(
            ['Staff', 'Server', 'Waiter', 'Cashier', 'Kitchen'],
            array_values((array) config('staff_capabilities.operational_branch_assignment_roles')),
        );
        $this->assertIsArray(config('staff_capabilities.role_id_branch_scopes'));
        $this->assertIsArray($roleBranchScopes);
        $this->assertSame(['*'], array_values((array) ($roleBranchScopes['Admin'] ?? [])));
        $this->assertSame(['default'], array_values((array) ($roleBranchScopes['Staff'] ?? [])));
        $this->assertSame(['default'], array_values((array) ($roleBranchScopes['Cashier'] ?? [])));
        $this->assertSame(['default'], array_values((array) ($roleBranchScopes['Kitchen'] ?? [])));
        $this->assertIsArray($routeCapabilities);
        $this->assertContains('reservation.manage', $knownCapabilities);
        $this->assertContains('menu.manage', $knownCapabilities);
        $this->assertContains('voucher.master_data.manage', $knownCapabilities);
        $this->assertContains('settlement.manage', config('staff_capabilities.role_capabilities.Cashier'));
        $this->assertContains('cashier.shift.manage', config('staff_capabilities.role_capabilities.Cashier'));
        $this->assertSame(['kitchen.manage'], array_values((array) config('staff_capabilities.role_capabilities.Kitchen')));
        $this->assertNotContains('kitchen.manage', config('staff_capabilities.role_capabilities.Staff'));
        $this->assertContains('cashier.shift.manage', config('staff_capabilities.role_capabilities.Staff'));
        $this->assertContains('settlement.manage', config('staff_capabilities.role_capabilities.Staff'));
        $this->assertNotContains('payment.refund', config('staff_capabilities.role_capabilities.Staff'));
        $this->assertNotContains('reporting.view', config('staff_capabilities.role_capabilities.Staff'));
        $this->assertNotContains('audit.view', config('staff_capabilities.role_capabilities.Staff'));
        $this->assertNotContains('settings.manage', config('staff_capabilities.role_capabilities.Staff'));
        $this->assertArrayNotHasKey('order.manage', config('staff_capabilities.capability_aliases'));
        $this->assertArrayNotHasKey('settlement.manage', config('staff_capabilities.capability_aliases'));

        foreach ($routeCapabilities as $surface => $capability) {
            $this->assertIsString($surface);
            $this->assertContains($capability, $knownCapabilities);
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\IdentityAccess\Application\Queries\StaffCapabilityResolver;
use Tests\TestCase;

class StaffCapabilityResolverTest extends TestCase
{
    public function test_it_prefers_role_id_capabilities_over_role_name_fallback(): void
    {
        config()->set('staff_capabilities.known_capabilities', ['reservation.manage', 'payment.refund']);
        config()->set('staff_capabilities.role_id_capabilities', [
            2 => ['reservation.manage'],
        ]);
        config()->set('staff_capabilities.role_capabilities', [
            'Staff' => ['payment.refund'],
        ]);

        $resolved = app(StaffCapabilityResolver::class)->resolveForActor(2, 'Staff');

        $this->assertSame(['reservation.manage'], $resolved['capabilities']);
        $this->assertSame('role_id_capabilities', $resolved['source']);
    }


    public function test_it_expands_configured_legacy_capability_aliases(): void
    {
        config()->set('staff_capabilities.known_capabilities', ['cashier.shift.manage', 'reporting.view']);
        config()->set('staff_capabilities.capability_aliases', [
            'settlement.manage' => ['cashier.shift.manage', 'reporting.view'],
        ]);
        config()->set('staff_capabilities.role_id_capabilities', [
            7 => ['settlement.manage'],
        ]);
        config()->set('staff_capabilities.role_capabilities', []);

        $resolved = app(StaffCapabilityResolver::class)->resolveForActor(7, 'Staff');

        $this->assertSame(['cashier.shift.manage', 'reporting.view', 'settlement.manage'], $resolved['capabilities']);
        $this->assertSame(['cashier.shift.manage', 'reporting.view'], $resolved['known_capabilities']);
        $this->assertSame('role_id_capabilities', $resolved['source']);
    }

    public function test_it_returns_deny_by_default_when_actor_has_no_capability_mapping(): void
    {
        config()->set('staff_capabilities.known_capabilities', ['reservation.manage']);
        config()->set('staff_capabilities.role_id_capabilities', []);
        config()->set('staff_capabilities.role_capabilities', []);

        $resolved = app(StaffCapabilityResolver::class)->resolveForActor(99, 'Unknown');

        $this->assertSame([], $resolved['capabilities']);
        $this->assertSame('deny_by_default', $resolved['source']);
    }
}

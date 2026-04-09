<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Tests\TestCase;

final class StaffFinanceReportingIdempotencyPolicyTest extends TestCase
{
    public function test_booking_config_declares_staff_finance_reporting_and_kitchen_idempotency_scopes(): void
    {
        $scopes = config('booking.idempotency_required_scopes', []);

        $this->assertIsArray($scopes);
        $this->assertContains('staff.cashier-shift.open', $scopes);
        $this->assertContains('staff.cashier-shift.close', $scopes);
        $this->assertContains('staff.finance-invoice.issue', $scopes);
        $this->assertContains('staff.order-item.update', $scopes);
        $this->assertContains('staff.order-item.status', $scopes);
        $this->assertContains('staff.kitchen.dispatch', $scopes);
        $this->assertContains('staff.kitchen.fire', $scopes);
        $this->assertContains('staff.kitchen.bump', $scopes);
        $this->assertContains('staff.kitchen.recall', $scopes);
        $this->assertContains('staff.conversation-assign', $scopes);
        $this->assertContains('staff.conversation-take-over', $scopes);
        $this->assertContains('staff.conversation-unassign', $scopes);
        $this->assertContains('staff.conversation-link', $scopes);
        $this->assertContains('staff.conversation-unlink-reservation', $scopes);
        $this->assertContains('staff.conversation-unlink-waiting-list', $scopes);
        $this->assertContains('staff.conversation-internal-note', $scopes);
        $this->assertContains('admin.reporting-snapshots.rebuild', $scopes);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class BookingReportingAndMultiBranchConfigContractTest extends TestCase
{
    public function test_booking_config_declares_reporting_and_multi_branch_defaults(): void
    {
        $this->assertSame(25, (int) config('booking.reporting_page_default'));
        $this->assertSame(100, (int) config('booking.reporting_page_max'));
        $this->assertSame(90, (int) config('booking.reporting_snapshot_rebuild_max_days'));
        $this->assertGreaterThanOrEqual(
            (int) config('booking.reporting_page_default'),
            (int) config('booking.reporting_page_max')
        );

        $this->assertSame('MAIN', (string) config('booking.multi_branch.default_branch_code'));
        $this->assertSame('Main Branch', (string) config('booking.multi_branch.default_branch_name'));
        $this->assertNotSame('', trim((string) config('booking.multi_branch.default_branch_timezone')));
        $this->assertSame('VND', (string) config('booking.multi_branch.default_branch_currency'));
        $this->assertIsArray(config('booking.branch_policy_defaults.business_hours'));
        $this->assertIsArray(config('booking.branch_policy_defaults.closure_windows'));
        $this->assertIsArray(config('booking.branch_policy_defaults.booking_policy'));
    }

    public function test_booking_config_declares_customer_admin_and_realtime_defaults(): void
    {
        $this->assertSame(20, (int) config('booking.customer_menu_page_default'));
        $this->assertSame(100, (int) config('booking.customer_menu_page_max'));
        $this->assertSame(10, (int) config('booking.customer_reservation_self_service_page_default'));
        $this->assertSame(20, (int) config('booking.customer_reservation_self_service_page_max'));
        $this->assertSame(20, (int) config('booking.customer_waiting_list_page_default'));
        $this->assertSame(100, (int) config('booking.customer_waiting_list_page_max'));
        $this->assertSame(25, (int) config('booking.admin_inventory_page_default'));
        $this->assertSame(100, (int) config('booking.admin_inventory_page_max'));
        $this->assertTrue((bool) config('booking.realtime.enabled'));
        $this->assertSame('file', config('booking.realtime.cache_store'));
        $this->assertSame(2, (int) config('booking.staff_table_board_close_fit_max_extra_seats'));
        $this->assertSame(5, (int) config('booking.staff_table_board_candidate_preview_limit'));
    }
}

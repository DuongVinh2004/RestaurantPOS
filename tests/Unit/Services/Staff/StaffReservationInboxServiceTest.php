<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Staff;

use App\Services\Staff\StaffReservationInboxService;
use Tests\TestCase;

class StaffReservationInboxServiceTest extends TestCase
{
    public function test_apply_common_filters_omits_guest_snapshot_columns_when_runtime_schema_lags(): void
    {
        $service = new class extends StaffReservationInboxService
        {
            protected function supportsGuestSnapshotColumns(): bool
            {
                return false;
            }
        };

        $query = $service->newQuery();
        $service->applyCommonFilters($query, [
            'phone' => '0901123456',
            'q' => 'UAT-DINE-001',
        ]);

        $sql = $query->toSql();

        $this->assertStringNotContainsString('guest_name', $sql);
        $this->assertStringNotContainsString('guest_phone', $sql);
        $this->assertStringNotContainsString('guest_email', $sql);
        $this->assertStringContainsString('reservation_code', $sql);
        $this->assertStringContainsString('notes', $sql);
    }

    public function test_apply_common_filters_keeps_guest_snapshot_search_when_schema_support_exists(): void
    {
        $service = new class extends StaffReservationInboxService
        {
            protected function supportsGuestSnapshotColumns(): bool
            {
                return true;
            }
        };

        $query = $service->newQuery();
        $service->applyCommonFilters($query, [
            'phone' => '0901123456',
            'q' => 'guest@example.test',
        ]);

        $sql = $query->toSql();

        $this->assertStringContainsString('guest_name', $sql);
        $this->assertStringContainsString('guest_phone', $sql);
        $this->assertStringContainsString('guest_email', $sql);
    }
}

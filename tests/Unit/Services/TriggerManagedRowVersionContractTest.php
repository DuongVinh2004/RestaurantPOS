<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;

class TriggerManagedRowVersionContractTest extends TestCase
{
    public function test_services_do_not_manually_increment_row_version_for_trigger_managed_tables(): void
    {
        $paths = [
            app_path('Modules/BranchScheduling/Application/Services/TableHoldService.php'),
            app_path('Modules/Reservations/Application/Services/ReservationCancellationService.php'),
            app_path('Modules/BranchScheduling/Application/Services/RestaurantTableStateService.php'),
            app_path('Modules/Reservations/Application/Services/ReservationService.php'),
            app_path('Modules/Waitlist/Application/Services/StaffWaitingListService.php'),
            app_path('Modules/Cashiering/Application/Workflows/OrderSettlementWorkflow.php'),
        ];

        foreach ($paths as $path) {
            $this->assertStringNotContainsString("DB::raw('row_version + 1')", (string) file_get_contents($path), sprintf('Manual row_version increment still present in %s', $path));
        }
    }
}

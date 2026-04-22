<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use App\Modules\BranchScheduling\Application\Services\RestaurantTableStateService;
use App\Modules\FloorOperations\Application\UseCases\Boards\StaffTableReleaseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Support\BuildsBookingScenario;
use Tests\TestCase;

class StaffTableReleaseServiceTest extends TestCase
{
    use BuildsBookingScenario;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBookingSchema();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_release_allows_future_confirmed_reservation_without_active_in_service_state(): void
    {
        $staffId = $this->createUser(['role_name' => 'Staff']);
        $annexBranchId = $this->createBranch([
            'branch_code' => 'ANNEXSRV',
            'branch_name' => 'Annex Service',
        ]);
        $tableId = $this->createRestaurantTable([
            'status' => 'Occupied',
            'branch_id' => $annexBranchId,
        ]);
        $reservationId = $this->createReservation([
            'branch_id' => $annexBranchId,
            'status' => 'Confirmed',
            'checked_in_at' => null,
        ]);
        $this->attachReservationTable($reservationId, $tableId);
        config()->set('staff_capabilities.role_branch_scopes.Staff', ['default', (string) $annexBranchId]);

        $locks = $this->mockReservationLocks();
        $tableStateService = Mockery::mock(RestaurantTableStateService::class);
        $tableStateService->shouldReceive('isOperationallyBlocked')->once()->andReturn(false);
        $tableStateService->shouldReceive('releaseModelSafely')->once()->andReturnUsing(function ($table) {
            $table->status = 'Available';

            return $table;
        });

        $service = new StaffTableReleaseService($locks, $tableStateService);
        $released = $service->release($tableId, $staffId, false, 'release table', 1);

        $this->assertSame('Available', (string) ($released->status->value ?? $released->status));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class SecondaryDomainRouteControllerOwnershipTest extends TestCase
{
    public function test_batch_six_routes_resolve_to_canonical_module_controllers(): void
    {
        $expectations = [
            'api/v1/waiting-list' => [
                'GET' => \App\Modules\WaitingList\Http\Controllers\Customer\CustomerWaitingListController::class . '@index',
                'POST' => \App\Modules\WaitingList\Http\Controllers\Customer\CustomerWaitingListController::class . '@store',
            ],
            'api/v1/staff/waiting-list' => [
                'GET' => \App\Modules\WaitingList\Http\Controllers\Staff\StaffWaitingListController::class . '@index',
                'POST' => \App\Modules\WaitingList\Http\Controllers\Staff\StaffWaitingListController::class . '@store',
            ],
            'api/v1/staff/conversations' => [
                'GET' => \App\Modules\Conversations\Http\Controllers\Staff\StaffConversationInboxController::class . '@index',
            ],
            'api/v1/staff/reservations' => [
                'GET' => \App\Modules\Conversations\Http\Controllers\Staff\StaffReservationInboxController::class . '@index',
            ],
            'api/v1/staff/audit-trail' => [
                'GET' => \App\Modules\PrivacyAudit\Http\Controllers\Staff\StaffAuditTrailController::class . '@index',
            ],
            'api/v1/staff/reporting/daily-sales' => [
                'GET' => \App\Modules\Reporting\Http\Controllers\Staff\StaffReportingController::class . '@dailySales',
            ],
            'api/v1/staff/tables/board/changes' => [
                'GET' => \App\Modules\Reporting\Http\Controllers\Staff\StaffOperationalRealtimeController::class . '@boardChanges',
            ],
            'api/v1/admin/privacy/requests' => [
                'GET' => \App\Modules\PrivacyAudit\Http\Controllers\Admin\AdminCustomerDataLifecycleController::class . '@index',
            ],
            'api/v1/admin/settings/reporting/snapshots/rebuild' => [
                'POST' => \App\Modules\Reporting\Http\Controllers\Admin\AdminReportingController::class . '@rebuild',
            ],
            'api/v1/admin/settings/branches/export' => [
                'GET' => \App\Modules\AdminMasterDataBulk\Http\Controllers\Admin\AdminMasterDataBulkController::class . '@export',
            ],
        ];

        foreach ($expectations as $uri => $methods) {
            foreach ($methods as $method => $action) {
                $matchingRoute = collect(Route::getRoutes()->getRoutes())
                    ->first(fn ($candidate) => (string) $candidate->uri() === $uri && in_array($method, $candidate->methods(), true));

                $this->assertNotNull($matchingRoute, sprintf('Missing %s route [%s].', $method, $uri));
                $this->assertSame($action, $matchingRoute->getActionName(), sprintf('Route [%s %s] drifted from canonical controller ownership.', $method, $uri));
            }
        }
    }
}

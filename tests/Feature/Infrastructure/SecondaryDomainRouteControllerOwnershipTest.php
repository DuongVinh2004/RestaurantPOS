<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Modules\Conversations\Http\Controllers\Staff\ConversationInboxController;
use App\Modules\Conversations\Http\Controllers\Staff\ReservationInboxController;
use App\Modules\FloorOperations\Http\Controllers\Staff\OperationalChangeFeedController;
use App\Modules\MasterDataExchange\Http\Controllers\Admin\MasterDataExportController;
use App\Modules\PrivacyCompliance\Http\Controllers\Admin\PrivacyController;
use App\Modules\PrivacyCompliance\Http\Controllers\Staff\AuditTrailController;
use App\Modules\Reporting\Http\Controllers\Admin\ReportingSnapshotController;
use App\Modules\Reporting\Http\Controllers\Staff\SalesReportController;
use App\Modules\Waitlist\Http\Controllers\Customer\WaitlistController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class SecondaryDomainRouteControllerOwnershipTest extends TestCase
{
    public function test_batch_six_routes_resolve_to_canonical_module_controllers(): void
    {
        $expectations = [
            'api/v1/waiting-list' => [
                'GET' => WaitlistController::class.'@index',
                'POST' => WaitlistController::class.'@store',
            ],
            'api/v1/staff/waiting-list' => [
                'GET' => \App\Modules\Waitlist\Http\Controllers\Staff\WaitlistController::class.'@index',
                'POST' => \App\Modules\Waitlist\Http\Controllers\Staff\WaitlistController::class.'@store',
            ],
            'api/v1/staff/conversations' => [
                'GET' => ConversationInboxController::class.'@index',
            ],
            'api/v1/staff/reservations' => [
                'GET' => ReservationInboxController::class.'@index',
            ],
            'api/v1/staff/audit-trail' => [
                'GET' => AuditTrailController::class.'@index',
            ],
            'api/v1/staff/reporting/daily-sales' => [
                'GET' => SalesReportController::class.'@index',
            ],
            'api/v1/staff/tables/board/changes' => [
                'GET' => OperationalChangeFeedController::class.'@boardChanges',
            ],
            'api/v1/admin/privacy/requests' => [
                'GET' => PrivacyController::class.'@index',
            ],
            'api/v1/admin/settings/reporting/snapshots/rebuild' => [
                'POST' => ReportingSnapshotController::class.'@rebuild',
            ],
            'api/v1/admin/settings/branches/export' => [
                'GET' => MasterDataExportController::class.'@export',
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

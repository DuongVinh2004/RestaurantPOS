<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Api\Admin\AdminBranchController;
use App\Http\Controllers\Api\Admin\AdminReportingController;
use App\Http\Controllers\Api\Staff\StaffReportingController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminReportingAndMultiBranchRouteSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_and_branch_routes_are_registered_to_runtime_surface(): void
    {
        $expected = [
            ['GET', 'api/v1/admin/settings/branches', AdminBranchController::class . '@index'],
            ['POST', 'api/v1/admin/settings/branches', AdminBranchController::class . '@store'],
            ['GET', 'api/v1/admin/settings/branches/{id}', AdminBranchController::class . '@show'],
            ['PATCH', 'api/v1/admin/settings/branches/{id}', AdminBranchController::class . '@update'],
            ['POST', 'api/v1/admin/settings/reporting/snapshots/rebuild', AdminReportingController::class . '@rebuild'],
            ['GET', 'api/v1/staff/reporting/daily-sales', StaffReportingController::class . '@dailySales'],
            ['GET', 'api/v1/staff/reporting/daily-operations', StaffReportingController::class . '@dailyOperations'],
            ['GET', 'api/v1/staff/reporting/daily-inventory', StaffReportingController::class . '@dailyInventory'],
        ];

        foreach ($expected as [$method, $uri, $action]) {
            $route = $this->findRoute($method, $uri);
            self::assertNotNull($route, sprintf('Expected route [%s %s] is not registered.', $method, $uri));
            self::assertSame($action, $route->getActionName(), sprintf('Route [%s %s] drifted to unexpected action.', $method, $uri));
        }
    }

    private function findRoute(string $method, string $uri): ?\Illuminate\Routing\Route
    {
        foreach (Route::getRoutes() as $route) {
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            if ($methods !== [$method]) {
                continue;
            }

            if (trim((string) $route->uri(), '/') !== trim($uri, '/')) {
                continue;
            }

            return $route;
        }

        return null;
    }
}

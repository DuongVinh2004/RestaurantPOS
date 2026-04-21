<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\BranchScheduling\Http\Controllers\Admin\BranchController;
use App\Modules\Reporting\Http\Controllers\Admin\ReportingSnapshotController;
use App\Modules\Reporting\Http\Controllers\Staff\InventoryReportController;
use App\Modules\Reporting\Http\Controllers\Staff\OperationsReportController;
use App\Modules\Reporting\Http\Controllers\Staff\SalesReportController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminReportingAndMultiBranchRouteSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_and_branch_routes_are_registered_to_runtime_surface(): void
    {
        $expected = [
            ['GET', 'api/v1/admin/settings/branches', BranchController::class.'@index'],
            ['POST', 'api/v1/admin/settings/branches', BranchController::class.'@store'],
            ['GET', 'api/v1/admin/settings/branches/{id}', BranchController::class.'@show'],
            ['PATCH', 'api/v1/admin/settings/branches/{id}', BranchController::class.'@update'],
            ['POST', 'api/v1/admin/settings/reporting/snapshots/rebuild', ReportingSnapshotController::class.'@rebuild'],
            ['GET', 'api/v1/staff/reporting/daily-sales', SalesReportController::class.'@index'],
            ['GET', 'api/v1/staff/reporting/daily-operations', OperationsReportController::class.'@index'],
            ['GET', 'api/v1/staff/reporting/daily-inventory', InventoryReportController::class.'@index'],
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

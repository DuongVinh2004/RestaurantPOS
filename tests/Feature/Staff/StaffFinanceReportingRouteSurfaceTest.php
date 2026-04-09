<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class StaffFinanceReportingRouteSurfaceTest extends TestCase
{
    public function test_staff_cashier_finance_reporting_kitchen_and_order_item_routes_are_registered(): void
    {
        $expected = [
            ['GET', 'v1/staff/cashier/shifts/current', 'App\\Http\\Controllers\\Api\\Staff\\StaffCashierShiftController@current'],
            ['POST', 'v1/staff/cashier/shifts/open', 'App\\Http\\Controllers\\Api\\Staff\\StaffCashierShiftController@open'],
            ['GET', 'v1/staff/cashier/shifts/{shift_id}', 'App\\Http\\Controllers\\Api\\Staff\\StaffCashierShiftController@show'],
            ['POST', 'v1/staff/cashier/shifts/{shift_id}/close', 'App\\Http\\Controllers\\Api\\Staff\\StaffCashierShiftController@close'],
            ['GET', 'v1/staff/finance/invoices/{reservation_id}', 'App\\Http\\Controllers\\Api\\Staff\\StaffFinanceInvoiceController@show'],
            ['POST', 'v1/staff/finance/invoices/{reservation_id}/issue', 'App\\Http\\Controllers\\Api\\Staff\\StaffFinanceInvoiceController@issue'],
            ['GET', 'v1/staff/finance/accounting-export', 'App\\Http\\Controllers\\Api\\Staff\\StaffFinanceInvoiceController@accountingExport'],
            ['GET', 'v1/staff/finance/reconciliation', 'App\\Http\\Controllers\\Api\\Staff\\StaffFinancialReconciliationController@index'],
            ['GET', 'v1/staff/finance/reconciliation/export', 'App\\Http\\Controllers\\Api\\Staff\\StaffFinancialReconciliationController@export'],
            ['GET', 'v1/staff/finance/reconciliation/{reservation_id}', 'App\\Http\\Controllers\\Api\\Staff\\StaffFinancialReconciliationController@show'],
            ['GET', 'v1/staff/reporting/daily-sales', 'App\\Http\\Controllers\\Api\\Staff\\StaffReportingController@dailySales'],
            ['GET', 'v1/staff/reporting/daily-operations', 'App\\Http\\Controllers\\Api\\Staff\\StaffReportingController@dailyOperations'],
            ['GET', 'v1/staff/reporting/daily-inventory', 'App\\Http\\Controllers\\Api\\Staff\\StaffReportingController@dailyInventory'],
            ['GET', 'v1/staff/kitchen/stations', 'App\\Http\\Controllers\\Api\\Staff\\StaffKitchenController@stations'],
            ['GET', 'v1/staff/kitchen/stations/{station_id}/tickets', 'App\\Http\\Controllers\\Api\\Staff\\StaffKitchenController@stationTickets'],
            ['POST', 'v1/staff/orders/{order_id}/kitchen/dispatch', 'App\\Http\\Controllers\\Api\\Staff\\StaffKitchenController@dispatchOrder'],
            ['POST', 'v1/staff/kitchen/tickets/{ticket_id}/fire', 'App\\Http\\Controllers\\Api\\Staff\\StaffKitchenController@fire'],
            ['POST', 'v1/staff/kitchen/tickets/{ticket_id}/bump', 'App\\Http\\Controllers\\Api\\Staff\\StaffKitchenController@bump'],
            ['POST', 'v1/staff/kitchen/tickets/{ticket_id}/recall', 'App\\Http\\Controllers\\Api\\Staff\\StaffKitchenController@recall'],
            ['GET', 'v1/staff/kitchen/changes', 'App\\Http\\Controllers\\Api\\Staff\\StaffKitchenController@changes'],
            ['PATCH', 'v1/staff/orders/{order_id}/items/{order_item_id}', 'App\\Http\\Controllers\\Api\\Staff\\StaffOrderItemLifecycleController@update'],
            ['POST', 'v1/staff/orders/{order_id}/items/{order_item_id}/status', 'App\\Http\\Controllers\\Api\\Staff\\StaffOrderItemLifecycleController@updateStatus'],
            ['POST', 'v1/admin/settings/reporting/snapshots/rebuild', 'App\\Http\\Controllers\\Api\\Admin\\AdminReportingController@rebuild'],
        ];

        foreach ($expected as [$method, $uri, $action]) {
            $route = $this->findRoute($method, $uri);

            self::assertNotNull($route, sprintf('Expected route [%s %s] is not registered.', $method, $uri));
            self::assertSame($action, $route->getActionName(), sprintf('Route [%s %s] drifted to unexpected action.', $method, $uri));
        }
    }

    private function findRoute(string $method, string $uri): ?IlluminateRoute
    {
        $normalizedCandidates = $this->uriCandidates($uri);

        return collect(Route::getRoutes()->getRoutes())
            ->first(static fn (IlluminateRoute $route): bool => in_array($method, $route->methods(), true)
                && in_array(trim($route->uri(), '/'), $normalizedCandidates, true));
    }

    /**
     * @return list<string>
     */
    private function uriCandidates(string $uri): array
    {
        $normalized = trim($uri, '/');

        $candidates = [$normalized];

        if (! str_starts_with($normalized, 'api/')) {
            $candidates[] = 'api/' . $normalized;
        }

        return array_values(array_unique($candidates));
    }
}

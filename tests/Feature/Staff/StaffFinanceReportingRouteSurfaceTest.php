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
            ['GET', 'v1/staff/cashier/shifts/current', 'App\\Modules\\Cashiering\\Http\\Controllers\\Staff\\CashierShiftController@current'],
            ['POST', 'v1/staff/cashier/shifts/open', 'App\\Modules\\Cashiering\\Http\\Controllers\\Staff\\CashierShiftController@open'],
            ['GET', 'v1/staff/cashier/shifts/{shift_id}', 'App\\Modules\\Cashiering\\Http\\Controllers\\Staff\\CashierShiftController@show'],
            ['POST', 'v1/staff/cashier/shifts/{shift_id}/close', 'App\\Modules\\Cashiering\\Http\\Controllers\\Staff\\CashierShiftController@close'],
            ['GET', 'v1/staff/finance/invoices/{reservation_id}', 'App\\Modules\\Billing\\Http\\Controllers\\Staff\\InvoiceController@show'],
            ['POST', 'v1/staff/finance/invoices/{reservation_id}/issue', 'App\\Modules\\Billing\\Http\\Controllers\\Staff\\InvoiceController@issue'],
            ['GET', 'v1/staff/finance/accounting-export', 'App\\Modules\\Billing\\Http\\Controllers\\Staff\\InvoiceController@accountingExport'],
            ['GET', 'v1/staff/finance/reconciliation', 'App\\Modules\\Cashiering\\Http\\Controllers\\Staff\\SettlementReconciliationController@index'],
            ['GET', 'v1/staff/finance/reconciliation/export', 'App\\Modules\\Cashiering\\Http\\Controllers\\Staff\\SettlementReconciliationController@export'],
            ['GET', 'v1/staff/finance/reconciliation/{reservation_id}', 'App\\Modules\\Cashiering\\Http\\Controllers\\Staff\\SettlementReconciliationController@show'],
            ['GET', 'v1/staff/reporting/daily-sales', 'App\\Modules\\Reporting\\Http\\Controllers\\Staff\\SalesReportController@index'],
            ['GET', 'v1/staff/reporting/daily-operations', 'App\\Modules\\Reporting\\Http\\Controllers\\Staff\\OperationsReportController@index'],
            ['GET', 'v1/staff/reporting/daily-inventory', 'App\\Modules\\Reporting\\Http\\Controllers\\Staff\\InventoryReportController@index'],
            ['GET', 'v1/staff/kitchen/stations', 'App\\Modules\\KitchenDispatch\\Http\\Controllers\\Staff\\KitchenDispatchController@stations'],
            ['GET', 'v1/staff/kitchen/stations/{station_id}/tickets', 'App\\Modules\\KitchenDispatch\\Http\\Controllers\\Staff\\KitchenDispatchController@stationTickets'],
            ['POST', 'v1/staff/orders/{order_id}/kitchen/dispatch', 'App\\Modules\\KitchenDispatch\\Http\\Controllers\\Staff\\KitchenDispatchController@dispatchOrder'],
            ['POST', 'v1/staff/kitchen/tickets/{ticket_id}/fire', 'App\\Modules\\KitchenDispatch\\Http\\Controllers\\Staff\\KitchenDispatchController@fire'],
            ['POST', 'v1/staff/kitchen/tickets/{ticket_id}/bump', 'App\\Modules\\KitchenDispatch\\Http\\Controllers\\Staff\\KitchenDispatchController@bump'],
            ['POST', 'v1/staff/kitchen/tickets/{ticket_id}/recall', 'App\\Modules\\KitchenDispatch\\Http\\Controllers\\Staff\\KitchenDispatchController@recall'],
            ['GET', 'v1/staff/kitchen/changes', 'App\\Modules\\KitchenDispatch\\Http\\Controllers\\Staff\\KitchenDispatchController@changes'],
            ['PATCH', 'v1/staff/orders/{order_id}/items/{order_item_id}', 'App\\Modules\\Ordering\\Http\\Controllers\\Staff\\OrderItemLifecycleController@update'],
            ['POST', 'v1/staff/orders/{order_id}/items/{order_item_id}/status', 'App\\Modules\\Ordering\\Http\\Controllers\\Staff\\OrderItemLifecycleController@updateStatus'],
            ['POST', 'v1/admin/settings/reporting/snapshots/rebuild', 'App\\Modules\\Reporting\\Http\\Controllers\\Admin\\ReportingSnapshotController@rebuild'],
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
            $candidates[] = 'api/'.$normalized;
        }

        return array_values(array_unique($candidates));
    }
}

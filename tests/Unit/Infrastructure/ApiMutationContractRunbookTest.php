<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use Tests\TestCase;

final class ApiMutationContractRunbookTest extends TestCase
{
    public function test_runbook_documents_required_mutation_contract_columns_and_sources(): void
    {
        $contents = $this->runbookContents();

        foreach ([
            '| Method/path | Required capability | Requires `Idempotency-Key` | Idempotency scope | Requires `row_version` | Expected stale response | Expected replay response | Expected conflict response |',
            'php artisan route:list --path=api',
            'php artisan booking:route-gate --json',
            'config/staff_capabilities.php',
            'app/Http/Middleware/IdempotencyMiddleware.php',
            'build/api-consumer/mutation-contracts.md',
            '409 stale_row_version',
            'Idempotency-Replayed: true',
            '409 idempotency_conflict',
            '422 idempotency_key_required',
        ] as $expected) {
            self::assertStringContainsString($expected, $contents);
        }
    }

    public function test_runbook_covers_go_live_priority_mutation_routes(): void
    {
        $contents = $this->runbookContents();

        $expectedRoutes = [
            'POST api/v1/staff/reservations/{id}/check-in',
            'POST api/v1/staff/reservations/{id}/reschedule',
            'POST api/v1/staff/reservations/{id}/move-table',
            'POST api/v1/staff/reservations/{id}/assign-table',
            'POST api/v1/staff/reservations/{id}/assign-best-fit',
            'POST api/v1/staff/tables/{table_id}/release',
            'POST api/v1/staff/service-sessions/walk-in',
            'POST api/v1/staff/tables/{table_id}/orders',
            'POST api/v1/staff/orders/{order_id}/items',
            'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}',
            'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status',
            'POST api/v1/staff/orders/{order_id}/kitchen/dispatch',
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/fire',
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/bump',
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/recall',
            'POST api/v1/staff/orders/{order_id}/bill-snapshot',
            'GET api/v1/staff/orders/{order_id}/settlement-preview',
            'POST api/v1/staff/orders/{order_id}/settlement/finalize',
            'POST api/v1/staff/reservations/{reservation_id}/refund',
            'POST api/v1/staff/reservations/{reservation_id}/refund-cancel',
            'POST api/v1/staff/cashier/shifts/open',
            'POST api/v1/staff/cashier/shifts/{shift_id}/close',
            'POST api/v1/admin/settings/branches/import',
            'POST api/v1/admin/restaurant/tables/import',
            'POST api/v1/admin/menu/categories/import',
            'POST api/v1/admin/menu/items/import',
            'POST api/v1/admin/menu/prices/import',
            'POST api/v1/admin/benefits/vouchers/import',
            'POST api/v1/admin/benefits/loyalty-tiers/import',
        ];

        foreach ($expectedRoutes as $route) {
            self::assertStringContainsString($route, $contents, sprintf('Runbook is missing route [%s].', $route));
        }
    }

    public function test_runbook_records_legacy_order_read_cleanup(): void
    {
        $contents = $this->runbookContents();

        foreach ([
            'OrderReadController@show',
            'StaffOrderReadService::findOrder',
            'ReservationOrderController@indexByReservation',
            'StaffOrderReadService::listOrdersByReservation',
            'No route should point at `ReservationOrderController@show`.',
        ] as $expected) {
            self::assertStringContainsString($expected, $contents);
        }
    }

    private function runbookContents(): string
    {
        $path = base_path('docs/runbooks/api-mutation-contract.md');

        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}

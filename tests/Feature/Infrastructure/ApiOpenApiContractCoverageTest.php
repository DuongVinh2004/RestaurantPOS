<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Services\ApiContract\ApiContractMetadataRegistry;
use App\Services\ApiContract\OpenApiSpecService;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class ApiOpenApiContractCoverageTest extends TestCase
{
    public function test_generated_openapi_contract_covers_every_expected_api_route(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/route_inventory_gate.json')), true, 512, JSON_THROW_ON_ERROR);
        $expected = collect((array) ($fixture['expected_routes'] ?? []))
            ->map(fn (array $route): string => strtoupper((string) $route['method']).' '.(string) $route['uri'])
            ->sort()
            ->values()
            ->all();

        $actual = collect($this->specOperations(app(OpenApiSpecService::class)->build()))
            ->keys()
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expected, $actual);
    }

    public function test_priority_routes_are_exported_as_full_contract_operations(): void
    {
        $operations = $this->specOperations(app(OpenApiSpecService::class)->build());

        foreach (app(ApiContractMetadataRegistry::class)->priorityOperations() as $signature => $metadata) {
            $this->assertArrayHasKey($signature, $operations, sprintf('Missing priority contract operation [%s].', $signature));
            $this->assertSame('full', $operations[$signature]['x-contract-grade'] ?? null, sprintf('Priority route [%s] is no longer full contract grade.', $signature));
            $this->assertSame($metadata['tags'] ?? [], $operations[$signature]['tags'] ?? [], sprintf('Priority route [%s] drifted tags.', $signature));
        }
    }

    public function test_declared_alias_routes_are_marked_deprecated_in_the_generated_spec(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/fixtures/route_inventory_gate.json')), true, 512, JSON_THROW_ON_ERROR);
        $operations = $this->specOperations(app(OpenApiSpecService::class)->build());
        $expectedRoutes = collect((array) ($fixture['expected_routes'] ?? []));

        foreach ((array) ($fixture['alias_groups'] ?? []) as $group) {
            $canonicalUri = (string) ($group['canonical'] ?? '');
            $canonicalSignature = $this->expectedSignatureForUri($expectedRoutes, $canonicalUri);

            $this->assertNotNull($canonicalSignature, sprintf('Canonical alias route [%s] is missing from expected route inventory.', $canonicalUri));
            $this->assertFalse((bool) ($operations[$canonicalSignature]['deprecated'] ?? false), sprintf('Canonical route [%s] must not be deprecated.', $canonicalSignature));

            foreach ((array) ($group['aliases'] ?? []) as $aliasDefinition) {
                if (is_string($aliasDefinition)) {
                    $aliasDefinition = ['uri' => $aliasDefinition];
                }

                $aliasUri = (string) ($aliasDefinition['uri'] ?? '');
                $aliasSignature = $this->expectedSignatureForUri($expectedRoutes, $aliasUri);

                $this->assertNotNull($aliasSignature, sprintf('Alias route [%s] is missing from expected route inventory.', $aliasUri));
                $this->assertTrue((bool) ($operations[$aliasSignature]['deprecated'] ?? false), sprintf('Alias route [%s] must be marked deprecated.', $aliasSignature));
            }
        }
    }

    public function test_customer_batch_one_routes_are_full_contract_and_typed(): void
    {
        $operations = $this->specOperations(app(OpenApiSpecService::class)->build());

        $expectations = [
            'GET api/v1/menu/items' => [
                'schema' => '#/components/schemas/CustomerMenuItemsCollectionEnvelope',
                'auth_mode' => 'public',
                'security' => [],
            ],
            'GET api/v1/me/loyalty' => [
                'schema' => '#/components/schemas/CustomerLoyaltySummaryEnvelope',
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
            ],
            'GET api/v1/reservations/{id}/benefits-preview' => [
                'schema' => '#/components/schemas/CustomerReservationBenefitsPreviewEnvelope',
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
            ],
            'GET api/v1/reservations/{id}/preorder' => [
                'schema' => '#/components/schemas/CustomerReservationPreorderEnvelope',
                'auth_mode' => 'customer_or_session',
                'security' => [['CustomerAccessToken' => []], ['CustomerSessionId' => []]],
            ],
            'PUT api/v1/reservations/{id}/preorder' => [
                'schema' => '#/components/schemas/CustomerReservationPreorderEnvelope',
                'auth_mode' => 'customer_or_session',
                'security' => [['CustomerAccessToken' => []], ['CustomerSessionId' => []]],
            ],
            'DELETE api/v1/reservations/{id}/preorder' => [
                'schema' => '#/components/schemas/CustomerReservationPreorderEnvelope',
                'auth_mode' => 'customer_or_session',
                'security' => [['CustomerAccessToken' => []], ['CustomerSessionId' => []]],
            ],
        ];

        foreach ($expectations as $signature => $expectation) {
            $this->assertArrayHasKey($signature, $operations);
            $this->assertSame('full', $operations[$signature]['x-contract-grade'] ?? null, sprintf('Route [%s] must stay full contract.', $signature));
            $this->assertSame($expectation['schema'], data_get($operations[$signature], 'responses.200.content.application/json.schema.$ref'), sprintf('Route [%s] drifted its 200 schema.', $signature));
            $this->assertSame($expectation['auth_mode'], $operations[$signature]['x-auth-mode'] ?? null, sprintf('Route [%s] drifted auth mode.', $signature));
            $this->assertSame($expectation['security'], $operations[$signature]['security'] ?? null, sprintf('Route [%s] drifted security requirements.', $signature));
        }

        $deleteParameters = collect((array) ($operations['DELETE api/v1/reservations/{id}/preorder']['parameters'] ?? []))
            ->keyBy(fn (array $parameter): string => (string) ($parameter['name'] ?? ''));

        $this->assertSame('query', $deleteParameters['row_version']['in'] ?? null);
        $this->assertSame('query', $deleteParameters['pre_order_row_version']['in'] ?? null);
        $this->assertSame('integer', data_get($deleteParameters['row_version'] ?? [], 'schema.type'));
        $this->assertSame('integer', data_get($deleteParameters['pre_order_row_version'] ?? [], 'schema.type'));

        $this->assertSame(
            '#/components/schemas/ReplaceCustomerReservationPreorderRequest',
            data_get($operations['PUT api/v1/reservations/{id}/preorder'], 'requestBody.content.application/json.schema.$ref')
        );
    }

    public function test_staff_batch_one_routes_are_full_contract_and_typed(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $operations = $this->specOperations($spec);

        $expectations = [
            'GET api/v1/auth/staff/me' => ['status' => '200', 'schema' => '#/components/schemas/StaffAuthSessionEnvelope'],
            'GET api/v1/staff/branches' => ['status' => '200', 'schema' => '#/components/schemas/BranchCollectionEnvelope'],
            'GET api/v1/staff/menu/items' => ['status' => '200', 'schema' => '#/components/schemas/CustomerMenuItemsCollectionEnvelope'],
            'GET api/v1/staff/tables/board' => ['status' => '200', 'schema' => '#/components/schemas/StaffTableBoardEnvelope'],
            'GET api/v1/staff/tables/board/changes' => ['status' => '200', 'schema' => '#/components/schemas/StaffOperationalRealtimeEnvelope'],
            'GET api/v1/staff/reservations' => ['status' => '200', 'schema' => '#/components/schemas/StaffReservationLookupCollectionEnvelope'],
            'GET api/v1/staff/reservations/{reservation_id}' => ['status' => '200', 'schema' => '#/components/schemas/ReservationEnvelope'],
            'POST api/v1/staff/reservations/{id}/check-in' => ['status' => '200', 'schema' => '#/components/schemas/ReservationEnvelope'],
            'POST api/v1/staff/tables/{table_id}/orders' => ['status' => '201', 'schema' => '#/components/schemas/StaffReservationOrderEnvelope'],
            'POST api/v1/staff/orders/{order_id}/items' => ['status' => '200', 'schema' => '#/components/schemas/StaffReservationOrderEnvelope'],
            'GET api/v1/staff/reservations/{reservation_id}/orders' => ['status' => '200', 'schema' => '#/components/schemas/StaffReservationOrderCollectionEnvelope'],
            'GET api/v1/staff/orders/{order_id}' => ['status' => '200', 'schema' => '#/components/schemas/StaffOrderReadEnvelope'],
            'GET api/v1/staff/waiting-list' => ['status' => '200', 'schema' => '#/components/schemas/StaffWaitingListCollectionEnvelope'],
            'POST api/v1/staff/waiting-list/{id}/notify' => ['status' => '200', 'schema' => '#/components/schemas/StaffWaitingListEnvelope'],
            'POST api/v1/staff/waiting-list/{id}/seat' => ['status' => '200', 'schema' => '#/components/schemas/StaffWaitingListSeatEnvelope'],
            'GET api/v1/staff/cashier/shifts' => ['status' => '200', 'schema' => '#/components/schemas/CashierShiftCollectionEnvelope'],
            'GET api/v1/staff/cashier/shifts/current' => ['status' => '200', 'schema' => '#/components/schemas/CashierShiftEnvelope'],
            'POST api/v1/staff/cashier/shifts/open' => ['status' => '201', 'schema' => '#/components/schemas/CashierShiftEnvelope'],
            'GET api/v1/staff/cashier/shifts/{shift_id}' => ['status' => '200', 'schema' => '#/components/schemas/CashierShiftEnvelope'],
            'POST api/v1/staff/cashier/shifts/{shift_id}/close' => ['status' => '200', 'schema' => '#/components/schemas/CashierShiftEnvelope'],
            'GET api/v1/staff/orders/{order_id}/settlement-preview' => ['status' => '200', 'schema' => '#/components/schemas/StaffCheckoutSettlementEnvelope'],
            'POST api/v1/staff/orders/{order_id}/settlement/finalize' => ['status' => '200', 'schema' => '#/components/schemas/StaffCheckoutSettlementEnvelope'],
            'GET api/v1/staff/reservations/{reservation_id}/refund-preview' => ['status' => '200', 'schema' => '#/components/schemas/StaffRefundPreviewEnvelope'],
            'POST api/v1/staff/reservations/{reservation_id}/refund' => ['status' => '200', 'schema' => '#/components/schemas/StaffRefundEnvelope'],
            'POST api/v1/staff/reservations/{reservation_id}/refund-cancel' => ['status' => '200', 'schema' => '#/components/schemas/StaffRefundEnvelope'],
        ];

        foreach ($expectations as $signature => $expectation) {
            $this->assertArrayHasKey($signature, $operations);
            $this->assertSame('full', $operations[$signature]['x-contract-grade'] ?? null, sprintf('Route [%s] must stay full contract.', $signature));
            $this->assertSame(
                $expectation['schema'],
                data_get($operations[$signature], 'responses.'.$expectation['status'].'.content.application/json.schema.$ref'),
                sprintf('Route [%s] drifted its %s schema.', $signature, $expectation['status'])
            );
            $this->assertSame('staff_api_key', $operations[$signature]['x-auth-mode'] ?? null, sprintf('Route [%s] drifted auth mode.', $signature));
            $this->assertSame([['StaffApiKey' => []]], $operations[$signature]['security'] ?? null, sprintf('Route [%s] drifted security requirements.', $signature));
        }

        $this->assertSame(
            '#/components/schemas/CheckInReservationRequest',
            data_get($operations['POST api/v1/staff/reservations/{id}/check-in'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/CreateTableOrderRequest',
            data_get($operations['POST api/v1/staff/tables/{table_id}/orders'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/AddOrderItemsRequest',
            data_get($operations['POST api/v1/staff/orders/{order_id}/items'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/NotifyWaitingListRequest',
            data_get($operations['POST api/v1/staff/waiting-list/{id}/notify'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/SeatWaitingListRequest',
            data_get($operations['POST api/v1/staff/waiting-list/{id}/seat'], 'requestBody.content.application/json.schema.$ref')
        );

        $this->assertSame(
            '#/components/schemas/StaffAuthUser',
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.user.$ref')
                ?? data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.user.anyOf.0.$ref')
        );
        $this->assertSame(
            'array',
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.capabilities.type')
        );
        $this->assertSame(
            'string',
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.capabilities.items.type')
        );
        $this->assertSame(
            'array',
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.known_capabilities.type')
        );
        $this->assertSame(
            'string',
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.capability_source.type')
        );
        $this->assertSame(
            '#/components/schemas/StaffStartupContext',
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.startup.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffStartupBranch',
            data_get($spec, 'components.schemas.StaffStartupContext.properties.default_branch.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffStartupCashierShift',
            data_get($spec, 'components.schemas.StaffStartupContext.properties.active_cashier_shift.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffStartupReadiness',
            data_get($spec, 'components.schemas.StaffStartupContext.properties.readiness.$ref')
        );
        $this->assertSame(
            ['ready', 'capability_missing'],
            data_get($spec, 'components.schemas.StaffStartupReadiness.properties.access.enum')
        );
        $this->assertSame(
            ['ready', 'missing'],
            data_get($spec, 'components.schemas.StaffStartupReadiness.properties.branch.enum')
        );
        $this->assertSame(
            ['ready', 'action_required', 'not_applicable'],
            data_get($spec, 'components.schemas.StaffStartupReadiness.properties.cashier_shift.enum')
        );
        $this->assertSame(
            '#/components/schemas/StaffTableBoardRow',
            data_get($spec, 'components.schemas.StaffTableBoardEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffTableBoardAssignedReservation',
            data_get($spec, 'components.schemas.StaffTableBoardRow.properties.reservations.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffTableBoardAssignedReservation',
            data_get($spec, 'components.schemas.StaffTableBoardRow.properties.reservation.$ref')
                ?? data_get($spec, 'components.schemas.StaffTableBoardRow.properties.reservation.anyOf.0.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffTableBoardCheckInAction',
            data_get($spec, 'components.schemas.StaffTableBoardRow.properties.actions.properties.check_in.$ref')
                ?? data_get($spec, 'components.schemas.StaffTableBoardRow.properties.actions.properties.check_in.anyOf.0.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffOrderReadPayload',
            data_get($spec, 'components.schemas.StaffOrderReadEnvelope.properties.data.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffOrderReadCustomer',
            data_get($spec, 'components.schemas.StaffOrderReadPayload.properties.customer.$ref')
                ?? data_get($spec, 'components.schemas.StaffOrderReadPayload.properties.customer.anyOf.0.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffOrderReadItemMenuItem',
            data_get($spec, 'components.schemas.StaffOrderReadPayload.properties.items.items.properties.item.$ref')
                ?? data_get($spec, 'components.schemas.StaffOrderReadPayload.properties.items.items.properties.item.anyOf.0.$ref')
        );
        $this->assertSame(
            'integer',
            data_get($spec, 'components.schemas.StaffOrderReadPayload.properties.items.items.properties.row_version.type')
        );
        $this->assertTrue(
            (bool) data_get($spec, 'components.schemas.StaffOrderReadPayload.properties.items.items.properties.row_version.nullable')
        );
        $this->assertSame(
            'integer',
            data_get($spec, 'components.schemas.ReservationOrder.properties.items.items.properties.row_version.type')
        );
        $this->assertTrue(
            (bool) data_get($spec, 'components.schemas.ReservationOrder.properties.items.items.properties.row_version.nullable')
        );
        $this->assertSame(
            '#/components/schemas/StaffWaitingListCollectionMeta',
            data_get($spec, 'components.schemas.StaffWaitingListCollectionEnvelope.properties.meta.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffReservationLookupEntry',
            data_get($spec, 'components.schemas.StaffReservationLookupCollectionEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffReservationLookupCollectionMeta',
            data_get($spec, 'components.schemas.StaffReservationLookupCollectionEnvelope.properties.meta.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffReservationOrderCollectionMeta',
            data_get($spec, 'components.schemas.StaffReservationOrderCollectionEnvelope.properties.meta.$ref')
        );
        $this->assertSame(
            '#/components/schemas/CashierShiftCollectionMeta',
            data_get($spec, 'components.schemas.CashierShiftCollectionEnvelope.properties.meta.$ref')
        );
    }

    public function test_admin_branch_update_route_exposes_auth_conflict_and_validation_contract(): void
    {
        $operations = $this->specOperations(app(OpenApiSpecService::class)->build());

        $this->assertArrayHasKey('PATCH api/v1/admin/settings/branches/{id}', $operations);
        $this->assertSame('full', $operations['PATCH api/v1/admin/settings/branches/{id}']['x-contract-grade'] ?? null);
        $this->assertSame('staff_api_key', $operations['PATCH api/v1/admin/settings/branches/{id}']['x-auth-mode'] ?? null);
        $this->assertSame([['StaffApiKey' => []]], $operations['PATCH api/v1/admin/settings/branches/{id}']['security'] ?? null);
        $this->assertSame(
            '#/components/schemas/UpdateAdminBranchRequest',
            data_get($operations['PATCH api/v1/admin/settings/branches/{id}'], 'requestBody.content.application/json.schema.$ref')
        );

        foreach (['200', '401', '403', '404', '409', '422'] as $status) {
            $this->assertArrayHasKey($status, $operations['PATCH api/v1/admin/settings/branches/{id}']['responses'] ?? []);
        }
    }

    public function test_staff_batch_four_kitchen_routes_are_full_contract_and_typed(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $operations = $this->specOperations($spec);

        $expectations = [
            'GET api/v1/staff/kitchen/changes' => ['status' => '200', 'schema' => '#/components/schemas/StaffOperationalRealtimeEnvelope'],
            'GET api/v1/staff/kitchen/stations' => ['status' => '200', 'schema' => '#/components/schemas/StaffKitchenStationCollectionEnvelope'],
            'GET api/v1/staff/kitchen/stations/{station_id}/tickets' => ['status' => '200', 'schema' => '#/components/schemas/StaffKitchenTicketCollectionEnvelope'],
            'POST api/v1/staff/orders/{order_id}/kitchen/dispatch' => ['status' => '200', 'schema' => '#/components/schemas/StaffKitchenDispatchEnvelope'],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/fire' => ['status' => '200', 'schema' => '#/components/schemas/StaffKitchenTicketEnvelope'],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/bump' => ['status' => '200', 'schema' => '#/components/schemas/StaffKitchenTicketEnvelope'],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/recall' => ['status' => '200', 'schema' => '#/components/schemas/StaffKitchenTicketEnvelope'],
        ];

        foreach ($expectations as $signature => $expectation) {
            $this->assertArrayHasKey($signature, $operations);
            $this->assertSame('full', $operations[$signature]['x-contract-grade'] ?? null, sprintf('Route [%s] must stay full contract.', $signature));
            $this->assertSame(
                $expectation['schema'],
                data_get($operations[$signature], 'responses.'.$expectation['status'].'.content.application/json.schema.$ref'),
                sprintf('Route [%s] drifted its %s schema.', $signature, $expectation['status'])
            );
            $this->assertSame('staff_api_key', $operations[$signature]['x-auth-mode'] ?? null, sprintf('Route [%s] drifted auth mode.', $signature));
            $this->assertSame([['StaffApiKey' => []]], $operations[$signature]['security'] ?? null, sprintf('Route [%s] drifted security requirements.', $signature));
            $this->assertSame(['Staff Kitchen'], $operations[$signature]['tags'] ?? null, sprintf('Route [%s] drifted tags.', $signature));
        }

        $this->assertSame(
            '#/components/schemas/DispatchKitchenTicketsRequest',
            data_get($operations['POST api/v1/staff/orders/{order_id}/kitchen/dispatch'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/KitchenStation',
            data_get($spec, 'components.schemas.StaffKitchenStationCollectionEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/KitchenOrderItemTicket',
            data_get($spec, 'components.schemas.StaffKitchenTicketCollectionEnvelope.properties.data.items.$ref')
        );
    }

    public function test_staff_batch_five_read_routes_are_full_contract_and_typed(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $operations = $this->specOperations($spec);

        $expectations = [
            'GET api/v1/staff/reporting/daily-sales' => ['schema' => '#/components/schemas/StaffReportingDailySalesCollectionEnvelope'],
            'GET api/v1/staff/reporting/daily-operations' => ['schema' => '#/components/schemas/StaffReportingDailyOperationsCollectionEnvelope'],
            'GET api/v1/staff/reporting/daily-inventory' => ['schema' => '#/components/schemas/StaffReportingDailyInventoryCollectionEnvelope'],
            'GET api/v1/admin/inventory/ingredients' => ['schema' => '#/components/schemas/AdminIngredientCollectionEnvelope'],
            'GET api/v1/admin/inventory/suppliers' => ['schema' => '#/components/schemas/AdminSupplierCollectionEnvelope'],
            'GET api/v1/admin/inventory/purchase-orders' => ['schema' => '#/components/schemas/AdminPurchaseOrderCollectionEnvelope'],
            'GET api/v1/admin/settings/branches' => ['schema' => '#/components/schemas/BranchCollectionEnvelope'],
        ];

        foreach ($expectations as $signature => $expectation) {
            $this->assertArrayHasKey($signature, $operations);
            $this->assertSame('full', $operations[$signature]['x-contract-grade'] ?? null, sprintf('Route [%s] must stay full contract.', $signature));
            $this->assertSame(
                $expectation['schema'],
                data_get($operations[$signature], 'responses.200.content.application/json.schema.$ref'),
                sprintf('Route [%s] drifted its 200 schema.', $signature)
            );
            $this->assertSame('staff_api_key', $operations[$signature]['x-auth-mode'] ?? null, sprintf('Route [%s] drifted auth mode.', $signature));
            $this->assertSame([['StaffApiKey' => []]], $operations[$signature]['security'] ?? null, sprintf('Route [%s] drifted security requirements.', $signature));
        }

        $this->assertSame(
            '#/components/schemas/ReportingDailySalesSnapshot',
            data_get($spec, 'components.schemas.StaffReportingDailySalesCollectionEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/ReportingDailyOperationSnapshot',
            data_get($spec, 'components.schemas.StaffReportingDailyOperationsCollectionEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/ReportingDailyInventoryMovementSnapshot',
            data_get($spec, 'components.schemas.StaffReportingDailyInventoryCollectionEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffReportingCollectionMeta',
            data_get($spec, 'components.schemas.StaffReportingDailySalesCollectionEnvelope.properties.meta.$ref')
        );
        $this->assertSame(
            '#/components/schemas/AdminIngredient',
            data_get($spec, 'components.schemas.AdminIngredientCollectionEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/AdminSupplier',
            data_get($spec, 'components.schemas.AdminSupplierCollectionEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/AdminPurchaseOrder',
            data_get($spec, 'components.schemas.AdminPurchaseOrderCollectionEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            'array',
            data_get($spec, 'components.schemas.Branch.properties.business_hours.type')
        );
        $this->assertSame(
            'array',
            data_get($spec, 'components.schemas.Branch.properties.closure_windows.type')
        );
        $this->assertSame(
            'object',
            data_get($spec, 'components.schemas.Branch.properties.booking_policy.type')
        );
    }

    public function test_staff_batch_seven_conversation_ai_assist_contract_is_full_and_typed(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $operations = $this->specOperations($spec);

        $this->assertArrayHasKey('GET api/v1/staff/conversations/{conversation_id}', $operations);
        $this->assertSame('full', $operations['GET api/v1/staff/conversations/{conversation_id}']['x-contract-grade'] ?? null);
        $this->assertSame(
            '#/components/schemas/StaffConversationDetailEnvelope',
            data_get($operations['GET api/v1/staff/conversations/{conversation_id}'], 'responses.200.content.application/json.schema.$ref')
        );
        $this->assertSame('staff_api_key', $operations['GET api/v1/staff/conversations/{conversation_id}']['x-auth-mode'] ?? null);
        $this->assertSame([['StaffApiKey' => []]], $operations['GET api/v1/staff/conversations/{conversation_id}']['security'] ?? null);
        $this->assertSame(
            '#/components/schemas/StaffConversationAiAssist',
            data_get($spec, 'components.schemas.StaffConversationDetailEnvelope.properties.data.properties.ai_assist.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffConversationAiAssistAction',
            data_get($spec, 'components.schemas.StaffConversationAiAssist.properties.suggested_actions.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffConversationAiAssistRiskFlag',
            data_get($spec, 'components.schemas.StaffConversationAiAssist.properties.risk_flags.items.$ref')
        );
        $this->assertSame(
            ['ready', 'disabled', 'unavailable'],
            data_get($spec, 'components.schemas.StaffConversationAiAssist.properties.status.enum')
        );
    }

    /**
     * @param  array<string,mixed>  $spec
     * @return array<string,array<string,mixed>>
     */
    private function specOperations(array $spec): array
    {
        $operations = [];

        foreach ((array) ($spec['paths'] ?? []) as $path => $pathOperations) {
            foreach ((array) $pathOperations as $method => $operation) {
                $operations[strtoupper((string) $method).' '.ltrim((string) $path, '/')] = (array) $operation;
            }
        }

        ksort($operations);

        return $operations;
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $expectedRoutes
     */
    private function expectedSignatureForUri($expectedRoutes, string $uri): ?string
    {
        $route = $expectedRoutes->first(fn (array $entry): bool => (string) ($entry['uri'] ?? '') === $uri);

        if (! is_array($route)) {
            return null;
        }

        return strtoupper((string) ($route['method'] ?? 'GET')).' '.(string) ($route['uri'] ?? '');
    }
}

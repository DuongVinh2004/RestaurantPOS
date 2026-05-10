<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use App\Platform\ApiContract\Services\ApiContractMetadataRegistry;
use App\Platform\ApiContract\Services\OpenApiSpecService;
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

    public function test_customer_web_minimum_surface_is_full_contract_and_curated_for_sdk(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $operations = $this->specOperations($spec);
        $curatedSignatures = $this->curatedApiConsumerSignatures();

        $minimumSurface = [
            'POST api/v1/auth/customer/register',
            'POST api/v1/auth/customer/login',
            'GET api/v1/auth/customer/me',
            'POST api/v1/auth/customer/refresh',
            'POST api/v1/auth/customer/logout',
            'GET api/v1/restaurant/profile',
            'GET api/v1/menu/categories',
            'GET api/v1/menu/items',
            'GET api/v1/menu/items/{id}',
            'POST api/v1/menu/preorder/preview',
            'GET api/v1/tables/available',
            'POST api/v1/table-holds',
            'GET api/v1/table-holds/{hold_id}',
            'PATCH api/v1/table-holds/{hold_id}/refresh',
            'DELETE api/v1/table-holds/{hold_id}',
            'POST api/v1/reservations',
            'GET api/v1/reservations',
            'GET api/v1/reservations/{id}',
            'POST api/v1/reservations/{id}/cancel',
            'POST api/v1/reservations/{id}/reschedule',
            'GET api/v1/reservations/{id}/preorder',
            'POST api/v1/reservations/{id}/preorder/preview',
            'PUT api/v1/reservations/{id}/preorder',
            'DELETE api/v1/reservations/{id}/preorder',
            'GET api/v1/reservations/{id}/deposit-preview',
            'POST api/v1/reservations/{id}/deposit/acknowledge',
            'POST api/v1/reservations/{id}/deposit/intent',
            'POST api/v1/reservations/{id}/deposit/intent/revoke',
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions',
            'GET api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}',
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh',
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm',
            'GET api/v1/reservations/{id}/benefits-preview',
            'POST api/v1/reservations/{id}/voucher/apply',
            'POST api/v1/reservations/{id}/voucher/remove',
            'POST api/v1/reservations/{id}/loyalty/redeem',
            'POST api/v1/reservations/{id}/loyalty/redeem/release',
            'GET api/v1/reservations/{reservation_id}/bill',
            'GET api/v1/reservations/{reservation_id}/active-order',
            'GET api/v1/reservations/{reservation_id}/bill-preview',
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions',
            'GET api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}',
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh',
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm',
            'GET api/v1/waiting-list',
            'POST api/v1/waiting-list',
            'GET api/v1/waiting-list/{id}',
            'POST api/v1/waiting-list/{id}/accept',
            'POST api/v1/waiting-list/{id}/confirm-arrival',
            'POST api/v1/waiting-list/{id}/decline',
            'POST api/v1/waiting-list/{id}/cancel',
            'GET api/v1/me/loyalty',
            'GET api/v1/me/vouchers',
            'GET api/v1/me/data-export',
            'GET api/v1/me/privacy-requests',
            'POST api/v1/me/privacy-requests',
        ];

        foreach ($minimumSurface as $signature) {
            $this->assertArrayHasKey($signature, $operations, sprintf('Customer-web route [%s] is missing from OpenAPI.', $signature));
            $this->assertSame('full', $operations[$signature]['x-contract-grade'] ?? null, sprintf('Customer-web route [%s] is fallback-only.', $signature));
            $this->assertContains($signature, $curatedSignatures, sprintf('Customer-web route [%s] is missing from curated SDK/Postman scope.', $signature));

            $schemaRef = $this->firstSuccessSchemaRef($operations[$signature]);
            $this->assertNotNull($schemaRef, sprintf('Customer-web route [%s] must expose a typed success envelope.', $signature));
            $this->assertNotSame('#/components/schemas/GenericDataEnvelope', $schemaRef, sprintf('Customer-web route [%s] must not use the generic fallback envelope.', $signature));
        }

        $this->assertSame(
            '#/components/schemas/CustomerLoginRequest',
            data_get($operations['POST api/v1/auth/customer/login'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/RegisterRequest',
            data_get($operations['POST api/v1/auth/customer/register'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertArrayHasKey('password_confirmation', data_get($spec, 'components.schemas.RegisterRequest.properties', []));
        $this->assertSame(
            '#/components/schemas/StaffLoginRequest',
            data_get($operations['POST api/v1/auth/staff/login'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertArrayHasKey('session_id', data_get($spec, 'components.schemas.CustomerLoginRequest.properties', []));
        $this->assertArrayNotHasKey('session_id', data_get($spec, 'components.schemas.StaffLoginRequest.properties', []));
        $this->assertSame(
            ['refresh_cookie'],
            data_get($spec, 'components.schemas.StaffLoginRequest.properties.session_transport.enum')
        );
    }

    public function test_public_restaurant_profile_route_exposes_branch_hours_contract(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $operations = $this->specOperations($spec);

        $this->assertArrayHasKey('GET api/v1/restaurant/profile', $operations);
        $this->assertSame('full', $operations['GET api/v1/restaurant/profile']['x-contract-grade'] ?? null);
        $this->assertSame('public', $operations['GET api/v1/restaurant/profile']['x-auth-mode'] ?? null);
        $this->assertSame([], $operations['GET api/v1/restaurant/profile']['security'] ?? null);
        $this->assertSame(
            '#/components/schemas/RestaurantProfileEnvelope',
            data_get($operations['GET api/v1/restaurant/profile'], 'responses.200.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/RestaurantProfile',
            data_get($spec, 'components.schemas.RestaurantProfileEnvelope.properties.data.$ref')
        );
        $this->assertSame(
            'array',
            data_get($spec, 'components.schemas.RestaurantProfile.properties.business_hours.type')
        );
        $this->assertSame(
            'boolean',
            data_get($spec, 'components.schemas.RestaurantProfile.properties.current_status.properties.is_open.type')
        );
    }

    public function test_staff_refresh_and_logout_publish_browser_cookie_csrf_security(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $operations = $this->specOperations($spec);

        $this->assertSame('cookie', data_get($spec, 'components.securitySchemes.StaffBrowserRefreshCookie.in'));
        $this->assertSame('staff_web_refresh', data_get($spec, 'components.securitySchemes.StaffBrowserRefreshCookie.name'));
        $this->assertSame('header', data_get($spec, 'components.securitySchemes.StaffBrowserCsrfToken.in'));
        $this->assertSame('X-Staff-CSRF', data_get($spec, 'components.securitySchemes.StaffBrowserCsrfToken.name'));

        foreach (['POST api/v1/auth/staff/refresh', 'POST api/v1/auth/staff/logout'] as $signature) {
            $this->assertArrayHasKey($signature, $operations);
            $this->assertSame('staff_browser_refresh_cookie', $operations[$signature]['x-auth-mode'] ?? null);
            $this->assertSame([
                ['StaffBrowserRefreshCookie' => [], 'StaffBrowserCsrfToken' => []],
                ['StaffApiKey' => []],
            ], $operations[$signature]['security'] ?? null);
            $this->assertNotSame([['StaffApiKey' => []]], $operations[$signature]['security'] ?? null);
        }
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
            'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}' => ['status' => '200', 'schema' => '#/components/schemas/StaffReservationOrderEnvelope'],
            'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status' => ['status' => '200', 'schema' => '#/components/schemas/StaffReservationOrderEnvelope'],
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
            'POST api/v1/staff/orders/{order_id}/pay' => ['status' => '200', 'schema' => '#/components/schemas/StaffCheckoutSettlementEnvelope'],
            'POST api/v1/staff/orders/{order_id}/settlement/finalize' => ['status' => '200', 'schema' => '#/components/schemas/StaffCheckoutSettlementEnvelope'],
            'GET api/v1/staff/reservations/{reservation_id}/refund-preview' => ['status' => '200', 'schema' => '#/components/schemas/StaffRefundPreviewEnvelope'],
            'POST api/v1/staff/reservations/{reservation_id}/refund' => ['status' => '200', 'schema' => '#/components/schemas/StaffRefundEnvelope'],
            'POST api/v1/staff/reservations/{reservation_id}/refund-cancel' => ['status' => '200', 'schema' => '#/components/schemas/StaffRefundEnvelope'],
            'GET api/v1/staff/audit-trail' => ['status' => '200', 'schema' => '#/components/schemas/StaffAuditTrailEnvelope'],
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
            '#/components/schemas/StaffCheckInReservationRequest',
            data_get($operations['POST api/v1/staff/reservations/{id}/check-in'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertArrayHasKey('StaffCheckInReservationRequest', data_get($spec, 'components.schemas', []));
        $this->assertSame(
            '#/components/schemas/CreateTableOrderRequest',
            data_get($operations['POST api/v1/staff/tables/{table_id}/orders'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/AddOrderItemsRequest',
            data_get($operations['POST api/v1/staff/orders/{order_id}/items'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/UpdateOrderItemRequest',
            data_get($operations['PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/UpdateOrderItemStatusRequest',
            data_get($operations['POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/InviteWaitlistCustomerRequest',
            data_get($operations['POST api/v1/staff/waiting-list/{id}/notify'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/SeatWaitlistRequest',
            data_get($operations['POST api/v1/staff/waiting-list/{id}/seat'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/PayOrderRequest',
            data_get($operations['POST api/v1/staff/orders/{order_id}/pay'], 'requestBody.content.application/json.schema.$ref')
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
            '#/components/schemas/StaffAuditTrailEntry',
            data_get($spec, 'components.schemas.StaffAuditTrailEnvelope.properties.data.items.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaffAuditTrailRequest',
            data_get($spec, 'components.schemas.StaffAuditTrailEntry.properties.request.$ref')
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
            ['staff_api_key', 'staff_browser_session'],
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.auth_mode.enum')
        );
        $this->assertSame(
            ['refresh_cookie'],
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.session_transport.enum')
        );
        $this->assertSame(
            '#/components/schemas/StaffStartupContext',
            data_get($spec, 'components.schemas.StaffAuthSessionEnvelope.properties.data.properties.startup.$ref')
        );
        $this->assertSame(
            ['ops', 'kitchen', 'admin'],
            data_get($spec, 'components.schemas.StaffStartupContext.properties.primary_workspace.enum')
        );
        $this->assertSame(
            ['ops', 'kitchen', 'admin'],
            data_get($spec, 'components.schemas.StaffStartupContext.properties.available_workspaces.items.enum')
        );
        $this->assertSame(
            'integer',
            data_get($spec, 'components.schemas.StaffStartupContext.properties.default_branch_id.type')
        );
        $this->assertSame(
            'array',
            data_get($spec, 'components.schemas.StaffStartupContext.properties.allowed_branch_ids.type')
        );
        $this->assertSame(
            'array',
            data_get($spec, 'components.schemas.StaffStartupContext.properties.assigned_station_ids.type')
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

    public function test_staff_order_item_write_routes_have_non_generic_contract_guards(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $operations = $this->specOperations($spec);

        $expectations = [
            'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}' => '#/components/schemas/UpdateOrderItemRequest',
            'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status' => '#/components/schemas/UpdateOrderItemStatusRequest',
        ];

        foreach ($expectations as $signature => $requestSchemaRef) {
            $this->assertArrayHasKey($signature, $operations);
            $this->assertSame('full', $operations[$signature]['x-contract-grade'] ?? null, sprintf('Route [%s] must be full contract.', $signature));
            $this->assertSame([['StaffApiKey' => []]], $operations[$signature]['security'] ?? null, sprintf('Route [%s] must require staff API key auth.', $signature));
            $this->assertSame('#/components/schemas/StaffReservationOrderEnvelope', data_get($operations[$signature], 'responses.200.content.application/json.schema.$ref'));
            $this->assertSame('#/components/schemas/UnauthorizedError', data_get($operations[$signature], 'responses.401.content.application/json.schema.$ref'));
            $this->assertSame('#/components/schemas/ForbiddenError', data_get($operations[$signature], 'responses.403.content.application/json.schema.$ref'));
            $this->assertSame('#/components/schemas/StaleRowVersionError', data_get($operations[$signature], 'responses.409.content.application/json.schema.$ref'));
            $this->assertSame('#/components/schemas/ValidationError', data_get($operations[$signature], 'responses.422.content.application/json.schema.$ref'));
            $this->assertSame($requestSchemaRef, data_get($operations[$signature], 'requestBody.content.application/json.schema.$ref'));

            $parameters = collect((array) ($operations[$signature]['parameters'] ?? []))
                ->keyBy(fn (array $parameter): string => (string) ($parameter['name'] ?? ''));

            $this->assertSame('path', $parameters['order_id']['in'] ?? null, sprintf('Route [%s] must expose order_id as path param.', $signature));
            $this->assertSame('path', $parameters['order_item_id']['in'] ?? null, sprintf('Route [%s] must expose order_item_id as path param.', $signature));
            $this->assertTrue((bool) ($parameters['Idempotency-Key']['required'] ?? false), sprintf('Route [%s] must require Idempotency-Key.', $signature));
            $this->assertSame('header', $parameters['Idempotency-Key']['in'] ?? null);
        }

        $updateRequest = data_get($spec, 'components.schemas.UpdateOrderItemRequest', []);
        $this->assertSame(['order_row_version', 'row_version'], data_get($updateRequest, 'required'));
        $this->assertSame('integer', data_get($updateRequest, 'properties.qty.type'));
        $this->assertSame(1.0, data_get($updateRequest, 'properties.qty.minimum'));
        $this->assertSame(100.0, data_get($updateRequest, 'properties.qty.maximum'));
        $this->assertSame('string', data_get($updateRequest, 'properties.note.type'));
        $this->assertTrue((bool) data_get($updateRequest, 'properties.note.nullable'));
        $this->assertSame(200, data_get($updateRequest, 'properties.note.maxLength'));

        $statusRequest = data_get($spec, 'components.schemas.UpdateOrderItemStatusRequest', []);
        $this->assertSame(['order_row_version', 'row_version', 'status'], data_get($statusRequest, 'required'));
        $this->assertSame(['InProgress', 'Served', 'Cancelled'], data_get($statusRequest, 'properties.status.enum'));

        $this->assertSame(
            'string',
            data_get($spec, 'components.schemas.ReservationOrder.properties.items.items.properties.line_total.type')
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
            '#/components/schemas/UpdateBranchRequest',
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
            '#/components/schemas/DispatchKitchenTicketRequest',
            data_get($operations['POST api/v1/staff/orders/{order_id}/kitchen/dispatch'], 'requestBody.content.application/json.schema.$ref')
        );
        $this->assertSame(
            '#/components/schemas/StaleRowVersionError',
            data_get($operations['POST api/v1/staff/orders/{order_id}/kitchen/dispatch'], 'responses.409.content.application/json.schema.$ref')
        );
        $this->assertContains(
            'row_version',
            data_get($spec, 'components.schemas.DispatchKitchenTicketRequest.required', []),
            'KDS dispatch must advertise the order row_version as required.'
        );
        $this->assertSame(
            'integer',
            data_get($spec, 'components.schemas.DispatchKitchenTicketRequest.properties.row_version.type')
        );
        $this->assertEquals(
            1,
            data_get($spec, 'components.schemas.DispatchKitchenTicketRequest.properties.row_version.minimum')
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

    public function test_legacy_api_user_contract_matches_runtime_auth_and_top_level_schema(): void
    {
        $operations = $this->specOperations(app(OpenApiSpecService::class)->build());
        $operation = $operations['GET api/user'] ?? null;

        $this->assertIsArray($operation);
        $this->assertTrue((bool) ($operation['deprecated'] ?? false));
        $this->assertSame('full', $operation['x-contract-grade'] ?? null);
        $this->assertSame('customer_or_staff', $operation['x-auth-mode'] ?? null);
        $this->assertSame([['CustomerAccessToken' => []], ['StaffApiKey' => []]], $operation['security'] ?? null);
        $this->assertNotContains(['CustomerSessionId' => []], $operation['security'] ?? []);
        $this->assertSame(
            '#/components/schemas/ApiUserEnvelope',
            data_get($operation, 'responses.200.content.application/json.schema.$ref')
        );
        $this->assertContains('GET api/user', app(ApiContractMetadataRegistry::class)->knownEnvelopeExceptions());
    }

    public function test_customer_waiting_list_schema_uses_backend_lean_payload_not_legacy_staff_lifecycle(): void
    {
        $spec = app(OpenApiSpecService::class)->build();
        $customerProperties = data_get($spec, 'components.schemas.CustomerWaitingListEntry.properties', []);
        $customerRequired = data_get($spec, 'components.schemas.CustomerWaitingListEntry.required', []);
        $staffProperties = data_get($spec, 'components.schemas.StaffWaitingListEntry.properties', []);

        foreach (['notify_window', 'available_actions', 'arrival_confirmation', 'next_step', 'response_state'] as $field) {
            $this->assertArrayHasKey($field, $customerProperties);
            $this->assertContains($field, $customerRequired);
        }

        $this->assertSame(
            ['none', 'accepted', 'arrival_confirmed', 'declined'],
            data_get($customerProperties, 'response_state.enum')
        );

        foreach (['current_response_state', 'response', 'invite_window', 'invite_lifecycle', 'invite_hold', 'orchestration'] as $legacyField) {
            $this->assertArrayNotHasKey($legacyField, $customerProperties, sprintf('Customer waiting-list schema still exposes legacy field [%s].', $legacyField));
        }

        $this->assertArrayHasKey('current_response_state', $staffProperties);
        $this->assertArrayHasKey('orchestration', $staffProperties);
    }

    public function test_staff_web_route_surface_is_frozen_contract_or_explicitly_allowlisted(): void
    {
        $operations = $this->specOperations(app(OpenApiSpecService::class)->build());

        $fullContractRoutes = [
            'POST api/v1/auth/staff/login',
            'GET api/v1/auth/staff/me',
            'POST api/v1/auth/staff/refresh',
            'POST api/v1/auth/staff/logout',
            'GET api/v1/admin/inventory/ingredients',
            'GET api/v1/admin/inventory/purchase-orders',
            'GET api/v1/admin/inventory/suppliers',
            'GET api/v1/admin/settings/branches',
            'POST api/v1/admin/settings/branches',
            'GET api/v1/reservations',
            'POST api/v1/reservations',
            'GET api/v1/staff/audit-trail',
            'GET api/v1/staff/cashier/shifts',
            'GET api/v1/staff/cashier/shifts/{shift_id}',
            'POST api/v1/staff/cashier/shifts/{shift_id}/close',
            'GET api/v1/staff/cashier/shifts/current',
            'POST api/v1/staff/cashier/shifts/open',
            'GET api/v1/staff/conversations',
            'GET api/v1/staff/conversations/{conversation_id}',
            'POST api/v1/staff/conversations/{conversation_id}/internal-notes',
            'POST api/v1/staff/conversations/{conversation_id}/outbound-replies',
            'POST api/v1/staff/conversations/{conversation_id}/take-over',
            'POST api/v1/staff/conversations/{conversation_id}/unassign',
            'GET api/v1/staff/kitchen/changes',
            'GET api/v1/staff/kitchen/stations',
            'GET api/v1/staff/kitchen/stations/{station_id}/tickets',
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/bump',
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/fire',
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/recall',
            'GET api/v1/staff/menu/items',
            'GET api/v1/staff/orders/{order_id}',
            'POST api/v1/staff/orders/{order_id}/bill-snapshot',
            'POST api/v1/staff/orders/{order_id}/items',
            'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}',
            'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status',
            'POST api/v1/staff/orders/{order_id}/kitchen/dispatch',
            'POST api/v1/staff/orders/{order_id}/pay',
            'POST api/v1/staff/orders/{order_id}/settlement/finalize',
            'GET api/v1/staff/orders/{order_id}/settlement-preview',
            'GET api/v1/staff/reporting/daily-inventory',
            'GET api/v1/staff/reporting/daily-operations',
            'GET api/v1/staff/reporting/daily-sales',
            'GET api/v1/staff/reservations',
            'GET api/v1/staff/reservations/{reservation_id}',
            'POST api/v1/staff/reservations/{id}/check-in',
            'GET api/v1/staff/reservations/{reservation_id}/orders',
            'POST api/v1/staff/reservations/{reservation_id}/refund',
            'POST api/v1/staff/reservations/{reservation_id}/refund-cancel',
            'GET api/v1/staff/reservations/{reservation_id}/refund-preview',
            'POST api/v1/staff/service-sessions/walk-in',
            'GET api/v1/staff/tables/board',
            'GET api/v1/staff/tables/board/changes',
            'POST api/v1/staff/tables/{table_id}/orders',
            'GET api/v1/staff/waiting-list',
            'GET api/v1/staff/waiting-list/changes',
            'POST api/v1/staff/waiting-list/{id}/notify',
            'POST api/v1/staff/waiting-list/{id}/seat',
        ];

        $allowlistedRoutes = [
            'POST api/v1/admin/inventory/ingredients' => 'Inventory create remains generic until the admin write contract batch.',
            'POST api/v1/admin/inventory/purchase-orders' => 'Purchase order create remains generic until the admin write contract batch.',
            'POST api/v1/admin/inventory/suppliers' => 'Supplier create remains generic until the admin write contract batch.',
            'PATCH api/v1/reservations/{id}/status' => 'Legacy reservation status mutation is still raw while staff POS uses full check-in and lifecycle routes.',
            'GET api/v1/staff/finance/invoices/{reservation_id}' => 'Finance invoice read belongs to the finance contract batch.',
            'POST api/v1/staff/finance/invoices/{reservation_id}/issue' => 'Finance invoice issue belongs to the finance contract batch.',
            'GET api/v1/staff/finance/reconciliation' => 'Finance reconciliation read belongs to the finance contract batch.',
            'GET api/v1/staff/finance/reconciliation/{reservation_id}' => 'Finance reconciliation detail belongs to the finance contract batch.',
            'GET api/v1/staff/reservations/{reservation_id}/active-order' => 'Active order lookup remains generic while order read routes are hardened.',
            'POST api/v1/staff/reservations/{id}/assign-best-fit' => 'Table assignment helper remains generic until floor operations mutation contracts.',
            'POST api/v1/staff/reservations/{id}/assign-table' => 'Table assignment helper remains generic until floor operations mutation contracts.',
            'POST api/v1/staff/reservations/{id}/move-table' => 'Move-table helper remains generic until floor operations mutation contracts.',
            'GET api/v1/staff/tables/{table_id}/active-order' => 'Table active order lookup remains generic while order read routes are hardened.',
            'POST api/v1/staff/tables/{table_id}/release' => 'Table release remains generic until floor operations mutation contracts.',
            'POST api/v1/staff/waiting-list' => 'Staff waiting-list create is still raw; customer owner waiting-list create is full contract.',
            'POST api/v1/staff/waiting-list/{id}/advance' => 'Semi-automation advance remains feature-batched and explicitly raw.',
            'POST api/v1/staff/waiting-list/{id}/cancel' => 'Staff waiting-list cancel remains raw until staff waiting-list write contracts are promoted.',
        ];

        foreach ($fullContractRoutes as $signature) {
            $this->assertArrayHasKey($signature, $operations, sprintf('Staff-web full route [%s] is missing from frozen OpenAPI.', $signature));
            $this->assertSame('full', $operations[$signature]['x-contract-grade'] ?? null, sprintf('Staff-web route [%s] must stay full contract.', $signature));
            $this->assertNotSame(
                '#/components/schemas/GenericDataEnvelope',
                $this->firstSuccessSchemaRef($operations[$signature]),
                sprintf('Staff-web route [%s] must not use the generic fallback envelope.', $signature)
            );
        }

        foreach ($allowlistedRoutes as $signature => $reason) {
            $this->assertNotSame('', trim($reason), sprintf('Staff-web allowlisted route [%s] must document why it is not full contract yet.', $signature));
            $this->assertArrayHasKey($signature, $operations, sprintf('Staff-web allowlisted route [%s] is missing from frozen OpenAPI.', $signature));
        }
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
     * @return list<string>
     */
    private function curatedApiConsumerSignatures(): array
    {
        $signatures = [];

        foreach ((array) config('api_artifacts.postman.groups', []) as $group) {
            foreach ((array) ($group['signatures'] ?? []) as $signature) {
                $signatures[] = (string) $signature;
            }
        }

        return array_values(array_unique($signatures));
    }

    /**
     * @param  array<string,mixed>  $operation
     */
    private function firstSuccessSchemaRef(array $operation): ?string
    {
        foreach (['200', '201', '202', '204'] as $status) {
            $ref = data_get($operation, 'responses.'.$status.'.content.application/json.schema.$ref');
            if (is_string($ref) && trim($ref) !== '') {
                return $ref;
            }
        }

        return null;
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

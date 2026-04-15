<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApiConsumerArtifactsGenerateCommandTest extends TestCase
{
    private string $root = 'storage/framework/testing/api_consumer_artifacts';

    private string $manifestPath = 'storage/framework/testing/api_consumer_artifacts/uat-manifest.json';

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path($this->root));
        parent::tearDown();
    }

    public function test_api_consumer_artifact_command_generates_collection_templates_sdk_and_uat_environment(): void
    {
        File::ensureDirectoryExists(dirname(base_path($this->manifestPath)));
        File::put(
            base_path($this->manifestPath),
            json_encode($this->sampleManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $exitCode = Artisan::call('booking:api-artifacts:generate', [
            '--json' => true,
            '--output-root' => $this->root,
            '--uat-manifest' => $this->manifestPath,
        ]);

        self::assertSame(0, $exitCode);

        /** @var array<string,mixed> $payload */
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue((bool) ($payload['ok'] ?? false));
        self::assertSame($this->root, $payload['output_root'] ?? null);
        self::assertTrue((bool) ($payload['summary']['uat_environment_generated'] ?? false));
        self::assertSame(6, $payload['summary']['mutation_contract_group_count'] ?? null);

        $collectionPath = base_path((string) ($payload['artifacts']['collection'] ?? ''));
        $localEnvPath = base_path((string) ($payload['artifacts']['local_environment'] ?? ''));
        $uatEnvPath = base_path((string) ($payload['artifacts']['uat_environment'] ?? ''));
        $sdkPath = base_path((string) ($payload['artifacts']['sdk_typescript'] ?? ''));
        $sdkEnumsPath = base_path((string) ($payload['artifacts']['enum_state_typescript'] ?? ''));
        $mutationContractPath = base_path((string) ($payload['artifacts']['mutation_contract'] ?? ''));
        $enumStateJsonPath = base_path((string) ($payload['artifacts']['enum_state_json'] ?? ''));

        self::assertArrayHasKey('mutation_contract', $payload['artifacts'] ?? []);
        self::assertArrayHasKey('enum_state_json', $payload['artifacts'] ?? []);
        self::assertArrayHasKey('enum_state_typescript', $payload['artifacts'] ?? []);
        self::assertFileExists($collectionPath);
        self::assertFileExists($localEnvPath);
        self::assertFileExists($uatEnvPath);
        self::assertFileExists($sdkPath);
        self::assertFileExists($sdkEnumsPath);
        self::assertFileExists($mutationContractPath);
        self::assertFileExists($enumStateJsonPath);

        /** @var array<string,mixed> $collection */
        $collection = json_decode((string) File::get($collectionPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('RestaurantPOS API Consumer Collection', $collection['info']['name'] ?? null);
        self::assertStringContainsString('Use the TypeScript SDK for curated priority routes', (string) ($collection['info']['description'] ?? ''));
        self::assertStringContainsString('do not treat controllers/resources as the consumer contract', (string) ($collection['info']['description'] ?? ''));
        self::assertSame('Auth', $collection['item'][0]['name'] ?? null);
        self::assertStringContainsString('/api/v1/auth/customer/login', json_encode($collection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        self::assertStringContainsString('/api/v1/payments/providers/{{providerCode}}/webhooks', json_encode($collection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $preorderRequest = $this->findCollectionRequestByUrl((array) ($collection['item'] ?? []), '{{baseUrl}}/api/v1/reservations/{{reservationIdPreorder}}/preorder');
        self::assertNotNull($preorderRequest);
        self::assertFalse($this->headerDisabledState($preorderRequest, 'X-Session-Id'));

        $reservationShowRequest = $this->findCollectionRequestByUrl((array) ($collection['item'] ?? []), '{{baseUrl}}/api/v1/reservations/{{reservationId}}');
        self::assertNotNull($reservationShowRequest);
        self::assertFalse($this->headerDisabledState($reservationShowRequest, 'X-Session-Id'));

        $loyaltyRequest = $this->findCollectionRequestByUrl((array) ($collection['item'] ?? []), '{{baseUrl}}/api/v1/me/loyalty');
        self::assertNotNull($loyaltyRequest);
        self::assertNull($this->headerDisabledState($loyaltyRequest, 'X-Session-Id'));

        /** @var array<string,mixed> $localEnv */
        $localEnv = json_decode((string) File::get($localEnvPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('RestaurantPOS Local', $localEnv['name'] ?? null);
        self::assertSame('http://127.0.0.1:8000', $this->environmentValue($localEnv, 'baseUrl'));
        self::assertSame('', $this->environmentValue($localEnv, 'staffApiKey'));

        /** @var array<string,mixed> $uatEnv */
        $uatEnv = json_decode((string) File::get($uatEnvPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('RestaurantPOS UAT', $uatEnv['name'] ?? null);
        self::assertSame('http://127.0.0.1:8000', $this->environmentValue($uatEnv, 'baseUrl'));
        self::assertSame('spk_staff_uat', $this->environmentValue($uatEnv, 'staffApiKey'));
        self::assertSame('101', $this->environmentValue($uatEnv, 'reservationIdDeposit'));
        self::assertSame('401', $this->environmentValue($uatEnv, 'tableTemplateId'));

        $sdk = (string) File::get($sdkPath);
        self::assertStringContainsString('export class RestaurantPosClient', $sdk);
        self::assertStringContainsString('this.fetchImpl = globalThis.fetch.bind(globalThis);', $sdk);
        self::assertStringContainsString('this.fetchImpl = providedFetch === globalThis.fetch', $sdk);
        self::assertStringContainsString('routeSupportsCustomerSession: boolean,', $sdk);
        self::assertStringContainsString('this.applyAuthHeaders(headers, authMode, options.authMode ?? \'auto\', routeSupportsCustomerSession);', $sdk);
        self::assertStringContainsString('this.applyCustomerHeaders(headers, customerToken, customerSessionId, routeSupportsCustomerSession);', $sdk);
        self::assertStringContainsString('if (routeSupportsCustomerSession && customerSessionId)', $sdk);
        self::assertStringContainsString('postV1AuthCustomerLogin', $sdk);
        self::assertStringContainsString('getV1MenuItems', $sdk);
        self::assertStringContainsString('getV1MeLoyalty', $sdk);
        self::assertStringContainsString('postV1Reservations', $sdk);
        self::assertStringContainsString('getV1ReservationsIdPreorder', $sdk);
        self::assertStringContainsString('putV1ReservationsIdPreorder', $sdk);
        self::assertStringContainsString('deleteV1ReservationsIdPreorder', $sdk);
        self::assertStringContainsString('getV1AuthStaffMe', $sdk);
        self::assertStringContainsString('staffTablesBoard', $sdk);
        self::assertStringContainsString('getV1StaffTablesBoardChanges', $sdk);
        self::assertStringContainsString('postV1StaffReservationsIdCheckIn', $sdk);
        self::assertStringContainsString('postV1StaffTablesTableIdOrders', $sdk);
        self::assertStringContainsString('postV1StaffOrdersOrderIdItems', $sdk);
        self::assertStringContainsString('getV1StaffOrdersOrderId', $sdk);
        self::assertStringContainsString('getV1StaffAuditTrail', $sdk);
        self::assertStringContainsString('getV1StaffWaitingList', $sdk);
        self::assertStringContainsString('postV1StaffWaitingListIdNotify', $sdk);
        self::assertStringContainsString('postV1StaffWaitingListIdSeat', $sdk);
        self::assertStringContainsString('getV1StaffCashierShiftsCurrent', $sdk);
        self::assertStringContainsString('postV1StaffCashierShiftsOpen', $sdk);
        self::assertStringContainsString('getV1StaffCashierShiftsShiftId', $sdk);
        self::assertStringContainsString('postV1StaffCashierShiftsShiftIdClose', $sdk);
        self::assertStringContainsString('getV1StaffOrdersOrderIdSettlementPreview', $sdk);
        self::assertStringContainsString('postV1StaffOrdersOrderIdSettlementFinalize', $sdk);
        self::assertStringContainsString('getV1StaffReservationsReservationIdRefundPreview', $sdk);
        self::assertStringContainsString('postV1StaffReservationsReservationIdRefund', $sdk);
        self::assertStringContainsString('postV1StaffReservationsReservationIdRefundCancel', $sdk);
        self::assertStringContainsString('CustomerMenuItemsCollectionEnvelope', $sdk);
        self::assertStringContainsString('CustomerLoyaltySummaryEnvelope', $sdk);
        self::assertStringContainsString('CustomerReservationPreorderEnvelope', $sdk);
        self::assertStringContainsString('ReservationEnvelope', $sdk);
        self::assertStringContainsString('StaffReservationOrderEnvelope', $sdk);
        self::assertStringContainsString('StaffTableBoardEnvelope', $sdk);
        self::assertStringContainsString('StaffTableBoardAssignedReservation', $sdk);
        self::assertStringContainsString('StaffOrderReadEnvelope', $sdk);
        self::assertStringContainsString('StaffOrderReadCustomer', $sdk);
        self::assertStringContainsString('StaffAuditTrailEnvelope', $sdk);
        self::assertStringContainsString('StaffWaitingListCollectionEnvelope', $sdk);
        self::assertStringContainsString('RestaurantPosApiError', $sdk);
        self::assertStringContainsString('user?: (StaffAuthUser) | null;', $sdk);
        self::assertStringContainsString('capabilities: Array<string>;', $sdk);
        self::assertStringContainsString('known_capabilities: Array<string>;', $sdk);
        self::assertStringContainsString('capability_source: string;', $sdk);
        self::assertStringContainsString('startup: StaffStartupContext;', $sdk);
        self::assertStringContainsString('export type StaffStartupContext = {', $sdk);
        self::assertStringContainsString('default_branch: (StaffStartupBranch) | null;', $sdk);
        self::assertStringContainsString('branch_access: StaffBranchAccessContext;', $sdk);
        self::assertStringContainsString('active_cashier_shift: (StaffStartupCashierShift) | null;', $sdk);
        self::assertStringContainsString('navigation: StaffNavigationContext;', $sdk);
        self::assertStringContainsString('readiness: StaffStartupReadiness;', $sdk);
        self::assertStringContainsString('export type StaffBranchAccessContext = {', $sdk);
        self::assertStringContainsString('accessible_branch_ids: Array<number>;', $sdk);
        self::assertStringContainsString('has_multi_branch_access: boolean;', $sdk);
        self::assertStringContainsString('export type StaffNavigationItem = {', $sdk);
        self::assertStringContainsString('required_capabilities: Array<string>;', $sdk);
        self::assertStringContainsString('primary_route: string;', $sdk);
        self::assertStringContainsString('export type StaffNavigationContext = Record<string, StaffNavigationItem>;', $sdk);
        self::assertStringContainsString('export type StaffStartupReadiness = {', $sdk);
        self::assertStringContainsString('granted_capability_count: number;', $sdk);
        self::assertStringContainsString('known_capability_count: number;', $sdk);
        self::assertStringContainsString('table: (RestaurantTable) | null;', $sdk);
        self::assertStringContainsString('reservation: (ReservationSummary) | null;', $sdk);
        self::assertStringContainsString('customer: (StaffOrderReadCustomer) | null;', $sdk);
        self::assertStringContainsString('reservations: Array<StaffTableBoardAssignedReservation>;', $sdk);
        self::assertStringContainsString('reservation: (StaffTableBoardAssignedReservation) | null;', $sdk);
        self::assertStringContainsString('check_in: (StaffTableBoardCheckInAction) | null;', $sdk);
        self::assertStringContainsString('move_table: (StaffTableBoardMoveTableAction) | null;', $sdk);
        self::assertStringContainsString('current_fit: (StaffTableBoardFit) | null;', $sdk);
        self::assertStringContainsString('active_order: (StaffTableBoardActiveOrder) | null;', $sdk);
        self::assertStringNotContainsString('user?: StaffAuthUser | unknown;', $sdk);
        self::assertStringNotContainsString('table: RestaurantTable | unknown;', $sdk);
        self::assertStringNotContainsString('reservation: ReservationSummary | unknown;', $sdk);
        self::assertStringNotContainsString('reservations: Array<Record<string, unknown>>;', $sdk);
        self::assertStringNotContainsString('check_in: Record<string, unknown> | unknown;', $sdk);
        self::assertStringNotContainsString('move_table: Record<string, unknown> | unknown;', $sdk);

        $sdkReadmePath = base_path((string) ($payload['artifacts']['sdk_readme'] ?? ''));
        self::assertFileExists($sdkReadmePath);

        $sdkReadme = (string) File::get($sdkReadmePath);
        self::assertStringContainsString('php artisan booking:api-contract --write', $sdkReadme);
        self::assertStringContainsString('php artisan booking:api-artifacts:generate', $sdkReadme);
        self::assertStringContainsString('php artisan booking:release-manifest --write', $sdkReadme);
        self::assertStringContainsString('The frozen OpenAPI artifact remains the only official schema source', $sdkReadme);
        self::assertStringContainsString('Do not treat controllers, resources, or ad-hoc route inspection as contract sources.', $sdkReadme);
        self::assertStringContainsString('build/api-consumer/mutation-contracts.md', $sdkReadme);
        self::assertStringContainsString('customerSessionId: () => sessionStorage.getItem(\'customerSessionId\') ?? undefined,', $sdkReadme);
        self::assertStringContainsString('keeps `X-Customer-Token` and `X-Session-Id` together when both are configured', $sdkReadme);
        self::assertStringContainsString('## Curated priority batch', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/auth/customer/login', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/menu/items', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/me/loyalty', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/reservations/{id}/preorder', $sdkReadme);
        self::assertStringContainsString('- PUT api/v1/reservations/{id}/preorder', $sdkReadme);
        self::assertStringContainsString('- DELETE api/v1/reservations/{id}/preorder', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/auth/staff/me', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/tables/board', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/tables/board/changes', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/reservations/{id}/check-in', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/tables/{table_id}/orders', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/orders/{order_id}/items', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/orders/{order_id}', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/cashier/shifts/current', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/cashier/shifts/open', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/cashier/shifts/{shift_id}', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/cashier/shifts/{shift_id}/close', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/orders/{order_id}/settlement-preview', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/orders/{order_id}/settlement/finalize', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/reservations/{reservation_id}/refund-preview', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/reservations/{reservation_id}/refund', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/reservations/{reservation_id}/refund-cancel', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/audit-trail', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/waiting-list', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/waiting-list/changes', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/waiting-list/{id}/notify', $sdkReadme);
        self::assertStringContainsString('- POST api/v1/staff/waiting-list/{id}/seat', $sdkReadme);
        self::assertStringContainsString('- GET api/v1/staff/conversations', $sdkReadme);
        self::assertStringContainsString('Reference` folder in `build/api-consumer/postman/RestaurantPOS.postman_collection.json`', $sdkReadme);
        self::assertStringContainsString('build/api-consumer/sdk/typescript/restaurantpos-enums.ts', $sdkReadme);
        self::assertStringContainsString('php artisan booking:release-build', $sdkReadme);

        $mutationContract = (string) File::get($mutationContractPath);
        self::assertStringContainsString('# RestaurantPOS Mutation Contract Matrix', $mutationContract);
        self::assertStringContainsString('`SDK (fallback)`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/reservations`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/reservations/{id}/cancel`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/waiting-list/{id}/cancel`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/staff/cashier/shifts/open`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/staff/cashier/shifts/{shift_id}/close`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/staff/orders/{order_id}/items`', $mutationContract);
        self::assertStringContainsString('`PATCH api/v1/admin/settings/branches/{id}`', $mutationContract);
        self::assertStringContainsString('body.session_id required with hold_id', $mutationContract);
        self::assertStringContainsString('X-Session-Id accepted', $mutationContract);
        self::assertStringContainsString('staff capability boundary', $mutationContract);
        self::assertStringContainsString('`OpenAPI`', $mutationContract);
        self::assertStringContainsString('`OpenAPI (fallback)`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/staff/reservations/{id}/check-in` | `SDK`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/staff/tables/{table_id}/orders` | `SDK`', $mutationContract);
        self::assertStringContainsString('`POST api/v1/staff/orders/{order_id}/items` | `SDK`', $mutationContract);

        $sdkEnums = (string) File::get($sdkEnumsPath);
        self::assertStringContainsString('export const reservationStatusValues', $sdkEnums);
        self::assertStringContainsString('export type ReservationStatus', $sdkEnums);
        self::assertStringContainsString('semanticAliases', $sdkEnums);
        self::assertStringContainsString('checked_in', $sdkEnums);

        /** @var array<string,mixed> $enumState */
        $enumState = json_decode((string) File::get($enumStateJsonPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('App\\Enums\\ReservationStatus', data_get($enumState, 'enums.ReservationStatus.php_class'));
        self::assertSame('Reserved', data_get($enumState, 'enums.ReservationStatus.semantic_aliases.checked_in'));
        self::assertContains('Success', (array) data_get($enumState, 'enums.PaymentStatus.values', []));
    }

    public function test_api_consumer_artifact_command_keeps_postman_artifacts_deterministic_for_same_inputs(): void
    {
        File::ensureDirectoryExists(dirname(base_path($this->manifestPath)));
        File::put(
            base_path($this->manifestPath),
            json_encode($this->sampleManifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $firstExitCode = Artisan::call('booking:api-artifacts:generate', [
            '--json' => true,
            '--output-root' => $this->root,
            '--uat-manifest' => $this->manifestPath,
        ]);
        self::assertSame(0, $firstExitCode);

        /** @var array<string,mixed> $firstPayload */
        $firstPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $firstCollectionHash = hash_file('sha256', base_path((string) ($firstPayload['artifacts']['collection'] ?? '')));
        $firstLocalEnvironmentHash = hash_file('sha256', base_path((string) ($firstPayload['artifacts']['local_environment'] ?? '')));
        $firstStagingEnvironmentHash = hash_file('sha256', base_path((string) ($firstPayload['artifacts']['staging_environment'] ?? '')));

        $secondExitCode = Artisan::call('booking:api-artifacts:generate', [
            '--json' => true,
            '--output-root' => $this->root,
            '--uat-manifest' => $this->manifestPath,
        ]);
        self::assertSame(0, $secondExitCode);

        /** @var array<string,mixed> $secondPayload */
        $secondPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($firstCollectionHash, hash_file('sha256', base_path((string) ($secondPayload['artifacts']['collection'] ?? ''))));
        self::assertSame($firstLocalEnvironmentHash, hash_file('sha256', base_path((string) ($secondPayload['artifacts']['local_environment'] ?? ''))));
        self::assertSame($firstStagingEnvironmentHash, hash_file('sha256', base_path((string) ($secondPayload['artifacts']['staging_environment'] ?? ''))));
    }

    /**
     * @param  array<string,mixed>  $environment
     */
    private function environmentValue(array $environment, string $key): string
    {
        $match = collect((array) ($environment['values'] ?? []))
            ->first(fn (array $entry): bool => (string) ($entry['key'] ?? '') === $key);

        return (string) ($match['value'] ?? '');
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @return array<string,mixed>|null
     */
    private function findCollectionRequestByUrl(array $items, string $url): ?array
    {
        foreach ($items as $item) {
            $request = (array) ($item['request'] ?? []);
            $requestUrl = (string) ($request['url'] ?? '');
            if ($requestUrl === $url || str_starts_with($requestUrl, $url.'?')) {
                return $request;
            }

            $nested = (array) ($item['item'] ?? []);
            if ($nested === []) {
                continue;
            }

            $match = $this->findCollectionRequestByUrl($nested, $url);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $request
     */
    private function headerDisabledState(array $request, string $headerKey): ?bool
    {
        foreach ((array) ($request['header'] ?? []) as $header) {
            if ((string) ($header['key'] ?? '') !== $headerKey) {
                continue;
            }

            return (bool) ($header['disabled'] ?? false);
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function sampleManifest(): array
    {
        return [
            'pack' => [
                'base_url' => 'http://127.0.0.1:8000',
            ],
            'branch' => [
                'branch_id' => 10,
                'currency' => 'VND',
            ],
            'auth' => [
                'customer_primary' => [
                    'username' => 'uat.customer.primary',
                    'password' => 'UatDemo!123',
                ],
                'staff' => [
                    'username' => 'uat.staff',
                    'password' => 'UatDemo!123',
                    'api_key' => 'spk_staff_uat',
                ],
                'admin' => [
                    'username' => 'uat.admin',
                    'password' => 'UatDemo!123',
                    'api_key' => 'spk_admin_uat',
                ],
            ],
            'scenarios' => [
                'availability_hold_reservation' => [
                    'session_id' => 'session-uat-001',
                    'from_utc' => '2026-04-05T12:00:00Z',
                    'to_utc' => '2026-04-05T14:00:00Z',
                    'guest_count' => 2,
                    'preferred_table_ids' => [11],
                ],
                'deposit_self_pay' => [
                    'reservation_id' => 101,
                    'payment_amount' => 100000,
                    'provider_code' => 'simulated',
                ],
                'dine_in_checkout' => [
                    'reservation_id' => 102,
                    'table_id' => 12,
                    'menu_item_ids' => [201, 202],
                ],
                'benefits' => [
                    'reservation_id' => 103,
                    'user_voucher_id' => 301,
                    'loyalty_points' => 100,
                ],
                'refund_partial' => [
                    'reservation_id' => 104,
                    'refund_amount' => 50000,
                    'refund_scope' => 'deposit',
                ],
                'refund_cancel' => [
                    'reservation_id' => 105,
                ],
                'admin_master_data' => [
                    'template_id' => 401,
                ],
                'conversation_inbox' => [
                    'conversation_id' => 'conv-uat-001',
                ],
                'waiting_list_lifecycle' => [
                    'customer_user_id' => 555,
                ],
            ],
            'reservations' => [
                'deposit_pending' => ['row_version' => 1],
                'dine_in_checkin' => ['row_version' => 2],
                'benefits_pending' => ['row_version' => 3],
                'refund_partial_ready' => ['row_version' => 4],
                'refund_cancel_ready' => ['row_version' => 5],
            ],
        ];
    }
}

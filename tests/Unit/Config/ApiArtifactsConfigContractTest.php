<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class ApiArtifactsConfigContractTest extends TestCase
{
    public function test_priority_consumer_groups_and_safe_environment_templates_are_registered(): void
    {
        self::assertSame('build/api-consumer', config('api_artifacts.output_root'));

        $groups = collect((array) config('api_artifacts.postman.groups', []))
            ->pluck('name')
            ->all();

        self::assertSame([
            'Auth',
            'Availability + Reservation',
            'Deposit Self-Pay',
            'Dine-In + Checkout',
            'Staff Lookup',
            'Refunds',
            'Waiting List',
            'Benefits',
            'Admin Master Data',
            'Conversation Inbox',
            'Payment Webhooks',
            'Health',
        ], $groups);

        self::assertTrue((bool) config('api_artifacts.postman.include_full_contract_reference'));
        self::assertSame('Reference', config('api_artifacts.postman.reference_folder_name'));
        self::assertNotEmpty(config('api_artifacts.postman.capture_variables.POST api/v1/auth/customer/login.customerToken'));
        self::assertNotEmpty(config('api_artifacts.postman.capture_variables.POST api/v1/auth/staff/login.staffApiKey'));
        self::assertSame('{{customerUsername}}', config('api_artifacts.postman.body_overrides.POST api/v1/auth/customer/login.identifier'));
        self::assertSame('{{providerSessionCode}}', config('api_artifacts.postman.body_overrides.POST api/v1/payments/providers/{provider_code}/webhooks.provider_session_code'));

        self::assertSame('sdk/typescript/restaurantpos-sdk.ts', config('api_artifacts.sdk.typescript'));
        self::assertSame('sdk/typescript/restaurantpos-enums.ts', config('api_artifacts.sdk.enums'));
        self::assertSame('sdk/typescript/README.md', config('api_artifacts.sdk.readme'));
        self::assertSame('enum-state-map.json', config('api_artifacts.enums.json'));
        self::assertSame('sdk/typescript/restaurantpos-enums.ts', config('api_artifacts.enums.typescript'));
        self::assertSame('mutation-contracts.md', config('api_artifacts.mutation_contract.readme'));
        self::assertContains('GET api/v1/menu/items', config('api_artifacts.postman.groups.1.signatures'));
        self::assertContains('GET api/v1/reservations/{id}/preorder', config('api_artifacts.postman.groups.1.signatures'));
        self::assertContains('PUT api/v1/reservations/{id}/preorder', config('api_artifacts.postman.groups.1.signatures'));
        self::assertContains('DELETE api/v1/reservations/{id}/preorder', config('api_artifacts.postman.groups.1.signatures'));
        self::assertContains('POST api/v1/reservations/{id}/cancel', config('api_artifacts.mutation_contract.groups.0.signatures'));
        self::assertContains('POST api/v1/reservations/{reservation_id}/bill/payment-sessions', config('api_artifacts.mutation_contract.groups.0.signatures'));
        self::assertContains('POST api/v1/waiting-list/{id}/cancel', config('api_artifacts.mutation_contract.groups.1.signatures'));
        self::assertContains('POST api/v1/staff/orders/{order_id}/items', config('api_artifacts.mutation_contract.groups.3.signatures'));
        self::assertContains('PATCH api/v1/admin/settings/branches/{id}', config('api_artifacts.mutation_contract.groups.4.signatures'));
        self::assertContains('GET api/v1/staff/tables/board', config('api_artifacts.postman.groups.3.signatures'));
        self::assertContains('GET api/v1/staff/tables/board/changes', config('api_artifacts.postman.groups.3.signatures'));
        self::assertContains('GET api/v1/staff/cashier/shifts/current', config('api_artifacts.postman.groups.3.signatures'));
        self::assertContains('GET api/v1/staff/reservations', config('api_artifacts.postman.groups.4.signatures'));
        self::assertContains('GET api/v1/staff/reservations/{reservation_id}/orders', config('api_artifacts.postman.groups.4.signatures'));
        self::assertContains('GET api/v1/staff/cashier/shifts', config('api_artifacts.postman.groups.4.signatures'));
        self::assertContains('GET api/v1/staff/waiting-list', config('api_artifacts.postman.groups.6.signatures'));
        self::assertContains('GET api/v1/staff/waiting-list/changes', config('api_artifacts.postman.groups.6.signatures'));
        self::assertContains('GET api/v1/me/loyalty', config('api_artifacts.postman.groups.7.signatures'));
        self::assertSame('boardZone', config('api_artifacts.postman.parameter_aliases.GET api/v1/staff/tables/board.query.zone'));
        self::assertSame('boardAfterVersion', config('api_artifacts.postman.parameter_aliases.GET api/v1/staff/tables/board/changes.query.after_version'));
        self::assertSame('waitingListStatus', config('api_artifacts.postman.parameter_aliases.GET api/v1/staff/waiting-list.query.status'));
        self::assertSame('waitingListAfterVersion', config('api_artifacts.postman.parameter_aliases.GET api/v1/staff/waiting-list/changes.query.after_version'));
        self::assertSame('reservationIdPreorder', config('api_artifacts.postman.parameter_aliases.GET api/v1/reservations/{id}/preorder.path.id'));
        self::assertSame('{{reservationRowVersionPreorder}}', config('api_artifacts.postman.body_overrides.PUT api/v1/reservations/{id}/preorder.row_version'));
        self::assertSame('1', config('api_artifacts.environments.local.includeHolds'));
        self::assertSame('0', config('api_artifacts.environments.local.boardAfterVersion'));
        self::assertSame('Waiting', config('api_artifacts.environments.local.waitingListStatus'));
        self::assertSame('-priority', config('api_artifacts.environments.local.waitingListSort'));
        self::assertSame('20', config('api_artifacts.environments.local.loyaltySummaryLimit'));
        self::assertSame('http://127.0.0.1:8000', config('api_artifacts.environments.local.baseUrl'));
        self::assertSame('', config('api_artifacts.environments.local.paymentWebhookSecret'));
        self::assertSame('1', config('api_artifacts.environments.staging.waitingListActiveOnly'));
        self::assertSame('https://staging.example.invalid', config('api_artifacts.environments.staging.baseUrl'));
        self::assertSame('', config('api_artifacts.environments.staging.staffApiKey'));
    }
}

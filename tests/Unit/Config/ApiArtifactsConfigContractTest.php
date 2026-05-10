<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class ApiArtifactsConfigContractTest extends TestCase
{
    public function test_priority_consumer_groups_and_safe_environment_templates_are_registered(): void
    {
        self::assertSame('build/api-consumer', config('api_artifacts.output_root'));

        $groups = collect((array) config('api_artifacts.postman.groups', []));
        $groupNames = $groups
            ->pluck('name')
            ->all();
        $groupsByName = $groups->keyBy('name');
        $mutationGroupsByName = collect((array) config('api_artifacts.mutation_contract.groups', []))
            ->keyBy('name');

        self::assertSame([
            'Auth',
            'Availability + Reservation',
            'Deposit Self-Pay',
            'Dine-In + Checkout',
            'Kitchen / KDS',
            'Staff Lookup',
            'Operations Read Models',
            'Refunds',
            'Staff Finance',
            'Waiting List',
            'Benefits',
            'Customer Privacy',
            'Admin Master Data',
            'Admin Benefits',
            'Admin Privacy',
            'Conversation Inbox',
            'Payment Webhooks',
            'Health',
        ], $groupNames);

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
        self::assertContains('GET api/v1/menu/items', $groupsByName['Availability + Reservation']['signatures']);
        self::assertContains('GET api/v1/reservations/{id}/preorder', $groupsByName['Availability + Reservation']['signatures']);
        self::assertContains('PUT api/v1/reservations/{id}/preorder', $groupsByName['Availability + Reservation']['signatures']);
        self::assertContains('DELETE api/v1/reservations/{id}/preorder', $groupsByName['Availability + Reservation']['signatures']);
        self::assertContains('POST api/v1/reservations/{id}/cancel', $mutationGroupsByName['Customer reservation + preorder + deposit + bill payment']['signatures']);
        self::assertContains('POST api/v1/reservations/{reservation_id}/bill/payment-sessions', $mutationGroupsByName['Customer reservation + preorder + deposit + bill payment']['signatures']);
        self::assertContains('POST api/v1/waiting-list/{id}/cancel', $mutationGroupsByName['Customer waiting list']['signatures']);
        self::assertContains('POST api/v1/staff/waiting-list', $mutationGroupsByName['Staff waiting list']['signatures']);
        self::assertContains('POST api/v1/staff/waiting-list/{id}/cancel', $mutationGroupsByName['Staff waiting list']['signatures']);
        self::assertContains('POST api/v1/staff/waiting-list/{id}/advance', $mutationGroupsByName['Staff waiting list']['signatures']);
        self::assertContains('POST api/v1/staff/orders/{order_id}/items', $mutationGroupsByName['Staff order + checkout + cashier core']['signatures']);
        self::assertContains('POST api/v1/staff/reservations/{id}/assign-table', $mutationGroupsByName['Staff order + checkout + cashier core']['signatures']);
        self::assertContains('POST api/v1/staff/reservations/{id}/assign-best-fit', $mutationGroupsByName['Staff order + checkout + cashier core']['signatures']);
        self::assertContains('POST api/v1/staff/reservations/{id}/move-table', $mutationGroupsByName['Staff order + checkout + cashier core']['signatures']);
        self::assertContains('POST api/v1/staff/tables/{table_id}/release', $mutationGroupsByName['Staff order + checkout + cashier core']['signatures']);
        self::assertContains('POST api/v1/staff/finance/invoices/{reservation_id}/issue', $mutationGroupsByName['Staff order + checkout + cashier core']['signatures']);
        self::assertContains('POST api/v1/staff/conversations/{conversation_id}/assign', $mutationGroupsByName['Staff conversation workflow']['signatures']);
        self::assertContains('POST api/v1/staff/conversations/{conversation_id}/workflow-state', $mutationGroupsByName['Staff conversation workflow']['signatures']);
        self::assertContains('POST api/v1/admin/inventory/ingredients/{id}/movements', $mutationGroupsByName['Admin inventory receiving']['signatures']);
        self::assertContains('POST api/v1/admin/inventory/purchase-orders/{id}/receipts', $mutationGroupsByName['Admin inventory receiving']['signatures']);
        self::assertContains('POST api/v1/admin/benefits/vouchers', $mutationGroupsByName['Admin benefits']['signatures']);
        self::assertContains('PATCH api/v1/admin/benefits/vouchers/{id}', $mutationGroupsByName['Admin benefits']['signatures']);
        self::assertContains('POST api/v1/admin/settings/benefits', $mutationGroupsByName['Admin benefits']['signatures']);
        self::assertContains('POST api/v1/admin/privacy/requests/{request_id}/review', $mutationGroupsByName['Admin privacy']['signatures']);
        self::assertContains('PATCH api/v1/admin/settings/branches/{id}', $mutationGroupsByName['Admin branch update']['signatures']);
        self::assertContains('GET api/v1/staff/tables/board', $groupsByName['Dine-In + Checkout']['signatures']);
        self::assertContains('GET api/v1/staff/tables/board/changes', $groupsByName['Dine-In + Checkout']['signatures']);
        self::assertContains('GET api/v1/staff/menu/items', $groupsByName['Dine-In + Checkout']['signatures']);
        self::assertContains('POST api/v1/staff/service-sessions/walk-in', $groupsByName['Dine-In + Checkout']['signatures']);
        self::assertContains('GET api/v1/staff/cashier/shifts/current', $groupsByName['Dine-In + Checkout']['signatures']);
        self::assertContains('GET api/v1/staff/reservations', $groupsByName['Staff Lookup']['signatures']);
        self::assertContains('GET api/v1/staff/branches', $groupsByName['Staff Lookup']['signatures']);
        self::assertContains('GET api/v1/staff/reservations/{reservation_id}', $groupsByName['Staff Lookup']['signatures']);
        self::assertContains('GET api/v1/staff/reservations/{reservation_id}/orders', $groupsByName['Staff Lookup']['signatures']);
        self::assertContains('GET api/v1/staff/cashier/shifts', $groupsByName['Staff Lookup']['signatures']);
        self::assertContains('GET api/v1/staff/finance/reconciliation', $groupsByName['Staff Finance']['signatures']);
        self::assertContains('GET api/v1/staff/finance/invoices/{reservation_id}', $groupsByName['Staff Finance']['signatures']);
        self::assertContains('POST api/v1/staff/finance/invoices/{reservation_id}/issue', $groupsByName['Staff Finance']['signatures']);
        self::assertContains('POST api/v1/staff/conversations/{conversation_id}/unassign', $groupsByName['Conversation Inbox']['signatures']);
        self::assertContains('POST api/v1/staff/conversations/{conversation_id}/assign', $groupsByName['Conversation Inbox']['signatures']);
        self::assertContains('POST api/v1/staff/conversations/{conversation_id}/workflow-state', $groupsByName['Conversation Inbox']['signatures']);
        self::assertContains('POST api/v1/staff/conversations/{conversation_id}/links', $groupsByName['Conversation Inbox']['signatures']);
        self::assertContains('GET api/v1/staff/waiting-list', $groupsByName['Waiting List']['signatures']);
        self::assertContains('GET api/v1/staff/waiting-list/changes', $groupsByName['Waiting List']['signatures']);
        self::assertContains('POST api/v1/staff/waiting-list', $groupsByName['Waiting List']['signatures']);
        self::assertContains('POST api/v1/staff/waiting-list/{id}/cancel', $groupsByName['Waiting List']['signatures']);
        self::assertContains('POST api/v1/staff/waiting-list/{id}/advance', $groupsByName['Waiting List']['signatures']);
        self::assertContains('GET api/v1/me/loyalty', $groupsByName['Benefits']['signatures']);
        self::assertContains('GET api/v1/me/data-export', $groupsByName['Customer Privacy']['signatures']);
        self::assertContains('GET api/v1/me/privacy-requests', $groupsByName['Customer Privacy']['signatures']);
        self::assertContains('POST api/v1/me/privacy-requests', $groupsByName['Customer Privacy']['signatures']);
        self::assertContains('GET api/v1/admin/benefits/vouchers', $groupsByName['Admin Benefits']['signatures']);
        self::assertContains('POST api/v1/admin/benefits/vouchers', $groupsByName['Admin Benefits']['signatures']);
        self::assertContains('PATCH api/v1/admin/benefits/loyalty-tiers/{id}', $groupsByName['Admin Benefits']['signatures']);
        self::assertContains('GET api/v1/admin/settings/benefits', $groupsByName['Admin Benefits']['signatures']);
        self::assertContains('GET api/v1/admin/privacy/requests', $groupsByName['Admin Privacy']['signatures']);
        self::assertContains('GET api/v1/admin/privacy/customers/{user_id}/data-export', $groupsByName['Admin Privacy']['signatures']);
        self::assertContains('POST api/v1/admin/privacy/requests/{request_id}/review', $groupsByName['Admin Privacy']['signatures']);
        self::assertContains('POST api/v1/me/privacy-requests', $mutationGroupsByName['Customer privacy']['signatures']);
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

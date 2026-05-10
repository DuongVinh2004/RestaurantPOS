<?php

declare(strict_types=1);

return [
    'source_openapi_path' => 'storage/app/booking_release/openapi-v1.json',
    'uat_manifest_path' => 'storage/app/uat/scenario-pack.json',
    'output_root' => 'build/api-consumer',

    'postman' => [
        'collection' => 'postman/RestaurantPOS.postman_collection.json',
        'local_template' => 'postman/RestaurantPOS.local.template.postman_environment.json',
        'staging_template' => 'postman/RestaurantPOS.staging.template.postman_environment.json',
        'uat_environment' => 'postman/RestaurantPOS.uat.postman_environment.json',
        'include_full_contract_reference' => true,
        'reference_folder_name' => 'Reference',
        'groups' => [
            [
                'name' => 'Auth',
                'signatures' => [
                    'POST api/v1/auth/customer/register',
                    'POST api/v1/auth/customer/login',
                    'GET api/v1/auth/customer/me',
                    'POST api/v1/auth/customer/refresh',
                    'POST api/v1/auth/customer/logout',
                    'POST api/v1/auth/staff/login',
                    'GET api/v1/auth/staff/me',
                    'POST api/v1/auth/staff/refresh',
                    'POST api/v1/auth/staff/logout',
                ],
            ],
            [
                'name' => 'Availability + Reservation',
                'signatures' => [
                    'GET api/v1/restaurant/profile',
                    'GET api/v1/tables/available',
                    'POST api/v1/table-holds',
                    'GET api/v1/table-holds/{hold_id}',
                    'PATCH api/v1/table-holds/{hold_id}/refresh',
                    'DELETE api/v1/table-holds/{hold_id}',
                    'GET api/v1/menu/categories',
                    'GET api/v1/menu/items',
                    'GET api/v1/menu/items/{id}',
                    'POST api/v1/menu/preorder/preview',
                    'POST api/v1/reservations',
                    'GET api/v1/reservations',
                    'GET api/v1/reservations/{id}',
                    'POST api/v1/reservations/{id}/cancel',
                    'POST api/v1/reservations/{id}/reschedule',
                    'GET api/v1/reservations/{id}/preorder',
                    'POST api/v1/reservations/{id}/preorder/preview',
                    'PUT api/v1/reservations/{id}/preorder',
                    'DELETE api/v1/reservations/{id}/preorder',
                ],
            ],
            [
                'name' => 'Deposit Self-Pay',
                'signatures' => [
                    'GET api/v1/reservations/{id}/deposit-preview',
                    'POST api/v1/reservations/{id}/deposit/acknowledge',
                    'POST api/v1/reservations/{id}/deposit/intent',
                    'POST api/v1/reservations/{id}/deposit/intent/revoke',
                    'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions',
                    'GET api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}',
                    'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh',
                    'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm',
                ],
            ],
            [
                'name' => 'Dine-In + Checkout',
                'signatures' => [
                    'GET api/v1/staff/menu/items',
                    'GET api/v1/staff/tables/board',
                    'GET api/v1/staff/tables/board/changes',
                    'POST api/v1/staff/service-sessions/walk-in',
                    'POST api/v1/staff/reservations/{id}/check-in',
                    'POST api/v1/staff/reservations/{id}/assign-table',
                    'POST api/v1/staff/reservations/{id}/assign-best-fit',
                    'POST api/v1/staff/reservations/{id}/move-table',
                    'POST api/v1/staff/tables/{table_id}/release',
                    'POST api/v1/staff/tables/{table_id}/orders',
                    'GET api/v1/staff/tables/{table_id}/active-order',
                    'POST api/v1/staff/orders/{order_id}/items',
                    'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}',
                    'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status',
                    'GET api/v1/reservations/{reservation_id}/active-order',
                    'POST api/v1/staff/orders/{order_id}/bill-snapshot',
                    'GET api/v1/reservations/{reservation_id}/bill-preview',
                    'GET api/v1/reservations/{reservation_id}/bill',
                    'POST api/v1/reservations/{reservation_id}/bill/payment-sessions',
                    'GET api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}',
                    'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh',
                    'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm',
                    'GET api/v1/staff/orders/{order_id}',
                    'GET api/v1/staff/cashier/shifts/current',
                    'POST api/v1/staff/cashier/shifts/open',
                    'GET api/v1/staff/cashier/shifts/{shift_id}',
                    'POST api/v1/staff/cashier/shifts/{shift_id}/close',
                    'GET api/v1/staff/orders/{order_id}/settlement-preview',
                    'POST api/v1/staff/orders/{order_id}/pay',
                    'POST api/v1/staff/orders/{order_id}/settlement/finalize',
                ],
            ],
            [
                'name' => 'Kitchen / KDS',
                'signatures' => [
                    'GET api/v1/staff/kitchen/changes',
                    'GET api/v1/staff/kitchen/stations',
                    'GET api/v1/staff/kitchen/stations/{station_id}/tickets',
                    'POST api/v1/staff/orders/{order_id}/kitchen/dispatch',
                    'POST api/v1/staff/kitchen/tickets/{ticket_id}/fire',
                    'POST api/v1/staff/kitchen/tickets/{ticket_id}/bump',
                    'POST api/v1/staff/kitchen/tickets/{ticket_id}/recall',
                ],
            ],
            [
                'name' => 'Staff Lookup',
                'signatures' => [
                    'GET api/v1/staff/branches',
                    'GET api/v1/staff/reservations',
                    'GET api/v1/staff/reservations/{reservation_id}',
                    'GET api/v1/staff/reservations/{reservation_id}/orders',
                    'GET api/v1/staff/cashier/shifts',
                ],
            ],
            [
                'name' => 'Operations Read Models',
                'signatures' => [
                    'GET api/v1/staff/audit-trail',
                    'GET api/v1/staff/reporting/daily-sales',
                    'GET api/v1/staff/reporting/daily-operations',
                    'GET api/v1/staff/reporting/daily-inventory',
                    'GET api/v1/admin/inventory/ingredients',
                    'GET api/v1/admin/inventory/suppliers',
                    'GET api/v1/admin/inventory/purchase-orders',
                    'GET api/v1/admin/inventory/ingredients/{id}/movements',
                    'POST api/v1/admin/inventory/ingredients/{id}/movements',
                    'GET api/v1/admin/inventory/purchase-orders/{id}/receipts',
                    'POST api/v1/admin/inventory/purchase-orders/{id}/receipts',
                    'GET api/v1/admin/settings/branches',
                ],
            ],
            [
                'name' => 'Refunds',
                'signatures' => [
                    'GET api/v1/staff/reservations/{reservation_id}/refund-preview',
                    'POST api/v1/staff/reservations/{reservation_id}/refund',
                    'POST api/v1/staff/reservations/{reservation_id}/refund-cancel',
                ],
            ],
            [
                'name' => 'Staff Finance',
                'signatures' => [
                    'GET api/v1/staff/finance/reconciliation',
                    'GET api/v1/staff/finance/reconciliation/{reservation_id}',
                    'GET api/v1/staff/finance/invoices/{reservation_id}',
                    'POST api/v1/staff/finance/invoices/{reservation_id}/issue',
                ],
            ],
            [
                'name' => 'Waiting List',
                'signatures' => [
                    'GET api/v1/waiting-list',
                    'POST api/v1/waiting-list',
                    'GET api/v1/waiting-list/{id}',
                    'GET api/v1/staff/waiting-list',
                    'GET api/v1/staff/waiting-list/changes',
                    'POST api/v1/staff/waiting-list',
                    'POST api/v1/staff/waiting-list/{id}/notify',
                    'POST api/v1/waiting-list/{id}/accept',
                    'POST api/v1/waiting-list/{id}/confirm-arrival',
                    'POST api/v1/waiting-list/{id}/decline',
                    'POST api/v1/waiting-list/{id}/cancel',
                    'POST api/v1/staff/waiting-list/{id}/cancel',
                    'POST api/v1/staff/waiting-list/{id}/advance',
                    'POST api/v1/staff/waiting-list/{id}/seat',
                ],
            ],
            [
                'name' => 'Benefits',
                'signatures' => [
                    'GET api/v1/me/loyalty',
                    'GET api/v1/me/vouchers',
                    'GET api/v1/reservations/{id}/benefits-preview',
                    'POST api/v1/reservations/{id}/voucher/apply',
                    'POST api/v1/reservations/{id}/voucher/remove',
                    'POST api/v1/reservations/{id}/loyalty/redeem',
                    'POST api/v1/reservations/{id}/loyalty/redeem/release',
                ],
            ],
            [
                'name' => 'Customer Privacy',
                'signatures' => [
                    'GET api/v1/me/data-export',
                    'GET api/v1/me/privacy-requests',
                    'POST api/v1/me/privacy-requests',
                ],
            ],
            [
                'name' => 'Admin Master Data',
                'signatures' => [
                    'GET api/v1/admin/restaurant/table-templates',
                    'POST api/v1/admin/restaurant/tables',
                    'POST api/v1/admin/menu/categories',
                    'POST api/v1/admin/menu/items',
                    'POST api/v1/admin/menu/items/{item_id}/prices',
                ],
            ],
            [
                'name' => 'Admin Benefits',
                'signatures' => [
                    'GET api/v1/admin/benefits/vouchers',
                    'GET api/v1/admin/benefits/vouchers/{id}',
                    'POST api/v1/admin/benefits/vouchers',
                    'PATCH api/v1/admin/benefits/vouchers/{id}',
                    'GET api/v1/admin/benefits/loyalty-tiers',
                    'GET api/v1/admin/benefits/loyalty-tiers/{id}',
                    'POST api/v1/admin/benefits/loyalty-tiers',
                    'PATCH api/v1/admin/benefits/loyalty-tiers/{id}',
                    'GET api/v1/admin/settings/benefits',
                    'POST api/v1/admin/settings/benefits',
                ],
            ],
            [
                'name' => 'Admin Privacy',
                'signatures' => [
                    'GET api/v1/admin/privacy/requests',
                    'GET api/v1/admin/privacy/customers/{user_id}/data-export',
                    'POST api/v1/admin/privacy/requests/{request_id}/review',
                ],
            ],
            [
                'name' => 'Conversation Inbox',
                'signatures' => [
                    'GET api/v1/staff/conversations',
                    'GET api/v1/staff/conversations/{conversation_id}',
                    'POST api/v1/staff/conversations/{conversation_id}/assign',
                    'POST api/v1/staff/conversations/{conversation_id}/take-over',
                    'POST api/v1/staff/conversations/{conversation_id}/unassign',
                    'POST api/v1/staff/conversations/{conversation_id}/workflow-state',
                    'POST api/v1/staff/conversations/{conversation_id}/links',
                    'DELETE api/v1/staff/conversations/{conversation_id}/links/reservation',
                    'DELETE api/v1/staff/conversations/{conversation_id}/links/waiting-list',
                    'POST api/v1/staff/conversations/{conversation_id}/internal-notes',
                    'POST api/v1/staff/conversations/{conversation_id}/outbound-replies',
                ],
            ],
            [
                'name' => 'Payment Webhooks',
                'signatures' => [
                    'POST api/v1/payments/providers/{provider_code}/webhooks',
                ],
            ],
            [
                'name' => 'Health',
                'signatures' => [
                    'GET api/v1/health',
                    'GET api/v1/health/detailed',
                    'GET api/v1/health/redis',
                ],
            ],
        ],
        'parameter_aliases' => [
            'GET api/v1/tables/available' => [
                'query' => [
                    'branch_id' => 'branchId',
                    'from' => 'availabilityFromUtc',
                    'to' => 'availabilityToUtc',
                    'guest_count' => 'guestCount',
                    'session_id' => 'customerSessionId',
                    'suggest' => 'suggestAvailability',
                ],
            ],
            'GET api/v1/table-holds/{hold_id}' => [
                'path' => ['hold_id' => 'holdId'],
            ],
            'PATCH api/v1/table-holds/{hold_id}/refresh' => [
                'path' => ['hold_id' => 'holdId'],
                'query' => [
                    'session_id' => 'customerSessionId',
                    'row_version' => 'tableHoldRowVersion',
                ],
            ],
            'DELETE api/v1/table-holds/{hold_id}' => [
                'path' => ['hold_id' => 'holdId'],
                'query' => [
                    'session_id' => 'customerSessionId',
                    'row_version' => 'tableHoldRowVersion',
                ],
            ],
            'GET api/v1/menu/categories' => [
                'query' => [
                    'service_time' => 'availabilityFromUtc',
                    'preorder_only' => 'menuPreorderOnly',
                ],
            ],
            'GET api/v1/menu/items' => [
                'query' => [
                    'service_time' => 'availabilityFromUtc',
                    'category_id' => 'menuCategoryId',
                    'per_page' => 'perPage',
                ],
            ],
            'GET api/v1/menu/items/{id}' => [
                'path' => ['id' => 'menuItemId'],
                'query' => [
                    'service_time' => 'availabilityFromUtc',
                ],
            ],
            'GET api/v1/reservations/{id}' => [
                'path' => ['id' => 'reservationId'],
            ],
            'GET api/v1/reservations' => [
                'query' => [
                    'status' => 'reservationStatus',
                    'active_only' => 'reservationActiveOnly',
                    'page' => 'page',
                    'per_page' => 'perPage',
                ],
            ],
            'POST api/v1/reservations/{id}/cancel' => [
                'path' => ['id' => 'reservationId'],
            ],
            'POST api/v1/reservations/{id}/reschedule' => [
                'path' => ['id' => 'reservationId'],
            ],
            'GET api/v1/reservations/{id}/preorder' => [
                'path' => ['id' => 'reservationIdPreorder'],
            ],
            'POST api/v1/reservations/{id}/preorder/preview' => [
                'path' => ['id' => 'reservationIdPreorder'],
            ],
            'PUT api/v1/reservations/{id}/preorder' => [
                'path' => ['id' => 'reservationIdPreorder'],
            ],
            'DELETE api/v1/reservations/{id}/preorder' => [
                'path' => ['id' => 'reservationIdPreorder'],
                'query' => [
                    'row_version' => 'reservationRowVersionPreorder',
                    'pre_order_row_version' => 'preorderRowVersion',
                ],
            ],
            'GET api/v1/reservations/{id}/deposit-preview' => [
                'path' => ['id' => 'reservationIdDeposit'],
            ],
            'POST api/v1/reservations/{id}/deposit/acknowledge' => [
                'path' => ['id' => 'reservationIdDeposit'],
            ],
            'POST api/v1/reservations/{id}/deposit/intent' => [
                'path' => ['id' => 'reservationIdDeposit'],
            ],
            'POST api/v1/reservations/{id}/deposit/intent/revoke' => [
                'path' => ['id' => 'reservationIdDeposit'],
            ],
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions' => [
                'path' => ['reservation_id' => 'reservationIdDeposit'],
            ],
            'GET api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}' => [
                'path' => [
                    'reservation_id' => 'reservationIdDeposit',
                    'session_id' => 'depositPaymentSessionId',
                ],
            ],
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh' => [
                'path' => [
                    'reservation_id' => 'reservationIdDeposit',
                    'session_id' => 'depositPaymentSessionId',
                ],
            ],
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm' => [
                'path' => [
                    'reservation_id' => 'reservationIdDeposit',
                    'session_id' => 'depositPaymentSessionId',
                ],
            ],
            'GET api/v1/staff/tables/board' => [
                'query' => [
                    'from' => 'availabilityFromUtc',
                    'to' => 'availabilityToUtc',
                    'zone' => 'boardZone',
                    'include_holds' => 'includeHolds',
                    'group_by' => 'boardGroupBy',
                ],
            ],
            'GET api/v1/staff/tables/board/changes' => [
                'query' => [
                    'after_version' => 'boardAfterVersion',
                    'limit' => 'eventLimit',
                ],
            ],
            'GET api/v1/staff/menu/items' => [
                'query' => [
                    'service_time' => 'availabilityFromUtc',
                    'category_id' => 'menuCategoryId',
                    'per_page' => 'perPage',
                ],
            ],
            'POST api/v1/staff/reservations/{id}/check-in' => [
                'path' => ['id' => 'reservationIdDineIn'],
            ],
            'POST api/v1/staff/reservations/{id}/assign-table' => [
                'path' => ['id' => 'reservationIdDineIn'],
            ],
            'POST api/v1/staff/reservations/{id}/assign-best-fit' => [
                'path' => ['id' => 'reservationIdDineIn'],
            ],
            'POST api/v1/staff/reservations/{id}/move-table' => [
                'path' => ['id' => 'reservationIdDineIn'],
            ],
            'POST api/v1/staff/tables/{table_id}/release' => [
                'path' => ['table_id' => 'dineInTableId'],
            ],
            'POST api/v1/staff/tables/{table_id}/orders' => [
                'path' => ['table_id' => 'dineInTableId'],
            ],
            'GET api/v1/staff/tables/{table_id}/active-order' => [
                'path' => ['table_id' => 'dineInTableId'],
            ],
            'POST api/v1/staff/orders/{order_id}/items' => [
                'path' => ['order_id' => 'orderId'],
            ],
            'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}' => [
                'path' => ['order_id' => 'orderId', 'order_item_id' => 'orderItemId'],
            ],
            'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status' => [
                'path' => ['order_id' => 'orderId', 'order_item_id' => 'orderItemId'],
            ],
            'POST api/v1/staff/orders/{order_id}/bill-snapshot' => [
                'path' => ['order_id' => 'orderId'],
            ],
            'GET api/v1/staff/orders/{order_id}' => [
                'path' => ['order_id' => 'orderId'],
            ],
            'GET api/v1/staff/reservations' => [
                'query' => [
                    'bucket' => 'reservationBucket',
                    'q' => 'reservationSearch',
                    'reservation_code' => 'reservationCode',
                    'phone' => 'reservationPhone',
                    'table_id' => 'dineInTableId',
                    'page' => 'page',
                    'per_page' => 'perPage',
                    'sort' => 'reservationSort',
                ],
            ],
            'GET api/v1/staff/reservations/{reservation_id}' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
            ],
            'GET api/v1/staff/reservations/{reservation_id}/orders' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
            ],
            'GET api/v1/staff/cashier/shifts' => [
                'query' => [
                    'status' => 'cashierShiftStatus',
                    'branch_id' => 'branchId',
                    'q' => 'cashierShiftSearch',
                    'shift_code' => 'cashierShiftCode',
                    'terminal_code' => 'cashierTerminalCode',
                    'page' => 'page',
                    'per_page' => 'perPage',
                    'sort' => 'cashierShiftSort',
                ],
            ],
            'GET api/v1/staff/reporting/daily-sales' => [
                'query' => [
                    'branch_id' => 'branchId',
                    'currency' => 'currency',
                    'start_date' => 'reportStartDate',
                    'end_date' => 'reportEndDate',
                    'page' => 'page',
                    'per_page' => 'perPage',
                    'sort' => 'reportingSalesSort',
                ],
            ],
            'GET api/v1/staff/reporting/daily-operations' => [
                'query' => [
                    'branch_id' => 'branchId',
                    'start_date' => 'reportStartDate',
                    'end_date' => 'reportEndDate',
                    'page' => 'page',
                    'per_page' => 'perPage',
                    'sort' => 'reportingOperationsSort',
                ],
            ],
            'GET api/v1/staff/reporting/daily-inventory' => [
                'query' => [
                    'branch_id' => 'branchId',
                    'ingredient_id' => 'inventoryIngredientId',
                    'start_date' => 'reportStartDate',
                    'end_date' => 'reportEndDate',
                    'page' => 'page',
                    'per_page' => 'perPage',
                    'sort' => 'reportingInventorySort',
                ],
            ],
            'GET api/v1/admin/inventory/ingredients' => [
                'query' => [
                    'is_active' => 'inventoryActiveOnly',
                    'q' => 'inventorySearch',
                    'page' => 'page',
                    'per_page' => 'perPage',
                    'sort' => 'inventoryIngredientSort',
                ],
            ],
            'GET api/v1/admin/inventory/suppliers' => [
                'query' => [
                    'is_active' => 'supplierActiveOnly',
                    'q' => 'supplierSearch',
                    'page' => 'page',
                    'per_page' => 'perPage',
                    'sort' => 'supplierSort',
                ],
            ],
            'GET api/v1/admin/inventory/purchase-orders' => [
                'query' => [
                    'supplier_id' => 'supplierId',
                    'branch_id' => 'branchId',
                    'purchase_order_status' => 'purchaseOrderStatus',
                    'q' => 'purchaseOrderSearch',
                    'page' => 'page',
                    'per_page' => 'perPage',
                    'sort' => 'purchaseOrderSort',
                ],
            ],
            'GET api/v1/admin/inventory/ingredients/{id}/movements' => [
                'path' => ['id' => 'ingredientId'],
                'query' => ['branch_id' => 'branchId'],
            ],
            'POST api/v1/admin/inventory/ingredients/{id}/movements' => [
                'path' => ['id' => 'ingredientId'],
            ],
            'GET api/v1/admin/inventory/purchase-orders/{id}/receipts' => [
                'path' => ['id' => 'purchaseOrderId'],
            ],
            'POST api/v1/admin/inventory/purchase-orders/{id}/receipts' => [
                'path' => ['id' => 'purchaseOrderId'],
            ],
            'GET api/v1/admin/settings/branches' => [
                'query' => [
                    'is_active' => 'branchActiveOnly',
                    'q' => 'branchSearch',
                ],
            ],
            'GET api/v1/staff/cashier/shifts/{shift_id}' => [
                'path' => ['shift_id' => 'cashierShiftId'],
            ],
            'POST api/v1/staff/cashier/shifts/{shift_id}/close' => [
                'path' => ['shift_id' => 'cashierShiftId'],
            ],
            'GET api/v1/staff/orders/{order_id}/settlement-preview' => [
                'path' => ['order_id' => 'orderId'],
            ],
            'POST api/v1/staff/orders/{order_id}/pay' => [
                'path' => ['order_id' => 'orderId'],
            ],
            'POST api/v1/staff/orders/{order_id}/settlement/finalize' => [
                'path' => ['order_id' => 'orderId'],
            ],
            'GET api/v1/staff/kitchen/changes' => [
                'query' => [
                    'after_version' => 'kitchenAfterVersion',
                    'limit' => 'eventLimit',
                ],
            ],
            'GET api/v1/staff/kitchen/stations/{station_id}/tickets' => [
                'path' => ['station_id' => 'kitchenStationId'],
                'query' => [
                    'status' => 'kitchenTicketStatus',
                    'include_terminal' => 'kitchenIncludeTerminal',
                ],
            ],
            'POST api/v1/staff/orders/{order_id}/kitchen/dispatch' => [
                'path' => ['order_id' => 'orderId'],
            ],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/fire' => [
                'path' => ['ticket_id' => 'kitchenTicketId'],
            ],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/bump' => [
                'path' => ['ticket_id' => 'kitchenTicketId'],
            ],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/recall' => [
                'path' => ['ticket_id' => 'kitchenTicketId'],
            ],
            'GET api/v1/reservations/{reservation_id}/active-order' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
            ],
            'GET api/v1/reservations/{reservation_id}/bill-preview' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
            ],
            'GET api/v1/reservations/{reservation_id}/bill' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
            ],
            'GET api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}' => [
                'path' => [
                    'reservation_id' => 'reservationIdDineIn',
                    'session_id' => 'billPaymentSessionId',
                ],
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh' => [
                'path' => [
                    'reservation_id' => 'reservationIdDineIn',
                    'session_id' => 'billPaymentSessionId',
                ],
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm' => [
                'path' => [
                    'reservation_id' => 'reservationIdDineIn',
                    'session_id' => 'billPaymentSessionId',
                ],
            ],
            'GET api/v1/staff/reservations/{reservation_id}/refund-preview' => [
                'path' => ['reservation_id' => 'reservationIdRefund'],
                'query' => [
                    'refund_scope' => 'refundScope',
                    'refund_amount' => 'refundAmount',
                    'cancel_after_payment' => 'cancelAfterPayment',
                ],
            ],
            'POST api/v1/staff/reservations/{reservation_id}/refund' => [
                'path' => ['reservation_id' => 'reservationIdRefund'],
            ],
            'POST api/v1/staff/reservations/{reservation_id}/refund-cancel' => [
                'path' => ['reservation_id' => 'reservationIdRefundCancel'],
            ],
            'GET api/v1/staff/finance/reconciliation' => [
                'query' => [
                    'branch_id' => 'branchId',
                    'reservation_id' => 'reservationIdDineIn',
                    'per_page' => 'perPage',
                    'sort' => 'financeReconciliationSort',
                ],
            ],
            'GET api/v1/staff/finance/reconciliation/{reservation_id}' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
                'query' => ['branch_id' => 'branchId'],
            ],
            'GET api/v1/staff/finance/invoices/{reservation_id}' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
                'query' => ['branch_id' => 'branchId'],
            ],
            'POST api/v1/staff/finance/invoices/{reservation_id}/issue' => [
                'path' => ['reservation_id' => 'reservationIdDineIn'],
                'query' => ['branch_id' => 'branchId'],
            ],
            'GET api/v1/waiting-list/{id}' => [
                'path' => ['id' => 'waitingListId'],
            ],
            'GET api/v1/staff/waiting-list' => [
                'query' => [
                    'status' => 'waitingListStatus',
                    'active_only' => 'waitingListActiveOnly',
                    'branch_id' => 'branchId',
                    'per_page' => 'perPage',
                    'sort' => 'waitingListSort',
                ],
            ],
            'GET api/v1/staff/waiting-list/changes' => [
                'query' => [
                    'after_version' => 'waitingListAfterVersion',
                    'limit' => 'eventLimit',
                ],
            ],
            'POST api/v1/staff/waiting-list/{id}/notify' => [
                'path' => ['id' => 'waitingListId'],
            ],
            'POST api/v1/staff/waiting-list/{id}/cancel' => [
                'path' => ['id' => 'waitingListId'],
            ],
            'POST api/v1/staff/waiting-list/{id}/advance' => [
                'path' => ['id' => 'waitingListId'],
            ],
            'POST api/v1/waiting-list/{id}/accept' => [
                'path' => ['id' => 'waitingListId'],
            ],
            'POST api/v1/waiting-list/{id}/confirm-arrival' => [
                'path' => ['id' => 'waitingListId'],
            ],
            'POST api/v1/staff/waiting-list/{id}/seat' => [
                'path' => ['id' => 'waitingListId'],
            ],
            'GET api/v1/me/loyalty' => [
                'query' => ['limit' => 'loyaltySummaryLimit'],
            ],
            'GET api/v1/reservations/{id}/benefits-preview' => [
                'path' => ['id' => 'reservationIdBenefits'],
            ],
            'GET api/v1/me/vouchers' => [
                'query' => [
                    'status' => 'voucherStatus',
                    'applicable_to_reservation_id' => 'reservationIdBenefits',
                    'page' => 'page',
                    'per_page' => 'perPage',
                ],
            ],
            'GET api/v1/me/privacy-requests' => [
                'query' => [
                    'status' => 'privacyRequestStatus',
                    'per_page' => 'perPage',
                ],
            ],
            'POST api/v1/reservations/{id}/voucher/apply' => [
                'path' => ['id' => 'reservationIdBenefits'],
            ],
            'POST api/v1/reservations/{id}/voucher/remove' => [
                'path' => ['id' => 'reservationIdBenefits'],
            ],
            'POST api/v1/reservations/{id}/loyalty/redeem' => [
                'path' => ['id' => 'reservationIdBenefits'],
            ],
            'POST api/v1/reservations/{id}/loyalty/redeem/release' => [
                'path' => ['id' => 'reservationIdBenefits'],
            ],
            'GET api/v1/admin/restaurant/table-templates' => [
                'query' => ['branch_id' => 'branchId'],
            ],
            'POST api/v1/admin/menu/items/{item_id}/prices' => [
                'path' => ['item_id' => 'menuItemId'],
            ],
            'GET api/v1/admin/benefits/vouchers/{id}' => [
                'path' => ['id' => 'voucherId'],
            ],
            'PATCH api/v1/admin/benefits/vouchers/{id}' => [
                'path' => ['id' => 'voucherId'],
            ],
            'GET api/v1/admin/benefits/loyalty-tiers/{id}' => [
                'path' => ['id' => 'loyaltyTierId'],
            ],
            'PATCH api/v1/admin/benefits/loyalty-tiers/{id}' => [
                'path' => ['id' => 'loyaltyTierId'],
            ],
            'GET api/v1/admin/privacy/customers/{user_id}/data-export' => [
                'path' => ['user_id' => 'customerUserId'],
            ],
            'POST api/v1/admin/privacy/requests/{request_id}/review' => [
                'path' => ['request_id' => 'privacyRequestId'],
            ],
            'GET api/v1/staff/conversations' => [
                'query' => [
                    'branch_id' => 'branchId',
                    'status' => 'conversationStatus',
                    'per_page' => 'perPage',
                ],
            ],
            'GET api/v1/staff/conversations/{conversation_id}' => [
                'path' => ['conversation_id' => 'conversationId'],
                'query' => [
                    'message_limit' => 'messageLimit',
                    'event_limit' => 'eventLimit',
                ],
            ],
            'POST api/v1/staff/conversations/{conversation_id}/take-over' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'POST api/v1/staff/conversations/{conversation_id}/assign' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'POST api/v1/staff/conversations/{conversation_id}/unassign' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'POST api/v1/staff/conversations/{conversation_id}/workflow-state' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'POST api/v1/staff/conversations/{conversation_id}/links' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'DELETE api/v1/staff/conversations/{conversation_id}/links/reservation' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'DELETE api/v1/staff/conversations/{conversation_id}/links/waiting-list' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'POST api/v1/staff/conversations/{conversation_id}/internal-notes' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'POST api/v1/staff/conversations/{conversation_id}/outbound-replies' => [
                'path' => ['conversation_id' => 'conversationId'],
            ],
            'POST api/v1/payments/providers/{provider_code}/webhooks' => [
                'path' => ['provider_code' => 'providerCode'],
            ],
        ],
        'body_overrides' => [
            'POST api/v1/auth/customer/register' => [
                'full_name' => 'Postman Customer',
                'email' => 'postman.customer+{{$timestamp}}@example.test',
                'phone' => '09{{$timestamp}}',
                'password' => '{{customerPassword}}',
                'password_confirmation' => '{{customerPassword}}',
                'session_label' => 'postman-customer-register',
            ],
            'POST api/v1/auth/customer/login' => [
                'identifier' => '{{customerUsername}}',
                'password' => '{{customerPassword}}',
                'session_label' => 'postman-customer',
            ],
            'POST api/v1/auth/staff/login' => [
                'identifier' => '{{staffUsername}}',
                'password' => '{{staffPassword}}',
                'label' => 'postman-staff',
            ],
            'POST api/v1/table-holds' => [
                'branch_id' => '{{branchId}}',
                'session_id' => '{{customerSessionId}}',
                'start_time' => '{{availabilityFromUtc}}',
                'end_time' => '{{availabilityToUtc}}',
                'table_ids.0' => '{{preferredTableId}}',
                'hold_minutes' => 5,
            ],
            'PATCH api/v1/table-holds/{hold_id}/refresh' => [
                'session_id' => '{{customerSessionId}}',
                'extend_minutes' => 5,
                'row_version' => '{{tableHoldRowVersion}}',
            ],
            'DELETE api/v1/table-holds/{hold_id}' => [
                'session_id' => '{{customerSessionId}}',
                'row_version' => '{{tableHoldRowVersion}}',
            ],
            'POST api/v1/menu/preorder/preview' => [
                'start_time' => '{{availabilityFromUtc}}',
                'pre_order_items.0.item_id' => '{{menuItemIdPrimary}}',
                'pre_order_items.0.quantity' => 1,
            ],
            'POST api/v1/reservations' => [
                'hold_id' => '{{holdId}}',
                'session_id' => '{{customerSessionId}}',
                'start_time' => '{{availabilityFromUtc}}',
                'end_time' => '{{availabilityToUtc}}',
                'guest_count' => '{{guestCount}}',
                'notes' => 'Postman availability -> hold -> reservation scenario',
            ],
            'POST api/v1/reservations/{id}/cancel' => [
                'row_version' => '{{reservationRowVersion}}',
                'cancel_reason' => 'Postman customer cancel',
            ],
            'POST api/v1/reservations/{id}/reschedule' => [
                'row_version' => '{{reservationRowVersion}}',
                'start_time' => '{{availabilityFromUtc}}',
                'end_time' => '{{availabilityToUtc}}',
            ],
            'POST api/v1/reservations/{id}/preorder/preview' => [
                'pre_order_items.0.item_id' => '{{menuItemIdPrimary}}',
                'pre_order_items.0.quantity' => 1,
            ],
            'PUT api/v1/reservations/{id}/preorder' => [
                'row_version' => '{{reservationRowVersionPreorder}}',
                'pre_order_row_version' => '{{preorderRowVersion}}',
                'pre_order_items.0.item_id' => '{{preorderReplacementItemId}}',
                'pre_order_items.0.quantity' => 2,
            ],
            'POST api/v1/staff/reservations/{id}/check-in' => [
                'table_ids.0' => '{{dineInTableId}}',
                'checked_in_at' => '{{checkedInAt}}',
                'row_version' => '{{reservationRowVersionDineIn}}',
            ],
            'POST api/v1/staff/service-sessions/walk-in' => [
                'branch_id' => '{{branchId}}',
                'table_ids.0' => '{{dineInTableId}}',
                'guest_count' => '{{guestCount}}',
                'started_at' => '{{checkedInAt}}',
                'service_minutes' => 90,
                'notes' => 'Postman walk-in service session',
            ],
            'POST api/v1/staff/tables/{table_id}/orders' => [
                'reservation_id' => '{{reservationIdDineIn}}',
                'row_version' => '{{reservationRowVersionDineIn}}',
                'items.0.menu_item_id' => '{{menuItemIdPrimary}}',
                'items.0.qty' => 1,
            ],
            'POST api/v1/staff/orders/{order_id}/items' => [
                'row_version' => '{{orderRowVersion}}',
                'items.0.menu_item_id' => '{{menuItemIdSecondary}}',
                'items.0.qty' => 1,
            ],
            'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}' => [
                'order_row_version' => '{{orderRowVersion}}',
                'row_version' => '{{orderItemRowVersion}}',
                'qty' => 2,
                'note' => 'Postman order item update',
            ],
            'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status' => [
                'order_row_version' => '{{orderRowVersion}}',
                'row_version' => '{{orderItemRowVersion}}',
                'status' => 'Served',
            ],
            'POST api/v1/staff/cashier/shifts/open' => [
                'opening_float_amount' => '{{openingFloatAmount}}',
                'currency' => '{{currency}}',
                'terminal_code' => 'POS-01',
                'notes' => 'Postman cashier open',
            ],
            'POST api/v1/staff/cashier/shifts/{shift_id}/close' => [
                'actual_cash_amount' => '{{closingCashAmount}}',
                'row_version' => '{{cashierShiftRowVersion}}',
                'notes' => 'Postman cashier close',
            ],
            'POST api/v1/staff/orders/{order_id}/bill-snapshot' => [
                'row_version' => '{{orderRowVersion}}',
                'notes' => 'Postman bill snapshot',
            ],
            'POST api/v1/staff/orders/{order_id}/pay' => [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => '{{paymentAmount}}',
                'currency' => '{{currency}}',
                'transaction_code' => 'PAY-{{$timestamp}}-{{$randomInt}}',
                'notes' => 'Postman order payment',
                'row_version' => '{{orderRowVersion}}',
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions' => [
                'row_version' => '{{reservationRowVersionDineIn}}',
                'amount' => '{{paymentAmount}}',
                'payment_method' => 'Online',
                'provider_code' => '{{providerCode}}',
                'currency' => '{{currency}}',
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh' => [
                'row_version' => '{{billPaymentSessionRowVersion}}',
                'simulation_outcome' => 'succeeded',
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm' => [
                'row_version' => '{{billPaymentSessionRowVersion}}',
                'simulation_outcome' => 'succeeded',
            ],
            'POST api/v1/staff/orders/{order_id}/settlement/finalize' => [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'paid_amount' => '{{paymentAmount}}',
                'currency' => '{{currency}}',
                'transaction_code' => 'POSTMAN-{{$timestamp}}-{{$randomInt}}',
                'notes' => 'Postman cashier settlement',
                'row_version' => '{{orderRowVersion}}',
            ],
            'POST api/v1/staff/orders/{order_id}/kitchen/dispatch' => [
                'row_version' => '{{orderRowVersion}}',
            ],
            'POST api/v1/staff/reservations/{reservation_id}/refund' => [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'refund_scope' => '{{refundScope}}',
                'refund_amount' => '{{refundAmount}}',
                'currency' => '{{currency}}',
                'transaction_code' => 'REFUND-{{$timestamp}}-{{$randomInt}}',
                'row_version' => '{{reservationRowVersionRefund}}',
            ],
            'POST api/v1/staff/reservations/{reservation_id}/refund-cancel' => [
                'payment_method' => 'Cash',
                'payment_provider' => 'Cash',
                'refund_scope' => '{{refundScope}}',
                'currency' => '{{currency}}',
                'transaction_code' => 'REFUND-CANCEL-{{$timestamp}}-{{$randomInt}}',
                'reason' => 'customer_request',
                'cancel_reason' => 'customer_request',
                'row_version' => '{{reservationRowVersionRefundCancel}}',
            ],
            'POST api/v1/waiting-list' => [
                'branch_id' => '{{branchId}}',
                'guest_count' => '{{guestCount}}',
                'notes' => 'Postman waiting-list scenario',
            ],
            'POST api/v1/staff/waiting-list/{id}/notify' => [
                'table_id' => '{{preferredTableId}}',
                'hold_minutes' => 10,
                'row_version' => '{{waitingListRowVersion}}',
            ],
            'POST api/v1/staff/waiting-list/{id}/seat' => [
                'user_id' => '{{customerUserIdSecondary}}',
                'service_minutes' => 90,
                'row_version' => '{{waitingListRowVersion}}',
            ],
            'POST api/v1/reservations/{id}/voucher/apply' => [
                'user_voucher_id' => '{{userVoucherId}}',
                'row_version' => '{{reservationRowVersionBenefits}}',
            ],
            'POST api/v1/reservations/{id}/voucher/remove' => [
                'row_version' => '{{reservationRowVersionBenefits}}',
            ],
            'POST api/v1/reservations/{id}/loyalty/redeem' => [
                'points' => '{{loyaltyPoints}}',
                'reason' => 'Postman loyalty redeem',
                'row_version' => '{{reservationRowVersionBenefits}}',
            ],
            'POST api/v1/reservations/{id}/loyalty/redeem/release' => [
                'reason' => 'Postman loyalty release',
                'row_version' => '{{reservationRowVersionBenefits}}',
            ],
            'POST api/v1/me/privacy-requests' => [
                'request_type' => 'anonymize',
                'reason' => 'Postman privacy request',
            ],
            'POST api/v1/admin/restaurant/tables' => [
                'branch_id' => '{{branchId}}',
                'table_code' => 'POSTMAN-{{$timestamp}}',
                'template_id' => '{{tableTemplateId}}',
                'zone' => 'Postman Zone',
                'pos_x' => 99,
                'pos_y' => 99,
                'status' => 'Available',
                'description' => 'Postman-created table',
                'price' => '0.00',
            ],
            'POST api/v1/admin/menu/categories' => [
                'name' => 'Postman Category {{$timestamp}}',
                'description' => 'Postman-created category',
                'sort_order' => 250,
                'is_deleted' => false,
            ],
            'POST api/v1/admin/menu/items' => [
                'category_id' => '{{menuCategoryId}}',
                'code' => 'POSTMAN-ITEM-{{$timestamp}}',
                'name' => 'Postman Item {{$timestamp}}',
                'description' => 'Postman-created item',
                'is_available' => true,
                'is_preorder_enabled' => false,
                'preorder_quota_per_day' => null,
                'preorder_cutoff_minutes' => 0,
            ],
            'POST api/v1/admin/menu/items/{item_id}/prices' => [
                'price' => '123000.00',
                'currency' => '{{currency}}',
                'effective_from' => '{{checkedInAt}}',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/take-over' => [
                'notes' => 'Taking ownership from Postman collection',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/unassign' => [
                'notes' => 'Returning conversation to shared inbox from Postman collection',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/internal-notes' => [
                'message_text' => 'Postman internal note',
                'related_reservation_id' => '{{reservationIdDineIn}}',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/outbound-replies' => [
                'message_text' => 'Postman outbound follow-up',
                'related_reservation_id' => '{{reservationIdDineIn}}',
            ],
            'POST api/v1/payments/providers/{provider_code}/webhooks' => [
                'payment_scope' => '{{paymentWebhookScope}}',
                'provider_session_code' => '{{providerSessionCode}}',
                'provider_event_code' => '{{providerEventCode}}',
                'event_type' => 'payment.session.updated',
                'session_status' => 'Succeeded',
                'provider_payment_code' => 'PAY-{{$timestamp}}',
                'occurred_at' => '{{paymentWebhookOccurredAt}}',
            ],
        ],
        'capture_variables' => [
            'POST api/v1/auth/customer/register' => [
                'customerToken' => 'data.access_token',
                'customerSessionId' => 'data.session_id',
            ],
            'POST api/v1/auth/customer/login' => [
                'customerToken' => 'data.access_token',
                'customerSessionId' => 'data.session_id',
            ],
            'POST api/v1/auth/staff/login' => [
                'staffApiKey' => 'data.access_token',
            ],
            'POST api/v1/table-holds' => [
                'holdId' => 'data.hold_id',
                'tableHoldRowVersion' => 'data.row_version',
            ],
            'PATCH api/v1/table-holds/{hold_id}/refresh' => [
                'tableHoldRowVersion' => 'data.row_version',
            ],
            'POST api/v1/reservations' => [
                'reservationId' => 'data.reservation_id',
            ],
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions' => [
                'depositPaymentSessionId' => 'data.payment_session.deposit_payment_session_id',
                'depositPaymentSessionRowVersion' => 'data.payment_session.row_version',
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions' => [
                'billPaymentSessionId' => 'data.payment_session.bill_payment_session_id',
                'billPaymentSessionRowVersion' => 'data.payment_session.row_version',
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh' => [
                'billPaymentSessionRowVersion' => 'data.payment_session.row_version',
            ],
            'POST api/v1/waiting-list' => [
                'waitingListId' => 'data.waiting_id',
                'waitingListRowVersion' => 'data.row_version',
            ],
            'POST api/v1/staff/tables/{table_id}/orders' => [
                'orderId' => 'data.order_id',
                'orderRowVersion' => 'data.row_version',
                'orderItemId' => 'data.items.0.order_item_id',
                'orderItemRowVersion' => 'data.items.0.row_version',
            ],
            'POST api/v1/staff/orders/{order_id}/items' => [
                'orderRowVersion' => 'data.row_version',
                'orderItemId' => 'data.items.0.order_item_id',
                'orderItemRowVersion' => 'data.items.0.row_version',
            ],
            'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}' => [
                'orderRowVersion' => 'data.row_version',
                'orderItemRowVersion' => 'data.items.0.row_version',
            ],
            'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status' => [
                'orderRowVersion' => 'data.row_version',
                'orderItemRowVersion' => 'data.items.0.row_version',
            ],
            'POST api/v1/staff/cashier/shifts/open' => [
                'cashierShiftId' => 'data.cashier_shift_id',
                'cashierShiftRowVersion' => 'data.row_version',
            ],
            'POST api/v1/staff/cashier/shifts/{shift_id}/close' => [
                'cashierShiftRowVersion' => 'data.row_version',
            ],
            'POST api/v1/admin/restaurant/tables' => [
                'tableId' => 'data.table_id',
            ],
            'POST api/v1/admin/menu/categories' => [
                'menuCategoryId' => 'data.category_id',
            ],
            'POST api/v1/admin/menu/items' => [
                'menuItemId' => 'data.item_id',
            ],
            'POST api/v1/admin/menu/items/{item_id}/prices' => [
                'priceId' => 'data.price_id',
            ],
            'GET api/v1/reservations/{id}/preorder' => [
                'reservationIdPreorder' => 'data.reservation_id',
                'reservationRowVersionPreorder' => 'data.reservation_row_version',
                'preorderRowVersion' => 'data.pre_order.order_row_version',
            ],
            'PUT api/v1/reservations/{id}/preorder' => [
                'reservationIdPreorder' => 'data.reservation_id',
                'reservationRowVersionPreorder' => 'data.reservation_row_version',
                'preorderRowVersion' => 'data.pre_order.order_row_version',
            ],
            'DELETE api/v1/reservations/{id}/preorder' => [
                'reservationIdPreorder' => 'data.reservation_id',
                'reservationRowVersionPreorder' => 'data.reservation_row_version',
                'preorderRowVersion' => 'data.pre_order.order_row_version',
            ],
        ],
    ],

    'sdk' => [
        'typescript' => 'sdk/typescript/restaurantpos-sdk.ts',
        'enums' => 'sdk/typescript/restaurantpos-enums.ts',
        'readme' => 'sdk/typescript/README.md',
    ],

    'enums' => [
        'json' => 'enum-state-map.json',
        'typescript' => 'sdk/typescript/restaurantpos-enums.ts',
    ],

    'mutation_contract' => [
        'readme' => 'mutation-contracts.md',
        'groups' => [
            [
                'name' => 'Customer availability + table holds',
                'signatures' => [
                    'POST api/v1/table-holds',
                    'PATCH api/v1/table-holds/{hold_id}/refresh',
                    'DELETE api/v1/table-holds/{hold_id}',
                ],
            ],
            [
                'name' => 'Customer reservation + preorder + deposit + bill payment',
                'signatures' => [
                    'POST api/v1/reservations',
                    'POST api/v1/reservations/{id}/cancel',
                    'POST api/v1/reservations/{id}/reschedule',
                    'PUT api/v1/reservations/{id}/preorder',
                    'DELETE api/v1/reservations/{id}/preorder',
                    'POST api/v1/reservations/{id}/deposit/acknowledge',
                    'POST api/v1/reservations/{id}/deposit/intent',
                    'POST api/v1/reservations/{id}/deposit/intent/revoke',
                    'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions',
                    'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh',
                    'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm',
                    'POST api/v1/reservations/{reservation_id}/bill/payment-sessions',
                    'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh',
                    'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm',
                ],
            ],
            [
                'name' => 'Customer waiting list',
                'signatures' => [
                    'POST api/v1/waiting-list',
                    'POST api/v1/waiting-list/{id}/accept',
                    'POST api/v1/waiting-list/{id}/confirm-arrival',
                    'POST api/v1/waiting-list/{id}/decline',
                    'POST api/v1/waiting-list/{id}/cancel',
                ],
            ],
            [
                'name' => 'Customer benefits',
                'signatures' => [
                    'POST api/v1/reservations/{id}/voucher/apply',
                    'POST api/v1/reservations/{id}/voucher/remove',
                    'POST api/v1/reservations/{id}/loyalty/redeem',
                    'POST api/v1/reservations/{id}/loyalty/redeem/release',
                ],
            ],
            [
                'name' => 'Customer privacy',
                'signatures' => [
                    'POST api/v1/me/privacy-requests',
                ],
            ],
            [
                'name' => 'Staff waiting list',
                'signatures' => [
                    'POST api/v1/staff/waiting-list',
                    'POST api/v1/staff/waiting-list/{id}/notify',
                    'POST api/v1/staff/waiting-list/{id}/cancel',
                    'POST api/v1/staff/waiting-list/{id}/advance',
                    'POST api/v1/staff/waiting-list/{id}/seat',
                ],
            ],
            [
                'name' => 'Staff order + checkout + cashier core',
                'signatures' => [
                    'POST api/v1/staff/reservations/{id}/check-in',
                    'POST api/v1/staff/reservations/{id}/assign-table',
                    'POST api/v1/staff/reservations/{id}/assign-best-fit',
                    'POST api/v1/staff/reservations/{id}/move-table',
                    'POST api/v1/staff/tables/{table_id}/release',
                    'POST api/v1/staff/tables/{table_id}/orders',
                    'POST api/v1/staff/orders/{order_id}/items',
                    'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}',
                    'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status',
                    'POST api/v1/staff/orders/{order_id}/bill-snapshot',
                    'POST api/v1/staff/orders/{order_id}/pay',
                    'POST api/v1/staff/orders/{order_id}/settlement/finalize',
                    'POST api/v1/staff/cashier/shifts/open',
                    'POST api/v1/staff/cashier/shifts/{shift_id}/close',
                    'POST api/v1/staff/finance/invoices/{reservation_id}/issue',
                ],
            ],
            [
                'name' => 'Staff conversation workflow',
                'signatures' => [
                    'POST api/v1/staff/conversations/{conversation_id}/assign',
                    'POST api/v1/staff/conversations/{conversation_id}/workflow-state',
                    'POST api/v1/staff/conversations/{conversation_id}/links',
                    'DELETE api/v1/staff/conversations/{conversation_id}/links/reservation',
                    'DELETE api/v1/staff/conversations/{conversation_id}/links/waiting-list',
                ],
            ],
            [
                'name' => 'Admin inventory receiving',
                'signatures' => [
                    'POST api/v1/admin/inventory/ingredients/{id}/movements',
                    'POST api/v1/admin/inventory/purchase-orders/{id}/receipts',
                ],
            ],
            [
                'name' => 'Admin benefits',
                'signatures' => [
                    'POST api/v1/admin/benefits/vouchers',
                    'PATCH api/v1/admin/benefits/vouchers/{id}',
                    'POST api/v1/admin/benefits/loyalty-tiers',
                    'PATCH api/v1/admin/benefits/loyalty-tiers/{id}',
                    'POST api/v1/admin/settings/benefits',
                ],
            ],
            [
                'name' => 'Admin privacy',
                'signatures' => [
                    'POST api/v1/admin/privacy/requests/{request_id}/review',
                ],
            ],
            [
                'name' => 'Staff kitchen core',
                'signatures' => [
                    'POST api/v1/staff/orders/{order_id}/kitchen/dispatch',
                    'POST api/v1/staff/kitchen/tickets/{ticket_id}/fire',
                    'POST api/v1/staff/kitchen/tickets/{ticket_id}/bump',
                    'POST api/v1/staff/kitchen/tickets/{ticket_id}/recall',
                ],
            ],
            [
                'name' => 'Admin branch update',
                'signatures' => [
                    'PATCH api/v1/admin/settings/branches/{id}',
                ],
            ],
        ],
    ],

    'environments' => [
        'local' => [
            'baseUrl' => 'http://127.0.0.1:8000',
            'providerCode' => 'simulated',
            'paymentWebhookScope' => 'deposit',
            'paymentWebhookSecret' => '',
            'paymentWebhookOccurredAt' => '2026-04-05T10:05:00Z',
            'customerUsername' => '',
            'customerPassword' => '',
            'customerToken' => '',
            'customerSessionId' => '',
            'staffUsername' => '',
            'staffPassword' => '',
            'staffApiKey' => '',
            'adminUsername' => '',
            'adminPassword' => '',
            'adminApiKey' => '',
            'branchId' => '',
            'reservationId' => '',
            'reservationIdPreorder' => '',
            'reservationIdDeposit' => '',
            'reservationIdDineIn' => '',
            'reservationIdBenefits' => '',
            'reservationIdRefund' => '',
            'reservationIdRefundCancel' => '',
            'holdId' => '',
            'tableHoldRowVersion' => '1',
            'tableId' => '',
            'dineInTableId' => '',
            'preferredTableId' => '',
            'tableTemplateId' => '',
            'waitingListId' => '',
            'waitingListRowVersion' => '',
            'conversationId' => '',
            'orderId' => '',
            'orderRowVersion' => '',
            'orderItemId' => '',
            'orderItemRowVersion' => '',
            'cashierShiftId' => '',
            'cashierShiftRowVersion' => '',
            'depositPaymentSessionId' => '',
            'depositPaymentSessionRowVersion' => '',
            'billPaymentSessionId' => '',
            'billPaymentSessionRowVersion' => '',
            'userVoucherId' => '',
            'customerUserIdSecondary' => '',
            'menuCategoryId' => '',
            'menuItemId' => '',
            'menuItemIdPrimary' => '',
            'menuItemIdSecondary' => '',
            'menuPreorderOnly' => '0',
            'priceId' => '',
            'availabilityFromUtc' => '2026-04-05T12:00:00Z',
            'availabilityToUtc' => '2026-04-05T14:00:00Z',
            'boardZone' => '',
            'includeHolds' => '1',
            'boardGroupBy' => '',
            'boardAfterVersion' => '0',
            'guestCount' => '2',
            'suggestAvailability' => '1',
            'checkedInAt' => '2026-04-05T12:05:00Z',
            'paymentAmount' => '100000',
            'openingFloatAmount' => '100000',
            'closingCashAmount' => '100000',
            'refundAmount' => '50000',
            'refundScope' => 'deposit',
            'cancelAfterPayment' => '0',
            'currency' => 'VND',
            'loyaltyPoints' => '100',
            'voucherStatus' => 'available',
            'privacyRequestStatus' => 'requested',
            'reservationStatus' => '',
            'reservationActiveOnly' => '1',
            'conversationStatus' => 'Open',
            'waitingListStatus' => 'Waiting',
            'waitingListActiveOnly' => '1',
            'waitingListSort' => '-priority',
            'waitingListAfterVersion' => '0',
            'perPage' => '20',
            'messageLimit' => '10',
            'eventLimit' => '10',
            'providerSessionCode' => 'sim-dep-001',
            'providerEventCode' => 'sim-webhook-001',
            'reservationRowVersionDineIn' => '1',
            'reservationRowVersion' => '1',
            'reservationRowVersionPreorder' => '1',
            'reservationRowVersionDeposit' => '1',
            'reservationRowVersionBenefits' => '1',
            'reservationRowVersionRefund' => '1',
            'reservationRowVersionRefundCancel' => '1',
            'preorderRowVersion' => '1',
            'preorderReplacementItemId' => '',
            'loyaltySummaryLimit' => '20',
        ],
        'staging' => [
            'baseUrl' => 'https://staging.example.invalid',
            'providerCode' => 'simulated',
            'paymentWebhookScope' => 'deposit',
            'paymentWebhookSecret' => '',
            'paymentWebhookOccurredAt' => '2026-04-05T10:05:00Z',
            'customerUsername' => '',
            'customerPassword' => '',
            'customerToken' => '',
            'customerSessionId' => '',
            'staffUsername' => '',
            'staffPassword' => '',
            'staffApiKey' => '',
            'adminUsername' => '',
            'adminPassword' => '',
            'adminApiKey' => '',
            'branchId' => '',
            'reservationId' => '',
            'reservationIdPreorder' => '',
            'reservationIdDeposit' => '',
            'reservationIdDineIn' => '',
            'reservationIdBenefits' => '',
            'reservationIdRefund' => '',
            'reservationIdRefundCancel' => '',
            'holdId' => '',
            'tableHoldRowVersion' => '',
            'tableId' => '',
            'dineInTableId' => '',
            'preferredTableId' => '',
            'tableTemplateId' => '',
            'waitingListId' => '',
            'waitingListRowVersion' => '',
            'conversationId' => '',
            'orderId' => '',
            'orderRowVersion' => '',
            'orderItemId' => '',
            'orderItemRowVersion' => '',
            'cashierShiftId' => '',
            'cashierShiftRowVersion' => '',
            'depositPaymentSessionId' => '',
            'depositPaymentSessionRowVersion' => '',
            'billPaymentSessionId' => '',
            'billPaymentSessionRowVersion' => '',
            'userVoucherId' => '',
            'customerUserIdSecondary' => '',
            'menuCategoryId' => '',
            'menuItemId' => '',
            'menuItemIdPrimary' => '',
            'menuItemIdSecondary' => '',
            'menuPreorderOnly' => '0',
            'priceId' => '',
            'availabilityFromUtc' => '',
            'availabilityToUtc' => '',
            'boardZone' => '',
            'includeHolds' => '1',
            'boardGroupBy' => '',
            'boardAfterVersion' => '0',
            'guestCount' => '2',
            'suggestAvailability' => '1',
            'checkedInAt' => '',
            'paymentAmount' => '',
            'openingFloatAmount' => '',
            'closingCashAmount' => '',
            'refundAmount' => '',
            'refundScope' => 'deposit',
            'cancelAfterPayment' => '0',
            'currency' => 'VND',
            'loyaltyPoints' => '100',
            'voucherStatus' => 'available',
            'privacyRequestStatus' => 'requested',
            'reservationStatus' => '',
            'reservationActiveOnly' => '1',
            'conversationStatus' => 'Open',
            'waitingListStatus' => 'Waiting',
            'waitingListActiveOnly' => '1',
            'waitingListSort' => '-priority',
            'waitingListAfterVersion' => '0',
            'perPage' => '20',
            'messageLimit' => '10',
            'eventLimit' => '10',
            'providerSessionCode' => '',
            'providerEventCode' => '',
            'reservationRowVersionDineIn' => '',
            'reservationRowVersion' => '',
            'reservationRowVersionPreorder' => '',
            'reservationRowVersionDeposit' => '',
            'reservationRowVersionBenefits' => '',
            'reservationRowVersionRefund' => '',
            'reservationRowVersionRefundCancel' => '',
            'preorderRowVersion' => '',
            'preorderReplacementItemId' => '',
            'loyaltySummaryLimit' => '20',
        ],
    ],
];

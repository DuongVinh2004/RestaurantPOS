<?php

declare(strict_types=1);

namespace App\Platform\ApiContract\Services;

use App\Enums\WaitingListCustomerResponseState;

class ApiContractMetadataRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function priorityOperations(): array
    {
        return [
            'POST api/v1/auth/customer/login' => [
                'summary' => 'Customer login',
                'description' => 'Issue an opaque customer access session token for productized customer self-service.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'CustomerAuthSessionEnvelope'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'identifier' => 'customer-auth-http',
                    'password' => 'secret-123',
                    'session_label' => 'web',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/auth/customer/register' => [
                'summary' => 'Customer registration',
                'description' => 'Create a customer account using the configured customer role and issue an opaque customer access session token.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'CustomerAuthSessionEnvelope'],
                    422 => ['schema' => 'ValidationError'],
                    503 => ['schema' => 'ApiError'],
                ],
                'request_example' => [
                    'full_name' => 'Demo Customer',
                    'email' => 'demo.customer@example.test',
                    'phone' => '0901000000',
                    'password' => 'secret-123',
                    'password_confirmation' => 'secret-123',
                    'session_label' => 'web',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/auth/customer/me' => [
                'summary' => 'Get current customer session',
                'description' => 'Return the currently authenticated customer access session and user profile.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'CustomerAuthSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/auth/customer/refresh' => [
                'summary' => 'Refresh customer session',
                'description' => 'Rotate the current customer access session and return a replacement opaque token.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'CustomerAuthSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/auth/customer/logout' => [
                'summary' => 'Logout customer session',
                'description' => 'Revoke the current customer access session token.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'CustomerSessionLogoutEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/auth/staff/login' => [
                'summary' => 'Staff login',
                'description' => 'Issue an opaque staff API key session for operational staff and admin APIs. During the opt-in staff-web rollout, session_transport=refresh_cookie sets an HttpOnly Secure SameSite refresh cookie and returns only a memory access token.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'StaffAuthSessionEnvelope'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'identifier' => 'staff-auth-http',
                    'password' => 'secret-123',
                    'label' => 'POS Terminal 01',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/auth/staff/me' => [
                'summary' => 'Get current staff session',
                'description' => 'Return the authenticated staff API key session and bound staff actor profile.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'StaffAuthSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/auth/staff/refresh' => [
                'summary' => 'Refresh staff session',
                'description' => 'Rotate the current staff API key session and return a replacement opaque token. Cookie-backed staff-web refresh requires X-Staff-CSRF and returns a memory access token without exposing the refresh cookie secret.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'StaffAuthSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    419 => ['schema' => 'UnauthorizedError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/auth/staff/logout' => [
                'summary' => 'Logout staff session',
                'description' => 'Revoke the current staff API key session. Cookie-backed staff-web logout clears the refresh and CSRF cookies and revokes the refresh/session key.',
                'tags' => ['Auth'],
                'responses' => [
                    200 => ['schema' => 'StaffSessionLogoutEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    419 => ['schema' => 'UnauthorizedError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/user' => [
                'summary' => 'Get legacy authenticated actor',
                'description' => 'Deprecated compatibility route for existing web auth probes. It returns the runtime top-level auth_mode/user payload for customer access tokens or staff API keys. Session-only customer auth is intentionally unsupported.',
                'tags' => ['Legacy'],
                'responses' => [
                    200 => ['schema' => 'ApiUserEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                ],
                'auth_mode' => 'customer_or_staff',
                'security' => [
                    ['CustomerAccessToken' => []],
                    ['StaffApiKey' => []],
                ],
                'deprecated' => true,
                'contract_grade' => 'full',
            ],
            'GET api/v1/restaurant/profile' => [
                'summary' => 'Show public restaurant profile',
                'description' => 'Return customer-web public restaurant operating context for footer and booking entry points, including default branch timezone, business hours, today hours, and current open state.',
                'tags' => ['Restaurant Profile'],
                'responses' => [
                    200 => ['schema' => 'RestaurantProfileEnvelope'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'auth_mode' => 'public',
                'security' => [],
                'contract_grade' => 'full',
            ],
            'GET api/v1/restaurant/branches' => [
                'summary' => 'List active restaurant branches',
                'description' => 'Return customer-web active restaurant branches and their operating context.',
                'tags' => ['Restaurant Profile'],
                'responses' => [
                    200 => ['schema' => 'RestaurantProfileCollectionEnvelope'],
                ],
                'auth_mode' => 'public',
                'security' => [],
                'contract_grade' => 'full',
            ],
            'GET api/v1/tables/available' => [
                'summary' => 'List available tables',
                'description' => 'Return customer-visible table availability for a requested time window, branch, guest count, and optional suggestion filters.',
                'tags' => ['Availability'],
                'responses' => [
                    200 => ['schema' => 'AvailableTablesCollectionEnvelope'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'public',
                'security' => [],
                'contract_grade' => 'full',
            ],
            'POST api/v1/table-holds' => [
                'summary' => 'Create table hold',
                'description' => 'Create an idempotent session-bound hold for one or more tables before reservation creation.',
                'tags' => ['Availability'],
                'responses' => [
                    201 => ['schema' => 'TableHoldEnvelope'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'session_id' => 'sess-demo-001',
                    'start_time' => '2026-04-05T12:00:00Z',
                    'end_time' => '2026-04-05T14:00:00Z',
                    'table_ids' => [11],
                    'guest_count' => 2,
                    'branch_id' => 10,
                ],
                'auth_mode' => 'customer_session',
                'security' => [['CustomerSessionId' => []]],
                'contract_grade' => 'full',
            ],
            'GET api/v1/table-holds/{hold_id}' => [
                'summary' => 'Show table hold',
                'description' => 'Return a session-bound table hold by hold id. The caller must provide the matching session id unless using staff access.',
                'tags' => ['Availability'],
                'responses' => [
                    200 => ['schema' => 'TableHoldEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'customer_or_staff',
                'security' => [
                    ['CustomerSessionId' => []],
                    ['StaffApiKey' => []],
                ],
                'contract_grade' => 'full',
            ],
            'PATCH api/v1/table-holds/{hold_id}/refresh' => [
                'summary' => 'Refresh table hold',
                'description' => 'Refresh an active table hold for the matching session id using row-version stale-write protection when supplied.',
                'tags' => ['Availability'],
                'responses' => [
                    200 => ['schema' => 'TableHoldEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'session_id' => 'sess-demo-001',
                    'extend_minutes' => 5,
                    'row_version' => 1,
                ],
                'auth_mode' => 'customer_or_staff',
                'security' => [
                    ['CustomerSessionId' => []],
                    ['StaffApiKey' => []],
                ],
                'contract_grade' => 'full',
            ],
            'DELETE api/v1/table-holds/{hold_id}' => [
                'summary' => 'Cancel table hold',
                'description' => 'Cancel a table hold for the matching session id using row-version stale-write protection when supplied.',
                'tags' => ['Availability'],
                'responses' => [
                    200 => ['schema' => 'TableHoldEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'customer_or_staff',
                'security' => [
                    ['CustomerSessionId' => []],
                    ['StaffApiKey' => []],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/menu/categories' => [
                'summary' => 'List visible customer menu categories',
                'description' => 'Return customer-facing menu categories and their visible items for the selected service time and preorder filter.',
                'tags' => ['Menu Catalog'],
                'responses' => [
                    200 => ['schema' => 'CustomerMenuCategoriesCollectionEnvelope'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'public',
                'security' => [],
                'contract_grade' => 'full',
            ],
            'GET api/v1/menu/items' => [
                'summary' => 'List visible customer menu items',
                'description' => 'Return the customer-facing menu catalog slice that is valid for the selected service time, category filter, and preorder visibility filter.',
                'tags' => ['Menu Catalog'],
                'responses' => [
                    200 => ['schema' => 'CustomerMenuItemsCollectionEnvelope'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'public',
                'security' => [],
                'contract_grade' => 'full',
            ],
            'GET api/v1/menu/items/{id}' => [
                'summary' => 'Show visible customer menu item',
                'description' => 'Return one customer-facing menu item including effective price and preorder policy for the selected service time.',
                'tags' => ['Menu Catalog'],
                'responses' => [
                    200 => ['schema' => 'CustomerMenuItemEnvelope'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'public',
                'security' => [],
                'contract_grade' => 'full',
            ],
            'POST api/v1/menu/preorder/preview' => [
                'summary' => 'Preview menu preorder',
                'description' => 'Validate customer pre-order item quantities and service-time constraints before reservation creation.',
                'tags' => ['Menu Catalog'],
                'responses' => [
                    200 => ['schema' => 'CustomerMenuPreorderPreviewEnvelope'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'start_time' => '2026-04-05T12:00:00Z',
                    'pre_order_items' => [
                        ['item_id' => 201, 'quantity' => 2],
                    ],
                ],
                'auth_mode' => 'public',
                'security' => [],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/menu/items' => [
                'summary' => 'List staff menu items',
                'description' => 'Return the staff ordering menu catalog slice for active dine-in order composition using the same price-effective visibility rules as customer menu reads.',
                'tags' => ['Menu Catalog'],
                'responses' => [
                    200 => ['schema' => 'CustomerMenuItemsCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations' => [
                'summary' => 'Create reservation',
                'description' => 'Create a reservation from a customer-owned/session-owned hold or by staff on behalf of a customer.',
                'tags' => ['Reservations'],
                'responses' => [
                    201 => ['schema' => 'ReservationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'start_time' => '2026-04-05T12:00:00Z',
                    'end_time' => '2026-04-05T14:00:00Z',
                    'guest_count' => 4,
                    'hold_id' => '550e8400-e29b-41d4-a716-446655440000',
                    'session_id' => 'sess-demo-001',
                    'notes' => 'Window side',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{id}' => [
                'summary' => 'Show reservation',
                'description' => 'Return the reservation contract view for staff, customer owner, or session-scoped self-service access.',
                'tags' => ['Reservations'],
                'responses' => [
                    200 => ['schema' => 'ReservationEnvelope'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{id}/preorder' => [
                'summary' => 'Show customer reservation pre-order',
                'description' => 'Return the current customer-visible pre-order snapshot and management policy for an owned reservation or a valid session-linked reservation.',
                'tags' => ['Reservations'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationPreorderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'auth_mode' => 'customer_or_session',
                'security' => [
                    ['CustomerAccessToken' => []],
                    ['CustomerSessionId' => []],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/preorder/preview' => [
                'summary' => 'Preview customer reservation pre-order',
                'description' => 'Validate proposed customer-managed pre-order lines against reservation service time and item preorder policy without persisting changes.',
                'tags' => ['Reservations'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationPreorderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'pre_order_items' => [
                        ['item_id' => 201, 'quantity' => 2],
                    ],
                ],
                'auth_mode' => 'customer_or_session',
                'security' => [
                    ['CustomerAccessToken' => []],
                    ['CustomerSessionId' => []],
                ],
                'contract_grade' => 'full',
            ],
            'PUT api/v1/reservations/{id}/preorder' => [
                'summary' => 'Replace customer reservation pre-order',
                'description' => 'Replace the current customer-managed pre-order lines for an owned reservation or a valid session-linked reservation. The reservation row version is always required and the pre-order row version becomes required when an active pre-order already exists.',
                'tags' => ['Reservations'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationPreorderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 4,
                    'pre_order_row_version' => 2,
                    'pre_order_items' => [
                        ['item_id' => 201, 'quantity' => 2],
                        ['item_id' => 305, 'quantity' => 1],
                    ],
                ],
                'auth_mode' => 'customer_or_session',
                'security' => [
                    ['CustomerAccessToken' => []],
                    ['CustomerSessionId' => []],
                ],
                'contract_grade' => 'full',
            ],
            'DELETE api/v1/reservations/{id}/preorder' => [
                'summary' => 'Clear customer reservation pre-order',
                'description' => 'Clear the current customer-managed pre-order for an owned reservation or a valid session-linked reservation. The reservation row version is always required and the pre-order row version becomes required when an active pre-order exists.',
                'tags' => ['Reservations'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationPreorderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'customer_or_session',
                'security' => [
                    ['CustomerAccessToken' => []],
                    ['CustomerSessionId' => []],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/me/loyalty' => [
                'summary' => 'Show customer loyalty summary',
                'description' => 'Return the authenticated customer owner loyalty profile and recent loyalty point transactions.',
                'tags' => ['Customer Benefits'],
                'responses' => [
                    200 => ['schema' => 'CustomerLoyaltySummaryEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'GET api/v1/me/vouchers' => [
                'summary' => 'List customer vouchers',
                'description' => 'Return the authenticated customer owner voucher wallet with applicability filters for customer-web.',
                'tags' => ['Customer Benefits'],
                'responses' => [
                    200 => ['schema' => 'CustomerVoucherCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'GET api/v1/me/data-export' => [
                'summary' => 'Export customer data',
                'description' => 'Return a customer-owner-scoped data export payload for account privacy self-service.',
                'tags' => ['Customer Privacy'],
                'responses' => [
                    200 => ['schema' => 'CustomerDataExportEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'GET api/v1/me/privacy-requests' => [
                'summary' => 'List customer privacy requests',
                'description' => 'Return the authenticated customer owner privacy request history.',
                'tags' => ['Customer Privacy'],
                'responses' => [
                    200 => ['schema' => 'CustomerPrivacyRequestCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'POST api/v1/me/privacy-requests' => [
                'summary' => 'Create customer privacy request',
                'description' => 'Create or return the current customer privacy request for account lifecycle self-service.',
                'tags' => ['Customer Privacy'],
                'responses' => [
                    200 => ['schema' => 'CustomerPrivacyRequestEnvelope'],
                    201 => ['schema' => 'CustomerPrivacyRequestEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'request_type' => 'anonymize',
                    'reason' => 'Customer requested account deletion.',
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{id}/benefits-preview' => [
                'summary' => 'Preview reservation loyalty and voucher benefits',
                'description' => 'Return the owner-scoped reservation loyalty snapshot together with voucher applicability preview rows for customer self-service.',
                'tags' => ['Customer Benefits'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationBenefitsPreviewEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/voucher/apply' => [
                'summary' => 'Apply reservation voucher',
                'description' => 'Apply an owned voucher to a customer-owned reservation with row-version protection.',
                'tags' => ['Customer Benefits'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationVoucherActionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'user_voucher_id' => 301,
                    'row_version' => 3,
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/voucher/remove' => [
                'summary' => 'Remove reservation voucher',
                'description' => 'Remove the currently applied voucher from a customer-owned reservation with row-version protection.',
                'tags' => ['Customer Benefits'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationVoucherActionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 4,
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/loyalty/redeem' => [
                'summary' => 'Redeem reservation loyalty points',
                'description' => 'Redeem customer loyalty points against a customer-owned reservation with row-version protection.',
                'tags' => ['Customer Benefits'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationLoyaltyActionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'points' => 100,
                    'reason' => 'Customer redemption',
                    'row_version' => 5,
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/loyalty/redeem/release' => [
                'summary' => 'Release reservation loyalty redemption',
                'description' => 'Release a customer loyalty redemption from a customer-owned reservation with row-version protection.',
                'tags' => ['Customer Benefits'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationLoyaltyActionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'reason' => 'Customer changed plan',
                    'row_version' => 6,
                ],
                'auth_mode' => 'customer_access_token',
                'security' => [['CustomerAccessToken' => []]],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{id}/deposit-preview' => [
                'summary' => 'Show reservation deposit preview',
                'description' => 'Return deposit requirement state, payment summary, and customer self-service affordances for a reservation.',
                'tags' => ['Reservation Deposit'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationDepositPreviewEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/deposit/acknowledge' => [
                'summary' => 'Acknowledge deposit requirement',
                'description' => 'Acknowledge the deposit requirement before a customer submits payment intent.',
                'tags' => ['Reservation Deposit'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationDepositPreviewEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 1,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/deposit/intent' => [
                'summary' => 'Submit deposit intent',
                'description' => 'Record customer acknowledgement that deposit payment will be completed through supported payment flows.',
                'tags' => ['Reservation Deposit'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationDepositPreviewEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/deposit/intent/revoke' => [
                'summary' => 'Revoke deposit intent',
                'description' => 'Revoke a previously submitted customer deposit intent.',
                'tags' => ['Reservation Deposit'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationDepositPreviewEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 3,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions' => [
                'summary' => 'Create deposit payment session',
                'description' => 'Create a customer-facing deposit payment session bound to a reservation and idempotency key.',
                'tags' => ['Reservation Deposit'],
                'responses' => [
                    201 => ['schema' => 'CustomerDepositPaymentSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 1,
                    'amount' => 100000,
                    'payment_method' => 'Online',
                    'provider_code' => 'simulated',
                    'currency' => 'VND',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}' => [
                'summary' => 'Show deposit payment session',
                'description' => 'Return a previously created deposit payment session with the current deposit snapshot.',
                'tags' => ['Reservation Deposit'],
                'responses' => [
                    200 => ['schema' => 'CustomerDepositPaymentSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh' => [
                'summary' => 'Refresh deposit payment session',
                'description' => 'Refresh a customer deposit payment session using provider-specific polling or simulated outcomes.',
                'tags' => ['Reservation Deposit'],
                'responses' => [
                    200 => ['schema' => 'CustomerDepositPaymentSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 1,
                    'simulation_outcome' => 'succeeded',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm' => [
                'summary' => 'Confirm deposit payment session',
                'description' => 'Confirm or reconcile a customer deposit payment session after provider completion.',
                'tags' => ['Reservation Deposit'],
                'responses' => [
                    200 => ['schema' => 'CustomerDepositPaymentSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 1,
                    'simulation_outcome' => 'succeeded',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{reservation_id}/bill' => [
                'summary' => 'Show customer bill',
                'description' => 'Return the customer-visible bill, settlement totals, and payment session workflow for a reservation.',
                'tags' => ['Reservation Billing'],
                'responses' => [
                    200 => ['schema' => 'CustomerReservationBillEnvelope'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{reservation_id}/bill-preview' => [
                'summary' => 'Preview bill',
                'description' => 'Return the active order plus a current bill preview for customer self-service.',
                'tags' => ['Reservation Billing'],
                'responses' => [
                    200 => ['schema' => 'CustomerBillPreviewEnvelope'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{reservation_id}/active-order' => [
                'summary' => 'Show active order',
                'description' => 'Return the currently active order snapshot associated with a reservation bill.',
                'tags' => ['Reservation Billing'],
                'responses' => [
                    200 => ['schema' => 'CustomerActiveOrderEnvelope'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions' => [
                'summary' => 'Create bill payment session',
                'description' => 'Create a customer-facing bill payment session for final settlement.',
                'tags' => ['Reservation Billing'],
                'responses' => [
                    201 => ['schema' => 'CustomerBillPaymentSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 1,
                    'amount' => 250000,
                    'payment_method' => 'Online',
                    'provider_code' => 'simulated',
                    'currency' => 'VND',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}' => [
                'summary' => 'Show bill payment session',
                'description' => 'Return a previously created bill payment session and current bill snapshot.',
                'tags' => ['Reservation Billing'],
                'responses' => [
                    200 => ['schema' => 'CustomerBillPaymentSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh' => [
                'summary' => 'Refresh bill payment session',
                'description' => 'Refresh a customer bill payment session using provider polling or simulated outcomes.',
                'tags' => ['Reservation Billing'],
                'responses' => [
                    200 => ['schema' => 'CustomerBillPaymentSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 1,
                    'simulation_outcome' => 'succeeded',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm' => [
                'summary' => 'Confirm bill payment session',
                'description' => 'Confirm or reconcile a customer bill payment session after provider completion.',
                'tags' => ['Reservation Billing'],
                'responses' => [
                    200 => ['schema' => 'CustomerBillPaymentSessionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 1,
                    'simulation_outcome' => 'succeeded',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/waiting-list' => [
                'summary' => 'List customer waiting-list entries',
                'description' => 'Return the authenticated customer owner waiting-list entries.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'CustomerWaitingListCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/waiting-list' => [
                'summary' => 'Create customer waiting-list entry',
                'description' => 'Create an owner-only waiting-list entry for the authenticated customer.',
                'tags' => ['Waiting List'],
                'responses' => [
                    201 => ['schema' => 'CustomerWaitingListEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'guest_count' => 3,
                    'notes' => 'Near window please',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/waiting-list/{id}' => [
                'summary' => 'Show customer waiting-list entry',
                'description' => 'Return one owner-scoped waiting-list entry.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'CustomerWaitingListEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/waiting-list/{id}/accept' => [
                'summary' => 'Accept notified waiting-list entry',
                'description' => 'Accept a notified waiting-list invitation within the active notify window.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'CustomerWaitingListEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/waiting-list/{id}/confirm-arrival' => [
                'summary' => 'Confirm arrival for notified waiting-list entry',
                'description' => 'Confirm that the customer has arrived on-site and is waiting for staff seating.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'CustomerWaitingListArrivalEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 3,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/waiting-list/{id}/decline' => [
                'summary' => 'Decline notified waiting-list entry',
                'description' => 'Decline a notified waiting-list invitation and release the associated notify hold.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'CustomerWaitingListEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 3,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/waiting-list/{id}/cancel' => [
                'summary' => 'Cancel customer waiting-list entry',
                'description' => 'Cancel an owner-scoped waiting-list entry and optionally record a customer cancel reason.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'CustomerWaitingListEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 2,
                    'cancel_reason' => 'Plans changed',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/payments/providers/{provider_code}/webhooks' => [
                'summary' => 'Receive payment provider webhook',
                'description' => 'Ingest a provider webhook, verify the request signature, deduplicate by provider event code, and reconcile deposit or bill payment sessions.',
                'tags' => ['Payment Webhooks'],
                'parameters' => [
                    [
                        'name' => 'X-Payment-Signature',
                        'in' => 'header',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => 'HMAC signature header. The effective header can be provider-specific, but defaults to X-Payment-Signature.',
                    ],
                    [
                        'name' => 'X-Payment-Timestamp',
                        'in' => 'header',
                        'required' => false,
                        'schema' => ['type' => 'string'],
                        'description' => 'Webhook timestamp header used by HMAC providers when max-age validation is enabled.',
                    ],
                ],
                'request_body' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'payment_scope' => ['type' => 'string', 'enum' => ['deposit', 'bill']],
                                    'provider_session_code' => ['type' => 'string'],
                                    'provider_event_code' => ['type' => 'string'],
                                    'event_type' => ['type' => 'string'],
                                    'session_status' => ['type' => 'string'],
                                    'provider_payment_code' => ['type' => 'string', 'nullable' => true],
                                    'occurred_at' => ['type' => 'string', 'format' => 'date-time'],
                                ],
                                'required' => ['provider_session_code', 'provider_event_code', 'session_status'],
                                'additionalProperties' => true,
                            ],
                            'example' => [
                                'payment_scope' => 'deposit',
                                'provider_session_code' => 'sim-dep-001',
                                'provider_event_code' => 'sim-webhook-001',
                                'event_type' => 'payment.session.updated',
                                'session_status' => 'Succeeded',
                                'provider_payment_code' => 'sim-pay-001',
                                'occurred_at' => '2026-04-05T10:05:00Z',
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    202 => ['schema' => 'WebhookReceiptEnvelope'],
                    401 => ['schema' => 'ValidationError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'security' => [],
                'contract_grade' => 'full',
            ],
            'GET api/v1/health' => [
                'summary' => 'Health check',
                'description' => 'Public minimal health endpoint exposing only service liveness status and timestamp.',
                'tags' => ['Health'],
                'responses' => [
                    200 => ['schema' => 'HealthStatusEnvelope'],
                    503 => ['schema' => 'HealthStatusEnvelope'],
                ],
                'security' => [],
                'contract_grade' => 'full',
                'envelope_exception' => true,
            ],
            'GET api/v1/health/detailed' => [
                'summary' => 'Detailed health check',
                'description' => 'Privileged operational health endpoint with internal db, redis, scheduler, disk, and ops diagnostics.',
                'tags' => ['Health'],
                'responses' => [
                    200 => ['schema' => 'HealthDetailedEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    503 => ['schema' => 'HealthDetailedEnvelope'],
                ],
                'security' => [['staffKey' => []]],
                'contract_grade' => 'full',
                'envelope_exception' => true,
            ],
            'GET api/v1/health/redis' => [
                'summary' => 'Redis health check',
                'description' => 'Privileged Redis readiness endpoint covering set/get and lock acquisition.',
                'tags' => ['Health'],
                'responses' => [
                    200 => ['schema' => 'HealthRedisEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    503 => ['schema' => 'HealthRedisEnvelope'],
                ],
                'security' => [['staffKey' => []]],
                'contract_grade' => 'full',
                'envelope_exception' => true,
            ],
            'GET api/v1/staff/tables/board' => [
                'summary' => 'Show staff table board',
                'description' => 'Return the operational floor board snapshot with table state, holds, reservations, orchestration hints, and realtime metadata.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'StaffTableBoardEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
                'envelope_exception' => true,
            ],
            'GET api/v1/staff/tables/board/changes' => [
                'summary' => 'Read table board change feed',
                'description' => 'Return the staff board realtime polling feed since the requested version cursor.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'StaffOperationalRealtimeEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/tables/{table_id}/active-service-session' => [
                'summary' => 'Show active service session by table',
                'description' => 'Return the active checked-in reservation-backed service session currently occupying the requested table.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'ReservationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/service-sessions/walk-in' => [
                'summary' => 'Start walk-in service session',
                'description' => 'Create a checked-in reservation-backed service session for a walk-in party, optionally provisioning a lightweight customer profile.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    201 => ['schema' => 'ReservationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'guest_name' => 'Walk-in Guest',
                    'phone' => '0901234567',
                    'table_ids' => [12],
                    'guest_count' => 2,
                    'service_minutes' => 120,
                    'notes' => 'Counter seating',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/reservations' => [
                'summary' => 'List staff reservations',
                'description' => 'Return the canonical staff reservation lookup collection for operational order and refund flows.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'StaffReservationLookupCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/reservations/{reservation_id}' => [
                'summary' => 'Show staff reservation detail',
                'description' => 'Return the staff-scoped reservation detail read model with tables, user context, order lines, and financial snapshots needed for operational drawers and linked order workflows. Reads fail closed outside the actor explicit branch entitlement scope.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'ReservationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/reservations/{id}/check-in' => [
                'summary' => 'Check in reservation',
                'description' => 'Transition a confirmed reservation into an in-service state using assigned tables and row_version protection.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'ReservationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'table_ids' => [12],
                    'checked_in_at' => '2026-04-05T12:05:00Z',
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/reservations/{id}/assign-table' => [
                'summary' => 'Assign reservation to selected table',
                'description' => 'Assign an unseated reservation to the selected board table using row_version protection and current board-window conflict checks.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'ReservationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'table_id' => 12,
                    'row_version' => 2,
                    'board_from' => '2026-04-05T11:00:00Z',
                    'board_to' => '2026-04-05T16:00:00Z',
                    'zone' => 'Main',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/reservations/{id}/assign-best-fit' => [
                'summary' => 'Assign reservation to best-fit table',
                'description' => 'Ask the floor assignment service to choose and assign the best currently available table for an unseated reservation.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'ReservationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 2,
                    'board_from' => '2026-04-05T11:00:00Z',
                    'board_to' => '2026-04-05T16:00:00Z',
                    'zone' => 'Main',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/reservations/{id}/move-table' => [
                'summary' => 'Move in-service reservation to another table',
                'description' => 'Move an in-service reservation from one table to another while preserving stale-write and floor-conflict guards.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'ReservationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'from_table_id' => 12,
                    'to_table_id' => 18,
                    'moved_at' => '2026-04-05T12:45:00Z',
                    'row_version' => 3,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/tables/{table_id}/release' => [
                'summary' => 'Release a staff table',
                'description' => 'Release an occupied table back into allocatable floor state with row_version protection and force guard metadata.',
                'tags' => ['Staff Tables'],
                'responses' => [
                    200 => ['schema' => 'RestaurantTableEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 7,
                    'force' => false,
                    'notes' => 'Service completed and table reset.',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/tables/{table_id}/orders' => [
                'summary' => 'Create table order',
                'description' => 'Create or resume the active in-service order for a table and optionally append the first line items in the same idempotent request.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    201 => ['schema' => 'StaffReservationOrderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'reservation_id' => 102,
                    'row_version' => 2,
                    'items' => [
                        [
                            'menu_item_id' => 201,
                            'qty' => 1,
                            'note' => 'No onion',
                        ],
                    ],
                    'notes' => 'Opened from floor tablet',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/orders/{order_id}/items' => [
                'summary' => 'Add order items',
                'description' => 'Append line items to an active staff order using row_version and idempotency guards.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffReservationOrderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 3,
                    'items' => [
                        [
                            'menu_item_id' => 202,
                            'qty' => 2,
                            'note' => 'Extra chili',
                        ],
                    ],
                ],
                'contract_grade' => 'full',
            ],
            'PATCH api/v1/staff/orders/{order_id}/items/{order_item_id}' => [
                'summary' => 'Update order item',
                'description' => 'Update an editable staff order item quantity or note using both order_row_version and item row_version stale-write guards. Quantity changes recompute line_total from the persisted unit_price snapshot.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffReservationOrderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'order_row_version' => 4,
                    'row_version' => 2,
                    'qty' => 3,
                    'note' => 'No onion',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/orders/{order_id}/items/{order_item_id}/status' => [
                'summary' => 'Update order item status',
                'description' => 'Transition an editable staff order item between InProgress, Served, and Cancelled using both order_row_version and item row_version stale-write guards.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffReservationOrderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'order_row_version' => 5,
                    'row_version' => 3,
                    'status' => 'Served',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/reservations/{reservation_id}/orders' => [
                'summary' => 'List reservation orders',
                'description' => 'Return the canonical staff order lookup collection for one reservation, ordered by creation sequence.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffReservationOrderCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/cashier/shifts' => [
                'summary' => 'List cashier shifts',
                'description' => 'Return recent cashier shifts for the authenticated staff actor with lean history filters.',
                'tags' => ['Staff Cashier'],
                'responses' => [
                    200 => ['schema' => 'CashierShiftCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/branches' => [
                'summary' => 'List accessible staff branches',
                'description' => 'Return the active branch context list exposed to staff-web for branch switching and branch-aware filters. The list is limited to the authenticated actor explicit branch entitlement scope and never expands from cashier shift state.',
                'tags' => ['Admin Settings'],
                'responses' => [
                    200 => ['schema' => 'BranchCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/cashier/shifts/current' => [
                'summary' => 'Show current cashier shift',
                'description' => 'Return the open cashier shift for the authenticated staff actor.',
                'tags' => ['Staff Cashier'],
                'responses' => [
                    200 => ['schema' => 'CashierShiftEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/cashier/shifts/open' => [
                'summary' => 'Open cashier shift',
                'description' => 'Open a cashier shift for the authenticated staff actor and initialize payment summary tracking.',
                'tags' => ['Staff Cashier'],
                'responses' => [
                    201 => ['schema' => 'CashierShiftEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'opening_float_amount' => 100000,
                    'currency' => 'VND',
                    'terminal_code' => 'POS-01',
                    'notes' => 'Opening shift',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/cashier/shifts/{shift_id}' => [
                'summary' => 'Show cashier shift',
                'description' => 'Return one cashier shift with aggregated payment and cash summaries.',
                'tags' => ['Staff Cashier'],
                'responses' => [
                    200 => ['schema' => 'CashierShiftEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/cashier/shifts/{shift_id}/close' => [
                'summary' => 'Close cashier shift',
                'description' => 'Close a cashier shift using the provided counted cash amount and row_version concurrency guard.',
                'tags' => ['Staff Cashier'],
                'responses' => [
                    200 => ['schema' => 'CashierShiftEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'actual_cash_amount' => 139500,
                    'row_version' => 1,
                    'notes' => 'Closing shift',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/reservations' => [
                'summary' => 'List accessible reservations',
                'description' => 'Return customer-owned or session-owned reservations visible to customer self-service. Staff actors must use staff reservation endpoints instead.',
                'tags' => ['Reservations'],
                'responses' => [
                    200 => ['schema' => 'ReservationSelfServiceCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/cancel' => [
                'summary' => 'Cancel accessible reservation',
                'description' => 'Cancel a customer-owned or session-owned reservation using row_version concurrency protection.',
                'tags' => ['Reservations'],
                'responses' => [
                    200 => ['schema' => 'ReservationActionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 3,
                    'cancel_reason' => 'Plans changed',
                    'session_id' => 'sess-demo-001',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/reservations/{id}/reschedule' => [
                'summary' => 'Reschedule accessible reservation',
                'description' => 'Reschedule a customer-owned or session-owned reservation. At least one reschedulable field must change.',
                'tags' => ['Reservations'],
                'responses' => [
                    200 => ['schema' => 'ReservationActionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 4,
                    'start_time' => '2026-04-05T13:00:00Z',
                    'end_time' => '2026-04-05T15:00:00Z',
                    'guest_count' => 5,
                    'reason' => 'Arriving later',
                    'session_id' => 'sess-demo-001',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/orders/{order_id}' => [
                'summary' => 'Show staff order detail',
                'description' => 'Return the staff order read model with reservation, table context, line summary, and financial summary.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffOrderReadEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/tables/{table_id}/active-order' => [
                'summary' => 'Show active order by table',
                'description' => 'Return the active staff order currently attached to the requested table, or a not-found error when no active order exists.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffOrderReadEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/orders/{order_id}/bill-snapshot' => [
                'summary' => 'Lock bill snapshot',
                'description' => 'Capture and lock the current bill snapshot for settlement without completing payment.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffReservationOrderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'discount_amount' => 25000,
                    'notes' => 'Manual adjustment before payment',
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/orders/{order_id}/close' => [
                'summary' => 'Lock bill snapshot (legacy alias)',
                'description' => 'Deprecated alias of `/api/v1/staff/orders/{order_id}/bill-snapshot` retained for compatibility clients.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffReservationOrderEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'discount_amount' => 25000,
                    'notes' => 'Manual adjustment before payment',
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/orders/{order_id}/pay' => [
                'summary' => 'Capture order payment',
                'description' => 'Capture a guarded staff order payment. Partial captures advance financial row_version without completing the reservation; a full final capture persists the immutable bill snapshot before completing settlement.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffCheckoutSettlementEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'payment_method' => 'Cash',
                    'payment_provider' => 'Cash',
                    'paid_amount' => 275000,
                    'currency' => 'VND',
                    'transaction_code' => 'PAY-20260405-0001',
                    'notes' => 'Paid at cashier',
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/waiting-list' => [
                'summary' => 'List staff waiting list queue',
                'description' => 'Return the operational waiting list queue with lifecycle, orchestration, summary, and realtime metadata for staff surfaces.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'StaffWaitingListCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/waiting-list/changes' => [
                'summary' => 'Read waiting list change feed',
                'description' => 'Return the staff waiting list realtime polling feed since the requested version cursor.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'StaffOperationalRealtimeEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/waiting-list' => [
                'summary' => 'Create staff waiting list entry',
                'description' => 'Create a staff-managed waiting-list entry for walk-up or phone guests with branch scope and idempotency protection.',
                'tags' => ['Waiting List'],
                'responses' => [
                    201 => ['schema' => 'StaffWaitingListEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'branch_id' => 1,
                    'guest_name' => 'Walk-up Guest',
                    'phone' => '0901234567',
                    'guest_count' => 2,
                    'priority' => 10,
                    'notes' => 'Prefers window seat',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/waiting-list/{id}/notify' => [
                'summary' => 'Notify waiting list party',
                'description' => 'Notify the next waiting list party, attach the operational hold context, and advance the entry row version.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'StaffWaitingListEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'table_id' => 12,
                    'hold_minutes' => 10,
                    'row_version' => 1,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/waiting-list/{id}/seat' => [
                'summary' => 'Seat waiting list party',
                'description' => 'Convert the waiting list entry into an operational reservation-backed seat assignment using row version protection.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'StaffWaitingListSeatEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'user_id' => 55,
                    'service_minutes' => 90,
                    'notes' => 'Escort to reserved table',
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/waiting-list/{id}/cancel' => [
                'summary' => 'Cancel staff waiting list entry',
                'description' => 'Cancel an active staff waiting-list entry using row_version protection.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'StaffWaitingListEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 2,
                    'cancel_reason' => 'Guest left before notification',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/waiting-list/{id}/advance' => [
                'summary' => 'Advance staff waiting list queue',
                'description' => 'Advance the queue after a declined or expired invite and optionally notify the next candidate using the semi-automation guardrails.',
                'tags' => ['Waiting List'],
                'responses' => [
                    200 => ['schema' => 'StaffWaitingListAdvanceEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 3,
                    'hold_minutes' => 10,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/orders/{order_id}/settlement-preview' => [
                'summary' => 'Preview settlement',
                'description' => 'Return the staff settlement snapshot for an order before finalizing payment.',
                'tags' => ['Staff Checkout'],
                'parameters' => [
                    [
                        'name' => 'currency',
                        'in' => 'query',
                        'required' => false,
                        'schema' => ['type' => 'string'],
                        'description' => 'Fallback response currency when the order does not already carry one.',
                    ],
                ],
                'responses' => [
                    200 => ['schema' => 'StaffCheckoutSettlementEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/orders/{order_id}/settlement/finalize' => [
                'summary' => 'Finalize settlement',
                'description' => 'Capture final payment, reconcile reservation settlement, and close the operational order flow.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffCheckoutSettlementEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'payment_method' => 'Cash',
                    'payment_provider' => 'Cash',
                    'paid_amount' => 275000,
                    'currency' => 'VND',
                    'transaction_code' => 'TXN-20260405-0001',
                    'notes' => 'Paid at cashier',
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/orders/{order_id}/checkout' => [
                'summary' => 'Finalize settlement (legacy alias)',
                'description' => 'Deprecated alias of `/api/v1/staff/orders/{order_id}/settlement/finalize` retained for compatibility clients.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffCheckoutSettlementEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'payment_method' => 'Cash',
                    'payment_provider' => 'Cash',
                    'paid_amount' => 275000,
                    'currency' => 'VND',
                    'transaction_code' => 'TXN-20260405-0001',
                    'notes' => 'Paid at cashier',
                    'row_version' => 2,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/reservations/{reservation_id}/refund-preview' => [
                'summary' => 'Preview refund',
                'description' => 'Return the refund plan and payment summary before executing a refund or refund-and-cancel operation.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffRefundPreviewEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/reservations/{reservation_id}/refund' => [
                'summary' => 'Refund reservation payments',
                'description' => 'Execute a refund against deposit payments, final payments, or both while keeping the reservation active.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffRefundEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'payment_method' => 'Cash',
                    'payment_provider' => 'Cash',
                    'refund_scope' => 'all',
                    'refund_amount' => 150000,
                    'currency' => 'VND',
                    'transaction_code' => 'RF-20260405-0001',
                    'reason' => 'Customer requested refund',
                    'row_version' => 3,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/reservations/{reservation_id}/refund-cancel' => [
                'summary' => 'Refund and cancel reservation',
                'description' => 'Execute the refund flow and transition the reservation into a cancelled state in the same guarded operation.',
                'tags' => ['Staff Checkout'],
                'responses' => [
                    200 => ['schema' => 'StaffRefundEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'payment_method' => 'Cash',
                    'payment_provider' => 'Cash',
                    'refund_scope' => 'all',
                    'refund_amount' => 150000,
                    'currency' => 'VND',
                    'transaction_code' => 'RF-20260405-0002',
                    'reason' => 'Service issue',
                    'cancel_reason' => 'Cancelled after refund',
                    'row_version' => 3,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/finance/reconciliation' => [
                'summary' => 'List financial reconciliation rows',
                'description' => 'Return paged staff financial reconciliation rows with reservation, payment, discrepancy, and settlement summary fields for finance review.',
                'tags' => ['Staff Finance'],
                'responses' => [
                    200 => ['schema' => 'FinancialReconciliationCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/finance/reconciliation/{reservation_id}' => [
                'summary' => 'Show financial reconciliation detail',
                'description' => 'Return the reconciliation detail for one reservation including payment rows and method breakdown.',
                'tags' => ['Staff Finance'],
                'responses' => [
                    200 => ['schema' => 'FinancialReconciliationDetailEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/finance/invoices/{reservation_id}' => [
                'summary' => 'Show finance invoice',
                'description' => 'Return the invoice projection or persisted billing invoice for a reservation, including reconciliation and payment method breakdown context.',
                'tags' => ['Staff Finance'],
                'responses' => [
                    200 => ['schema' => 'FinanceInvoiceEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/finance/invoices/{reservation_id}/issue' => [
                'summary' => 'Issue finance invoice',
                'description' => 'Create or replay the guarded reservation invoice issuance operation and return the canonical invoice read model.',
                'tags' => ['Staff Finance'],
                'responses' => [
                    200 => ['schema' => 'FinanceInvoiceEnvelope'],
                    201 => ['schema' => 'FinanceInvoiceEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/reporting/daily-sales' => [
                'summary' => 'List daily sales reporting snapshots',
                'description' => 'Return paged daily sales reporting snapshots for the requested branch, currency, and business-date range so branch leads can review billed, payment, invoice, and cashier totals from staff-web.',
                'tags' => ['Staff Reporting'],
                'responses' => [
                    200 => ['schema' => 'StaffReportingDailySalesCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/reporting/daily-operations' => [
                'summary' => 'List daily operations reporting snapshots',
                'description' => 'Return paged daily operations reporting snapshots for the requested branch and business-date range so branch leads can review reservation, turn-time, and waiting-list throughput from staff-web.',
                'tags' => ['Staff Reporting'],
                'responses' => [
                    200 => ['schema' => 'StaffReportingDailyOperationsCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/reporting/daily-inventory' => [
                'summary' => 'List daily inventory reporting snapshots',
                'description' => 'Return paged daily inventory movement snapshots for the requested branch, ingredient, and business-date range so branch leads can review net stock movement without leaving staff-web.',
                'tags' => ['Staff Reporting'],
                'responses' => [
                    200 => ['schema' => 'StaffReportingDailyInventoryCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/kitchen/changes' => [
                'summary' => 'Read kitchen change feed',
                'description' => 'Return the staff kitchen realtime polling feed since the requested version cursor so KDS surfaces can refresh only when backend state changed.',
                'tags' => ['Staff Kitchen'],
                'responses' => [
                    200 => ['schema' => 'StaffOperationalRealtimeEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/kitchen/stations' => [
                'summary' => 'List kitchen stations',
                'description' => 'Return the active kitchen station roster with route counts, queued or fired or ready ticket counts, and realtime metadata for staff KDS visibility. Branch reads are constrained to the authenticated actor explicit branch entitlement scope and inaccessible branch filters fail closed.',
                'tags' => ['Staff Kitchen'],
                'responses' => [
                    200 => ['schema' => 'StaffKitchenStationCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/kitchen/stations/{station_id}/tickets' => [
                'summary' => 'List kitchen station tickets',
                'description' => 'Return the kitchen tickets routed to the selected station, including order-item status snapshots, routing details, and optional terminal tickets for operator review. The station read is limited to the authenticated actor explicit branch entitlement scope and returns not found outside that scope.',
                'tags' => ['Staff Kitchen'],
                'responses' => [
                    200 => ['schema' => 'StaffKitchenTicketCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/orders/{order_id}/kitchen/dispatch' => [
                'summary' => 'Dispatch order items to kitchen',
                'description' => 'Route the current order items into kitchen tickets using station category routing while preserving ticket state for redispatch-safe cases. Requires the current order row_version for stale-write protection.',
                'tags' => ['Staff Kitchen'],
                'responses' => [
                    200 => ['schema' => 'StaffKitchenDispatchEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 14,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/fire' => [
                'summary' => 'Fire kitchen ticket',
                'description' => 'Advance a queued kitchen ticket into the fired state and keep the linked order item in sync for production work. Requires the current ticket row_version for stale-write protection.',
                'tags' => ['Staff Kitchen'],
                'responses' => [
                    200 => ['schema' => 'StaffKitchenTicketEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 3,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/bump' => [
                'summary' => 'Bump kitchen ticket to ready',
                'description' => 'Advance a fired kitchen ticket into the ready state once the station completes production work. Requires the current ticket row_version for stale-write protection.',
                'tags' => ['Staff Kitchen'],
                'responses' => [
                    200 => ['schema' => 'StaffKitchenTicketEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 4,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/kitchen/tickets/{ticket_id}/recall' => [
                'summary' => 'Recall kitchen ticket',
                'description' => 'Return a ready kitchen ticket back to the fired state when the station must resume work on the item. Requires the current ticket row_version for stale-write protection.',
                'tags' => ['Staff Kitchen'],
                'responses' => [
                    200 => ['schema' => 'StaffKitchenTicketEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 5,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/audit-trail' => [
                'summary' => 'List staff audit trail',
                'description' => 'Return the operational audit trail for the authenticated staff actor with branch, subject, actor, and request correlation filters.',
                'tags' => ['Audit Trail'],
                'responses' => [
                    200 => ['schema' => 'StaffAuditTrailEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/conversations' => [
                'summary' => 'List staff conversations',
                'description' => 'Return the operational inbox for staff conversations with assignment, branch, reservation, and waiting-list filters.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/conversations/{conversation_id}' => [
                'summary' => 'Show conversation detail',
                'description' => 'Return one conversation with messages, files, entities, event history, analyses, and assignment history for staff operations.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationDetailEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/conversations/{conversation_id}/files/{file_id}/access' => [
                'summary' => 'Access conversation file',
                'description' => 'Redirect an authorized staff actor to a short-lived conversation file download target using a signed backend access handle.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    302 => [],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/staff/conversations/{conversation_id}/messages/{message_id}/attachment' => [
                'summary' => 'Access legacy conversation attachment',
                'description' => 'Redirect an authorized staff actor to a short-lived legacy message attachment target using a signed backend access handle.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    302 => [],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/assign' => [
                'summary' => 'Assign conversation',
                'description' => 'Assign an unowned conversation to a staff actor that is allowed to manage conversation inbox work.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'agent_user_id' => 12,
                    'notes' => 'Assigning to front desk shift owner.',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/take-over' => [
                'summary' => 'Take over conversation',
                'description' => 'Replace the current active assignment with the authenticated staff actor using the unique active assignment invariant.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'notes' => 'Taking over after escalation.',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/unassign' => [
                'summary' => 'Unassign conversation',
                'description' => 'Release the current active assignment while preserving assignment history in the operational timeline.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'request_example' => [
                    'notes' => 'Returning conversation to the shared inbox.',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/workflow-state' => [
                'summary' => 'Update conversation workflow state',
                'description' => 'Move a staff conversation through the explicit inbox workflow with guarded transitions for triage, waiting-on-customer, resolve, reopen, and close.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'workflow_state' => 'Resolved',
                    'expected_workflow_state' => 'Assigned',
                    'reason' => 'Customer confirmed the issue is resolved.',
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/links' => [
                'summary' => 'Link conversation to operational records',
                'description' => 'Attach a conversation to a reservation, waiting-list entry, and customer record while keeping branch and user linkage consistent.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'reservation_id' => 101,
                    'waiting_list_id' => 55,
                    'customer_user_id' => 77,
                    'notes' => 'Linked after staff triage.',
                ],
                'contract_grade' => 'full',
            ],
            'DELETE api/v1/staff/conversations/{conversation_id}/links/reservation' => [
                'summary' => 'Unlink reservation',
                'description' => 'Remove the reservation link from a conversation while preserving the rest of the conversation history.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'DELETE api/v1/staff/conversations/{conversation_id}/links/waiting-list' => [
                'summary' => 'Unlink waiting-list entry',
                'description' => 'Remove the waiting-list link from a conversation while preserving the rest of the conversation history.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    200 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/internal-notes' => [
                'summary' => 'Add internal note',
                'description' => 'Record an internal operational note inside the conversation history without sending an outbound customer reply.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    201 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'message_text' => 'Call guest back if they do not arrive in 10 minutes.',
                    'related_reservation_id' => 101,
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/staff/conversations/{conversation_id}/outbound-replies' => [
                'summary' => 'Queue outbound reply',
                'description' => 'Queue a staff-authored outbound follow-up through the currently supported real delivery channel for the linked customer record.',
                'tags' => ['Staff Conversations'],
                'responses' => [
                    201 => ['schema' => 'StaffConversationMutationEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'message_text' => 'Your table is ready for the updated arrival time. Please reply if you need any changes.',
                    'related_reservation_id' => 101,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/benefits/vouchers' => [
                'summary' => 'List admin vouchers',
                'description' => 'Return paged voucher master data for admin benefits management.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    200 => ['schema' => 'AdminVoucherCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/benefits/vouchers/{id}' => [
                'summary' => 'Show admin voucher',
                'description' => 'Return one voucher master-data record with row_version for guarded updates.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    200 => ['schema' => 'AdminVoucherEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/admin/benefits/vouchers' => [
                'summary' => 'Create admin voucher',
                'description' => 'Create a voucher master-data record under the admin benefits capability.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    201 => ['schema' => 'AdminVoucherEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'code' => 'WELCOME50',
                    'discount_type' => 'Fixed',
                    'discount_value' => 50000,
                    'description' => 'Welcome discount',
                    'is_active' => true,
                ],
                'contract_grade' => 'full',
            ],
            'PATCH api/v1/admin/benefits/vouchers/{id}' => [
                'summary' => 'Update admin voucher',
                'description' => 'Update a voucher master-data record using row_version stale-write protection.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    200 => ['schema' => 'AdminVoucherEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 2,
                    'description' => 'Updated welcome discount',
                    'discount_value' => 60000,
                    'is_active' => true,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/benefits/loyalty-tiers' => [
                'summary' => 'List admin loyalty tiers',
                'description' => 'Return paged loyalty tier master data for admin benefits management.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    200 => ['schema' => 'AdminLoyaltyTierCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/benefits/loyalty-tiers/{id}' => [
                'summary' => 'Show admin loyalty tier',
                'description' => 'Return one loyalty tier master-data record with row_version for guarded updates.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    200 => ['schema' => 'AdminLoyaltyTierEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/admin/benefits/loyalty-tiers' => [
                'summary' => 'Create admin loyalty tier',
                'description' => 'Create a loyalty tier master-data record under the admin benefits capability.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    201 => ['schema' => 'AdminLoyaltyTierEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'tier_code' => 'GOLD',
                    'tier_name' => 'Gold',
                    'min_points' => 1000,
                    'benefits_json' => [],
                    'is_active' => true,
                ],
                'contract_grade' => 'full',
            ],
            'PATCH api/v1/admin/benefits/loyalty-tiers/{id}' => [
                'summary' => 'Update admin loyalty tier',
                'description' => 'Update a loyalty tier master-data record using row_version stale-write protection.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    200 => ['schema' => 'AdminLoyaltyTierEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'StaleRowVersionError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'row_version' => 3,
                    'tier_name' => 'Gold Plus',
                    'min_points' => 1200,
                    'is_active' => true,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/settings/benefits' => [
                'summary' => 'List admin benefit settings',
                'description' => 'Return runtime benefit settings used by voucher and loyalty operations.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    200 => ['schema' => 'AdminBenefitSettingCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/admin/settings/benefits' => [
                'summary' => 'Upsert admin benefit setting',
                'description' => 'Upsert one benefit runtime setting with idempotency and optional expected timestamp guard.',
                'tags' => ['Admin Benefits'],
                'responses' => [
                    200 => ['schema' => 'AdminBenefitSettingEnvelope'],
                    201 => ['schema' => 'AdminBenefitSettingEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'setting_key' => 'loyalty.enabled',
                    'value' => 'true',
                    'expected_updated_at' => '2026-04-05T10:00:00Z',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/privacy/requests' => [
                'summary' => 'List admin privacy requests',
                'description' => 'Return paged customer privacy requests for admin review with stable request identifiers and status fields.',
                'tags' => ['Admin Privacy'],
                'responses' => [
                    200 => ['schema' => 'AdminPrivacyRequestCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/privacy/customers/{user_id}/data-export' => [
                'summary' => 'Export admin customer data',
                'description' => 'Return the admin-visible customer data export payload while preserving finance and audit lineage.',
                'tags' => ['Admin Privacy'],
                'responses' => [
                    200 => ['schema' => 'AdminCustomerDataExportEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/admin/privacy/requests/{request_id}/review' => [
                'summary' => 'Review admin privacy request',
                'description' => 'Dry-run or commit an admin privacy request review decision while keeping anonymization side effects explicit.',
                'tags' => ['Admin Privacy'],
                'responses' => [
                    200 => ['schema' => 'AdminPrivacyReviewEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'decision' => 'approve',
                    'mode' => 'dry_run',
                    'notes' => 'Reviewed by data protection owner.',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/inventory/ingredients' => [
                'summary' => 'List ingredients',
                'description' => 'Return paged inventory ingredients with stock-on-hand and recipe usage counts for the current inventory uplift rollout.',
                'tags' => ['Admin Inventory'],
                'responses' => [
                    200 => ['schema' => 'AdminIngredientCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/inventory/suppliers' => [
                'summary' => 'List suppliers',
                'description' => 'Return paged purchasing suppliers with contact and active-state data for inventory operations.',
                'tags' => ['Admin Inventory'],
                'responses' => [
                    200 => ['schema' => 'AdminSupplierCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/inventory/purchase-orders' => [
                'summary' => 'List purchase orders',
                'description' => 'Return paged purchase orders with branch, supplier, status, and ordered-versus-received summaries for branch inventory follow-up.',
                'tags' => ['Admin Inventory'],
                'responses' => [
                    200 => ['schema' => 'AdminPurchaseOrderCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/inventory/ingredients/{id}/movements' => [
                'summary' => 'List ingredient stock movements',
                'description' => 'Return paged stock movement ledger rows for one ingredient, scoped by staff branch access and optional branch filter.',
                'tags' => ['Admin Inventory'],
                'responses' => [
                    200 => ['schema' => 'AdminIngredientMovementCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/admin/inventory/ingredients/{id}/movements' => [
                'summary' => 'Create ingredient stock movement',
                'description' => 'Create a guarded stock movement for one ingredient and return the posted ledger row with updated stock-on-hand metadata.',
                'tags' => ['Admin Inventory'],
                'responses' => [
                    201 => ['schema' => 'AdminIngredientMovementEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'movement_type' => 'AdjustmentDecrease',
                    'branch_id' => 1,
                    'quantity' => 2.5,
                    'unit_code' => 'kg',
                    'reference_type' => 'manual_adjustment',
                    'reference_id' => 'ADJ-20260405-01',
                    'notes' => 'Spoilage recorded during prep',
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/inventory/purchase-orders/{id}/receipts' => [
                'summary' => 'List purchase order receipts',
                'description' => 'Return posted receiving records for one purchase order, including purchase-order context and receipt line summaries.',
                'tags' => ['Admin Inventory'],
                'responses' => [
                    200 => ['schema' => 'AdminPurchaseOrderReceiptCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/admin/inventory/purchase-orders/{id}/receipts' => [
                'summary' => 'Receive purchase order stock',
                'description' => 'Post a purchase-order receiving transaction, update stock ledger rows, and return the posted receipt plus refreshed purchase-order context.',
                'tags' => ['Admin Inventory'],
                'responses' => [
                    201 => ['schema' => 'AdminPurchaseOrderReceiptEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'receipt_code' => 'RCV-20260405-001',
                    'received_at' => '2026-04-05T09:30:00Z',
                    'supplier_document_no' => 'SUP-DOC-7788',
                    'notes' => 'Partial morning delivery',
                    'lines' => [
                        [
                            'purchase_order_line_id' => 701,
                            'received_quantity' => 5,
                            'unit_code' => 'kg',
                            'unit_cost' => 120000,
                            'notes' => 'Checked by receiving staff',
                        ],
                    ],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/settings/branches' => [
                'summary' => 'List branches',
                'description' => 'Return admin branch master data used to scope operations and reporting.',
                'tags' => ['Admin Settings'],
                'responses' => [
                    200 => ['schema' => 'BranchCollectionEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                ],
                'contract_grade' => 'full',
            ],
            'POST api/v1/admin/settings/branches' => [
                'summary' => 'Create branch',
                'description' => 'Create a branch record for multi-branch operations.',
                'tags' => ['Admin Settings'],
                'responses' => [
                    201 => ['schema' => 'BranchEnvelope'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'branch_code' => 'HCM01',
                    'branch_name' => 'Ho Chi Minh 01',
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'currency' => 'VND',
                    'is_active' => true,
                    'is_default' => false,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/settings/branches/{id}' => [
                'summary' => 'Show branch',
                'description' => 'Return one branch master-data record.',
                'tags' => ['Admin Settings'],
                'responses' => [
                    200 => ['schema' => 'BranchEnvelope'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'PATCH api/v1/admin/settings/branches/{id}' => [
                'summary' => 'Update branch',
                'description' => 'Update one branch master-data record.',
                'tags' => ['Admin Settings'],
                'responses' => [
                    200 => ['schema' => 'BranchEnvelope'],
                    401 => ['schema' => 'UnauthorizedError'],
                    403 => ['schema' => 'ForbiddenError'],
                    404 => ['schema' => 'NotFoundError'],
                    409 => ['schema' => 'ConflictError'],
                    422 => ['schema' => 'ValidationError'],
                ],
                'request_example' => [
                    'branch_name' => 'Ho Chi Minh 01 - Updated',
                    'is_active' => true,
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/menu/categories' => [
                'summary' => 'List admin menu categories',
                'description' => 'Return staff menu categories for admin management.',
                'tags' => ['Admin Menu'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/menu/categories/export' => [
                'summary' => 'Export admin menu categories',
                'description' => 'Export menu categories.',
                'tags' => ['Admin Menu'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/menu/items' => [
                'summary' => 'List admin menu items',
                'description' => 'Return menu items for admin management.',
                'tags' => ['Admin Menu'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/menu/items/export' => [
                'summary' => 'Export admin menu items',
                'description' => 'Export menu items.',
                'tags' => ['Admin Menu'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/menu/items/{item_id}' => [
                'summary' => 'Show admin menu item',
                'description' => 'Return single menu item for admin.',
                'tags' => ['Admin Menu'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/menu/items/{item_id}/prices' => [
                'summary' => 'List admin menu item prices',
                'description' => 'Return prices for a menu item.',
                'tags' => ['Admin Menu'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/menu/prices/export' => [
                'summary' => 'Export admin menu prices',
                'description' => 'Export menu prices.',
                'tags' => ['Admin Menu'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/menu/prices/{price_id}' => [
                'summary' => 'Show admin menu price',
                'description' => 'Return single menu price.',
                'tags' => ['Admin Menu'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                    404 => ['schema' => 'NotFoundError'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/restaurant/tables' => [
                'summary' => 'List admin restaurant tables',
                'description' => 'Return restaurant tables for admin management.',
                'tags' => ['Admin Settings'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                ],
                'contract_grade' => 'full',
            ],
            'GET api/v1/admin/restaurant/tables/export' => [
                'summary' => 'Export admin restaurant tables',
                'description' => 'Export restaurant tables.',
                'tags' => ['Admin Settings'],
                'responses' => [
                    200 => ['schema' => 'GenericDataEnvelope'],
                ],
                'contract_grade' => 'full',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function componentSchemas(): array
    {
        $genericError = [
            'type' => 'object',
            'required' => ['error_code', 'category_code', 'message'],
            'properties' => [
                'error_code' => ['type' => 'string'],
                'category_code' => ['type' => 'string', 'description' => 'Canonical frontend branch key for the important API failure family.'],
                'message' => ['type' => 'string'],
                'request_id' => ['type' => 'string', 'nullable' => true],
                'error' => ['type' => 'string', 'nullable' => true, 'description' => 'Legacy idempotency alias retained for compatibility.'],
                'conflict_type' => ['type' => 'string', 'nullable' => true, 'description' => 'Machine-readable conflict family such as stale_write or idempotency_payload_mismatch.'],
                'replay_state' => ['type' => 'string', 'nullable' => true, 'description' => 'Machine-readable idempotency replay state such as in_progress or payload_mismatch.'],
                'state_reason' => ['type' => 'string', 'nullable' => true, 'description' => 'Stable reason token describing why the request was denied or conflicted.'],
                'required_capability' => ['type' => 'string', 'nullable' => true, 'description' => 'Required staff capability when a capability guard denied the request.'],
                'staff_role_name' => ['type' => 'string', 'nullable' => true, 'description' => 'Resolved staff role name when a capability guard denied the request.'],
                'warnings' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'next_actions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'deprecated_aliases' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Deprecated top-level aliases still emitted for backwards compatibility.',
                ],
                'errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'details' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
        ];

        $reservationCustomerSummarySchema = [
            'type' => 'object',
            'required' => ['user_id', 'full_name', 'email', 'phone', 'current_points', 'current_tier'],
            'properties' => [
                'user_id' => ['type' => 'integer', 'nullable' => true],
                'full_name' => ['type' => 'string', 'nullable' => true],
                'email' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'current_points' => ['type' => 'integer', 'nullable' => true],
                'current_tier' => ['$ref' => '#/components/schemas/CustomerLoyaltyTier', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $reservationGuestSnapshotSchema = [
            'type' => 'object',
            'required' => ['full_name', 'phone', 'email', 'is_snapshot_only'],
            'properties' => [
                'full_name' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'email' => ['type' => 'string', 'nullable' => true],
                'is_snapshot_only' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $reservationSummary = [
            'type' => 'object',
            'required' => ['reservation_id', 'reservation_code', 'status', 'row_version'],
            'properties' => [
                'reservation_id' => ['type' => 'integer'],
                'reservation_code' => ['type' => 'string'],
                'access_scope' => ['type' => 'string'],
                'booking_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'reserved_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'start_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'end_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'guest_count' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
                'deposit_status' => ['type' => 'string', 'nullable' => true],
                'deposit_required_amount' => ['type' => 'string', 'nullable' => true],
                'deposit_paid_amount' => ['type' => 'string', 'nullable' => true],
                'final_bill_amount' => ['type' => 'string', 'nullable' => true],
                'bill_currency' => ['type' => 'string', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'status_flags' => ['type' => 'object', 'additionalProperties' => true],
                'customer_self_service' => ['type' => 'object', 'additionalProperties' => true],
                'table_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'table_summary' => ['type' => 'object', 'additionalProperties' => true],
                'user' => ['$ref' => '#/components/schemas/ReservationCustomerSummary', 'nullable' => true],
                'guest' => ['$ref' => '#/components/schemas/ReservationGuestSnapshot', 'nullable' => true],
                'payments' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true], 'nullable' => true],
                'payment_summary' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'deposit_summary' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
            ],
            'additionalProperties' => true,
            'example' => [
                'reservation_id' => 101,
                'reservation_code' => 'RSV-20260405-0001',
                'access_scope' => 'owner',
                'booking_time' => '2026-04-05T12:00:00Z',
                'reserved_at' => '2026-04-05T12:00:00Z',
                'start_time' => '2026-04-05T12:00:00Z',
                'end_time' => '2026-04-05T14:00:00Z',
                'guest_count' => 4,
                'status' => 'Confirmed',
                'deposit_status' => 'Pending',
                'deposit_required_amount' => '150000.00',
                'deposit_paid_amount' => '0.00',
                'bill_currency' => 'VND',
                'row_version' => 1,
                'status_flags' => ['is_active' => true],
                'customer_self_service' => ['can_attempt_cancel' => true, 'can_attempt_reschedule' => true],
                'table_ids' => [12, 13],
                'table_summary' => ['count' => 2, 'table_codes' => ['A-12', 'A-13']],
                'user' => [
                    'user_id' => 77,
                    'full_name' => 'Customer Demo',
                    'email' => 'customer@example.invalid',
                    'phone' => '+84901234567',
                    'current_points' => 650,
                    'current_tier' => [
                        'tier_id' => 1,
                        'tier_code' => 'BRONZE',
                        'tier_name' => 'Bronze',
                        'min_points' => 0,
                    ],
                ],
                'guest' => null,
            ],
        ];

        $customerWaitingResponseStateValues = array_map(
            static fn (WaitingListCustomerResponseState $state): string => $state->value,
            WaitingListCustomerResponseState::cases(),
        );

        $customerWaitingListEntry = [
            'type' => 'object',
            'required' => [
                'waiting_id',
                'branch_id',
                'guest_name',
                'phone',
                'guest_count',
                'requested_at',
                'status',
                'priority',
                'notified_at',
                'notify_expires_at',
                'seated_at',
                'cancelled_at',
                'cancel_reason',
                'notes',
                'row_version',
                'response_state',
                'can_accept',
                'can_decline',
                'can_confirm_arrival',
                'can_cancel',
                'notify_window',
                'window',
                'available_actions',
                'staff_seat_required',
                'next_step',
                'arrival_confirmation',
            ],
            'properties' => [
                'waiting_id' => ['type' => 'integer'],
                'branch_id' => ['type' => 'integer', 'nullable' => true],
                'guest_name' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'guest_count' => ['type' => 'integer'],
                'requested_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'status' => ['type' => 'string'],
                'priority' => ['type' => 'integer'],
                'notified_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'notify_expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'seated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'cancelled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'cancel_reason' => ['type' => 'string', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'response_state' => [
                    'type' => 'string',
                    'enum' => $customerWaitingResponseStateValues,
                    'description' => 'Stable customer-response state for lean owner waiting-list clients. Distinguishes accepted from arrival_confirmed without localized text.',
                ],
                'can_accept' => ['type' => 'boolean'],
                'can_decline' => ['type' => 'boolean'],
                'can_confirm_arrival' => ['type' => 'boolean'],
                'can_cancel' => ['type' => 'boolean'],
                'notify_window' => [
                    'type' => 'object',
                    'required' => ['is_open', 'expires_at'],
                    'properties' => [
                        'is_open' => ['type' => 'boolean'],
                        'expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'window' => [
                    'type' => 'object',
                    'required' => ['is_notified_window_open'],
                    'properties' => [
                        'is_notified_window_open' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'available_actions' => [
                    'type' => 'object',
                    'required' => ['accept', 'decline', 'confirm_arrival', 'cancel'],
                    'properties' => [
                        'accept' => ['type' => 'boolean'],
                        'decline' => ['type' => 'boolean'],
                        'confirm_arrival' => ['type' => 'boolean'],
                        'cancel' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'staff_seat_required' => ['type' => 'boolean'],
                'next_step' => ['type' => 'string', 'nullable' => true],
                'arrival_confirmation' => [
                    'type' => 'object',
                    'required' => ['supported', 'staff_seat_required', 'message'],
                    'properties' => [
                        'supported' => ['type' => 'boolean'],
                        'staff_seat_required' => ['type' => 'boolean'],
                        'message' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffWaitingListEntry = [
            'type' => 'object',
            'required' => [
                'waiting_id',
                'branch_id',
                'user_id',
                'guest_name',
                'phone',
                'guest_count',
                'requested_at',
                'status',
                'priority',
                'notified_at',
                'notify_expires_at',
                'notified_by',
                'seated_at',
                'cancelled_at',
                'cancel_reason',
                'notes',
                'updated_by',
                'row_version',
                'current_response_state',
                'response',
                'invite_window',
                'invite_lifecycle',
                'invite_hold',
                'orchestration',
            ],
            'properties' => [
                'waiting_id' => ['type' => 'integer'],
                'branch_id' => ['type' => 'integer', 'nullable' => true],
                'user_id' => ['type' => 'integer', 'nullable' => true],
                'guest_name' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'guest_count' => ['type' => 'integer'],
                'requested_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'status' => ['type' => 'string'],
                'priority' => ['type' => 'integer'],
                'notified_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'notify_expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'notified_by' => ['type' => 'integer', 'nullable' => true],
                'seated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'cancelled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'cancel_reason' => ['type' => 'string', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'updated_by' => ['type' => 'integer', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'current_response_state' => ['type' => 'string'],
                'response' => [
                    'type' => 'object',
                    'required' => ['status', 'responded_at', 'confirmed_arrival_at'],
                    'properties' => [
                        'status' => ['type' => 'string', 'nullable' => true],
                        'responded_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'confirmed_arrival_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'invite_window' => [
                    'type' => 'object',
                    'required' => ['notified_at', 'expires_at', 'is_active', 'is_expired', 'seconds_remaining'],
                    'properties' => [
                        'notified_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'is_active' => ['type' => 'boolean'],
                        'is_expired' => ['type' => 'boolean'],
                        'seconds_remaining' => ['type' => 'integer'],
                    ],
                    'additionalProperties' => false,
                ],
                'invite_lifecycle' => [
                    'type' => 'object',
                    'required' => [
                        'requires_explicit_staff_seat',
                        'auto_convert_to_reservation',
                        'seat_readiness',
                        'customer_next_step',
                        'staff_next_step',
                        'can_staff_seat_now',
                    ],
                    'properties' => [
                        'requires_explicit_staff_seat' => ['type' => 'boolean'],
                        'auto_convert_to_reservation' => ['type' => 'boolean'],
                        'seat_readiness' => ['type' => 'string'],
                        'customer_next_step' => ['type' => 'string'],
                        'staff_next_step' => ['type' => 'string'],
                        'can_staff_seat_now' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'invite_hold' => [
                    'type' => 'object',
                    'required' => ['has_active_hold', 'active', 'latest'],
                    'properties' => [
                        'has_active_hold' => ['type' => 'boolean'],
                        'active' => [
                            'type' => 'object',
                            'nullable' => true,
                            'required' => ['hold_id', 'status', 'session_id', 'expires_at', 'confirmed_reservation_id', 'table_ids'],
                            'properties' => [
                                'hold_id' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                                'session_id' => ['type' => 'string'],
                                'expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                                'confirmed_reservation_id' => ['type' => 'integer', 'nullable' => true],
                                'table_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            ],
                            'additionalProperties' => false,
                        ],
                        'latest' => [
                            'type' => 'object',
                            'nullable' => true,
                            'required' => ['hold_id', 'status', 'session_id', 'expires_at', 'confirmed_reservation_id', 'table_ids'],
                            'properties' => [
                                'hold_id' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                                'session_id' => ['type' => 'string'],
                                'expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                                'confirmed_reservation_id' => ['type' => 'integer', 'nullable' => true],
                                'table_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            ],
                            'additionalProperties' => false,
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'orchestration' => [
                    'type' => 'object',
                    'required' => ['mode', 'actionable_state', 'recommended_action', 'released_table', 'advance_queue', 'actions'],
                    'properties' => [
                        'mode' => ['type' => 'string'],
                        'actionable_state' => ['type' => 'string'],
                        'recommended_action' => ['type' => 'string'],
                        'released_table' => [
                            'type' => 'object',
                            'nullable' => true,
                            'required' => ['table_id', 'table_ids', 'table_code', 'zone', 'status', 'seats'],
                            'properties' => [
                                'table_id' => ['type' => 'integer'],
                                'table_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                'table_code' => ['type' => 'string', 'nullable' => true],
                                'zone' => ['type' => 'string', 'nullable' => true],
                                'status' => ['type' => 'string', 'nullable' => true],
                                'seats' => ['type' => 'integer', 'nullable' => true],
                            ],
                            'additionalProperties' => false,
                        ],
                        'advance_queue' => [
                            'type' => 'object',
                            'required' => ['supported', 'can_apply_now', 'resulting_action', 'released_table_available', 'next_candidate', 'disabled_reason'],
                            'properties' => [
                                'supported' => ['type' => 'boolean'],
                                'can_apply_now' => ['type' => 'boolean'],
                                'resulting_action' => ['type' => 'string'],
                                'released_table_available' => ['type' => 'boolean'],
                                'next_candidate' => [
                                    'type' => 'object',
                                    'nullable' => true,
                                    'required' => ['waiting_id', 'user_id', 'guest_name', 'guest_count', 'priority', 'requested_at', 'row_version', 'capacity_fit'],
                                    'properties' => [
                                        'waiting_id' => ['type' => 'integer'],
                                        'user_id' => ['type' => 'integer', 'nullable' => true],
                                        'guest_name' => ['type' => 'string', 'nullable' => true],
                                        'guest_count' => ['type' => 'integer'],
                                        'priority' => ['type' => 'integer'],
                                        'requested_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                                        'row_version' => ['type' => 'integer'],
                                        'capacity_fit' => [
                                            'type' => 'object',
                                            'required' => ['table_seats', 'seat_delta'],
                                            'properties' => [
                                                'table_seats' => ['type' => 'integer'],
                                                'seat_delta' => ['type' => 'integer'],
                                            ],
                                            'additionalProperties' => false,
                                        ],
                                    ],
                                    'additionalProperties' => false,
                                ],
                                'disabled_reason' => ['type' => 'string', 'nullable' => true],
                            ],
                            'additionalProperties' => false,
                        ],
                        'actions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['key', 'method', 'href', 'enabled', 'reason'],
                                'properties' => [
                                    'key' => ['type' => 'string'],
                                    'method' => ['type' => 'string'],
                                    'href' => ['type' => 'string'],
                                    'enabled' => ['type' => 'boolean'],
                                    'reason' => ['type' => 'string'],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'user' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
            'example' => [
                'waiting_id' => 55,
                'branch_id' => 3,
                'user_id' => 77,
                'guest_name' => 'Walk-in Guest',
                'phone' => '0901234567',
                'guest_count' => 3,
                'requested_at' => '2026-04-05T11:00:00Z',
                'status' => 'Notified',
                'priority' => 0,
                'notified_at' => '2026-04-05T11:05:00Z',
                'notify_expires_at' => '2026-04-05T11:15:00Z',
                'notified_by' => 5,
                'seated_at' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
                'notes' => 'Front door queue',
                'updated_by' => 5,
                'row_version' => 2,
                'current_response_state' => 'arrival_confirmed',
                'response' => [
                    'status' => 'Accepted',
                    'responded_at' => '2026-04-05T11:06:00Z',
                    'confirmed_arrival_at' => '2026-04-05T11:07:00Z',
                ],
                'invite_window' => [
                    'notified_at' => '2026-04-05T11:05:00Z',
                    'expires_at' => '2026-04-05T11:15:00Z',
                    'is_active' => true,
                    'is_expired' => false,
                    'seconds_remaining' => 480,
                ],
                'invite_lifecycle' => [
                    'requires_explicit_staff_seat' => true,
                    'auto_convert_to_reservation' => false,
                    'seat_readiness' => 'ready_to_seat',
                    'customer_next_step' => 'wait_for_staff_seat',
                    'staff_next_step' => 'seat_customer',
                    'can_staff_seat_now' => true,
                ],
                'invite_hold' => [
                    'has_active_hold' => true,
                    'active' => [
                        'hold_id' => 'hold-001',
                        'status' => 'Holding',
                        'session_id' => 'waiting-list:55',
                        'expires_at' => '2026-04-05T11:15:00Z',
                        'confirmed_reservation_id' => null,
                        'table_ids' => [12],
                    ],
                    'latest' => [
                        'hold_id' => 'hold-001',
                        'status' => 'Holding',
                        'session_id' => 'waiting-list:55',
                        'expires_at' => '2026-04-05T11:15:00Z',
                        'confirmed_reservation_id' => null,
                        'table_ids' => [12],
                    ],
                ],
                'orchestration' => [
                    'mode' => 'semi_automated_waiting_list_orchestration',
                    'actionable_state' => 'seat_customer',
                    'recommended_action' => 'seat_current_customer',
                    'released_table' => [
                        'table_id' => 12,
                        'table_ids' => [12],
                        'table_code' => 'A-12',
                        'zone' => 'Main Hall',
                        'status' => 'Available',
                        'seats' => 4,
                    ],
                    'advance_queue' => [
                        'supported' => false,
                        'can_apply_now' => false,
                        'resulting_action' => 'none',
                        'released_table_available' => true,
                        'next_candidate' => null,
                        'disabled_reason' => null,
                    ],
                    'actions' => [
                        [
                            'key' => 'seat',
                            'method' => 'POST',
                            'href' => '/api/v1/staff/waiting-list/55/seat',
                            'enabled' => true,
                            'reason' => 'canonical_staff_seat_flow',
                        ],
                    ],
                ],
                'user' => [
                    'user_id' => 77,
                    'full_name' => 'Walk-in Guest',
                    'email' => 'guest@example.invalid',
                    'phone' => '0901234567',
                ],
            ],
        ];

        $depositPaymentSession = [
            'type' => 'object',
            'required' => ['deposit_payment_session_id', 'reservation_id', 'provider_code', 'provider_session_code', 'session_status', 'settlement_status', 'row_version'],
            'properties' => [
                'deposit_payment_session_id' => ['type' => 'integer'],
                'reservation_id' => ['type' => 'integer'],
                'provider_code' => ['type' => 'string'],
                'provider_session_code' => ['type' => 'string'],
                'provider_payment_code' => ['type' => 'string', 'nullable' => true],
                'payment_method' => ['type' => 'string', 'nullable' => true],
                'amount' => ['type' => 'string', 'nullable' => true],
                'currency' => ['type' => 'string', 'nullable' => true],
                'session_status' => ['type' => 'string'],
                'settlement_status' => ['type' => 'string'],
                'linked_payment_id' => ['type' => 'integer', 'nullable' => true],
                'failure_code' => ['type' => 'string', 'nullable' => true],
                'failure_message' => ['type' => 'string', 'nullable' => true],
                'provider_payload' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'provider_expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'last_reconciled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'confirmed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'failed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'cancelled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'expired_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $billPaymentSession = [
            'allOf' => [
                ['$ref' => '#/components/schemas/CustomerDepositPaymentSession'],
                [
                    'type' => 'object',
                    'properties' => [
                        'bill_payment_session_id' => ['type' => 'integer'],
                        'order_id' => ['type' => 'integer', 'nullable' => true],
                    ],
                    'required' => ['bill_payment_session_id'],
                ],
            ],
            'example' => [
                'bill_payment_session_id' => 9101,
                'reservation_id' => 101,
                'order_id' => 7001,
                'provider_code' => 'simulated',
                'provider_session_code' => 'sim-bill-001',
                'payment_method' => 'Online',
                'amount' => '250000.00',
                'currency' => 'VND',
                'session_status' => 'Pending',
                'settlement_status' => 'Pending',
                'linked_payment_id' => null,
                'row_version' => 1,
            ],
        ];

        $customerMenuItemPriceSchema = [
            'type' => 'object',
            'required' => ['price_id', 'amount', 'currency', 'effective_from', 'effective_to'],
            'properties' => [
                'price_id' => ['type' => 'integer', 'nullable' => true],
                'amount' => ['type' => 'string', 'nullable' => true],
                'currency' => ['type' => 'string', 'nullable' => true],
                'effective_from' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'effective_to' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
            'example' => [
                'price_id' => 9001,
                'amount' => '85000.00',
                'currency' => 'VND',
                'effective_from' => '2026-04-05T00:00:00Z',
                'effective_to' => null,
            ],
        ];

        $customerMenuItemPreorderSchema = [
            'type' => 'object',
            'required' => ['enabled', 'cutoff_minutes', 'quota_per_day', 'requires_preview_validation'],
            'properties' => [
                'enabled' => ['type' => 'boolean'],
                'cutoff_minutes' => ['type' => 'integer'],
                'quota_per_day' => ['type' => 'integer', 'nullable' => true],
                'requires_preview_validation' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
            'example' => [
                'enabled' => true,
                'cutoff_minutes' => 30,
                'quota_per_day' => 20,
                'requires_preview_validation' => true,
            ],
        ];

        $customerMenuItemComboComponentSchema = [
            'type' => 'object',
            'required' => ['component_item_id', 'name', 'quantity', 'is_optional', 'additional_price'],
            'properties' => [
                'component_item_id' => ['type' => 'integer'],
                'name' => ['type' => 'string', 'nullable' => true],
                'quantity' => ['type' => 'integer'],
                'is_optional' => ['type' => 'boolean'],
                'additional_price' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
            'example' => [
                'component_item_id' => 101,
                'name' => 'Coca Cola',
                'quantity' => 1,
                'is_optional' => false,
                'additional_price' => null,
            ],
        ];

        $customerMenuItemSchema = [
            'type' => 'object',
            'required' => [
                'item_id',
                'category_id',
                'category_name',
                'code',
                'name',
                'description',
                'img_url',
                'is_available',
                'is_combo',
                'is_best_seller',
                'price',
                'preorder',
                'created_at',
                'updated_at',
            ],
            'properties' => [
                'item_id' => ['type' => 'integer'],
                'category_id' => ['type' => 'integer', 'nullable' => true],
                'category_name' => ['type' => 'string', 'nullable' => true],
                'code' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string', 'nullable' => true],
                'img_url' => ['type' => 'string', 'nullable' => true],
                'is_available' => ['type' => 'boolean'],
                'is_combo' => ['type' => 'boolean'],
                'is_best_seller' => ['type' => 'boolean'],
                'compare_at_price_amount' => ['type' => 'string', 'nullable' => true],
                'serving_size' => ['type' => 'integer', 'nullable' => true],
                'combo_items_json' => ['type' => 'array', 'nullable' => true, 'items' => ['type' => 'object', 'additionalProperties' => true]],
                'combo_components' => ['type' => 'array', 'nullable' => true, 'items' => ['$ref' => '#/components/schemas/CustomerMenuItemComboComponent']],
                'price' => ['$ref' => '#/components/schemas/CustomerMenuItemPrice'],
                'preorder' => ['$ref' => '#/components/schemas/CustomerMenuItemPreorderPolicy'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
            'example' => [
                'item_id' => 201,
                'category_id' => 15,
                'category_name' => 'Signature Noodles',
                'code' => 'PHO-BO',
                'name' => 'Pho Bo Tai',
                'description' => 'Thin-sliced beef noodle soup.',
                'img_url' => 'https://cdn.example.invalid/menu/pho-bo-tai.jpg',
                'is_available' => true,
                'price' => $customerMenuItemPriceSchema['example'],
                'preorder' => $customerMenuItemPreorderSchema['example'],
                'created_at' => '2026-04-01T08:00:00Z',
                'updated_at' => '2026-04-05T09:00:00Z',
            ],
        ];

        $customerMenuItemsMetaSchema = [
            'type' => 'object',
            'required' => [
                'current_page',
                'per_page',
                'from',
                'to',
                'total',
                'last_page',
                'has_more_pages',
                'service_time',
                'filters',
            ],
            'properties' => [
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'service_time' => ['type' => 'string', 'format' => 'date-time'],
                'filters' => [
                    'type' => 'object',
                    'required' => ['category_id', 'preorder_only', 'q'],
                    'properties' => [
                        'category_id' => ['type' => 'integer', 'nullable' => true],
                        'preorder_only' => ['type' => 'boolean'],
                        'q' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
            'example' => [
                'current_page' => 1,
                'per_page' => 20,
                'from' => 1,
                'to' => 2,
                'total' => 2,
                'last_page' => 1,
                'has_more_pages' => false,
                'service_time' => '2026-04-05T12:00:00Z',
                'filters' => [
                    'category_id' => null,
                    'preorder_only' => true,
                    'q' => 'pho',
                ],
            ],
        ];

        $customerLoyaltyTierSchema = [
            'type' => 'object',
            'required' => ['tier_id', 'tier_code', 'tier_name', 'min_points'],
            'properties' => [
                'tier_id' => ['type' => 'integer'],
                'tier_code' => ['type' => 'string'],
                'tier_name' => ['type' => 'string'],
                'min_points' => ['type' => 'integer'],
                'points_to_unlock' => ['type' => 'integer', 'nullable' => true],
            ],
            'additionalProperties' => false,
            'example' => [
                'tier_id' => 2,
                'tier_code' => 'SILVER',
                'tier_name' => 'Silver',
                'min_points' => 1000,
                'points_to_unlock' => 350,
            ],
        ];

        $customerLoyaltyUserSummarySchema = [
            'type' => 'object',
            'required' => ['user_id', 'full_name', 'email', 'phone', 'total_points', 'current_tier', 'next_tier'],
            'properties' => [
                'user_id' => ['type' => 'integer'],
                'full_name' => ['type' => 'string', 'nullable' => true],
                'email' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'total_points' => ['type' => 'integer'],
                'current_tier' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/CustomerLoyaltyTier'],
                        ['type' => 'null'],
                    ],
                ],
                'next_tier' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/CustomerLoyaltyTier'],
                        ['type' => 'null'],
                    ],
                ],
            ],
            'additionalProperties' => false,
            'example' => [
                'user_id' => 77,
                'full_name' => 'Customer Demo',
                'email' => 'customer@example.invalid',
                'phone' => '+84901234567',
                'total_points' => 650,
                'current_tier' => [
                    'tier_id' => 1,
                    'tier_code' => 'BRONZE',
                    'tier_name' => 'Bronze',
                    'min_points' => 0,
                ],
                'next_tier' => $customerLoyaltyTierSchema['example'],
            ],
        ];

        $loyaltyPointTransactionSchema = [
            'type' => 'object',
            'required' => ['txn_id', 'user_id', 'reservation_id', 'txn_type', 'points', 'amount_basis', 'currency', 'reason', 'created_at', 'created_by'],
            'properties' => [
                'txn_id' => ['type' => 'integer', 'nullable' => true],
                'user_id' => ['type' => 'integer', 'nullable' => true],
                'reservation_id' => ['type' => 'integer', 'nullable' => true],
                'txn_type' => ['type' => 'string', 'nullable' => true],
                'points' => ['type' => 'integer'],
                'amount_basis' => ['type' => 'string', 'nullable' => true],
                'currency' => ['type' => 'string'],
                'reason' => ['type' => 'string', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'created_by' => ['type' => 'integer', 'nullable' => true],
            ],
            'additionalProperties' => false,
            'example' => [
                'txn_id' => 501,
                'user_id' => 77,
                'reservation_id' => 103,
                'txn_type' => 'Redeem',
                'points' => -100,
                'amount_basis' => '100000.00',
                'currency' => 'VND',
                'reason' => 'redeem.apply',
                'created_at' => '2026-04-05T10:30:00Z',
                'created_by' => null,
            ],
        ];

        $customerLoyaltySummarySchema = [
            'type' => 'object',
            'required' => ['user', 'transactions'],
            'properties' => [
                'user' => ['$ref' => '#/components/schemas/CustomerLoyaltyUserSummary'],
                'transactions' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/LoyaltyPointTransaction']],
            ],
            'additionalProperties' => false,
            'example' => [
                'user' => $customerLoyaltyUserSummarySchema['example'],
                'transactions' => [$loyaltyPointTransactionSchema['example']],
            ],
        ];

        $customerVoucherFreeItemSchema = [
            'type' => 'object',
            'required' => ['item_id', 'quantity', 'item_name'],
            'properties' => [
                'item_id' => ['type' => 'integer'],
                'quantity' => ['type' => 'integer'],
                'item_name' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
            'example' => [
                'item_id' => 305,
                'quantity' => 1,
                'item_name' => 'Spring Roll',
            ],
        ];

        $customerVoucherSchema = [
            'type' => 'object',
            'required' => [
                'user_voucher_id',
                'voucher_id',
                'voucher_code',
                'description',
                'discount_type',
                'discount_value',
                'min_spend',
                'free_item',
                'assigned_at',
                'used_at',
                'used_reservation_id',
                'starts_at',
                'expires_at',
                'is_used',
                'current_status',
                'is_usable_now',
                'is_locked',
                'is_locked_by_other',
                'locked_until',
                'row_version',
                'is_currently_applied',
                'preview_discount_amount',
                'preview_subtotal_amount',
                'preview_currency',
                'can_apply',
                'applicability_reason_codes',
                'applicability_reasons',
            ],
            'properties' => [
                'user_voucher_id' => ['type' => 'integer'],
                'voucher_id' => ['type' => 'integer'],
                'voucher_code' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'discount_type' => ['type' => 'string'],
                'discount_value' => ['type' => 'string', 'nullable' => true],
                'min_spend' => ['type' => 'string', 'nullable' => true],
                'free_item' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/CustomerVoucherFreeItem'],
                        ['type' => 'null'],
                    ],
                ],
                'assigned_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'used_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'used_reservation_id' => ['type' => 'integer', 'nullable' => true],
                'starts_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'expires_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'is_used' => ['type' => 'boolean'],
                'current_status' => ['type' => 'string'],
                'is_usable_now' => ['type' => 'boolean'],
                'is_locked' => ['type' => 'boolean'],
                'is_locked_by_other' => ['type' => 'boolean'],
                'locked_until' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'row_version' => ['type' => 'integer', 'nullable' => true],
                'is_currently_applied' => ['type' => 'boolean'],
                'preview_discount_amount' => ['type' => 'string', 'nullable' => true],
                'preview_subtotal_amount' => ['type' => 'string', 'nullable' => true],
                'preview_currency' => ['type' => 'string', 'nullable' => true],
                'can_apply' => ['type' => 'boolean'],
                'applicability_reason_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'applicability_reasons' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'additionalProperties' => false,
            'example' => [
                'user_voucher_id' => 301,
                'voucher_id' => 22,
                'voucher_code' => 'FIX50',
                'description' => 'Fixed 50k discount',
                'discount_type' => 'FixedAmount',
                'discount_value' => '50000.00',
                'min_spend' => '100000.00',
                'free_item' => null,
                'assigned_at' => '2026-04-03T12:00:00Z',
                'used_at' => null,
                'used_reservation_id' => null,
                'starts_at' => null,
                'expires_at' => null,
                'is_used' => false,
                'current_status' => 'Active',
                'is_usable_now' => false,
                'is_locked' => false,
                'is_locked_by_other' => false,
                'locked_until' => null,
                'row_version' => null,
                'is_currently_applied' => false,
                'preview_discount_amount' => '50000.00',
                'preview_subtotal_amount' => '170000.00',
                'preview_currency' => 'VND',
                'can_apply' => true,
                'applicability_reason_codes' => [],
                'applicability_reasons' => [],
            ],
        ];

        $customerReservationBenefitsReservationSchema = [
            'type' => 'object',
            'required' => ['reservation_id', 'reservation_code', 'user_id', 'status', 'row_version', 'bill', 'loyalty', 'user'],
            'properties' => [
                'reservation_id' => ['type' => 'integer'],
                'reservation_code' => ['type' => 'string'],
                'user_id' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
                'row_version' => ['type' => 'integer'],
                'bill' => [
                    'type' => 'object',
                    'required' => [
                        'subtotal_amount',
                        'manual_discount_amount',
                        'loyalty_discount_amount',
                        'discount_amount',
                        'payable_amount',
                        'currency',
                    ],
                    'properties' => [
                        'subtotal_amount' => ['type' => 'string'],
                        'manual_discount_amount' => ['type' => 'string'],
                        'loyalty_discount_amount' => ['type' => 'string'],
                        'discount_amount' => ['type' => 'string'],
                        'payable_amount' => ['type' => 'string'],
                        'currency' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'loyalty' => [
                    'type' => 'object',
                    'required' => [
                        'enabled',
                        'available_points',
                        'redeemed_points',
                        'discount_amount',
                        'redeem_amount_per_point',
                        'earn_amount_per_point',
                        'min_redeem_points',
                        'max_redeemable_points',
                        'earn_preview_points',
                        'earned_points_current',
                        'can_redeem',
                        'can_release',
                    ],
                    'properties' => [
                        'enabled' => ['type' => 'boolean'],
                        'available_points' => ['type' => 'integer'],
                        'redeemed_points' => ['type' => 'integer'],
                        'discount_amount' => ['type' => 'number'],
                        'redeem_amount_per_point' => ['type' => 'string'],
                        'earn_amount_per_point' => ['type' => 'string'],
                        'min_redeem_points' => ['type' => 'integer'],
                        'max_redeemable_points' => ['type' => 'integer'],
                        'earn_preview_points' => ['type' => 'integer'],
                        'earned_points_current' => ['type' => 'integer'],
                        'can_redeem' => ['type' => 'boolean'],
                        'can_release' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'user' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/CustomerLoyaltyUserSummary'],
                        ['type' => 'null'],
                    ],
                ],
            ],
            'additionalProperties' => false,
            'example' => [
                'reservation_id' => 103,
                'reservation_code' => 'RSV-20260405-0103',
                'user_id' => 77,
                'status' => 'Confirmed',
                'row_version' => 3,
                'bill' => [
                    'subtotal_amount' => '170000.00',
                    'manual_discount_amount' => '0.00',
                    'loyalty_discount_amount' => '0.00',
                    'discount_amount' => '0.00',
                    'payable_amount' => '170000.00',
                    'currency' => 'VND',
                ],
                'loyalty' => [
                    'enabled' => true,
                    'available_points' => 300,
                    'redeemed_points' => 0,
                    'discount_amount' => 0.0,
                    'redeem_amount_per_point' => '1000.00',
                    'earn_amount_per_point' => '10000.00',
                    'min_redeem_points' => 1,
                    'max_redeemable_points' => 170,
                    'earn_preview_points' => 17,
                    'earned_points_current' => 0,
                    'can_redeem' => true,
                    'can_release' => false,
                ],
                'user' => $customerLoyaltyUserSummarySchema['example'],
            ],
        ];

        $customerReservationBenefitsPreviewSchema = [
            'type' => 'object',
            'required' => ['reservation', 'available_vouchers'],
            'properties' => [
                'reservation' => ['$ref' => '#/components/schemas/CustomerReservationBenefitsReservation'],
                'available_vouchers' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CustomerVoucher']],
            ],
            'additionalProperties' => false,
            'example' => [
                'reservation' => $customerReservationBenefitsReservationSchema['example'],
                'available_vouchers' => [$customerVoucherSchema['example']],
            ],
        ];

        $customerReservationPreorderNormalizedItemSchema = [
            'type' => 'object',
            'required' => ['item_id', 'quantity'],
            'properties' => [
                'item_id' => ['type' => 'integer'],
                'quantity' => ['type' => 'integer'],
            ],
            'additionalProperties' => false,
            'example' => [
                'item_id' => 201,
                'quantity' => 2,
            ],
        ];

        $customerReservationPreorderTotalsSchema = [
            'type' => 'object',
            'required' => ['item_count', 'quantity', 'subtotal'],
            'properties' => [
                'item_count' => ['type' => 'integer'],
                'quantity' => ['type' => 'integer'],
                'subtotal' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
            'example' => [
                'item_count' => 2,
                'quantity' => 3,
                'subtotal' => '255000.00',
            ],
        ];

        $customerReservationPreorderLineSchema = [
            'type' => 'object',
            'required' => [
                'order_item_id',
                'item_id',
                'quantity',
                'status',
                'name',
                'code',
                'unit_price',
                'line_total',
                'currency',
                'notes',
                'updated_at',
            ],
            'properties' => [
                'order_item_id' => ['type' => 'integer'],
                'item_id' => ['type' => 'integer'],
                'quantity' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'code' => ['type' => 'string', 'nullable' => true],
                'unit_price' => ['type' => 'string'],
                'line_total' => ['type' => 'string'],
                'currency' => ['type' => 'string'],
                'notes' => ['type' => 'string', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
            'example' => [
                'order_item_id' => 8801,
                'item_id' => 201,
                'quantity' => 3,
                'status' => 'Ordered',
                'name' => 'Pho Bo Tai',
                'code' => 'PHO-BO',
                'unit_price' => '85000.00',
                'line_total' => '255000.00',
                'currency' => 'VND',
                'notes' => null,
                'updated_at' => '2026-04-05T09:45:00Z',
            ],
        ];

        $customerReservationPreorderSnapshotSchema = [
            'type' => 'object',
            'required' => [
                'present',
                'order_id',
                'order_row_version',
                'order_status',
                'service_time',
                'currency',
                'lines',
                'totals',
                'normalized_pre_order_items',
            ],
            'properties' => [
                'present' => ['type' => 'boolean'],
                'order_id' => ['type' => 'integer', 'nullable' => true],
                'order_row_version' => ['type' => 'integer', 'nullable' => true],
                'order_status' => ['type' => 'string', 'nullable' => true],
                'service_time' => ['type' => 'string', 'format' => 'date-time'],
                'currency' => ['type' => 'string'],
                'lines' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CustomerReservationPreorderLine']],
                'totals' => ['$ref' => '#/components/schemas/CustomerReservationPreorderTotals'],
                'normalized_pre_order_items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CustomerReservationPreorderNormalizedItem']],
            ],
            'additionalProperties' => false,
            'example' => [
                'present' => true,
                'order_id' => 701,
                'order_row_version' => 2,
                'order_status' => 'Active',
                'service_time' => '2026-04-05T12:00:00Z',
                'currency' => 'VND',
                'lines' => [$customerReservationPreorderLineSchema['example']],
                'totals' => $customerReservationPreorderTotalsSchema['example'],
                'normalized_pre_order_items' => [$customerReservationPreorderNormalizedItemSchema['example']],
            ],
        ];

        $customerReservationPreorderManagementPolicySchema = [
            'type' => 'object',
            'required' => ['can_manage', 'reservation_status', 'cutoff_minutes', 'service_start', 'manage_until', 'reasons'],
            'properties' => [
                'can_manage' => ['type' => 'boolean'],
                'reservation_status' => ['type' => 'string'],
                'cutoff_minutes' => ['type' => 'integer'],
                'service_start' => ['type' => 'string', 'format' => 'date-time'],
                'manage_until' => ['type' => 'string', 'format' => 'date-time'],
                'reasons' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'additionalProperties' => false,
            'example' => [
                'can_manage' => true,
                'reservation_status' => 'Confirmed',
                'cutoff_minutes' => 60,
                'service_start' => '2026-04-05T12:00:00Z',
                'manage_until' => '2026-04-05T11:00:00Z',
                'reasons' => [],
            ],
        ];

        $customerReservationPreorderPayloadSchema = [
            'type' => 'object',
            'required' => [
                'reservation_id',
                'reservation_code',
                'reservation_status',
                'reservation_row_version',
                'pre_order',
                'management_policy',
            ],
            'properties' => [
                'reservation_id' => ['type' => 'integer'],
                'reservation_code' => ['type' => 'string'],
                'reservation_status' => ['type' => 'string'],
                'reservation_row_version' => ['type' => 'integer'],
                'pre_order' => ['$ref' => '#/components/schemas/CustomerReservationPreorderSnapshot'],
                'management_policy' => ['$ref' => '#/components/schemas/CustomerReservationPreorderManagementPolicy'],
            ],
            'additionalProperties' => false,
            'example' => [
                'reservation_id' => 103,
                'reservation_code' => 'RSV-20260405-0103',
                'reservation_status' => 'Confirmed',
                'reservation_row_version' => 4,
                'pre_order' => $customerReservationPreorderSnapshotSchema['example'],
                'management_policy' => $customerReservationPreorderManagementPolicySchema['example'],
            ],
        ];

        $branchBusinessHoursPeriodSchema = [
            'type' => 'object',
            'required' => ['start_time', 'end_time'],
            'properties' => [
                'start_time' => ['type' => 'string'],
                'end_time' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $branchBusinessHoursDaySchema = [
            'type' => 'object',
            'required' => ['day_of_week', 'periods'],
            'properties' => [
                'day_of_week' => ['type' => 'integer'],
                'periods' => [
                    'type' => 'array',
                    'items' => $branchBusinessHoursPeriodSchema,
                ],
            ],
            'additionalProperties' => false,
        ];

        $branchClosureWindowSchema = [
            'type' => 'object',
            'required' => ['start_local', 'end_local', 'type', 'reason'],
            'properties' => [
                'start_local' => ['type' => 'string'],
                'end_local' => ['type' => 'string'],
                'type' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $branchBookingPolicySchema = [
            'type' => 'object',
            'required' => ['reservation', 'waiting_list'],
            'properties' => [
                'reservation' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'waiting_list' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
            ],
            'additionalProperties' => true,
        ];

        $branchSchema = [
            'type' => 'object',
            'required' => ['branch_id', 'branch_code', 'branch_name', 'business_hours', 'closure_windows', 'booking_policy', 'is_active', 'is_default'],
            'properties' => [
                'branch_id' => ['type' => 'integer'],
                'branch_code' => ['type' => 'string'],
                'branch_name' => ['type' => 'string'],
                'description' => ['type' => 'string', 'nullable' => true],
                'timezone' => ['type' => 'string', 'nullable' => true],
                'currency' => ['type' => 'string', 'nullable' => true],
                'business_hours' => [
                    'type' => 'array',
                    'items' => $branchBusinessHoursDaySchema,
                ],
                'closure_windows' => [
                    'type' => 'array',
                    'items' => $branchClosureWindowSchema,
                ],
                'booking_policy' => $branchBookingPolicySchema,
                'is_active' => ['type' => 'boolean'],
                'is_default' => ['type' => 'boolean'],
                'row_version' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $restaurantProfileSchema = [
            'type' => 'object',
            'required' => ['branch_id', 'branch_code', 'branch_name', 'timezone', 'business_hours', 'today_hours', 'current_status'],
            'properties' => [
                'branch_id' => ['type' => 'integer'],
                'branch_code' => ['type' => 'string'],
                'branch_name' => ['type' => 'string'],
                'timezone' => ['type' => 'string'],
                'business_hours' => [
                    'type' => 'array',
                    'items' => $branchBusinessHoursDaySchema,
                ],
                'today_hours' => [
                    'type' => 'object',
                    'required' => ['day_of_week', 'periods', 'is_closed'],
                    'properties' => [
                        'day_of_week' => ['type' => 'integer'],
                        'periods' => [
                            'type' => 'array',
                            'items' => $branchBusinessHoursPeriodSchema,
                        ],
                        'is_closed' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'current_status' => [
                    'type' => 'object',
                    'required' => ['is_open', 'reason', 'checked_at_local', 'timezone'],
                    'properties' => [
                        'is_open' => ['type' => 'boolean'],
                        'reason' => ['type' => 'string', 'nullable' => true],
                        'checked_at_local' => ['type' => 'string'],
                        'timezone' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $cashierShiftSchema = [
            'type' => 'object',
            'required' => ['cashier_shift_id', 'branch_id', 'shift_code', 'status', 'currency', 'row_version'],
            'properties' => [
                'cashier_shift_id' => ['type' => 'integer'],
                'branch_id' => ['type' => 'integer'],
                'branch' => [
                    'anyOf' => [
                        [
                            'type' => 'object',
                            'required' => ['branch_id', 'branch_code', 'branch_name', 'is_default'],
                            'properties' => [
                                'branch_id' => ['type' => 'integer'],
                                'branch_code' => ['type' => 'string'],
                                'branch_name' => ['type' => 'string'],
                                'is_default' => ['type' => 'boolean'],
                            ],
                            'additionalProperties' => false,
                        ],
                        ['type' => 'null'],
                    ],
                ],
                'shift_code' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'currency' => ['type' => 'string'],
                'terminal_code' => ['type' => 'string', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'opened_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'closed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'opening_float_amount' => ['type' => 'string'],
                'expected_cash_amount' => ['type' => 'string', 'nullable' => true],
                'actual_cash_amount' => ['type' => 'string', 'nullable' => true],
                'cash_discrepancy_amount' => ['type' => 'string', 'nullable' => true],
                'opening_note' => ['type' => 'string', 'nullable' => true],
                'closing_note' => ['type' => 'string', 'nullable' => true],
                'cashier' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'full_name' => ['type' => 'string', 'nullable' => true],
                        'email' => ['type' => 'string', 'nullable' => true],
                    ],
                    'required' => ['user_id', 'full_name', 'email'],
                    'additionalProperties' => false,
                ],
                'opened_by' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'full_name' => ['type' => 'string', 'nullable' => true],
                        'email' => ['type' => 'string', 'nullable' => true],
                    ],
                    'required' => ['user_id', 'full_name', 'email'],
                    'additionalProperties' => false,
                ],
                'closed_by' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'user_id' => ['type' => 'integer'],
                        'full_name' => ['type' => 'string', 'nullable' => true],
                        'email' => ['type' => 'string', 'nullable' => true],
                    ],
                    'required' => ['user_id', 'full_name', 'email'],
                    'additionalProperties' => false,
                ],
                'summary' => [
                    'type' => 'object',
                    'required' => ['payments', 'cash', 'methods'],
                    'properties' => [
                        'payments' => [
                            'type' => 'object',
                            'required' => ['captured_total', 'refunded_total', 'net_paid_total', 'deposit_net', 'final_net', 'payment_count', 'refund_count', 'currency'],
                            'properties' => [
                                'captured_total' => ['type' => 'string'],
                                'refunded_total' => ['type' => 'string'],
                                'net_paid_total' => ['type' => 'string'],
                                'deposit_net' => ['type' => 'string'],
                                'final_net' => ['type' => 'string'],
                                'payment_count' => ['type' => 'integer'],
                                'refund_count' => ['type' => 'integer'],
                                'currency' => [
                                    'type' => 'object',
                                    'required' => ['currency', 'currencies', 'has_mixed_currencies'],
                                    'properties' => [
                                        'currency' => ['type' => 'string', 'nullable' => true],
                                        'currencies' => ['type' => 'array', 'items' => ['type' => 'string']],
                                        'has_mixed_currencies' => ['type' => 'boolean'],
                                    ],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'additionalProperties' => false,
                        ],
                        'cash' => [
                            'type' => 'object',
                            'required' => ['currency', 'opening_float_amount', 'captured_amount', 'refunded_amount', 'expected_cash_amount', 'excluded_cash_currencies', 'has_excluded_cash_currencies'],
                            'properties' => [
                                'currency' => ['type' => 'string'],
                                'opening_float_amount' => ['type' => 'string'],
                                'captured_amount' => ['type' => 'string'],
                                'refunded_amount' => ['type' => 'string'],
                                'expected_cash_amount' => ['type' => 'string'],
                                'excluded_cash_currencies' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'has_excluded_cash_currencies' => ['type' => 'boolean'],
                            ],
                            'additionalProperties' => false,
                        ],
                        'methods' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'required' => ['payment_method', 'currency', 'captured_amount', 'refunded_amount', 'net_amount', 'payment_count', 'refund_count'],
                                'properties' => [
                                    'payment_method' => ['type' => 'string'],
                                    'currency' => ['type' => 'string'],
                                    'captured_amount' => ['type' => 'string'],
                                    'refunded_amount' => ['type' => 'string'],
                                    'net_amount' => ['type' => 'string'],
                                    'payment_count' => ['type' => 'integer'],
                                    'refund_count' => ['type' => 'integer'],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'flags' => [
                    'type' => 'object',
                    'required' => ['is_open', 'has_payments', 'has_refunds', 'has_mixed_payment_currencies'],
                    'properties' => [
                        'is_open' => ['type' => 'boolean'],
                        'has_payments' => ['type' => 'boolean'],
                        'has_refunds' => ['type' => 'boolean'],
                        'has_mixed_payment_currencies' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $reservationOrderSchema = [
            'type' => 'object',
            'required' => ['order_id', 'reservation_id', 'order_type', 'status'],
            'properties' => [
                'order_id' => ['type' => 'integer'],
                'reservation_id' => ['type' => 'integer'],
                'order_type' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'row_version' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'created_by' => ['type' => 'integer', 'nullable' => true],
                'updated_by' => ['type' => 'integer', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'workflow' => [
                    'type' => 'object',
                    'required' => ['settlement_scope', 'canonical_bill_snapshot_action', 'legacy_bill_snapshot_action'],
                    'properties' => [
                        'settlement_scope' => ['type' => 'string'],
                        'canonical_bill_snapshot_action' => ['type' => 'string'],
                        'legacy_bill_snapshot_action' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'payment_status' => ['type' => 'string', 'nullable' => true],
                'items' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => [
                        'type' => 'object',
                        'required' => ['order_item_id', 'item_id', 'quantity', 'status', 'row_version', 'item_name_snapshot', 'unit_price', 'currency', 'line_total', 'notes', 'item'],
                        'properties' => [
                            'order_item_id' => ['type' => 'integer'],
                            'item_id' => ['type' => 'integer'],
                            'quantity' => ['type' => 'integer'],
                            'status' => ['type' => 'string'],
                            'row_version' => ['type' => 'integer', 'nullable' => true],
                            'item_name_snapshot' => ['type' => 'string', 'nullable' => true],
                            'unit_price' => ['type' => 'string'],
                            'currency' => ['type' => 'string'],
                            'line_total' => ['type' => 'string'],
                            'notes' => ['type' => 'string', 'nullable' => true],
                            'item' => [
                                'type' => 'object',
                                'nullable' => true,
                                'properties' => [
                                    'name' => ['type' => 'string', 'nullable' => true],
                                    'code' => ['type' => 'string', 'nullable' => true],
                                ],
                                'required' => ['name', 'code'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'totals' => [
                    'type' => 'object',
                    'required' => ['subtotal', 'discount', 'total_due', 'paid', 'deposit_applied', 'deposit_net', 'final_paid', 'outstanding', 'currency'],
                    'properties' => [
                        'subtotal' => ['type' => 'string', 'nullable' => true],
                        'discount' => ['type' => 'string', 'nullable' => true],
                        'total_due' => ['type' => 'string', 'nullable' => true],
                        'paid' => ['type' => 'string', 'nullable' => true],
                        'deposit_applied' => ['type' => 'string', 'nullable' => true],
                        'deposit_net' => ['type' => 'string', 'nullable' => true],
                        'final_paid' => ['type' => 'string', 'nullable' => true],
                        'outstanding' => ['type' => 'string', 'nullable' => true],
                        'currency' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffCheckoutSettlementSchema = [
            'type' => 'object',
            'required' => [
                'order_id',
                'reservation_id',
                'row_version',
                'total_amount',
                'currency',
                'paid_amount',
                'deposit_applied_amount',
                'final_paid_amount',
                'outstanding_amount',
                'payment_status',
                'status',
                'order_status',
                'reservation_status',
            ],
            'properties' => [
                'order_id' => ['type' => 'integer'],
                'reservation_id' => ['type' => 'integer'],
                'row_version' => ['type' => 'integer'],
                'total_amount' => ['type' => 'number'],
                'currency' => ['type' => 'string'],
                'paid_amount' => ['type' => 'number'],
                'deposit_applied_amount' => ['type' => 'number'],
                'final_paid_amount' => ['type' => 'number'],
                'outstanding_amount' => ['type' => 'number'],
                'payment_status' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'order_status' => ['type' => 'string'],
                'reservation_status' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffRefundDataSchema = [
            'type' => 'object',
            'required' => ['reservation', 'refund'],
            'properties' => [
                'reservation' => ['$ref' => '#/components/schemas/ReservationSummary'],
                'refund' => [
                    'type' => 'object',
                    'required' => [
                        'refund_payment_ids',
                        'refund_amount',
                        'currency',
                        'refund_scope',
                        'cancelled',
                        'reservation_status',
                        'payment_summary',
                    ],
                    'properties' => [
                        'refund_payment_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'refund_amount' => ['type' => 'string'],
                        'currency' => ['type' => 'string'],
                        'refund_scope' => ['type' => 'string'],
                        'cancelled' => ['type' => 'boolean'],
                        'reservation_status' => ['type' => 'string'],
                        'payment_summary' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationAssignmentSchema = [
            'type' => 'object',
            'required' => ['assignment_id', 'conversation_id', 'agent_user_id', 'is_active'],
            'properties' => [
                'assignment_id' => ['type' => 'integer'],
                'conversation_id' => ['type' => 'string', 'format' => 'uuid'],
                'agent_user_id' => ['type' => 'integer'],
                'agent' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'assigned_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'released_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'is_active' => ['type' => 'boolean'],
                'notes' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationAnalysisSchema = [
            'type' => 'object',
            'required' => ['analysis_id', 'conversation_id', 'is_spam'],
            'properties' => [
                'analysis_id' => ['type' => 'integer'],
                'conversation_id' => ['type' => 'string', 'format' => 'uuid'],
                'analyzer_name' => ['type' => 'string', 'nullable' => true],
                'is_spam' => ['type' => 'boolean'],
                'quality_score' => ['type' => 'string', 'nullable' => true],
                'extracted_info' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationAiAssistActionSchema = [
            'type' => 'object',
            'required' => ['code', 'label'],
            'properties' => [
                'code' => ['type' => 'string'],
                'label' => ['type' => 'string'],
                'reason' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationAiAssistRiskSchema = [
            'type' => 'object',
            'required' => ['code', 'label', 'severity'],
            'properties' => [
                'code' => ['type' => 'string'],
                'label' => ['type' => 'string'],
                'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationAiAssistSchema = [
            'type' => 'object',
            'required' => [
                'status',
                'feature_key',
                'suggested_actions',
                'risk_flags',
                'disclaimer',
                'generated_from',
            ],
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['ready', 'disabled', 'unavailable']],
                'feature_key' => ['type' => 'string'],
                'provider' => ['type' => 'string', 'nullable' => true],
                'model' => ['type' => 'string', 'nullable' => true],
                'priority' => ['type' => 'string', 'nullable' => true, 'enum' => ['high', 'normal', 'low']],
                'summary' => ['type' => 'string', 'nullable' => true],
                'suggested_actions' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffConversationAiAssistAction']],
                'risk_flags' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffConversationAiAssistRiskFlag']],
                'fallback_reason_code' => ['type' => 'string', 'nullable' => true],
                'fallback_reason' => ['type' => 'string', 'nullable' => true],
                'disclaimer' => ['type' => 'string'],
                'latency_budget_ms' => ['type' => 'integer', 'nullable' => true],
                'cost_tier' => ['type' => 'string', 'nullable' => true],
                'generated_from' => [
                    'type' => 'object',
                    'required' => ['message_count', 'customer_message_count', 'internal_note_count', 'analysis_count'],
                    'properties' => [
                        'message_count' => ['type' => 'integer'],
                        'customer_message_count' => ['type' => 'integer'],
                        'internal_note_count' => ['type' => 'integer'],
                        'analysis_count' => ['type' => 'integer'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationMessageSchema = [
            'type' => 'object',
            'required' => ['message_id', 'conversation_id', 'sender', 'message_text', 'message_type', 'is_internal_note'],
            'properties' => [
                'message_id' => ['type' => 'integer'],
                'conversation_id' => ['type' => 'string', 'format' => 'uuid'],
                'sender' => ['type' => 'string'],
                'sender_id' => ['type' => 'integer', 'nullable' => true],
                'sender_user' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'message_text' => ['type' => 'string'],
                'message_type' => ['type' => 'string'],
                'is_internal_note' => ['type' => 'boolean'],
                'attachment_url' => ['type' => 'string', 'nullable' => true],
                'attachment' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'file_id' => ['type' => 'integer', 'nullable' => true],
                        'message_id' => ['type' => 'integer'],
                        'access_url' => ['type' => 'string'],
                        'access_expires_at' => ['type' => 'string', 'format' => 'date-time'],
                        'mime_type' => ['type' => 'string', 'nullable' => true],
                    ],
                    'required' => ['file_id', 'message_id', 'access_url', 'access_expires_at', 'mime_type'],
                    'additionalProperties' => false,
                ],
                'is_processed' => ['type' => 'boolean'],
                'processing_status' => ['type' => 'string', 'nullable' => true],
                'confidence' => ['type' => 'string', 'nullable' => true],
                'related_reservation_id' => ['type' => 'integer', 'nullable' => true],
                'related_order_id' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'files' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'file_id' => ['type' => 'integer'],
                            'file_url' => ['type' => 'string'],
                            'access_expires_at' => ['type' => 'string', 'format' => 'date-time'],
                            'mime_type' => ['type' => 'string', 'nullable' => true],
                            'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        ],
                        'required' => ['file_id', 'file_url', 'access_expires_at'],
                        'additionalProperties' => false,
                    ],
                ],
                'entities' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'message_entity_id' => ['type' => 'integer'],
                            'entity_type' => ['type' => 'string'],
                            'entity_text' => ['type' => 'string'],
                            'entity_normalized' => ['type' => 'string', 'nullable' => true],
                            'extra_json' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                            'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        ],
                        'required' => ['message_entity_id', 'entity_type', 'entity_text'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationEventSchema = [
            'type' => 'object',
            'required' => ['event_id', 'conversation_id', 'event_type'],
            'properties' => [
                'event_id' => ['type' => 'integer'],
                'conversation_id' => ['type' => 'string', 'format' => 'uuid'],
                'event_type' => ['type' => 'string'],
                'event_by_user_id' => ['type' => 'integer', 'nullable' => true],
                'by_user' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'event_data' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffAuditTrailPrimarySubjectSchema = [
            'type' => 'object',
            'required' => ['type', 'id'],
            'properties' => [
                'type' => ['type' => 'string'],
                'id' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $staffAuditTrailSubjectSchema = [
            'type' => 'object',
            'required' => ['type', 'id', 'role'],
            'properties' => [
                'type' => ['type' => 'string'],
                'id' => ['type' => 'string'],
                'role' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffAuditTrailActorUserSchema = [
            'type' => 'object',
            'required' => ['user_id', 'full_name'],
            'properties' => [
                'user_id' => ['type' => 'integer'],
                'full_name' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $staffAuditTrailActorSchema = [
            'type' => 'object',
            'required' => ['user_id', 'type', 'key', 'user'],
            'properties' => [
                'user_id' => ['type' => 'integer', 'nullable' => true],
                'type' => ['type' => 'string', 'nullable' => true],
                'key' => ['type' => 'string', 'nullable' => true],
                'user' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/StaffAuditTrailActorUser'],
                        ['type' => 'null'],
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffAuditTrailRequestSchema = [
            'type' => 'object',
            'required' => ['request_id', 'branch_id', 'ip', 'user_agent', 'method', 'path'],
            'properties' => [
                'request_id' => ['type' => 'string', 'nullable' => true],
                'branch_id' => ['type' => 'integer', 'nullable' => true],
                'ip' => ['type' => 'string', 'nullable' => true],
                'user_agent' => ['type' => 'string', 'nullable' => true],
                'method' => ['type' => 'string', 'nullable' => true],
                'path' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffAuditTrailEntrySchema = [
            'type' => 'object',
            'required' => ['audit_id', 'action', 'occurred_at', 'primary_subject', 'subjects', 'actor', 'request', 'before', 'after', 'summary', 'meta'],
            'properties' => [
                'audit_id' => ['type' => 'integer'],
                'action' => ['type' => 'string'],
                'occurred_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'primary_subject' => ['$ref' => '#/components/schemas/StaffAuditTrailPrimarySubject'],
                'subjects' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffAuditTrailSubject']],
                'actor' => ['$ref' => '#/components/schemas/StaffAuditTrailActor'],
                'request' => ['$ref' => '#/components/schemas/StaffAuditTrailRequest'],
                'before' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'after' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'summary' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'meta' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffAuditTrailCollectionMetaSchema = [
            'type' => 'object',
            'required' => [
                'action',
                'page',
                'filters',
                'sort',
                'pagination',
                'current_page',
                'per_page',
                'from',
                'to',
                'total',
                'last_page',
                'has_more_pages',
                'query_contract',
            ],
            'properties' => [
                'action' => ['type' => 'string'],
                'page' => ['type' => 'integer'],
                'filters' => ['type' => 'object', 'additionalProperties' => true],
                'sort' => [
                    'type' => 'object',
                    'required' => ['supported', 'value', 'by', 'dir'],
                    'properties' => [
                        'supported' => ['type' => 'boolean'],
                        'value' => ['type' => 'string', 'nullable' => true],
                        'by' => ['type' => 'string', 'nullable' => true],
                        'dir' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'pagination' => [
                    'type' => 'object',
                    'required' => ['mode', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages'],
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['paged']],
                        'current_page' => ['type' => 'integer'],
                        'per_page' => ['type' => 'integer'],
                        'from' => ['type' => 'integer', 'nullable' => true],
                        'to' => ['type' => 'integer', 'nullable' => true],
                        'total' => ['type' => 'integer'],
                        'last_page' => ['type' => 'integer'],
                        'has_more_pages' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationSummarySchema = [
            'type' => 'object',
            'required' => ['conversation_id', 'branch_id', 'status', 'workflow', 'channel', 'counts', 'assignment_state', 'operational'],
            'properties' => [
                'conversation_id' => ['type' => 'string', 'format' => 'uuid'],
                'branch_id' => ['type' => 'integer'],
                'branch' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'status' => ['type' => 'string'],
                'workflow' => [
                    'type' => 'object',
                    'properties' => [
                        'state' => ['type' => 'string'],
                        'state_reason' => ['type' => 'string', 'nullable' => true],
                        'state_changed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'first_triaged_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'resolved_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'closed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'is_terminal' => ['type' => 'boolean'],
                        'allowed_actions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['state', 'state_reason', 'state_changed_at', 'first_triaged_at', 'resolved_at', 'closed_at', 'is_terminal', 'allowed_actions'],
                    'additionalProperties' => false,
                ],
                'channel' => ['type' => 'string'],
                'intent_detected' => ['type' => 'string', 'nullable' => true],
                'customer_session_id' => ['type' => 'string', 'nullable' => true],
                'session_id' => ['type' => 'string', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'closed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'latest_activity_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'user' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'linked_reservation' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'linked_waiting_list' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'active_assignment' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/StaffConversationAssignment'],
                        ['type' => 'null'],
                    ],
                ],
                'latest_message' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/StaffConversationMessage'],
                        ['type' => 'null'],
                    ],
                ],
                'latest_analysis' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/StaffConversationAnalysis'],
                        ['type' => 'null'],
                    ],
                ],
                'counts' => [
                    'type' => 'object',
                    'properties' => [
                        'messages' => ['type' => 'integer'],
                        'internal_notes' => ['type' => 'integer'],
                        'events' => ['type' => 'integer'],
                        'analyses' => ['type' => 'integer'],
                    ],
                    'required' => ['messages', 'internal_notes', 'events', 'analyses'],
                    'additionalProperties' => false,
                ],
                'assignment_state' => [
                    'type' => 'object',
                    'properties' => [
                        'is_assigned' => ['type' => 'boolean'],
                        'is_unassigned' => ['type' => 'boolean'],
                        'is_mine' => ['type' => 'boolean'],
                    ],
                    'required' => ['is_assigned', 'is_unassigned', 'is_mine'],
                    'additionalProperties' => false,
                ],
                'operational' => [
                    'type' => 'object',
                    'properties' => [
                        'is_overdue' => ['type' => 'boolean'],
                        'overdue_after_minutes' => ['type' => 'integer'],
                        'queue_bucket' => ['type' => 'string'],
                    ],
                    'required' => ['is_overdue', 'overdue_after_minutes', 'queue_bucket'],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationDetailPayload = [
            'type' => 'object',
            'required' => ['conversation', 'messages', 'events', 'analyses', 'ai_assist', 'assignment_history', 'capabilities'],
            'properties' => [
                'conversation' => ['$ref' => '#/components/schemas/StaffConversationSummary'],
                'messages' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffConversationMessage']],
                'events' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffConversationEvent']],
                'analyses' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffConversationAnalysis']],
                'ai_assist' => ['$ref' => '#/components/schemas/StaffConversationAiAssist'],
                'assignment_history' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffConversationAssignment']],
                'capabilities' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffConversationMutationPayload = [
            'type' => 'object',
            'required' => ['action', 'conversation'],
            'properties' => [
                'action' => ['type' => 'string'],
                'conversation' => ['$ref' => '#/components/schemas/StaffConversationSummary'],
                'assignment' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/StaffConversationAssignment'],
                        ['type' => 'null'],
                    ],
                ],
                'event' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/StaffConversationEvent'],
                        ['type' => 'null'],
                    ],
                ],
                'message' => [
                    'anyOf' => [
                        ['$ref' => '#/components/schemas/StaffConversationMessage'],
                        ['type' => 'null'],
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];

        $restaurantTableSchema = [
            'type' => 'object',
            'required' => ['table_id', 'branch_id', 'table_code', 'template_id', 'seats', 'zone', 'pos_x', 'pos_y', 'status', 'description', 'price', 'row_version', 'pivot', 'created_at', 'updated_at'],
            'properties' => [
                'table_id' => ['type' => 'integer'],
                'branch_id' => ['type' => 'integer', 'nullable' => true],
                'table_code' => ['type' => 'string', 'nullable' => true],
                'template_id' => ['type' => 'integer', 'nullable' => true],
                'seats' => ['type' => 'integer', 'nullable' => true],
                'zone' => ['type' => 'string', 'nullable' => true],
                'pos_x' => ['type' => 'integer', 'nullable' => true],
                'pos_y' => ['type' => 'integer', 'nullable' => true],
                'status' => ['type' => 'string'],
                'description' => ['type' => 'string', 'nullable' => true],
                'price' => ['type' => 'string', 'nullable' => true],
                'row_version' => ['type' => 'integer', 'nullable' => true],
                'pivot' => [
                    'anyOf' => [
                        [
                            'type' => 'object',
                            'required' => ['reservation_id', 'table_id', 'reservation_table_id'],
                            'properties' => [
                                'reservation_id' => ['type' => 'integer', 'nullable' => true],
                                'table_id' => ['type' => 'integer', 'nullable' => true],
                                'reservation_table_id' => ['type' => 'integer', 'nullable' => true],
                            ],
                            'additionalProperties' => false,
                        ],
                        ['type' => 'null'],
                    ],
                ],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffAuthUserSchema = [
            'type' => 'object',
            'required' => ['user_id', 'username', 'full_name', 'email', 'phone', 'role_id', 'role_name'],
            'properties' => [
                'user_id' => ['type' => 'integer'],
                'username' => ['type' => 'string'],
                'full_name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'role_id' => ['type' => 'integer', 'nullable' => true],
                'role_name' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $apiUserCustomerProfileSchema = [
            'type' => 'object',
            'required' => ['user_id', 'full_name', 'email', 'phone', 'role_id', 'current_tier_id'],
            'properties' => [
                'user_id' => ['type' => 'integer'],
                'full_name' => ['type' => 'string'],
                'email' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'role_id' => ['type' => 'integer', 'nullable' => true],
                'current_tier_id' => ['type' => 'integer', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $apiUserStaffProfileSchema = [
            'type' => 'object',
            'required' => ['user_id', 'role_id', 'role_name', 'staff_auth_mode'],
            'properties' => [
                'user_id' => ['type' => 'integer'],
                'role_id' => ['type' => 'integer', 'nullable' => true],
                'role_name' => ['type' => 'string', 'nullable' => true],
                'staff_auth_mode' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $apiUserEnvelopeSchema = [
            'type' => 'object',
            'required' => ['auth_mode', 'user'],
            'properties' => [
                'auth_mode' => ['type' => 'string', 'enum' => ['customer', 'staff']],
                'user' => [
                    'oneOf' => [
                        ['$ref' => '#/components/schemas/ApiUserCustomerProfile'],
                        ['$ref' => '#/components/schemas/ApiUserStaffProfile'],
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffStartupBranchSchema = [
            'type' => 'object',
            'required' => ['branch_id', 'branch_code', 'branch_name', 'timezone', 'currency', 'is_default', 'is_active'],
            'properties' => [
                'branch_id' => ['type' => 'integer'],
                'branch_code' => ['type' => 'string'],
                'branch_name' => ['type' => 'string'],
                'timezone' => ['type' => 'string', 'nullable' => true],
                'currency' => ['type' => 'string', 'nullable' => true],
                'is_default' => ['type' => 'boolean'],
                'is_active' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $staffStartupCashierShiftSchema = [
            'type' => 'object',
            'required' => ['cashier_shift_id', 'branch_id', 'shift_code', 'status', 'currency', 'terminal_code', 'row_version', 'opened_at'],
            'properties' => [
                'cashier_shift_id' => ['type' => 'integer'],
                'branch_id' => ['type' => 'integer'],
                'branch' => ['$ref' => '#/components/schemas/StaffStartupBranch', 'nullable' => true],
                'shift_code' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'currency' => ['type' => 'string'],
                'terminal_code' => ['type' => 'string', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'opened_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffWorkspaceIdSchema = [
            'type' => 'string',
            'enum' => ['ops', 'kitchen', 'admin'],
        ];

        $staffBranchAccessContextSchema = [
            'type' => 'object',
            'required' => [
                'accessible_branch_ids',
                'default_branch_id',
                'current_branch_id',
                'has_default_branch_access',
                'has_multi_branch_access',
                'branch_selector_enabled',
                'access_source',
                'branches_uri',
            ],
            'properties' => [
                'accessible_branch_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'default_branch_id' => ['type' => 'integer', 'nullable' => true],
                'current_branch_id' => ['type' => 'integer', 'nullable' => true],
                'has_default_branch_access' => ['type' => 'boolean'],
                'has_multi_branch_access' => ['type' => 'boolean'],
                'branch_selector_enabled' => ['type' => 'boolean'],
                'access_source' => ['type' => 'string'],
                'branches_uri' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $staffNavigationItemSchema = [
            'type' => 'object',
            'required' => ['key', 'required_capabilities', 'can_access', 'primary_route'],
            'properties' => [
                'key' => ['type' => 'string'],
                'required_capabilities' => ['type' => 'array', 'items' => ['type' => 'string']],
                'can_access' => ['type' => 'boolean'],
                'primary_route' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $staffNavigationContextSchema = [
            'type' => 'object',
            'additionalProperties' => ['$ref' => '#/components/schemas/StaffNavigationItem'],
        ];

        $staffBranchScopeMetaSchema = [
            'type' => 'object',
            'required' => ['requested_branch_id', 'accessible_branch_ids', 'uses_explicit_entitlement'],
            'properties' => [
                'requested_branch_id' => ['type' => 'integer', 'nullable' => true],
                'accessible_branch_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'uses_explicit_entitlement' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $staffStartupReadinessSchema = [
            'type' => 'object',
            'required' => [
                'access',
                'branch',
                'cashier_shift',
                'operator_ready',
                'requires_cashier_shift',
                'granted_capability_count',
                'known_capability_count',
            ],
            'properties' => [
                'access' => ['type' => 'string', 'enum' => ['ready', 'capability_missing']],
                'branch' => ['type' => 'string', 'enum' => ['ready', 'missing']],
                'cashier_shift' => ['type' => 'string', 'enum' => ['ready', 'action_required', 'not_applicable']],
                'operator_ready' => ['type' => 'boolean'],
                'requires_cashier_shift' => ['type' => 'boolean'],
                'granted_capability_count' => ['type' => 'integer'],
                'known_capability_count' => ['type' => 'integer'],
            ],
            'additionalProperties' => false,
        ];

        $staffStartupContextSchema = [
            'type' => 'object',
            'required' => [
                'primary_workspace',
                'available_workspaces',
                'default_branch_id',
                'allowed_branch_ids',
                'assigned_station_ids',
                'default_branch',
                'branch_access',
                'active_cashier_shift',
                'navigation',
                'readiness',
            ],
            'properties' => [
                'primary_workspace' => $staffWorkspaceIdSchema,
                'available_workspaces' => ['type' => 'array', 'items' => $staffWorkspaceIdSchema],
                'default_branch_id' => ['type' => 'integer', 'nullable' => true],
                'allowed_branch_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'assigned_station_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'default_branch' => ['$ref' => '#/components/schemas/StaffStartupBranch', 'nullable' => true],
                'branch_access' => ['$ref' => '#/components/schemas/StaffBranchAccessContext'],
                'active_cashier_shift' => ['$ref' => '#/components/schemas/StaffStartupCashierShift', 'nullable' => true],
                'navigation' => ['$ref' => '#/components/schemas/StaffNavigationContext'],
                'readiness' => ['$ref' => '#/components/schemas/StaffStartupReadiness'],
            ],
            'additionalProperties' => false,
        ];

        $staffOperationalRealtimeDescriptorSchema = [
            'type' => 'object',
            'required' => ['enabled', 'topic', 'channel', 'current_version', 'changes_uri', 'polling_compatible', 'default_refresh_targets', 'poll_hint_ms'],
            'properties' => [
                'enabled' => ['type' => 'boolean'],
                'topic' => ['type' => 'string'],
                'channel' => ['type' => 'string'],
                'current_version' => ['type' => 'integer'],
                'changes_uri' => ['type' => 'string'],
                'polling_compatible' => ['type' => 'boolean'],
                'default_refresh_targets' => ['type' => 'array', 'items' => ['type' => 'string']],
                'poll_hint_ms' => ['type' => 'integer'],
            ],
            'additionalProperties' => false,
        ];

        $staffOperationalRealtimeEventSchema = [
            'type' => 'object',
            'required' => ['topic', 'channel', 'version', 'type', 'occurred_at', 'refresh_targets', 'payload'],
            'properties' => [
                'topic' => ['type' => 'string'],
                'channel' => ['type' => 'string'],
                'version' => ['type' => 'integer'],
                'type' => ['type' => 'string'],
                'occurred_at' => ['type' => 'string', 'format' => 'date-time'],
                'refresh_targets' => ['type' => 'array', 'items' => ['type' => 'string']],
                'payload' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffOperationalRealtimeStateSchema = [
            'type' => 'object',
            'required' => ['enabled', 'topic', 'channel', 'after_version', 'current_version', 'oldest_available_version', 'events', 'has_changes', 'stale_cursor', 'poll_hint_ms'],
            'properties' => [
                'enabled' => ['type' => 'boolean'],
                'topic' => ['type' => 'string'],
                'channel' => ['type' => 'string'],
                'after_version' => ['type' => 'integer'],
                'current_version' => ['type' => 'integer'],
                'oldest_available_version' => ['type' => 'integer'],
                'events' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffOperationalRealtimeEvent']],
                'has_changes' => ['type' => 'boolean'],
                'stale_cursor' => ['type' => 'boolean'],
                'poll_hint_ms' => ['type' => 'integer'],
            ],
            'additionalProperties' => false,
        ];

        $listingQueryContractSchema = [
            'type' => 'object',
            'required' => ['parameters', 'filter_keys', 'sort_fields', 'default_sort', 'pagination', 'legacy_aliases'],
            'properties' => [
                'parameters' => [
                    'type' => 'object',
                    'required' => ['filter', 'sort', 'page', 'per_page'],
                    'properties' => [
                        'filter' => ['type' => 'string'],
                        'sort' => ['type' => 'string'],
                        'page' => ['type' => 'string', 'nullable' => true],
                        'per_page' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'filter_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
                'sort_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                'default_sort' => ['type' => 'string', 'nullable' => true],
                'pagination' => [
                    'type' => 'object',
                    'required' => ['supported', 'max_per_page'],
                    'properties' => [
                        'supported' => ['type' => 'boolean'],
                        'max_per_page' => ['type' => 'integer', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'legacy_aliases' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'string'],
                ],
            ],
            'additionalProperties' => false,
        ];

        $listingSortSchema = [
            'type' => 'object',
            'required' => ['supported', 'value', 'by', 'dir'],
            'properties' => [
                'supported' => ['type' => 'boolean'],
                'value' => ['type' => 'string', 'nullable' => true],
                'by' => ['type' => 'string', 'nullable' => true],
                'dir' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $tableBoardPaginationSchema = [
            'type' => 'object',
            'required' => ['mode', 'supported'],
            'properties' => [
                'mode' => ['type' => 'string', 'enum' => ['none']],
                'supported' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $waitingListPaginationSchema = [
            'type' => 'object',
            'required' => ['mode', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages'],
            'properties' => [
                'mode' => ['type' => 'string', 'enum' => ['paged', 'legacy_unbounded']],
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $branchReferenceSchema = [
            'type' => 'object',
            'required' => ['branch_id', 'branch_code', 'branch_name', 'is_default'],
            'properties' => [
                'branch_id' => ['type' => 'integer'],
                'branch_code' => ['type' => 'string'],
                'branch_name' => ['type' => 'string'],
                'is_default' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $ingredientReferenceSchema = [
            'type' => 'object',
            'required' => ['ingredient_id', 'code', 'name', 'unit_code', 'is_active'],
            'properties' => [
                'ingredient_id' => ['type' => 'integer'],
                'code' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'unit_code' => ['type' => 'string'],
                'is_active' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $adminIngredientSchema = [
            'type' => 'object',
            'required' => ['ingredient_id', 'code', 'name', 'unit_code', 'is_active', 'row_version', 'stock', 'recipe_usage_count', 'created_at', 'updated_at'],
            'properties' => [
                'ingredient_id' => ['type' => 'integer'],
                'code' => ['type' => 'string', 'nullable' => true],
                'name' => ['type' => 'string'],
                'unit_code' => ['type' => 'string'],
                'description' => ['type' => 'string', 'nullable' => true],
                'is_active' => ['type' => 'boolean'],
                'row_version' => ['type' => 'integer'],
                'stock' => [
                    'type' => 'object',
                    'required' => ['on_hand', 'unit_code'],
                    'properties' => [
                        'on_hand' => ['type' => 'string'],
                        'unit_code' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'recipe_usage_count' => ['type' => 'integer'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $adminSupplierSchema = [
            'type' => 'object',
            'required' => ['supplier_id', 'code', 'name', 'contact_name', 'phone', 'email', 'notes', 'is_active', 'row_version', 'created_at', 'updated_at'],
            'properties' => [
                'supplier_id' => ['type' => 'integer'],
                'code' => ['type' => 'string', 'nullable' => true],
                'name' => ['type' => 'string'],
                'contact_name' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'email' => ['type' => 'string', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'is_active' => ['type' => 'boolean'],
                'row_version' => ['type' => 'integer'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $adminPurchaseOrderSummarySchema = [
            'type' => 'object',
            'required' => ['line_count', 'receipt_count', 'ordered_total_quantity', 'received_total_quantity', 'remaining_total_quantity'],
            'properties' => [
                'line_count' => ['type' => 'integer'],
                'receipt_count' => ['type' => 'integer'],
                'ordered_total_quantity' => ['type' => 'string'],
                'received_total_quantity' => ['type' => 'string'],
                'remaining_total_quantity' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $adminIngredientMovementSchema = [
            'type' => 'object',
            'required' => ['movement_id', 'branch_id', 'ingredient_id', 'movement_type', 'quantity_delta', 'unit_code', 'reference', 'notes', 'created_by', 'created_at'],
            'properties' => [
                'movement_id' => ['type' => 'integer'],
                'branch_id' => ['type' => 'integer', 'nullable' => true],
                'ingredient_id' => ['type' => 'integer'],
                'movement_type' => ['type' => 'string'],
                'quantity_delta' => ['type' => 'string'],
                'unit_code' => ['type' => 'string'],
                'reference' => [
                    'type' => 'object',
                    'required' => ['type', 'id'],
                    'properties' => [
                        'type' => ['type' => 'string', 'nullable' => true],
                        'id' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'notes' => ['type' => 'string', 'nullable' => true],
                'created_by' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $adminPurchaseOrderLineSchema = [
            'type' => 'object',
            'required' => ['po_line_id', 'ingredient_id', 'ordered_quantity', 'received_quantity', 'remaining_quantity', 'unit_code', 'unit_cost', 'notes', 'sort_order'],
            'properties' => [
                'po_line_id' => ['type' => 'integer'],
                'ingredient_id' => ['type' => 'integer'],
                'ingredient' => [
                    'anyOf' => [
                        $ingredientReferenceSchema,
                        ['type' => 'null'],
                    ],
                ],
                'ordered_quantity' => ['type' => 'string'],
                'received_quantity' => ['type' => 'string'],
                'remaining_quantity' => ['type' => 'string'],
                'unit_code' => ['type' => 'string'],
                'unit_cost' => ['type' => 'string', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'sort_order' => ['type' => 'integer'],
            ],
            'additionalProperties' => false,
        ];

        $adminPurchaseOrderReceiptLineSchema = [
            'type' => 'object',
            'required' => ['receipt_line_id', 'purchase_order_line_id', 'ingredient_id', 'received_quantity', 'unit_code', 'unit_cost', 'notes'],
            'properties' => [
                'receipt_line_id' => ['type' => 'integer'],
                'purchase_order_line_id' => ['type' => 'integer'],
                'ingredient_id' => ['type' => 'integer'],
                'ingredient' => [
                    'anyOf' => [
                        $ingredientReferenceSchema,
                        ['type' => 'null'],
                    ],
                ],
                'received_quantity' => ['type' => 'string'],
                'unit_code' => ['type' => 'string'],
                'unit_cost' => ['type' => 'string', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => true,
        ];

        $adminPurchaseOrderReceiptSchema = [
            'type' => 'object',
            'required' => ['receipt_id', 'branch_id', 'purchase_order_id', 'receipt_code', 'receipt_status', 'received_at', 'supplier_document_no', 'notes', 'summary', 'created_by', 'created_at'],
            'properties' => [
                'receipt_id' => ['type' => 'integer'],
                'branch_id' => ['type' => 'integer', 'nullable' => true],
                'purchase_order_id' => ['type' => 'integer'],
                'receipt_code' => ['type' => 'string'],
                'receipt_status' => ['type' => 'string'],
                'received_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'supplier_document_no' => ['type' => 'string', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'summary' => [
                    'type' => 'object',
                    'required' => ['line_count', 'received_total_quantity'],
                    'properties' => [
                        'line_count' => ['type' => 'integer'],
                        'received_total_quantity' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'lines' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/AdminPurchaseOrderReceiptLine'],
                ],
                'created_by' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $adminPurchaseOrderSchema = [
            'type' => 'object',
            'required' => ['purchase_order_id', 'branch_id', 'order_code', 'purchase_order_status', 'supplier_id', 'row_version', 'summary', 'created_at', 'updated_at'],
            'properties' => [
                'purchase_order_id' => ['type' => 'integer'],
                'branch_id' => ['type' => 'integer', 'nullable' => true],
                'branch' => [
                    'anyOf' => [
                        $branchReferenceSchema,
                        ['type' => 'null'],
                    ],
                ],
                'order_code' => ['type' => 'string'],
                'purchase_order_status' => ['type' => 'string'],
                'supplier_id' => ['type' => 'integer'],
                'supplier' => [
                    'anyOf' => [
                        [
                            'type' => 'object',
                            'required' => ['supplier_id', 'code', 'name', 'is_active'],
                            'properties' => [
                                'supplier_id' => ['type' => 'integer'],
                                'code' => ['type' => 'string', 'nullable' => true],
                                'name' => ['type' => 'string'],
                                'is_active' => ['type' => 'boolean'],
                            ],
                            'additionalProperties' => false,
                        ],
                        ['type' => 'null'],
                    ],
                ],
                'ordered_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'expected_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'received_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'supplier_reference' => ['type' => 'string', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'summary' => $adminPurchaseOrderSummarySchema,
                'lines' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/AdminPurchaseOrderLine'],
                ],
                'receipts' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/AdminPurchaseOrderReceipt'],
                ],
                'created_by' => ['type' => 'integer', 'nullable' => true],
                'updated_by' => ['type' => 'integer', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $adminVoucherSchema = [
            'type' => 'object',
            'required' => ['voucher_id', 'code', 'discount_type', 'discount_value', 'is_active', 'row_version'],
            'properties' => [
                'voucher_id' => ['type' => 'integer'],
                'code' => ['type' => 'string'],
                'description' => ['type' => 'string', 'nullable' => true],
                'discount_type' => ['type' => 'string'],
                'discount_value' => ['type' => 'number', 'nullable' => true],
                'free_item_id' => ['type' => 'integer', 'nullable' => true],
                'free_item_qty' => ['type' => 'integer', 'nullable' => true],
                'max_usage' => ['type' => 'integer', 'nullable' => true],
                'max_usage_per_user' => ['type' => 'integer', 'nullable' => true],
                'min_spend' => ['type' => 'number', 'nullable' => true],
                'start_date' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'expiry_date' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'is_active' => ['type' => 'boolean'],
                'row_version' => ['type' => 'integer'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => true,
        ];

        $adminLoyaltyTierSchema = [
            'type' => 'object',
            'required' => ['tier_id', 'tier_code', 'tier_name', 'min_points', 'is_active', 'row_version'],
            'properties' => [
                'tier_id' => ['type' => 'integer'],
                'tier_code' => ['type' => 'string'],
                'tier_name' => ['type' => 'string'],
                'min_points' => ['type' => 'integer'],
                'benefits_json' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true], 'nullable' => true],
                'is_active' => ['type' => 'boolean'],
                'row_version' => ['type' => 'integer'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => true,
        ];

        $adminBenefitSettingSchema = [
            'type' => 'object',
            'required' => ['setting_key', 'value'],
            'properties' => [
                'setting_key' => ['type' => 'string'],
                'value' => ['type' => 'string'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => true,
        ];

        $adminPrivacyRequestSchema = [
            'type' => 'object',
            'required' => ['request_id', 'user_id', 'request_type', 'status'],
            'properties' => [
                'request_id' => ['type' => 'integer'],
                'privacy_request_id' => ['type' => 'integer'],
                'customer_privacy_request_id' => ['type' => 'integer'],
                'user_id' => ['type' => 'integer', 'nullable' => true],
                'customer_user_id' => ['type' => 'integer', 'nullable' => true],
                'request_type' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'decision' => ['type' => 'string', 'nullable' => true],
                'requested_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'reviewed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'processed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'resolution_notes' => ['type' => 'string', 'nullable' => true],
                'result_summary' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => true,
        ];

        $financialReservationSummarySchema = [
            'type' => 'object',
            'required' => ['reservation_id', 'reservation_code', 'status', 'deposit_status', 'customer'],
            'properties' => [
                'reservation_id' => ['type' => 'integer'],
                'reservation_code' => ['type' => 'string'],
                'row_version' => ['type' => 'integer', 'nullable' => true],
                'status' => ['type' => 'string'],
                'deposit_status' => ['type' => 'string'],
                'start_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'end_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'billed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'bill_currency' => ['type' => 'string', 'nullable' => true],
                'customer' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => true,
        ];

        $financialReconciliationRowSchema = [
            'type' => 'object',
            'required' => ['reservation', 'payment_summary', 'reconciliation', 'flags'],
            'properties' => [
                'reservation' => ['$ref' => '#/components/schemas/FinancialReservationSummary'],
                'payment_summary' => ['type' => 'object', 'additionalProperties' => true],
                'reconciliation' => ['type' => 'object', 'additionalProperties' => true],
                'flags' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => true,
        ];

        $financeInvoicePayloadSchema = [
            'type' => 'object',
            'required' => ['invoice', 'reservation', 'reconciliation', 'method_breakdown'],
            'properties' => [
                'invoice' => [
                    'type' => 'object',
                    'required' => ['billing_invoice_id', 'reservation_id', 'invoice_number', 'invoice_status', 'currency', 'row_version'],
                    'properties' => [
                        'billing_invoice_id' => ['type' => 'integer'],
                        'reservation_id' => ['type' => 'integer'],
                        'invoice_number' => ['type' => 'string'],
                        'invoice_status' => ['type' => 'string'],
                        'currency' => ['type' => 'string'],
                        'bill_amounts' => ['type' => 'object', 'additionalProperties' => true],
                        'tax' => ['type' => 'object', 'additionalProperties' => true],
                        'seller' => ['type' => 'object', 'additionalProperties' => true],
                        'issued_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'issued_by' => ['type' => 'object', 'additionalProperties' => true],
                        'row_version' => ['type' => 'integer'],
                        'metadata' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'additionalProperties' => true,
                ],
                'reservation' => ['$ref' => '#/components/schemas/FinancialReservationSummary'],
                'reconciliation' => ['$ref' => '#/components/schemas/FinancialReconciliationRow'],
                'method_breakdown' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
            ],
            'additionalProperties' => true,
        ];

        $reportingSnapshotHealthSchema = [
            'type' => 'object',
            'required' => [
                'family',
                'row_count',
                'date_range',
                'latest_business_date',
                'latest_refreshed_at_utc',
                'latest_refresh_age_seconds',
                'scope_count',
                'healthy_scope_count',
                'stale_scope_count',
                'stale_scope_examples',
                'health_reference_refreshed_at_utc',
                'health_reference_refresh_age_seconds',
                'stale_threshold_seconds',
                'is_empty',
                'is_stale',
                'status',
                'reasons',
            ],
            'properties' => [
                'family' => ['type' => 'string'],
                'row_count' => ['type' => 'integer'],
                'date_range' => [
                    'type' => 'object',
                    'required' => ['start_date', 'end_date'],
                    'properties' => [
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'latest_business_date' => ['type' => 'string', 'nullable' => true],
                'latest_refreshed_at_utc' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'latest_refresh_age_seconds' => ['type' => 'integer', 'nullable' => true],
                'scope_count' => ['type' => 'integer'],
                'healthy_scope_count' => ['type' => 'integer'],
                'stale_scope_count' => ['type' => 'integer'],
                'stale_scope_examples' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                    ],
                ],
                'health_reference_refreshed_at_utc' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'health_reference_refresh_age_seconds' => ['type' => 'integer', 'nullable' => true],
                'stale_threshold_seconds' => ['type' => 'integer'],
                'is_empty' => ['type' => 'boolean'],
                'is_stale' => ['type' => 'boolean'],
                'status' => ['type' => 'string', 'enum' => ['ok', 'degraded']],
                'reasons' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'additionalProperties' => false,
        ];

        $reportingDailySalesSnapshotSchema = [
            'type' => 'object',
            'required' => ['snapshot_id', 'business_date', 'currency', 'branch_id', 'billed', 'invoices', 'payments', 'cashier', 'freshness'],
            'properties' => [
                'snapshot_id' => ['type' => 'integer'],
                'business_date' => ['type' => 'string', 'nullable' => true],
                'currency' => ['type' => 'string'],
                'branch_id' => ['type' => 'integer'],
                'branch' => $branchReferenceSchema,
                'billed' => [
                    'type' => 'object',
                    'required' => ['reservation_count', 'guest_count', 'gross_bill_amount', 'discount_amount', 'billed_total_amount'],
                    'properties' => [
                        'reservation_count' => ['type' => 'integer'],
                        'guest_count' => ['type' => 'integer'],
                        'gross_bill_amount' => ['type' => 'number'],
                        'discount_amount' => ['type' => 'number'],
                        'billed_total_amount' => ['type' => 'number'],
                    ],
                    'additionalProperties' => false,
                ],
                'invoices' => [
                    'type' => 'object',
                    'required' => ['issued_count', 'issued_total_amount', 'tax_amount'],
                    'properties' => [
                        'issued_count' => ['type' => 'integer'],
                        'issued_total_amount' => ['type' => 'number'],
                        'tax_amount' => ['type' => 'number'],
                    ],
                    'additionalProperties' => false,
                ],
                'payments' => [
                    'type' => 'object',
                    'required' => ['payment_row_count', 'refund_row_count', 'captured_amount', 'refunded_amount', 'net_paid_amount', 'deposit_net_amount', 'final_net_amount'],
                    'properties' => [
                        'payment_row_count' => ['type' => 'integer'],
                        'refund_row_count' => ['type' => 'integer'],
                        'captured_amount' => ['type' => 'number'],
                        'refunded_amount' => ['type' => 'number'],
                        'net_paid_amount' => ['type' => 'number'],
                        'deposit_net_amount' => ['type' => 'number'],
                        'final_net_amount' => ['type' => 'number'],
                    ],
                    'additionalProperties' => false,
                ],
                'cashier' => [
                    'type' => 'object',
                    'required' => ['closed_shift_count', 'cash_discrepancy_amount'],
                    'properties' => [
                        'closed_shift_count' => ['type' => 'integer'],
                        'cash_discrepancy_amount' => ['type' => 'number'],
                    ],
                    'additionalProperties' => false,
                ],
                'freshness' => [
                    'type' => 'object',
                    'required' => ['refreshed_at'],
                    'properties' => [
                        'refreshed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $reportingDailyOperationsSnapshotSchema = [
            'type' => 'object',
            'required' => ['snapshot_id', 'business_date', 'branch_id', 'reservations', 'turn_time', 'waiting_list', 'freshness'],
            'properties' => [
                'snapshot_id' => ['type' => 'integer'],
                'business_date' => ['type' => 'string', 'nullable' => true],
                'branch_id' => ['type' => 'integer'],
                'branch' => $branchReferenceSchema,
                'reservations' => [
                    'type' => 'object',
                    'required' => ['scheduled_count', 'scheduled_guest_count', 'scheduled_minutes_total', 'checked_in_count', 'completed_count', 'cancelled_count', 'no_show_count'],
                    'properties' => [
                        'scheduled_count' => ['type' => 'integer'],
                        'scheduled_guest_count' => ['type' => 'integer'],
                        'scheduled_minutes_total' => ['type' => 'integer'],
                        'checked_in_count' => ['type' => 'integer'],
                        'completed_count' => ['type' => 'integer'],
                        'cancelled_count' => ['type' => 'integer'],
                        'no_show_count' => ['type' => 'integer'],
                    ],
                    'additionalProperties' => false,
                ],
                'turn_time' => [
                    'type' => 'object',
                    'required' => ['turn_count', 'turn_minutes_total', 'avg_turn_minutes'],
                    'properties' => [
                        'turn_count' => ['type' => 'integer'],
                        'turn_minutes_total' => ['type' => 'integer'],
                        'avg_turn_minutes' => ['type' => 'number', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'waiting_list' => [
                    'type' => 'object',
                    'required' => ['created_count', 'notified_count', 'seated_count', 'cancelled_count', 'confirmed_arrival_count', 'seated_conversion_rate', 'arrival_confirmation_rate'],
                    'properties' => [
                        'created_count' => ['type' => 'integer'],
                        'notified_count' => ['type' => 'integer'],
                        'seated_count' => ['type' => 'integer'],
                        'cancelled_count' => ['type' => 'integer'],
                        'confirmed_arrival_count' => ['type' => 'integer'],
                        'seated_conversion_rate' => ['type' => 'number', 'nullable' => true],
                        'arrival_confirmation_rate' => ['type' => 'number', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'freshness' => [
                    'type' => 'object',
                    'required' => ['refreshed_at'],
                    'properties' => [
                        'refreshed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $reportingDailyInventorySnapshotSchema = [
            'type' => 'object',
            'required' => ['snapshot_id', 'business_date', 'branch_id', 'ingredient_id', 'unit_code', 'movement_summary', 'freshness'],
            'properties' => [
                'snapshot_id' => ['type' => 'integer'],
                'business_date' => ['type' => 'string', 'nullable' => true],
                'branch_id' => ['type' => 'integer'],
                'branch' => $branchReferenceSchema,
                'ingredient_id' => ['type' => 'integer'],
                'ingredient' => $ingredientReferenceSchema,
                'unit_code' => ['type' => 'string'],
                'movement_summary' => [
                    'type' => 'object',
                    'required' => ['movement_count', 'purchase_receipt_movement_count', 'stock_in_quantity', 'stock_out_quantity', 'adjustment_increase_quantity', 'adjustment_decrease_quantity', 'wastage_quantity', 'net_quantity_delta', 'last_movement_at'],
                    'properties' => [
                        'movement_count' => ['type' => 'integer'],
                        'purchase_receipt_movement_count' => ['type' => 'integer'],
                        'stock_in_quantity' => ['type' => 'number'],
                        'stock_out_quantity' => ['type' => 'number'],
                        'adjustment_increase_quantity' => ['type' => 'number'],
                        'adjustment_decrease_quantity' => ['type' => 'number'],
                        'wastage_quantity' => ['type' => 'number'],
                        'net_quantity_delta' => ['type' => 'number'],
                        'last_movement_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'freshness' => [
                    'type' => 'object',
                    'required' => ['refreshed_at'],
                    'properties' => [
                        'refreshed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffReportingCollectionMetaSchema = [
            'type' => 'object',
            'required' => ['filters', 'sort', 'pagination', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages', 'query_contract', 'action', 'snapshot_health'],
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'additionalProperties' => true,
                ],
                'sort' => $listingSortSchema,
                'pagination' => $waitingListPaginationSchema,
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
                'action' => ['type' => 'string'],
                'snapshot_health' => $reportingSnapshotHealthSchema,
            ],
            'additionalProperties' => false,
        ];

        $adminIngredientCollectionMetaSchema = [
            'type' => 'object',
            'required' => ['filters', 'sort', 'pagination', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages', 'query_contract'],
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'required' => ['is_active', 'q'],
                    'properties' => [
                        'is_active' => ['type' => 'boolean', 'nullable' => true],
                        'q' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'sort' => $listingSortSchema,
                'pagination' => $waitingListPaginationSchema,
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
            ],
            'additionalProperties' => false,
        ];

        $adminSupplierCollectionMetaSchema = [
            'type' => 'object',
            'required' => ['filters', 'sort', 'pagination', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages', 'query_contract'],
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'required' => ['is_active', 'q'],
                    'properties' => [
                        'is_active' => ['type' => 'boolean', 'nullable' => true],
                        'q' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'sort' => $listingSortSchema,
                'pagination' => $waitingListPaginationSchema,
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
            ],
            'additionalProperties' => false,
        ];

        $adminPurchaseOrderCollectionMetaSchema = [
            'type' => 'object',
            'required' => ['filters', 'sort', 'pagination', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages', 'query_contract'],
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'required' => ['supplier_id', 'branch_id', 'purchase_order_status', 'q'],
                    'properties' => [
                        'supplier_id' => ['type' => 'integer', 'nullable' => true],
                        'branch_id' => ['type' => 'integer', 'nullable' => true],
                        'purchase_order_status' => ['type' => 'string', 'nullable' => true],
                        'q' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'sort' => $listingSortSchema,
                'pagination' => $waitingListPaginationSchema,
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardFitSchema = [
            'type' => 'object',
            'required' => ['status', 'extra_seats', 'reason_code'],
            'properties' => [
                'status' => ['type' => 'string'],
                'extra_seats' => ['type' => 'integer'],
                'reason_code' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardAssignmentRequestContextSchema = [
            'type' => 'object',
            'required' => ['board_from', 'board_to', 'zone', 'include_slot_only_candidates'],
            'properties' => [
                'board_from' => ['type' => 'string', 'format' => 'date-time'],
                'board_to' => ['type' => 'string', 'format' => 'date-time'],
                'zone' => ['type' => 'string', 'nullable' => true],
                'include_slot_only_candidates' => ['type' => 'boolean'],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardCandidateTableSchema = [
            'type' => 'object',
            'required' => ['table_id', 'table_code', 'zone', 'board_state', 'rank', 'fit', 'score', 'reason_codes', 'policy_flags', 'assignment_window', 'assignment_request_context'],
            'properties' => [
                'table_id' => ['type' => 'integer'],
                'table_code' => ['type' => 'string'],
                'zone' => ['type' => 'string', 'nullable' => true],
                'board_state' => ['type' => 'string'],
                'rank' => ['type' => 'integer'],
                'fit' => ['$ref' => '#/components/schemas/StaffTableBoardFit'],
                'score' => ['type' => 'integer'],
                'reason_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'policy_flags' => [
                    'type' => 'object',
                    'required' => ['board_window_open', 'slot_only_candidate'],
                    'properties' => [
                        'board_window_open' => ['type' => 'boolean'],
                        'slot_only_candidate' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'assignment_window' => [
                    'type' => 'object',
                    'required' => ['availability_mode', 'reservation_window_start', 'reservation_window_end', 'board_window_start', 'board_window_end'],
                    'properties' => [
                        'availability_mode' => ['type' => 'string'],
                        'reservation_window_start' => ['type' => 'string', 'format' => 'date-time'],
                        'reservation_window_end' => ['type' => 'string', 'format' => 'date-time'],
                        'board_window_start' => ['type' => 'string', 'format' => 'date-time'],
                        'board_window_end' => ['type' => 'string', 'format' => 'date-time'],
                    ],
                    'additionalProperties' => false,
                ],
                'assignment_request_context' => ['$ref' => '#/components/schemas/StaffTableBoardAssignmentRequestContext'],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardReservationUserSchema = [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => 'integer', 'nullable' => true],
                'full_name' => ['type' => 'string', 'nullable' => true],
                'email' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardReservationDepositSchema = [
            'type' => 'object',
            'required' => ['status', 'required_amount', 'paid_amount', 'outstanding_amount', 'currency', 'self_service'],
            'properties' => [
                'status' => ['type' => 'string'],
                'required_amount' => ['type' => 'string'],
                'paid_amount' => ['type' => 'string'],
                'outstanding_amount' => ['type' => 'string'],
                'currency' => ['type' => 'string'],
                'self_service' => ['type' => 'object', 'additionalProperties' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardAssignedReservationSchema = [
            'type' => 'object',
            'required' => ['reservation_id', 'reservation_code', 'status', 'row_version', 'table_ids', 'start_time', 'end_time', 'guest_count', 'checked_in_at', 'user', 'guest', 'deposit', 'flags'],
            'properties' => [
                'reservation_id' => ['type' => 'integer'],
                'reservation_code' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'row_version' => ['type' => 'integer'],
                'table_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'start_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'end_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'guest_count' => ['type' => 'integer'],
                'checked_in_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'user' => ['$ref' => '#/components/schemas/StaffTableBoardReservationUser', 'nullable' => true],
                'guest' => ['$ref' => '#/components/schemas/ReservationGuestSnapshot', 'nullable' => true],
                'deposit' => ['$ref' => '#/components/schemas/StaffTableBoardReservationDeposit'],
                'flags' => [
                    'type' => 'object',
                    'required' => ['deposit_self_service_follow_up'],
                    'properties' => [
                        'deposit_self_service_follow_up' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardHoldSchema = [
            'type' => 'object',
            'required' => ['hold_id', 'hold_status', 'row_version', 'start_time', 'end_time', 'expire_at'],
            'properties' => [
                'hold_id' => ['type' => 'string'],
                'hold_status' => ['type' => 'string'],
                'row_version' => ['type' => 'integer'],
                'start_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'end_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'expire_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardCheckInActionSchema = [
            'type' => 'object',
            'required' => ['available', 'blocked_reason_code', 'method', 'endpoint', 'required_payload', 'preferred_payload', 'checks'],
            'properties' => [
                'available' => ['type' => 'boolean'],
                'blocked_reason_code' => ['type' => 'string', 'nullable' => true],
                'method' => ['type' => 'string'],
                'endpoint' => ['type' => 'string'],
                'required_payload' => ['type' => 'array', 'items' => ['type' => 'string']],
                'preferred_payload' => [
                    'type' => 'object',
                    'required' => ['row_version', 'table_ids'],
                    'properties' => [
                        'row_version' => ['type' => 'integer'],
                        'table_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    ],
                    'additionalProperties' => false,
                ],
                'checks' => [
                    'type' => 'object',
                    'additionalProperties' => ['type' => 'boolean'],
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardMoveTableActionSchema = [
            'type' => 'object',
            'required' => ['available', 'method', 'endpoint', 'required_payload', 'preferred_payload'],
            'properties' => [
                'available' => ['type' => 'boolean'],
                'method' => ['type' => 'string'],
                'endpoint' => ['type' => 'string'],
                'required_payload' => ['type' => 'array', 'items' => ['type' => 'string']],
                'preferred_payload' => [
                    'type' => 'object',
                    'required' => ['from_table_id', 'row_version'],
                    'properties' => [
                        'from_table_id' => ['type' => 'integer'],
                        'row_version' => ['type' => 'integer'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardActiveOrderSchema = [
            'type' => 'object',
            'required' => ['order_id', 'status', 'order_type', 'row_version'],
            'properties' => [
                'order_id' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
                'order_type' => ['type' => 'string'],
                'row_version' => ['type' => 'integer'],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardRowSchema = [
            'type' => 'object',
            'required' => ['table_id', 'table_code', 'zone', 'pos_x', 'pos_y', 'row_version', 'realtime_status', 'board_state', 'reservations', 'holds', 'reservation', 'hold', 'capacity', 'availability', 'operational_hints', 'actions', 'candidate_reservations', 'current_fit', 'active_order'],
            'properties' => [
                'table_id' => ['type' => 'integer'],
                'table_code' => ['type' => 'string'],
                'zone' => ['type' => 'string', 'nullable' => true],
                'pos_x' => ['type' => 'integer', 'nullable' => true],
                'pos_y' => ['type' => 'integer', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'realtime_status' => ['type' => 'string'],
                'board_state' => ['type' => 'string'],
                'reservations' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffTableBoardAssignedReservation']],
                'holds' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffTableBoardHold']],
                'reservation' => ['$ref' => '#/components/schemas/StaffTableBoardAssignedReservation', 'nullable' => true],
                'hold' => ['$ref' => '#/components/schemas/StaffTableBoardHold', 'nullable' => true],
                'capacity' => [
                    'type' => 'object',
                    'required' => ['template_id', 'seats'],
                    'properties' => [
                        'template_id' => ['type' => 'integer', 'nullable' => true],
                        'seats' => ['type' => 'integer', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'availability' => [
                    'type' => 'object',
                    'required' => ['accepts_new_assignment', 'is_operationally_blocked', 'is_realtime_occupied', 'has_reservation_in_range', 'has_hold_in_range', 'requires_deposit_follow_up'],
                    'properties' => [
                        'accepts_new_assignment' => ['type' => 'boolean'],
                        'is_operationally_blocked' => ['type' => 'boolean'],
                        'is_realtime_occupied' => ['type' => 'boolean'],
                        'has_reservation_in_range' => ['type' => 'boolean'],
                        'has_hold_in_range' => ['type' => 'boolean'],
                        'requires_deposit_follow_up' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'operational_hints' => [
                    'type' => 'object',
                    'required' => ['assignment_candidate', 'preferred_action'],
                    'properties' => [
                        'assignment_candidate' => ['type' => 'boolean'],
                        'preferred_action' => ['type' => 'string'],
                    ],
                    'additionalProperties' => false,
                ],
                'actions' => [
                    'type' => 'object',
                    'required' => ['check_in', 'move_table'],
                    'properties' => [
                        'check_in' => ['$ref' => '#/components/schemas/StaffTableBoardCheckInAction', 'nullable' => true],
                        'move_table' => ['$ref' => '#/components/schemas/StaffTableBoardMoveTableAction', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'candidate_reservations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['reservation_id', 'reservation_code', 'row_version', 'guest_count', 'user', 'guest', 'flags', 'policy_flags', 'deposit'],
                        'properties' => [
                            'reservation_id' => ['type' => 'integer'],
                            'reservation_code' => ['type' => 'string'],
                            'row_version' => ['type' => 'integer'],
                            'guest_count' => ['type' => 'integer'],
                            'user' => ['$ref' => '#/components/schemas/StaffTableBoardReservationUser', 'nullable' => true],
                            'guest' => ['$ref' => '#/components/schemas/ReservationGuestSnapshot', 'nullable' => true],
                            'flags' => [
                                'type' => 'object',
                                'required' => ['due_soon', 'late', 'overdue'],
                                'properties' => [
                                    'due_soon' => ['type' => 'boolean'],
                                    'late' => ['type' => 'boolean'],
                                    'overdue' => ['type' => 'boolean'],
                                ],
                                'additionalProperties' => false,
                            ],
                            'policy_flags' => [
                                'type' => 'object',
                                'additionalProperties' => ['type' => 'boolean'],
                            ],
                            'deposit' => [
                                'type' => 'object',
                                'required' => ['self_service'],
                                'properties' => [
                                    'self_service' => ['type' => 'object', 'additionalProperties' => true],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'current_fit' => ['$ref' => '#/components/schemas/StaffTableBoardFit', 'nullable' => true],
                'active_order' => ['$ref' => '#/components/schemas/StaffTableBoardActiveOrder', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardZoneSummarySchema = [
            'type' => 'object',
            'required' => ['zone', 'summary'],
            'properties' => [
                'zone' => ['type' => 'string'],
                'summary' => [
                    'type' => 'object',
                    'required' => ['table_count', 'available_count', 'occupied_now_count', 'reserved_in_range_count', 'held_in_range_count'],
                    'properties' => [
                        'table_count' => ['type' => 'integer'],
                        'available_count' => ['type' => 'integer'],
                        'occupied_now_count' => ['type' => 'integer'],
                        'reserved_in_range_count' => ['type' => 'integer'],
                        'held_in_range_count' => ['type' => 'integer'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardUnassignedReservationSchema = [
            'type' => 'object',
            'required' => ['reservation_id', 'reservation_code', 'status', 'row_version', 'guest_count', 'start_time', 'end_time', 'user', 'guest', 'flags', 'deposit', 'orchestration'],
            'properties' => [
                'reservation_id' => ['type' => 'integer'],
                'reservation_code' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'row_version' => ['type' => 'integer'],
                'guest_count' => ['type' => 'integer'],
                'start_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'end_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'user' => ['$ref' => '#/components/schemas/StaffTableBoardReservationUser', 'nullable' => true],
                'guest' => ['$ref' => '#/components/schemas/ReservationGuestSnapshot', 'nullable' => true],
                'flags' => [
                    'type' => 'object',
                    'required' => ['due_soon', 'late', 'overdue', 'deposit_self_service_follow_up'],
                    'properties' => [
                        'due_soon' => ['type' => 'boolean'],
                        'late' => ['type' => 'boolean'],
                        'overdue' => ['type' => 'boolean'],
                        'deposit_self_service_follow_up' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'deposit' => ['$ref' => '#/components/schemas/StaffTableBoardReservationDeposit'],
                'orchestration' => [
                    'type' => 'object',
                    'required' => ['candidate_table_count', 'candidate_tables', 'best_fit_table', 'assignment_request_context'],
                    'properties' => [
                        'candidate_table_count' => ['type' => 'integer'],
                        'candidate_tables' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffTableBoardCandidateTable']],
                        'best_fit_table' => ['$ref' => '#/components/schemas/StaffTableBoardCandidateTable', 'nullable' => true],
                        'assignment_request_context' => ['$ref' => '#/components/schemas/StaffTableBoardAssignmentRequestContext'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffTableBoardMetaSchema = [
            'type' => 'object',
            'required' => ['filters', 'sort', 'pagination', 'query_contract', 'action', 'supported_actions', 'realtime'],
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'required' => ['from', 'to', 'zone', 'include_holds', 'group_by'],
                    'properties' => [
                        'from' => ['type' => 'string', 'format' => 'date-time'],
                        'to' => ['type' => 'string', 'format' => 'date-time'],
                        'zone' => ['type' => 'string', 'nullable' => true],
                        'include_holds' => ['type' => 'boolean'],
                        'group_by' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'sort' => $listingSortSchema,
                'pagination' => $tableBoardPaginationSchema,
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
                'action' => ['type' => 'string'],
                'supported_actions' => ['type' => 'object', 'additionalProperties' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']]],
                'realtime' => ['$ref' => '#/components/schemas/StaffOperationalRealtimeDescriptor'],
            ],
            'additionalProperties' => false,
        ];

        $staffOrderReadCustomerSchema = [
            'type' => 'object',
            'required' => ['user_id', 'full_name', 'email', 'phone', 'current_points', 'current_tier'],
            'properties' => [
                'user_id' => ['type' => 'integer', 'nullable' => true],
                'full_name' => ['type' => 'string', 'nullable' => true],
                'email' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
                'current_points' => ['type' => 'integer', 'nullable' => true],
                'current_tier' => ['$ref' => '#/components/schemas/CustomerLoyaltyTier', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffOrderReadItemMenuItemSchema = [
            'type' => 'object',
            'required' => ['name', 'code'],
            'properties' => [
                'name' => ['type' => 'string', 'nullable' => true],
                'code' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffOrderReadPayloadSchema = [
            'type' => 'object',
            'required' => ['order', 'table', 'tables', 'reservation', 'customer', 'items', 'item_summary', 'financial_summary'],
            'properties' => [
                'order' => ['$ref' => '#/components/schemas/ReservationOrder'],
                'table' => ['$ref' => '#/components/schemas/RestaurantTable', 'nullable' => true],
                'tables' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/RestaurantTable']],
                'reservation' => ['$ref' => '#/components/schemas/ReservationSummary', 'nullable' => true],
                'customer' => ['$ref' => '#/components/schemas/StaffOrderReadCustomer', 'nullable' => true],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['order_item_id', 'item_id', 'quantity', 'status', 'row_version', 'item_name_snapshot', 'unit_price', 'currency', 'line_total', 'notes', 'item'],
                        'properties' => [
                            'order_item_id' => ['type' => 'integer'],
                            'item_id' => ['type' => 'integer'],
                            'quantity' => ['type' => 'integer'],
                            'status' => ['type' => 'string'],
                            'row_version' => ['type' => 'integer', 'nullable' => true],
                            'item_name_snapshot' => ['type' => 'string', 'nullable' => true],
                            'unit_price' => ['type' => 'string'],
                            'currency' => ['type' => 'string'],
                            'line_total' => ['type' => 'string'],
                            'notes' => ['type' => 'string', 'nullable' => true],
                            'item' => ['$ref' => '#/components/schemas/StaffOrderReadItemMenuItem', 'nullable' => true],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'item_summary' => [
                    'type' => 'object',
                    'required' => ['line_count', 'quantity_total', 'active_quantity', 'cancelled_quantity', 'status_counts', 'status_quantities'],
                    'properties' => [
                        'line_count' => ['type' => 'integer'],
                        'quantity_total' => ['type' => 'integer'],
                        'active_quantity' => ['type' => 'integer'],
                        'cancelled_quantity' => ['type' => 'integer'],
                        'status_counts' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
                        'status_quantities' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']],
                    ],
                    'additionalProperties' => false,
                ],
                'financial_summary' => [
                    'type' => 'object',
                    'required' => ['settlement_scope', 'subtotal', 'discount', 'total_due', 'paid', 'deposit_applied', 'deposit_net', 'final_paid', 'outstanding', 'currency', 'payment_status', 'reservation_payment_summary'],
                    'properties' => [
                        'settlement_scope' => ['type' => 'string'],
                        'subtotal' => ['type' => 'string', 'nullable' => true],
                        'discount' => ['type' => 'string', 'nullable' => true],
                        'total_due' => ['type' => 'string', 'nullable' => true],
                        'paid' => ['type' => 'string', 'nullable' => true],
                        'deposit_applied' => ['type' => 'string', 'nullable' => true],
                        'deposit_net' => ['type' => 'string', 'nullable' => true],
                        'final_paid' => ['type' => 'string', 'nullable' => true],
                        'outstanding' => ['type' => 'string', 'nullable' => true],
                        'currency' => ['type' => 'string', 'nullable' => true],
                        'payment_status' => ['type' => 'string', 'nullable' => true],
                        'reservation_payment_summary' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'additionalProperties' => false,
        ];

        $staffReservationLookupUserSchema = [
            'type' => 'object',
            'required' => ['user_id', 'full_name', 'email', 'phone'],
            'properties' => [
                'user_id' => ['type' => 'integer', 'nullable' => true],
                'full_name' => ['type' => 'string', 'nullable' => true],
                'email' => ['type' => 'string', 'nullable' => true],
                'phone' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffReservationLookupTableSchema = [
            'type' => 'object',
            'required' => ['table_id', 'table_code', 'zone', 'status', 'seats'],
            'properties' => [
                'table_id' => ['type' => 'integer'],
                'table_code' => ['type' => 'string'],
                'zone' => ['type' => 'string', 'nullable' => true],
                'status' => ['type' => 'string', 'nullable' => true],
                'seats' => ['type' => 'integer', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffReservationLookupEntrySchema = [
            'type' => 'object',
            'required' => [
                'reservation_id',
                'reservation_code',
                'status',
                'source',
                'guest_count',
                'start_time',
                'end_time',
                'checked_in_at',
                'checked_out_at',
                'cancelled_at',
                'cancel_reason',
                'no_show_at',
                'notes',
                'row_version',
                'created_at',
                'updated_at',
                'user',
                'guest',
                'table_ids',
                'tables',
                'summary',
                'deposit_self_service',
                'financials',
            ],
            'properties' => [
                'reservation_id' => ['type' => 'integer'],
                'reservation_code' => ['type' => 'string'],
                'status' => ['type' => 'string'],
                'source' => ['type' => 'string', 'nullable' => true],
                'guest_count' => ['type' => 'integer'],
                'start_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'end_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'checked_in_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'checked_out_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'cancelled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'cancel_reason' => ['type' => 'string', 'nullable' => true],
                'no_show_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'notes' => ['type' => 'string', 'nullable' => true],
                'row_version' => ['type' => 'integer'],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'user' => ['$ref' => '#/components/schemas/StaffReservationLookupUser', 'nullable' => true],
                'guest' => ['$ref' => '#/components/schemas/ReservationGuestSnapshot', 'nullable' => true],
                'table_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'tables' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffReservationLookupTable']],
                'summary' => [
                    'type' => 'object',
                    'required' => [
                        'table_count',
                        'is_active',
                        'is_checked_in',
                        'is_cancelled',
                        'is_completed',
                        'deposit_acknowledged',
                        'deposit_intent_submitted',
                        'deposit_self_service_follow_up',
                    ],
                    'properties' => [
                        'table_count' => ['type' => 'integer'],
                        'is_active' => ['type' => 'boolean'],
                        'is_checked_in' => ['type' => 'boolean'],
                        'is_cancelled' => ['type' => 'boolean'],
                        'is_completed' => ['type' => 'boolean'],
                        'deposit_acknowledged' => ['type' => 'boolean'],
                        'deposit_intent_submitted' => ['type' => 'boolean'],
                        'deposit_self_service_follow_up' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'deposit_self_service' => ['type' => 'object', 'additionalProperties' => true],
                'financials' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                'deposit_status' => ['type' => 'string', 'nullable' => true],
            ],
            'additionalProperties' => false,
        ];

        $staffReservationLookupCollectionMetaSchema = [
            'type' => 'object',
            'required' => ['filters', 'sort', 'pagination', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages', 'query_contract'],
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'required' => ['bucket', 'status', 'reservation_code', 'source', 'q', 'phone', 'deposit_acknowledged', 'deposit_intent_status', 'user_id', 'table_id', 'start_from', 'start_to', 'include_financials'],
                    'properties' => [
                        'bucket' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'nullable' => true],
                        'reservation_code' => ['type' => 'string', 'nullable' => true],
                        'source' => ['type' => 'string', 'nullable' => true],
                        'q' => ['type' => 'string', 'nullable' => true],
                        'phone' => ['type' => 'string', 'nullable' => true],
                        'deposit_acknowledged' => ['type' => 'boolean', 'nullable' => true],
                        'deposit_intent_status' => ['type' => 'string', 'nullable' => true],
                        'user_id' => ['type' => 'integer', 'nullable' => true],
                        'table_id' => ['type' => 'integer', 'nullable' => true],
                        'start_from' => ['type' => 'string', 'nullable' => true],
                        'start_to' => ['type' => 'string', 'nullable' => true],
                        'include_financials' => ['type' => 'boolean'],
                    ],
                    'additionalProperties' => false,
                ],
                'sort' => $listingSortSchema,
                'pagination' => $waitingListPaginationSchema,
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
            ],
            'additionalProperties' => false,
        ];

        $staffReservationOrderCollectionMetaSchema = [
            'type' => 'object',
            'required' => ['action', 'reservation_id', 'count', 'sort', 'pagination', 'query_contract'],
            'properties' => [
                'action' => ['type' => 'string'],
                'reservation_id' => ['type' => 'integer'],
                'count' => ['type' => 'integer'],
                'sort' => $listingSortSchema,
                'pagination' => $tableBoardPaginationSchema,
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
            ],
            'additionalProperties' => false,
        ];

        $cashierShiftCollectionMetaSchema = [
            'type' => 'object',
            'required' => ['filters', 'sort', 'pagination', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages', 'query_contract', 'action', 'count', 'scope'],
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'required' => ['status', 'branch_id', 'shift_code', 'terminal_code', 'q'],
                    'properties' => [
                        'status' => ['type' => 'string', 'nullable' => true],
                        'branch_id' => ['type' => 'integer', 'nullable' => true],
                        'shift_code' => ['type' => 'string', 'nullable' => true],
                        'terminal_code' => ['type' => 'string', 'nullable' => true],
                        'q' => ['type' => 'string', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'sort' => $listingSortSchema,
                'pagination' => $waitingListPaginationSchema,
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
                'action' => ['type' => 'string'],
                'count' => ['type' => 'integer'],
                'scope' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $staffWaitingListCollectionMetaSchema = [
            'type' => 'object',
            'required' => ['filters', 'sort', 'pagination', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages', 'query_contract', 'summary', 'realtime'],
            'properties' => [
                'filters' => [
                    'type' => 'object',
                    'required' => ['status', 'active_only', 'phone', 'guest_name', 'branch_id'],
                    'properties' => [
                        'status' => ['type' => 'string', 'nullable' => true],
                        'active_only' => ['type' => 'boolean'],
                        'phone' => ['type' => 'string', 'nullable' => true],
                        'guest_name' => ['type' => 'string', 'nullable' => true],
                        'branch_id' => ['type' => 'integer', 'nullable' => true],
                    ],
                    'additionalProperties' => false,
                ],
                'sort' => $listingSortSchema,
                'pagination' => $waitingListPaginationSchema,
                'current_page' => ['type' => 'integer'],
                'per_page' => ['type' => 'integer'],
                'from' => ['type' => 'integer', 'nullable' => true],
                'to' => ['type' => 'integer', 'nullable' => true],
                'total' => ['type' => 'integer'],
                'last_page' => ['type' => 'integer'],
                'has_more_pages' => ['type' => 'boolean'],
                'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
                'summary' => [
                    'type' => 'object',
                    'required' => ['mode', 'ready_to_seat_count', 'advance_queue_ready_count', 'advance_queue_blocked_count', 'awaiting_customer_follow_up_count', 'hold_investigation_count'],
                    'properties' => [
                        'mode' => ['type' => 'string'],
                        'ready_to_seat_count' => ['type' => 'integer'],
                        'advance_queue_ready_count' => ['type' => 'integer'],
                        'advance_queue_blocked_count' => ['type' => 'integer'],
                        'awaiting_customer_follow_up_count' => ['type' => 'integer'],
                        'hold_investigation_count' => ['type' => 'integer'],
                    ],
                    'additionalProperties' => false,
                ],
                'realtime' => ['$ref' => '#/components/schemas/StaffOperationalRealtimeDescriptor'],
            ],
            'additionalProperties' => false,
        ];

        return [
            'ApiError' => $genericError,
            'ValidationError' => array_replace_recursive($genericError, ['example' => [
                'error_code' => 'validation_error',
                'category_code' => 'validation_error',
                'message' => 'Validation error.',
                'request_id' => 'req-123',
                'errors' => ['identifier' => ['Invalid credentials.']],
            ]]),
            'UnauthorizedError' => array_replace_recursive($genericError, ['example' => [
                'error_code' => 'unauthorized',
                'category_code' => 'authentication_required',
                'message' => 'Authentication is required.',
                'request_id' => 'req-123',
            ]]),
            'ForbiddenError' => array_replace_recursive($genericError, ['example' => [
                'error_code' => 'forbidden',
                'category_code' => 'policy_denied',
                'message' => 'Access to this API operation is denied by policy.',
                'request_id' => 'req-123',
            ]]),
            'NotFoundError' => array_replace_recursive($genericError, ['example' => [
                'error_code' => 'not_found',
                'category_code' => 'not_found',
                'message' => 'Reservation not found.',
                'request_id' => 'req-123',
            ]]),
            'ConflictError' => array_replace_recursive($genericError, ['example' => [
                'error_code' => 'conflict',
                'category_code' => 'resource_conflict',
                'message' => 'State conflict detected.',
                'request_id' => 'req-123',
                'conflict_type' => 'state_conflict',
                'state_reason' => 'constraint_violation',
                'next_actions' => ['reload_resource', 'retry_with_current_state'],
            ]]),
            'StaleRowVersionError' => array_replace_recursive($genericError, ['example' => [
                'error_code' => 'stale_row_version',
                'category_code' => 'stale_write',
                'message' => 'The resource was modified by another writer. Reload data and try again.',
                'request_id' => 'req-123',
                'conflict_type' => 'stale_write',
                'state_reason' => 'row_version_mismatch',
                'next_actions' => ['reload_resource', 'retry_with_latest_row_version'],
                'errors' => [
                    'row_version' => ['The resource was modified by another writer.'],
                ],
            ]]),
            'CustomerMenuItemPrice' => $customerMenuItemPriceSchema,
            'CustomerMenuItemPreorderPolicy' => $customerMenuItemPreorderSchema,
            'CustomerMenuItemComboComponent' => $customerMenuItemComboComponentSchema,
            'CustomerMenuItem' => $customerMenuItemSchema,
            'CustomerMenuItemsMeta' => $customerMenuItemsMetaSchema,
            'CustomerLoyaltyTier' => $customerLoyaltyTierSchema,
            'CustomerLoyaltyUserSummary' => $customerLoyaltyUserSummarySchema,
            'LoyaltyPointTransaction' => $loyaltyPointTransactionSchema,
            'CustomerLoyaltySummary' => $customerLoyaltySummarySchema,
            'CustomerVoucherFreeItem' => $customerVoucherFreeItemSchema,
            'CustomerVoucher' => $customerVoucherSchema,
            'CustomerReservationBenefitsReservation' => $customerReservationBenefitsReservationSchema,
            'CustomerReservationBenefitsPreview' => $customerReservationBenefitsPreviewSchema,
            'CustomerReservationPreorderNormalizedItem' => $customerReservationPreorderNormalizedItemSchema,
            'CustomerReservationPreorderTotals' => $customerReservationPreorderTotalsSchema,
            'CustomerReservationPreorderLine' => $customerReservationPreorderLineSchema,
            'CustomerReservationPreorderSnapshot' => $customerReservationPreorderSnapshotSchema,
            'CustomerReservationPreorderManagementPolicy' => $customerReservationPreorderManagementPolicySchema,
            'CustomerReservationPreorderPayload' => $customerReservationPreorderPayloadSchema,
            'ReservationCustomerSummary' => $reservationCustomerSummarySchema,
            'ReservationGuestSnapshot' => $reservationGuestSnapshotSchema,
            'ReservationSummary' => $reservationSummary,
            'CustomerWaitingListEntry' => $customerWaitingListEntry,
            'CustomerDepositPaymentSession' => $depositPaymentSession,
            'CustomerBillPaymentSession' => $billPaymentSession,
            'Branch' => $branchSchema,
            'RestaurantProfile' => $restaurantProfileSchema,
            'AdminIngredient' => $adminIngredientSchema,
            'AdminSupplier' => $adminSupplierSchema,
            'AdminPurchaseOrder' => $adminPurchaseOrderSchema,
            'AdminIngredientMovement' => $adminIngredientMovementSchema,
            'AdminPurchaseOrderLine' => $adminPurchaseOrderLineSchema,
            'AdminPurchaseOrderReceiptLine' => $adminPurchaseOrderReceiptLineSchema,
            'AdminPurchaseOrderReceipt' => $adminPurchaseOrderReceiptSchema,
            'AdminVoucher' => $adminVoucherSchema,
            'AdminLoyaltyTier' => $adminLoyaltyTierSchema,
            'AdminBenefitSetting' => $adminBenefitSettingSchema,
            'AdminPrivacyRequest' => $adminPrivacyRequestSchema,
            'FinancialReservationSummary' => $financialReservationSummarySchema,
            'FinancialReconciliationRow' => $financialReconciliationRowSchema,
            'ReportingSnapshotHealth' => $reportingSnapshotHealthSchema,
            'ReportingDailySalesSnapshot' => $reportingDailySalesSnapshotSchema,
            'ReportingDailyOperationSnapshot' => $reportingDailyOperationsSnapshotSchema,
            'ReportingDailyInventoryMovementSnapshot' => $reportingDailyInventorySnapshotSchema,
            'CashierShift' => $cashierShiftSchema,
            'ReservationOrder' => $reservationOrderSchema,
            'StaffReservationLookupUser' => $staffReservationLookupUserSchema,
            'StaffReservationLookupTable' => $staffReservationLookupTableSchema,
            'StaffReservationLookupEntry' => $staffReservationLookupEntrySchema,
            'StaffReservationLookupCollectionMeta' => $staffReservationLookupCollectionMetaSchema,
            'StaffReservationOrderCollectionMeta' => $staffReservationOrderCollectionMetaSchema,
            'CashierShiftCollectionMeta' => $cashierShiftCollectionMetaSchema,
            'StaffReportingCollectionMeta' => $staffReportingCollectionMetaSchema,
            'AdminIngredientCollectionMeta' => $adminIngredientCollectionMetaSchema,
            'AdminSupplierCollectionMeta' => $adminSupplierCollectionMetaSchema,
            'AdminPurchaseOrderCollectionMeta' => $adminPurchaseOrderCollectionMetaSchema,
            'RestaurantTable' => $restaurantTableSchema,
            'RestaurantTableEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/RestaurantTable',
            ]),
            'StaffAuthUser' => $staffAuthUserSchema,
            'ApiUserCustomerProfile' => $apiUserCustomerProfileSchema,
            'ApiUserStaffProfile' => $apiUserStaffProfileSchema,
            'ApiUserEnvelope' => $apiUserEnvelopeSchema,
            'StaffStartupBranch' => $staffStartupBranchSchema,
            'StaffStartupCashierShift' => $staffStartupCashierShiftSchema,
            'StaffBranchAccessContext' => $staffBranchAccessContextSchema,
            'StaffNavigationItem' => $staffNavigationItemSchema,
            'StaffNavigationContext' => $staffNavigationContextSchema,
            'StaffStartupReadiness' => $staffStartupReadinessSchema,
            'StaffStartupContext' => $staffStartupContextSchema,
            'StaffOperationalRealtimeDescriptor' => $staffOperationalRealtimeDescriptorSchema,
            'StaffOperationalRealtimeEvent' => $staffOperationalRealtimeEventSchema,
            'StaffOperationalRealtimeState' => $staffOperationalRealtimeStateSchema,
            'ListingQueryContract' => $listingQueryContractSchema,
            'StaffTableBoardFit' => $staffTableBoardFitSchema,
            'StaffTableBoardAssignmentRequestContext' => $staffTableBoardAssignmentRequestContextSchema,
            'StaffTableBoardCandidateTable' => $staffTableBoardCandidateTableSchema,
            'StaffTableBoardReservationUser' => $staffTableBoardReservationUserSchema,
            'StaffTableBoardReservationDeposit' => $staffTableBoardReservationDepositSchema,
            'StaffTableBoardAssignedReservation' => $staffTableBoardAssignedReservationSchema,
            'StaffTableBoardHold' => $staffTableBoardHoldSchema,
            'StaffTableBoardCheckInAction' => $staffTableBoardCheckInActionSchema,
            'StaffTableBoardMoveTableAction' => $staffTableBoardMoveTableActionSchema,
            'StaffTableBoardActiveOrder' => $staffTableBoardActiveOrderSchema,
            'StaffTableBoardRow' => $staffTableBoardRowSchema,
            'StaffTableBoardZoneSummary' => $staffTableBoardZoneSummarySchema,
            'StaffTableBoardUnassignedReservation' => $staffTableBoardUnassignedReservationSchema,
            'StaffTableBoardMeta' => $staffTableBoardMetaSchema,
            'StaffOrderReadCustomer' => $staffOrderReadCustomerSchema,
            'StaffOrderReadItemMenuItem' => $staffOrderReadItemMenuItemSchema,
            'StaffOrderReadPayload' => $staffOrderReadPayloadSchema,
            'StaffWaitingListEntry' => $staffWaitingListEntry,
            'StaffWaitingListCollectionMeta' => $staffWaitingListCollectionMetaSchema,
            'StaffCheckoutSettlement' => $staffCheckoutSettlementSchema,
            'StaffAuditTrailPrimarySubject' => $staffAuditTrailPrimarySubjectSchema,
            'StaffAuditTrailSubject' => $staffAuditTrailSubjectSchema,
            'StaffAuditTrailActorUser' => $staffAuditTrailActorUserSchema,
            'StaffAuditTrailActor' => $staffAuditTrailActorSchema,
            'StaffAuditTrailRequest' => $staffAuditTrailRequestSchema,
            'StaffAuditTrailEntry' => $staffAuditTrailEntrySchema,
            'StaffAuditTrailCollectionMeta' => $staffAuditTrailCollectionMetaSchema,
            'StaffConversationAssignment' => $staffConversationAssignmentSchema,
            'StaffConversationAnalysis' => $staffConversationAnalysisSchema,
            'StaffConversationAiAssistAction' => $staffConversationAiAssistActionSchema,
            'StaffConversationAiAssistRiskFlag' => $staffConversationAiAssistRiskSchema,
            'StaffConversationAiAssist' => $staffConversationAiAssistSchema,
            'StaffConversationMessage' => $staffConversationMessageSchema,
            'StaffConversationEvent' => $staffConversationEventSchema,
            'StaffConversationSummary' => $staffConversationSummarySchema,
            'CustomerAuthSessionEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'auth_mode' => ['type' => 'string', 'enum' => ['customer_access_session']],
                    'token_type' => ['type' => 'string', 'enum' => ['opaque']],
                    'auth_header' => ['type' => 'string'],
                    'access_token' => ['type' => 'string', 'nullable' => true],
                    'access_session_id' => ['type' => 'integer'],
                    'session_id' => ['type' => 'string'],
                    'expires_at_utc' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'user' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['auth_mode', 'token_type', 'auth_header', 'access_session_id', 'session_id'],
                'additionalProperties' => false,
            ]),
            'CustomerSessionLogoutEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'auth_mode' => ['type' => 'string', 'enum' => ['customer_access_session']],
                    'access_session_id' => ['type' => 'integer'],
                    'revoked_at_utc' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
                'required' => ['auth_mode', 'access_session_id'],
                'additionalProperties' => false,
            ]),
            'StaffAuthSessionEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'auth_mode' => ['type' => 'string', 'enum' => ['staff_api_key', 'staff_browser_session']],
                    'session_transport' => ['type' => 'string', 'enum' => ['refresh_cookie'], 'nullable' => true],
                    'token_type' => ['type' => 'string', 'enum' => ['opaque']],
                    'auth_header' => ['type' => 'string'],
                    'access_token' => ['type' => 'string', 'nullable' => true],
                    'staff_api_key_id' => ['type' => 'integer'],
                    'expires_at_utc' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'user' => ['$ref' => '#/components/schemas/StaffAuthUser', 'nullable' => true],
                    'capabilities' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'known_capabilities' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'capability_source' => ['type' => 'string'],
                    'startup' => ['$ref' => '#/components/schemas/StaffStartupContext'],
                ],
                'required' => ['auth_mode', 'token_type', 'auth_header', 'staff_api_key_id', 'capabilities', 'known_capabilities', 'capability_source', 'startup'],
                'additionalProperties' => false,
            ]),
            'StaffSessionLogoutEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'auth_mode' => ['type' => 'string', 'enum' => ['staff_api_key', 'staff_browser_session']],
                    'session_transport' => ['type' => 'string', 'enum' => ['refresh_cookie'], 'nullable' => true],
                    'staff_api_key_id' => ['type' => 'integer'],
                    'revoked_at_utc' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
                'required' => ['auth_mode', 'staff_api_key_id'],
                'additionalProperties' => false,
            ]),
            'CustomerMenuItemsCollectionEnvelope' => [
                'type' => 'object',
                'required' => ['data', 'meta'],
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CustomerMenuItem'],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/CustomerMenuItemsMeta'],
                ],
                'additionalProperties' => false,
            ],
            'CustomerMenuCategory' => [
                'type' => 'object',
                'required' => ['category_id', 'name', 'description', 'sort_order', 'items'],
                'properties' => [
                    'category_id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'sort_order' => ['type' => 'integer', 'nullable' => true],
                    'items' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CustomerMenuItem'],
                    ],
                ],
                'additionalProperties' => true,
            ],
            'CustomerMenuCategoriesCollectionEnvelope' => [
                'type' => 'object',
                'required' => ['data', 'meta'],
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CustomerMenuCategory'],
                    ],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'service_time' => ['type' => 'string', 'nullable' => true],
                            'preorder_only' => ['type' => 'boolean'],
                            'count' => ['type' => 'integer'],
                        ],
                        'additionalProperties' => true,
                    ],
                ],
                'additionalProperties' => false,
            ],
            'CustomerMenuItemEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/CustomerMenuItem',
            ], [
                'type' => 'object',
                'properties' => [
                    'service_time' => ['type' => 'string', 'nullable' => true],
                ],
                'additionalProperties' => true,
            ]),
            'CustomerMenuPreorderPreviewEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'totals' => ['type' => 'object', 'additionalProperties' => true],
                    'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'policy' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => true,
            ]),
            'AvailableTablesCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/RestaurantTable',
            ], [
                'type' => 'object',
                'properties' => [
                    'timezone' => ['type' => 'string'],
                    'branch_id' => ['type' => 'integer', 'nullable' => true],
                    'branch_timezone' => ['type' => 'string', 'nullable' => true],
                    'from_utc' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'to_utc' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'filters' => ['type' => 'object', 'additionalProperties' => true],
                    'availability_policy' => ['type' => 'object', 'additionalProperties' => true],
                    'count' => ['type' => 'integer'],
                    'suggestions' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                ],
                'additionalProperties' => true,
            ]),
            'TableHold' => [
                'type' => 'object',
                'required' => ['hold_id', 'session_hash', 'start_time', 'end_time', 'duration_minutes', 'hold_status', 'confirmed_reservation_id', 'row_version', 'tables'],
                'properties' => [
                    'hold_id' => ['type' => 'string'],
                    'session_hash' => ['type' => 'string', 'nullable' => true],
                    'start_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'end_time' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'duration_minutes' => ['type' => 'integer', 'nullable' => true],
                    'hold_status' => ['type' => 'string'],
                    'confirmed_reservation_id' => ['type' => 'integer', 'nullable' => true],
                    'row_version' => ['type' => 'integer'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'expire_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'tables' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/RestaurantTable'],
                    ],
                ],
                'additionalProperties' => true,
            ],
            'TableHoldEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/TableHold',
            ]),
            'CustomerLoyaltySummaryEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/CustomerLoyaltySummary',
            ]),
            'CustomerVoucherCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/CustomerVoucher',
            ], [
                'type' => 'object',
                'properties' => [
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'filters' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => true,
            ]),
            'CustomerDataExportEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'additionalProperties' => true,
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'additionalProperties' => true,
            ]),
            'CustomerPrivacyRequest' => [
                'type' => 'object',
                'required' => ['customer_privacy_request_id', 'request_type', 'status', 'requested_at'],
                'properties' => [
                    'customer_privacy_request_id' => ['type' => 'integer'],
                    'request_type' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'reason' => ['type' => 'string', 'nullable' => true],
                    'requested_via' => ['type' => 'string', 'nullable' => true],
                    'requested_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'reviewed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'processed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'resolution_notes' => ['type' => 'string', 'nullable' => true],
                    'result_summary' => ['type' => 'string', 'nullable' => true],
                ],
                'additionalProperties' => true,
            ],
            'CustomerPrivacyRequestCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/CustomerPrivacyRequest',
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                ],
                'additionalProperties' => true,
            ]),
            'CustomerPrivacyRequestEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'request' => ['$ref' => '#/components/schemas/CustomerPrivacyRequest'],
                    'created' => ['type' => 'boolean'],
                ],
                'required' => ['request', 'created'],
                'additionalProperties' => true,
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'additionalProperties' => true,
            ]),
            'ReservationEnvelope' => $this->dataEnvelope($reservationSummary),
            'ReservationCollectionEnvelope' => $this->collectionEnvelope($reservationSummary),
            'ReservationSelfServiceCollectionEnvelope' => $this->collectionEnvelope($reservationSummary, [
                'type' => 'object',
                'properties' => [
                    'access_scope' => ['type' => 'string'],
                    'pagination' => [
                        'type' => 'object',
                        'properties' => [
                            'current_page' => ['type' => 'integer'],
                            'last_page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                            'count' => ['type' => 'integer'],
                            'has_more_pages' => ['type' => 'boolean'],
                        ],
                        'required' => ['current_page', 'last_page', 'per_page', 'total', 'count', 'has_more_pages'],
                        'additionalProperties' => false,
                    ],
                ],
                'required' => ['access_scope', 'pagination'],
                'additionalProperties' => false,
            ]),
            'ReservationActionEnvelope' => $this->dataEnvelope($reservationSummary, [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'access_scope' => ['type' => 'string'],
                ],
                'required' => ['action', 'access_scope'],
                'additionalProperties' => false,
            ]),
            'CustomerWaitingListEnvelope' => $this->dataEnvelope($customerWaitingListEntry),
            'CustomerWaitingListCollectionEnvelope' => $this->collectionEnvelope($customerWaitingListEntry),
            'CustomerWaitingListArrivalEnvelope' => [
                'type' => 'object',
                'required' => ['data', 'meta'],
                'properties' => [
                    'data' => ['$ref' => '#/components/schemas/CustomerWaitingListEntry'],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string'],
                            'staff_seat_required' => ['type' => 'boolean'],
                            'message' => ['type' => 'string', 'nullable' => true],
                        ],
                        'required' => ['action', 'staff_seat_required'],
                        'additionalProperties' => false,
                    ],
                ],
                'additionalProperties' => false,
            ],
            'CustomerReservationBenefitsPreviewEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/CustomerReservationBenefitsPreview',
            ]),
            'CustomerReservationVoucherActionEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'reservation' => ['$ref' => '#/components/schemas/CustomerReservationBenefitsReservation'],
                    'available_vouchers' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CustomerVoucher'],
                    ],
                    'voucher' => [
                        'anyOf' => [
                            ['$ref' => '#/components/schemas/CustomerVoucher'],
                            ['type' => 'null'],
                        ],
                    ],
                    'removed_voucher' => [
                        'anyOf' => [
                            ['$ref' => '#/components/schemas/CustomerVoucher'],
                            ['type' => 'null'],
                        ],
                    ],
                ],
                'required' => ['reservation', 'available_vouchers'],
                'additionalProperties' => true,
            ]),
            'CustomerReservationLoyaltyActionEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'reservation' => ['$ref' => '#/components/schemas/CustomerReservationBenefitsReservation'],
                    'transactions' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/LoyaltyPointTransaction'],
                    ],
                ],
                'required' => ['reservation', 'transactions'],
                'additionalProperties' => true,
            ]),
            'CustomerReservationPreorderEnvelope' => [
                'type' => 'object',
                'required' => ['data', 'meta'],
                'properties' => [
                    'data' => ['$ref' => '#/components/schemas/CustomerReservationPreorderPayload'],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string'],
                            'access_scope' => ['type' => 'string'],
                        ],
                        'required' => ['action', 'access_scope'],
                        'additionalProperties' => false,
                    ],
                ],
                'additionalProperties' => false,
            ],
            'CustomerReservationDepositPreviewEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'reservation' => ['$ref' => '#/components/schemas/ReservationSummary'],
                    'deposit' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['reservation', 'deposit'],
                'additionalProperties' => false,
            ]),
            'CustomerDepositPaymentSessionEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'reservation_id' => ['type' => 'integer'],
                    'deposit' => ['type' => 'object', 'additionalProperties' => true],
                    'payment_session' => ['$ref' => '#/components/schemas/CustomerDepositPaymentSession'],
                ],
                'required' => ['reservation_id', 'deposit', 'payment_session'],
                'additionalProperties' => false,
            ]),
            'CustomerReservationBillEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'reservation_id' => ['type' => 'integer'],
                    'access_scope' => ['type' => 'string'],
                    'bill' => ['type' => 'object', 'additionalProperties' => true],
                    'settlement' => ['type' => 'object', 'additionalProperties' => true],
                    'orders' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'workflow' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['reservation_id', 'bill', 'settlement', 'orders', 'workflow'],
                'additionalProperties' => false,
            ]),
            'CustomerBillPreviewEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'reservation_id' => ['type' => 'integer'],
                    'active_order' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                    'bill_preview' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['reservation_id'],
                'additionalProperties' => false,
            ]),
            'CustomerActiveOrderEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'reservation_id' => ['type' => 'integer'],
                    'active_order' => ['type' => 'object', 'nullable' => true, 'additionalProperties' => true],
                ],
                'required' => ['reservation_id'],
                'additionalProperties' => false,
            ]),
            'CustomerBillPaymentSessionEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'reservation_id' => ['type' => 'integer'],
                    'bill' => ['type' => 'object', 'additionalProperties' => true],
                    'payment_session' => ['$ref' => '#/components/schemas/CustomerBillPaymentSession'],
                ],
                'required' => ['reservation_id', 'bill', 'payment_session'],
                'additionalProperties' => false,
            ]),
            'WebhookReceiptEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'properties' => [
                    'duplicate' => ['type' => 'boolean'],
                    'provider_code' => ['type' => 'string'],
                    'provider_event_code' => ['type' => 'string'],
                    'provider_session_code' => ['type' => 'string'],
                    'payment_scope' => ['type' => 'string', 'nullable' => true],
                    'delivery_status' => ['type' => 'string'],
                    'receipt_id' => ['type' => 'integer'],
                    'ignored_reason' => ['type' => 'string', 'nullable' => true],
                    'failure_message' => ['type' => 'string', 'nullable' => true],
                    'message' => ['type' => 'string', 'nullable' => true],
                ],
                'required' => ['duplicate', 'provider_code', 'provider_event_code', 'provider_session_code', 'delivery_status', 'receipt_id'],
                'additionalProperties' => false,
            ]),
            'HealthStatusEnvelope' => [
                'type' => 'object',
                'required' => ['status', 'service', 'timestamp_utc'],
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['ok', 'degraded', 'fail']],
                    'service' => ['type' => 'string'],
                    'timestamp_utc' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'additionalProperties' => false,
            ],
            'HealthDetailedEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['ok', 'degraded', 'fail']],
                    'checks' => ['type' => 'object', 'additionalProperties' => true],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['status', 'checks', 'meta'],
                'additionalProperties' => false,
            ],
            'HealthRedisEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'status' => ['type' => 'string', 'enum' => ['ok', 'degraded', 'fail']],
                    'checks' => ['type' => 'object', 'additionalProperties' => true],
                    'meta' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['status', 'checks', 'meta'],
                'additionalProperties' => false,
            ],
            'StaffOperationalRealtimeEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/StaffOperationalRealtimeState',
            ]),
            'StaffTableBoardEnvelope' => [
                'type' => 'object',
                'required' => ['data', 'zones', 'summary', 'unassigned_reservations', 'orchestration', 'meta'],
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffTableBoardRow']],
                    'zones' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffTableBoardZoneSummary']],
                    'summary' => [
                        'type' => 'object',
                        'required' => ['zone_count', 'active_order_count', 'unassigned_reservation_count', 'unassigned_with_slot_only_candidate_count', 'deposit_acknowledged_reservation_count', 'deposit_intent_submitted_reservation_count', 'deposit_self_service_follow_up_count'],
                        'properties' => [
                            'zone_count' => ['type' => 'integer'],
                            'active_order_count' => ['type' => 'integer'],
                            'unassigned_reservation_count' => ['type' => 'integer'],
                            'unassigned_with_slot_only_candidate_count' => ['type' => 'integer'],
                            'deposit_acknowledged_reservation_count' => ['type' => 'integer'],
                            'deposit_intent_submitted_reservation_count' => ['type' => 'integer'],
                            'deposit_self_service_follow_up_count' => ['type' => 'integer'],
                        ],
                        'additionalProperties' => false,
                    ],
                    'unassigned_reservations' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StaffTableBoardUnassignedReservation']],
                    'orchestration' => [
                        'type' => 'object',
                        'required' => ['mode', 'write_side', 'capacity_policy'],
                        'properties' => [
                            'mode' => ['type' => 'string'],
                            'write_side' => [
                                'type' => 'object',
                                'required' => ['assign_suggested_table_supported', 'assign_best_fit_supported', 'assign_suggested_table_requires_current_candidate'],
                                'properties' => [
                                    'assign_suggested_table_supported' => ['type' => 'boolean'],
                                    'assign_best_fit_supported' => ['type' => 'boolean'],
                                    'assign_suggested_table_requires_current_candidate' => ['type' => 'boolean'],
                                ],
                                'additionalProperties' => false,
                            ],
                            'capacity_policy' => [
                                'type' => 'object',
                                'required' => ['close_fit_max_extra_seats'],
                                'properties' => [
                                    'close_fit_max_extra_seats' => ['type' => 'integer'],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                    'meta' => ['$ref' => '#/components/schemas/StaffTableBoardMeta'],
                ],
                'additionalProperties' => false,
            ],
            'StaffOrderReadEnvelope' => [
                'type' => 'object',
                'required' => ['data', 'meta'],
                'properties' => [
                    'data' => ['$ref' => '#/components/schemas/StaffOrderReadPayload'],
                    'meta' => [
                        'type' => 'object',
                        'required' => ['action', 'selection_policy'],
                        'properties' => [
                            'action' => ['type' => 'string'],
                            'selection_policy' => ['type' => 'string'],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
                'additionalProperties' => false,
            ],
            'StaffWaitingListEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/StaffWaitingListEntry',
            ]),
            'StaffReservationLookupCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/StaffReservationLookupEntry',
            ], [
                '$ref' => '#/components/schemas/StaffReservationLookupCollectionMeta',
            ]),
            'StaffWaitingListCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/StaffWaitingListEntry',
            ], [
                '$ref' => '#/components/schemas/StaffWaitingListCollectionMeta',
            ]),
            'StaffAuditTrailEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/StaffAuditTrailEntry',
            ], [
                '$ref' => '#/components/schemas/StaffAuditTrailCollectionMeta',
            ]),
            'StaffWaitingListSeatEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'required' => ['waiting_list', 'reservation'],
                'properties' => [
                    'waiting_list' => ['$ref' => '#/components/schemas/StaffWaitingListEntry'],
                    'reservation' => ['$ref' => '#/components/schemas/ReservationSummary'],
                ],
                'additionalProperties' => false,
            ]),
            'StaffWaitingListAdvanceEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'required' => ['source_waiting_list', 'advanced_waiting_list', 'automation'],
                'properties' => [
                    'source_waiting_list' => ['$ref' => '#/components/schemas/StaffWaitingListEntry'],
                    'advanced_waiting_list' => [
                        'anyOf' => [
                            ['$ref' => '#/components/schemas/StaffWaitingListEntry'],
                            ['type' => 'null'],
                        ],
                    ],
                    'automation' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => false,
            ]),
            'CashierShiftEnvelope' => $this->dataEnvelope($cashierShiftSchema, [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'required' => ['action'],
                'additionalProperties' => false,
            ]),
            'CashierShiftCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/CashierShift',
            ], [
                '$ref' => '#/components/schemas/CashierShiftCollectionMeta',
            ]),
            'BranchEnvelope' => $this->dataEnvelope($branchSchema, [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'required' => ['action'],
                'additionalProperties' => false,
            ]),
            'RestaurantProfileEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/RestaurantProfile',
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'required' => ['action'],
                'additionalProperties' => false,
            ]),
            'RestaurantProfileCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/RestaurantProfile',
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'required' => ['action'],
                'additionalProperties' => false,
            ]),
            'BranchCollectionEnvelope' => $this->collectionEnvelope($branchSchema, [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'count' => ['type' => 'integer'],
                    'branch_access' => ['$ref' => '#/components/schemas/StaffBranchAccessContext'],
                    'accessible_branch_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'default_branch_id' => ['type' => 'integer', 'nullable' => true],
                    'current_branch_id' => ['type' => 'integer', 'nullable' => true],
                    'has_multi_branch_access' => ['type' => 'boolean'],
                ],
                'required' => ['action', 'count'],
                'additionalProperties' => false,
            ]),
            'AdminIngredientCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminIngredient',
            ], [
                '$ref' => '#/components/schemas/AdminIngredientCollectionMeta',
            ]),
            'AdminSupplierCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminSupplier',
            ], [
                '$ref' => '#/components/schemas/AdminSupplierCollectionMeta',
            ]),
            'AdminPurchaseOrderCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminPurchaseOrder',
            ], [
                '$ref' => '#/components/schemas/AdminPurchaseOrderCollectionMeta',
            ]),
            'AdminIngredientMovementCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminIngredientMovement',
            ], [
                'type' => 'object',
                'properties' => [
                    'ingredient' => ['type' => 'object', 'additionalProperties' => true],
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'filters' => ['type' => 'object', 'additionalProperties' => true],
                    'sort' => $listingSortSchema,
                    'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
                ],
                'additionalProperties' => true,
            ]),
            'AdminIngredientMovementEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/AdminIngredientMovement',
            ], [
                'type' => 'object',
                'properties' => [
                    'stock_on_hand' => ['type' => 'string'],
                ],
                'additionalProperties' => true,
            ]),
            'AdminPurchaseOrderReceiptCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminPurchaseOrderReceipt',
            ], [
                'type' => 'object',
                'properties' => [
                    'purchase_order' => ['$ref' => '#/components/schemas/AdminPurchaseOrder'],
                    'count' => ['type' => 'integer'],
                ],
                'required' => ['purchase_order', 'count'],
                'additionalProperties' => false,
            ]),
            'AdminPurchaseOrderReceiptEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/AdminPurchaseOrderReceipt',
            ], [
                'type' => 'object',
                'properties' => [
                    'purchase_order' => ['$ref' => '#/components/schemas/AdminPurchaseOrder'],
                ],
                'required' => ['purchase_order'],
                'additionalProperties' => false,
            ]),
            'AdminVoucherCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminVoucher',
            ], [
                'type' => 'object',
                'additionalProperties' => true,
            ]),
            'AdminVoucherEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/AdminVoucher',
            ]),
            'AdminLoyaltyTierCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminLoyaltyTier',
            ], [
                'type' => 'object',
                'additionalProperties' => true,
            ]),
            'AdminLoyaltyTierEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/AdminLoyaltyTier',
            ]),
            'AdminBenefitSettingCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminBenefitSetting',
            ], [
                'type' => 'object',
                'additionalProperties' => true,
            ]),
            'AdminBenefitSettingEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/AdminBenefitSetting',
            ], [
                'type' => 'object',
                'additionalProperties' => true,
            ]),
            'AdminPrivacyRequestCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/AdminPrivacyRequest',
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                ],
                'additionalProperties' => true,
            ]),
            'AdminCustomerDataExportEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'additionalProperties' => true,
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'additionalProperties' => true,
            ]),
            'AdminPrivacyReviewEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'additionalProperties' => true,
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'mode' => ['type' => 'string'],
                    'committed' => ['type' => 'boolean'],
                ],
                'additionalProperties' => true,
            ]),
            'FinancialReconciliationCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/FinancialReconciliationRow',
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'filters' => ['type' => 'object', 'additionalProperties' => true],
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
                ],
                'additionalProperties' => true,
            ]),
            'FinancialReconciliationDetailEnvelope' => $this->dataEnvelope([
                'type' => 'object',
                'required' => ['reservation', 'summary', 'payments', 'method_breakdown'],
                'properties' => [
                    'reservation' => ['$ref' => '#/components/schemas/FinancialReservationSummary'],
                    'summary' => ['$ref' => '#/components/schemas/FinancialReconciliationRow'],
                    'payments' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                    'method_breakdown' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => true]],
                ],
                'additionalProperties' => true,
            ], [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'branch_id' => ['type' => 'integer', 'nullable' => true],
                ],
                'additionalProperties' => true,
            ]),
            'FinanceInvoiceEnvelope' => $this->dataEnvelope($financeInvoicePayloadSchema, [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'created' => ['type' => 'boolean'],
                    'branch_id' => ['type' => 'integer', 'nullable' => true],
                ],
                'additionalProperties' => true,
            ]),
            'StaffReportingDailySalesCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/ReportingDailySalesSnapshot',
            ], [
                '$ref' => '#/components/schemas/StaffReportingCollectionMeta',
            ]),
            'StaffReportingDailyOperationsCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/ReportingDailyOperationSnapshot',
            ], [
                '$ref' => '#/components/schemas/StaffReportingCollectionMeta',
            ]),
            'StaffReportingDailyInventoryCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/ReportingDailyInventoryMovementSnapshot',
            ], [
                '$ref' => '#/components/schemas/StaffReportingCollectionMeta',
            ]),
            'StaffReservationOrderEnvelope' => $this->dataEnvelope($reservationOrderSchema, [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'legacy_route_alias' => ['type' => 'string', 'nullable' => true],
                    'legacy_route_deprecated' => ['type' => 'boolean', 'nullable' => true],
                    'semantics' => ['type' => 'string', 'nullable' => true],
                ],
                'required' => ['action'],
                'additionalProperties' => false,
            ]),
            'StaffReservationOrderCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/ReservationOrder',
            ], [
                '$ref' => '#/components/schemas/StaffReservationOrderCollectionMeta',
            ]),
            'StaffCheckoutSettlementEnvelope' => $this->dataEnvelope($staffCheckoutSettlementSchema),
            'StaffRefundEnvelope' => $this->dataEnvelope($staffRefundDataSchema),
            'StaffRefundPreviewEnvelope' => $this->dataEnvelope($staffRefundDataSchema, [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'required' => ['action'],
                'additionalProperties' => false,
            ]),
            'KitchenStation' => [
                'type' => 'object',
                'required' => ['station_id', 'branch_id', 'code', 'name', 'description', 'output_mode', 'printer_target', 'is_active', 'route_count', 'ticket_counts', 'created_at', 'updated_at'],
                'properties' => [
                    'station_id' => ['type' => 'integer'],
                    'branch_id' => ['type' => 'integer'],
                    'code' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'output_mode' => ['type' => 'string'],
                    'printer_target' => ['type' => 'string', 'nullable' => true],
                    'is_active' => ['type' => 'boolean'],
                    'route_count' => ['type' => 'integer'],
                    'ticket_counts' => [
                        'type' => 'object',
                        'required' => ['queued', 'fired', 'ready'],
                        'properties' => [
                            'queued' => ['type' => 'integer'],
                            'fired' => ['type' => 'integer'],
                            'ready' => ['type' => 'integer'],
                        ],
                        'additionalProperties' => false,
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
                'additionalProperties' => false,
            ],
            'KitchenOrderItemTicket' => [
                'type' => 'object',
                'required' => ['ticket_id', 'row_version', 'ticket_status', 'route_source', 'dispatch_count', 'recall_count', 'output_mode', 'printer_target', 'ticket_notes', 'order', 'station', 'route', 'routing', 'order_item', 'item', 'lifecycle', 'reconciliation', 'first_dispatched_at', 'fired_at', 'ready_at', 'completed_at', 'cancelled_at', 'last_recalled_at', 'created_at', 'updated_at'],
                'properties' => [
                    'ticket_id' => ['type' => 'integer'],
                    'row_version' => ['type' => 'integer', 'nullable' => true],
                    'ticket_status' => ['type' => 'string'],
                    'route_source' => ['type' => 'string', 'nullable' => true],
                    'dispatch_count' => ['type' => 'integer'],
                    'recall_count' => ['type' => 'integer'],
                    'output_mode' => ['type' => 'string'],
                    'printer_target' => ['type' => 'string', 'nullable' => true],
                    'ticket_notes' => ['type' => 'string', 'nullable' => true],
                    'order' => [
                        'type' => 'object',
                        'required' => ['order_id', 'reservation_id'],
                        'properties' => [
                            'order_id' => ['type' => 'integer'],
                            'reservation_id' => ['type' => 'integer'],
                        ],
                        'additionalProperties' => false,
                    ],
                    'station' => [
                        'type' => 'object',
                        'nullable' => true,
                        'required' => ['station_id', 'code', 'name'],
                        'properties' => [
                            'station_id' => ['type' => 'integer'],
                            'code' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                        ],
                        'additionalProperties' => false,
                    ],
                    'route' => [
                        'type' => 'object',
                        'nullable' => true,
                        'required' => ['route_id', 'category_id', 'sort_order', 'is_active'],
                        'properties' => [
                            'route_id' => ['type' => 'integer'],
                            'category_id' => ['type' => 'integer'],
                            'sort_order' => ['type' => 'integer'],
                            'is_active' => ['type' => 'boolean'],
                        ],
                        'additionalProperties' => false,
                    ],
                    'routing' => [
                        'type' => 'object',
                        'required' => ['route_present', 'route_active', 'station_matches_route'],
                        'properties' => [
                            'route_present' => ['type' => 'boolean'],
                            'route_active' => ['type' => 'boolean', 'nullable' => true],
                            'station_matches_route' => ['type' => 'boolean', 'nullable' => true],
                        ],
                        'additionalProperties' => false,
                    ],
                    'order_item' => [
                        'type' => 'object',
                        'nullable' => true,
                        'required' => ['order_item_id', 'item_id', 'quantity', 'status', 'row_version', 'notes', 'item_name_snapshot'],
                        'properties' => [
                            'order_item_id' => ['type' => 'integer'],
                            'item_id' => ['type' => 'integer'],
                            'quantity' => ['type' => 'integer'],
                            'status' => ['type' => 'string'],
                            'row_version' => ['type' => 'integer', 'nullable' => true],
                            'notes' => ['type' => 'string', 'nullable' => true],
                            'item_name_snapshot' => ['type' => 'string', 'nullable' => true],
                        ],
                        'additionalProperties' => false,
                    ],
                    'item' => [
                        'type' => 'object',
                        'nullable' => true,
                        'required' => ['item_id', 'name', 'category_id', 'category_name'],
                        'properties' => [
                            'item_id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'category_id' => ['type' => 'integer', 'nullable' => true],
                            'category_name' => ['type' => 'string', 'nullable' => true],
                        ],
                        'additionalProperties' => false,
                    ],
                    'lifecycle' => [
                        'type' => 'object',
                        'required' => ['status', 'state_reason', 'is_terminal', 'allowed_actions'],
                        'properties' => [
                            'status' => ['type' => 'string'],
                            'state_reason' => ['type' => 'string'],
                            'is_terminal' => ['type' => 'boolean'],
                            'allowed_actions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'additionalProperties' => false,
                    ],
                    'reconciliation' => [
                        'type' => 'object',
                        'required' => ['sync_status', 'routing_status', 'order_item_expected_status', 'order_item_matches_ticket', 'station_active', 'drift_reasons', 'next_actions'],
                        'properties' => [
                            'sync_status' => ['type' => 'string'],
                            'routing_status' => ['type' => 'string'],
                            'order_item_expected_status' => ['type' => 'string', 'nullable' => true],
                            'order_item_matches_ticket' => ['type' => 'boolean', 'nullable' => true],
                            'station_active' => ['type' => 'boolean', 'nullable' => true],
                            'drift_reasons' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'next_actions' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'additionalProperties' => false,
                    ],
                    'first_dispatched_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'fired_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'ready_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'completed_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'cancelled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'last_recalled_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
                'additionalProperties' => false,
            ],
            'StaffKitchenStationCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/KitchenStation',
            ], [
                'type' => 'object',
                'required' => ['count', 'branch_id', 'branch_scope', 'realtime'],
                'properties' => [
                    'count' => ['type' => 'integer'],
                    'branch_id' => ['type' => 'integer', 'nullable' => true],
                    'branch_scope' => $staffBranchScopeMetaSchema,
                    'realtime' => ['$ref' => '#/components/schemas/StaffOperationalRealtimeDescriptor'],
                ],
                'additionalProperties' => false,
            ]),
            'StaffKitchenTicketCollectionEnvelope' => $this->collectionEnvelope([
                '$ref' => '#/components/schemas/KitchenOrderItemTicket',
            ], [
                'type' => 'object',
                'required' => ['station_id', 'branch_id', 'count', 'branch_scope'],
                'properties' => [
                    'station_id' => ['type' => 'integer'],
                    'branch_id' => ['type' => 'integer', 'nullable' => true],
                    'count' => ['type' => 'integer'],
                    'branch_scope' => $staffBranchScopeMetaSchema,
                ],
                'additionalProperties' => false,
            ]),
            'StaffKitchenDispatchEnvelope' => $this->dataEnvelope([
                'type' => 'array',
                'items' => ['$ref' => '#/components/schemas/KitchenOrderItemTicket'],
            ], [
                'type' => 'object',
                'required' => ['action', 'created_count', 'reused_count', 'unrouted_count', 'pinned_route_count'],
                'properties' => [
                    'action' => ['type' => 'string'],
                    'created_count' => ['type' => 'integer'],
                    'reused_count' => ['type' => 'integer'],
                    'unrouted_count' => ['type' => 'integer'],
                    'pinned_route_count' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ]),
            'StaffKitchenTicketEnvelope' => $this->dataEnvelope([
                '$ref' => '#/components/schemas/KitchenOrderItemTicket',
            ], [
                'type' => 'object',
                'required' => ['action'],
                'properties' => [
                    'action' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
            ]),
            'StaffConversationCollectionEnvelope' => $this->collectionEnvelope($staffConversationSummarySchema, [
                'type' => 'object',
                'properties' => [
                    'action' => ['type' => 'string'],
                    'filters' => ['type' => 'object', 'additionalProperties' => true],
                    'sort' => $listingSortSchema,
                    'pagination' => $waitingListPaginationSchema,
                    'current_page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'from' => ['type' => 'integer', 'nullable' => true],
                    'to' => ['type' => 'integer', 'nullable' => true],
                    'total' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'has_more_pages' => ['type' => 'boolean'],
                    'query_contract' => ['$ref' => '#/components/schemas/ListingQueryContract'],
                    'summary' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['action', 'filters', 'sort', 'pagination', 'current_page', 'per_page', 'from', 'to', 'total', 'last_page', 'has_more_pages', 'query_contract', 'summary'],
                'additionalProperties' => false,
            ]),
            'StaffConversationDetailEnvelope' => $this->dataEnvelope($staffConversationDetailPayload, [
                'type' => 'object',
                'properties' => [
                    'message_limit' => ['type' => 'integer'],
                    'event_limit' => ['type' => 'integer'],
                    'include_closed_assignments' => ['type' => 'boolean'],
                    'returned_counts' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'required' => ['message_limit', 'event_limit', 'include_closed_assignments', 'returned_counts'],
                'additionalProperties' => false,
            ]),
            'StaffConversationMutationEnvelope' => $this->dataEnvelope($staffConversationMutationPayload, [
                'type' => 'object',
                'properties' => [
                    'conversation_id' => ['type' => 'string', 'format' => 'uuid'],
                ],
                'required' => ['conversation_id'],
                'additionalProperties' => false,
            ]),
            'GenericDataEnvelope' => $this->dataEnvelope(['type' => 'object', 'additionalProperties' => true]),
            'GenericCollectionEnvelope' => $this->collectionEnvelope(['type' => 'object', 'additionalProperties' => true]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function tagDescriptions(): array
    {
        return [
            'Auth' => 'Opaque customer and staff authentication sessions.',
            'Health' => 'Operational health and readiness endpoints.',
            'Restaurant Profile' => 'Public customer-web restaurant profile, default branch hours, and open-state context.',
            'Availability' => 'Customer-facing table availability and session-bound table hold flows.',
            'Menu Catalog' => 'Customer-visible menu browsing and service-time-aware item availability.',
            'Reservations' => 'Reservation lifecycle and self-service access.',
            'Customer Benefits' => 'Customer loyalty summaries plus reservation-scoped loyalty and voucher applicability previews.',
            'Customer Privacy' => 'Customer account data export and privacy request self-service.',
            'Reservation Deposit' => 'Deposit preview, acknowledgement, intent, and payment session flows.',
            'Reservation Billing' => 'Customer-visible bill, active order, and bill payment session flows.',
            'Waiting List' => 'Customer owner self-service plus staff operational queue, notify, seat, and realtime waiting-list flows.',
            'Payment Webhooks' => 'Inbound payment provider webhook receipts and reconciliation.',
            'Staff Tables' => 'Operational table board and floor activity surfaces.',
            'Staff Kitchen' => 'Kitchen station visibility, ticket routing, dispatch, and safe KDS state transitions for staff operators.',
            'Staff Conversations' => 'Operational inbox flows for conversation triage, assignment, linkage, internal notes, and queue-backed outbound replies.',
            'Staff Cashier' => 'Cashier shift opening, current view, and closing flows.',
            'Staff Checkout' => 'Bill snapshot, settlement finalization, and refund flows for staff operators.',
            'Admin Settings' => 'Administrative master-data settings for multi-branch operation.',
            'Legacy' => 'Non-versioned or backward-compatible alias routes retained for compatibility.',
        ];
    }

    /**
     * @return list<string>
     */
    public function knownEnvelopeExceptions(): array
    {
        return [
            'GET api/user',
            'GET api/v1/health',
            'GET api/v1/health/detailed',
            'GET api/v1/health/redis',
            'GET api/v1/staff/tables/board',
        ];
    }

    /**
     * @param  array<string,mixed>  $dataSchema
     * @param  array<string,mixed>|null  $metaSchema
     * @return array<string,mixed>
     */
    private function dataEnvelope(array $dataSchema, ?array $metaSchema = null): array
    {
        $schema = [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => $dataSchema,
            ],
            'additionalProperties' => false,
        ];

        if ($metaSchema !== null) {
            $schema['properties']['meta'] = $metaSchema;
        }

        return $schema;
    }

    /**
     * @param  array<string,mixed>  $itemSchema
     * @param  array<string,mixed>|null  $metaSchema
     * @return array<string,mixed>
     */
    private function collectionEnvelope(array $itemSchema, ?array $metaSchema = null): array
    {
        return $this->dataEnvelope([
            'type' => 'array',
            'items' => $itemSchema,
        ], $metaSchema);
    }
}

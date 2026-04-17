# Customer-Web Readiness Audit 2026-04-17

## Scope

- Repo: `C:\Users\Duong Vinh\RestaurantPOS-Laravel`
- Audit date: `2026-04-17`
- Audit target: backend readiness for starting `customer-web`
- Audit basis: current worktree state, not an explicitly cleaned branch

## Final verdict

`NOT READY`

The backend is not yet safe to treat as a closed, frontend-stable contract for `customer-web`.
Three issues block a strict readiness verdict:

1. core customer-web surfaces still remain `fallback` in frozen OpenAPI
2. one customer-facing waiting-list owner-contract regression is currently failing
3. runtime proof is missing because Redis/MySQL-backed readiness checks fail while most customer flows are behind `require.redis`

## Findings

### P0. Core availability + table-hold flow is still fallback-only while the official SDK exposes it as if it were ready

Evidence:

- `GET /api/v1/tables/available` => `grade=fallback`
- `POST /api/v1/table-holds` => `grade=fallback`
- `GET /api/v1/table-holds/{hold_id}` => `grade=fallback`
- `PATCH /api/v1/table-holds/{hold_id}/refresh` => `grade=fallback`
- `DELETE /api/v1/table-holds/{hold_id}` => `grade=fallback`

Source evidence:

- [routes/api/customer_self_service.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/routes/api/customer_self_service.php:146)
- [build/api-consumer/sdk/typescript/README.md](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/build/api-consumer/sdk/typescript/README.md:41)
- [docs/runbooks/booking-api-contract.md](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/runbooks/booking-api-contract.md:205)

Why this blocks readiness:

- `customer-web` cannot avoid availability and hold flows.
- The prompt criteria were strict: minimum frontend surfaces must not be treated as stable if they are still `fallback`.
- The current repo-generated SDK advertises these routes in the curated batch, but the frozen contract still labels them non-contract-grade. That is exactly the kind of FE-facing ambiguity that creates rework.

What must close:

- promote availability and table-hold endpoints from `fallback` to `full`
- add explicit schema and FE-facing coverage for those routes
- make the SDK/docs reflect the true contract state without ambiguity

### P0. Several minimum customer-web surfaces are still fallback-only in the frozen contract

Observed fallback-only customer-web routes:

- `GET /api/v1/menu/categories`
- `GET /api/v1/menu/items/{id}`
- `POST /api/v1/menu/preorder/preview`
- `GET /api/v1/me/data-export`
- `GET /api/v1/me/vouchers`
- `GET /api/v1/me/privacy-requests`
- `POST /api/v1/me/privacy-requests`

Source evidence:

- [docs/runbooks/booking-api-contract.md](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/docs/runbooks/booking-api-contract.md:203)
- [storage/app/booking_release/openapi-v1.json](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/storage/app/booking_release/openapi-v1.json:8570)
- [storage/app/booking_release/openapi-v1.json](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/storage/app/booking_release/openapi-v1.json:8741)
- [storage/app/booking_release/openapi-v1.json](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/storage/app/booking_release/openapi-v1.json:8857)
- [storage/app/booking_release/openapi-v1.json](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/storage/app/booking_release/openapi-v1.json:9069)
- [storage/app/booking_release/openapi-v1.json](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/storage/app/booking_release/openapi-v1.json:9133)

Why this blocks readiness:

- these are all minimum-surface routes a real `customer-web` will need
- docs already acknowledge customer menu and loyalty/voucher reads are still below contract-grade
- building FE against fallback-only routes violates the repo's own contract story

What must close:

- promote these routes to `full`
- freeze request/response/error shapes
- add artifact coverage so FE no longer depends on coarse fallback envelopes

### P1. Waiting-list owner-only error semantics are drifting between tests and runtime/artifacts

Observed failure:

- `php artisan test ... CustomerWaitingListOwnerContractHttpFlowTest.php ...`
- failing test: guest session headers expect `403 owner_scope_denied`
- actual runtime response: `401`

Source evidence:

- [tests/Feature/WaitingList/CustomerWaitingListOwnerContractHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/WaitingList/CustomerWaitingListOwnerContractHttpFlowTest.php:108)
- [app/Modules/WaitingList/Http/Controllers/Customer/CustomerWaitingListController.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/app/Modules/WaitingList/Http/Controllers/Customer/CustomerWaitingListController.php:140)
- [build/api-consumer/mutation-contracts.md](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/build/api-consumer/mutation-contracts.md:51)

Why this matters:

- FE branches heavily on `401` vs `403`
- the test expects owner-scope semantics, while runtime/artifact behavior currently documents authentication semantics
- this is not a theoretical mismatch: the automated test suite is red on a customer-facing contract

What must close:

- choose the canonical behavior for guest-session misuse on owner-only waiting-list routes
- align controller/runtime, mutation-contract docs, and tests to the same answer

### P1. Runtime readiness for customer-web is not proven because Redis/MySQL are down and tests explicitly bypass real Redis requirements

Evidence:

- `php artisan booking:doctor --json` failed runtime DB/Redis/scheduler checks
- `php artisan booking:deploy-check --mode=preflight` failed on MySQL inspection and multiple ops gates
- most customer routes live under `require.redis`
- runtime smoke tests explicitly disable `booking.require_redis_for_booking_api`

Source evidence:

- [routes/api/customer_self_service.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/routes/api/customer_self_service.php:31)
- [tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Infrastructure/ApiLiveRuntimeRegressionGateTest.php:27)
- [tests/Feature/Reservation/CustomerReservationSelfServiceHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Reservation/CustomerReservationSelfServiceHttpFlowTest.php:29)
- [tests/Feature/Reservation/CustomerReservationDepositSelfServiceFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/Reservation/CustomerReservationDepositSelfServiceFlowTest.php:23)
- [tests/Feature/WaitingList/CustomerWaitingListSelfServiceHttpFlowTest.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/tests/Feature/WaitingList/CustomerWaitingListSelfServiceHttpFlowTest.php:25)

Why this blocks readiness:

- passing sqlite-backed feature tests do not prove real runtime behavior for customer flows
- the local environment currently cannot prove the exact flows customer-web depends on
- a strict audit cannot call this ready while the required backing services are unavailable

What must close:

- bring up MySQL + Redis + scheduler heartbeat
- rerun runtime gates on real dependencies
- verify customer login -> availability -> hold -> reservation and self-pay flows without test-only Redis bypasses

### P2. Several full-contract customer mutations are still missing from the official TypeScript SDK batch

Observed `full` in OpenAPI but absent from curated SDK README:

- `POST /api/v1/reservations/{id}/cancel`
- `POST /api/v1/reservations/{id}/reschedule`
- `POST /api/v1/reservations/{reservation_id}/bill/payment-sessions`
- `POST /api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh`
- `POST /api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm`
- `GET /api/v1/waiting-list`
- `POST /api/v1/waiting-list/{id}/decline`
- `POST /api/v1/waiting-list/{id}/cancel`

Source evidence:

- [build/api-consumer/sdk/typescript/README.md](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/build/api-consumer/sdk/typescript/README.md:26)
- [build/api-consumer/mutation-contracts.md](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/build/api-consumer/mutation-contracts.md:33)
- [build/api-consumer/mutation-contracts.md](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/build/api-consumer/mutation-contracts.md:43)
- [build/api-consumer/mutation-contracts.md](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/build/api-consumer/mutation-contracts.md:54)

Why this matters:

- FE can still generate from full OpenAPI, so this is not as severe as fallback-only routes
- but the official repo story for TypeScript consumers becomes uneven right where customer mutations matter most

What must close:

- either curate these full-contract routes into the SDK batch
- or explicitly publish a customer-web generation path that is treated as first-class and stable

### P3. `customer_auth.session_bound_route_contracts` is duplicated line-for-line and is maintenance-fragile

Observed state:

- `48` literal entries in source
- only `24` unique action keys at runtime
- every action key appears twice

Source evidence:

- [config/customer_auth.php](C:/Users/Duong%20Vinh/RestaurantPOS-Laravel/config/customer_auth.php:56)

Why this matters:

- behavior is currently not broken because the duplicates are identical
- but the first copy of each key is dead-on-arrival
- a future partial edit can silently change only one copy and create misleading config review evidence that current harnesses do not catch

What must close:

- de-duplicate the array
- add a config test or lint that rejects repeated action keys

## Route inventory summary

Legend:

- `full`: frozen OpenAPI marks the route contract-grade
- `fallback`: route exists in the frozen artifact but is not yet endorsed FE contract
- `SDK`: route is present in the curated TypeScript SDK README
- `No SDK`: route is absent from the curated TypeScript SDK README

### Auth and account

| Route | Auth | Contract | SDK | Status |
|---|---|---|---|---|
| `POST /api/v1/auth/customer/login` | customer_access_token issuance | full | SDK | closed |
| `GET /api/v1/auth/customer/me` | customer_access_token | full | SDK | closed |
| `POST /api/v1/auth/customer/refresh` | customer_access_token | full | SDK | closed |
| `POST /api/v1/auth/customer/logout` | customer_access_token | full | SDK | closed |
| `GET /api/v1/me/loyalty` | customer_access_token | full | SDK | closed |
| `GET /api/v1/me/vouchers` | customer_or_staff | fallback | No SDK | blocked |
| `GET /api/v1/me/data-export` | customer_or_staff | fallback | No SDK | blocked |
| `GET /api/v1/me/privacy-requests` | customer_or_staff | fallback | No SDK | blocked |
| `POST /api/v1/me/privacy-requests` | customer_or_staff | fallback | No SDK | blocked |

### Menu and discovery

| Route | Auth | Contract | SDK | Status |
|---|---|---|---|---|
| `GET /api/v1/menu/categories` | customer_access_token | fallback | No SDK | blocked |
| `GET /api/v1/menu/items` | public | full | SDK | closed |
| `GET /api/v1/menu/items/{id}` | customer_access_token | fallback | No SDK | blocked |
| `POST /api/v1/menu/preorder/preview` | customer_access_token | fallback | No SDK | blocked |

### Availability and table holds

| Route | Auth | Contract | SDK | Status |
|---|---|---|---|---|
| `GET /api/v1/tables/available` | customer_access_token | fallback | SDK | blocked |
| `POST /api/v1/table-holds` | customer_access_token | fallback | SDK | blocked |
| `GET /api/v1/table-holds/{hold_id}` | customer_access_token | fallback | SDK | blocked |
| `PATCH /api/v1/table-holds/{hold_id}/refresh` | customer_access_token | fallback | SDK | blocked |
| `DELETE /api/v1/table-holds/{hold_id}` | customer_access_token | fallback | SDK | blocked |

### Reservations, preorder, deposit, bill

| Route | Auth | Contract | SDK | Status |
|---|---|---|---|---|
| `POST /api/v1/reservations` | customer_or_staff | full | SDK | closed |
| `GET /api/v1/reservations/{id}` | customer_or_staff | full | SDK | closed |
| `GET /api/v1/reservations` | customer_or_staff | full | No SDK | partial |
| `POST /api/v1/reservations/{id}/cancel` | customer_or_staff | full | No SDK | partial |
| `POST /api/v1/reservations/{id}/reschedule` | customer_or_staff | full | No SDK | partial |
| `GET /api/v1/reservations/{id}/preorder` | customer_or_session | full | SDK | closed |
| `POST /api/v1/reservations/{id}/preorder/preview` | customer_or_session | fallback alias/canonical preview path split | No SDK | blocked |
| `PUT /api/v1/reservations/{id}/preorder` | customer_or_session | full | SDK | closed |
| `DELETE /api/v1/reservations/{id}/preorder` | customer_or_session | full | SDK | closed |
| `GET /api/v1/reservations/{id}/deposit-preview` | customer_or_staff | full | SDK | closed |
| `POST /api/v1/reservations/{id}/deposit/acknowledge` | customer_or_staff | full | SDK | closed |
| `POST /api/v1/reservations/{id}/deposit/intent` | customer_or_staff | full | SDK | closed |
| `POST /api/v1/reservations/{id}/deposit/intent/revoke` | customer_or_staff | full | SDK | closed |
| `POST /api/v1/reservations/{reservation_id}/deposit/payment-sessions` | customer_or_staff | full | SDK | closed |
| `GET /api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}` | customer_or_staff | full | SDK | closed |
| `POST /api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/refresh` | customer_or_staff | full | SDK | closed |
| `POST /api/v1/reservations/{reservation_id}/deposit/payment-sessions/{session_id}/confirm` | customer_or_staff | full | SDK | closed |
| `GET /api/v1/reservations/{id}/benefits-preview` | customer_access_token | full | SDK | closed |
| `POST /api/v1/reservations/{id}/voucher/apply` | customer_access_token | full | SDK | closed |
| `POST /api/v1/reservations/{id}/voucher/remove` | customer_access_token | full | SDK | closed |
| `POST /api/v1/reservations/{id}/loyalty/redeem` | customer_access_token | full | SDK | closed |
| `POST /api/v1/reservations/{id}/loyalty/redeem/release` | customer_access_token | full | SDK | closed |
| `GET /api/v1/reservations/{reservation_id}/bill` | customer_or_staff | full | SDK | closed |
| `GET /api/v1/reservations/{reservation_id}/active-order` | customer_or_staff | full | SDK | closed |
| `GET /api/v1/reservations/{reservation_id}/bill-preview` | customer_or_staff | full | SDK | closed |
| `POST /api/v1/reservations/{reservation_id}/bill/payment-sessions` | customer_or_staff | full | No SDK | partial |
| `GET /api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}` | customer_or_staff | full | No SDK | partial |
| `POST /api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/refresh` | customer_or_staff | full | No SDK | partial |
| `POST /api/v1/reservations/{reservation_id}/bill/payment-sessions/{session_id}/confirm` | customer_or_staff | full | No SDK | partial |

### Waiting list

| Route | Auth | Contract | SDK | Status |
|---|---|---|---|---|
| `GET /api/v1/waiting-list` | customer_access_token | full | No SDK | partial |
| `POST /api/v1/waiting-list` | customer_access_token | full | SDK | partial because semantic drift test is red |
| `GET /api/v1/waiting-list/{id}` | customer_access_token | full | SDK | closed |
| `POST /api/v1/waiting-list/{id}/accept` | customer_access_token | full | SDK | closed |
| `POST /api/v1/waiting-list/{id}/confirm-arrival` | customer_access_token | full | SDK | closed |
| `POST /api/v1/waiting-list/{id}/decline` | customer_access_token | full | No SDK | partial |
| `POST /api/v1/waiting-list/{id}/cancel` | customer_access_token | full | No SDK | partial |

## Commands run

Passed:

- `php artisan booking:harness:web-auth --json`
- `php artisan booking:harness:golden-flows --json`
- `php artisan booking:route-contract:reconcile --json`
- `php artisan booking:release-manifest --verify-frozen --json`
- `php artisan test tests/Feature/Auth tests/Unit/Http/Middleware tests/Unit/Config/CustomerAuthConfigContractTest.php tests/Unit/Config/StaffAuthConfigContractTest.php tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php tests/Feature/CorsContractTest.php tests/Feature/Http/ApiValidationPayloadCompatibilityTest.php`
- `php artisan test tests/Feature/Reservation/CustomerReservationDepositPaymentRouteSurfaceTest.php tests/Feature/Reservation/CustomerReservationDepositPaymentSessionFlowTest.php tests/Feature/Reservation/CustomerReservationDepositPaymentVisibilityFlowTest.php tests/Feature/Reservation/CustomerReservationDepositSelfServiceFlowTest.php tests/Feature/Reservation/CustomerReservationDepositVisibilityFlowTest.php tests/Feature/Reservation/CustomerReservationPreorderManagementFlowTest.php tests/Feature/Reservation/CustomerReservationPreorderRouteSurfaceTest.php tests/Feature/Reservation/CustomerReservationPreorderSessionAccessTest.php tests/Feature/Reservation/CustomerReservationSelfServiceHttpFlowTest.php tests/Feature/Reservation/CustomerReservationSelfServiceVisibilityAndGuardTest.php`
- `php artisan test tests/Feature/Console/ApiConsumerArtifactsGenerateCommandTest.php tests/Feature/Infrastructure/ApiOpenApiContractCoverageTest.php`

Failed:

- `php artisan test tests/Feature/Reservation/CustomerReservationOrderBillPaymentIdempotencyEnforcementTest.php tests/Feature/Reservation/CustomerReservationOrderBillPaymentRouteSurfaceTest.php tests/Feature/Reservation/CustomerReservationOrderBillSelfPaymentFlowTest.php tests/Feature/Reservation/CustomerReservationOrderBillSessionAccessFlowTest.php tests/Feature/WaitingList/CustomerWaitingListOwnerContractHttpFlowTest.php tests/Feature/WaitingList/CustomerWaitingListOwnerResponseFlowTest.php tests/Feature/WaitingList/CustomerWaitingListSelfServiceHttpFlowTest.php tests/Feature/Customer/CustomerBenefitsSelfServiceHttpFlowTest.php tests/Feature/Customer/CustomerReservationBenefitsIdempotencyEnforcementTest.php tests/Feature/Customer/CustomerReservationBenefitsMutationHttpFlowTest.php tests/Feature/Customer/CustomerReservationOrderBillControllerTest.php tests/Feature/Customer/CustomerSelfServiceErrorEnvelopeContractTest.php`

Failure detail:

- `Tests\Feature\WaitingList\CustomerWaitingListOwnerContractHttpFlowTest::test_guest_session_headers_are_rejected_for_owner_only_waiting_list_contract`
- expected `403 owner_scope_denied`
- got `401`

Environment/runtime blockers:

- `php artisan booking:doctor --json`
- `php artisan booking:deploy-check --mode=preflight`

Both failed because MySQL and Redis were unavailable locally. `deploy-check` also reported unrelated ops release-risk states outside the immediate customer-web scope.

## Closure plan

1. Promote minimum customer-web fallback routes to `full`
   - menu categories
   - menu item detail
   - preorder preview
   - vouchers
   - privacy requests
   - availability and table-holds

2. Resolve waiting-list owner-only error semantics
   - decide canonical `401` vs `403`
   - align controller, tests, and mutation-contract docs

3. Close SDK coverage gaps for already-full customer mutations
   - reservation cancel/reschedule
   - bill payment session lifecycle
   - waiting-list list/decline/cancel

4. Remove duplicate `session_bound_route_contracts` keys and add a guard test

5. Bring up real MySQL + Redis and rerun live customer runtime gates without test-only Redis bypasses

## Bottom line

The repo is close on several customer flows. Auth, reservation self-service, deposit self-pay, bill self-pay, benefits, and most waiting-list behavior already have meaningful automated coverage.

But a strict readiness pass still fails today because the contract is not closed on several minimum customer-web surfaces, the waiting-list owner-only boundary is internally inconsistent, and runtime dependencies required by customer routes are not currently validated live.

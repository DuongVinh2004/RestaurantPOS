# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/runbooks/api-consumer-artifacts.md`
- `docs/runbooks/booking-api-contract.md`

## Code hotspots

- `config/customer_auth.php`
- `config/staff_auth.php`
- `config/cors.php`
- `app/Http/Controllers/Api/Auth/CustomerAuthController.php`
- `app/Http/Controllers/Api/Auth/StaffAuthController.php`
- `app/Http/Middleware/ResolveCustomerAuthMiddleware.php`
- `app/Http/Middleware/StaffApiKeyMiddleware.php`
- `app/Http/Middleware/CustomerOrStaffMiddleware.php`
- `app/Support/CustomerSessionRouteContract.php`
- `app/Support/StaffActorResolver.php`
- `app/Services/CustomerAccessSessionService.php`

## Test surface

- `tests/Feature/Auth/*`
- `tests/Feature/CorsContractTest.php`
- `tests/Feature/Reservation/CustomerReservation*SessionAccess*.php`
- `tests/Feature/WaitingList/CustomerWaitingListOwnerContractHttpFlowTest.php`
- `tests/Unit/Http/Middleware/*`
- `tests/Unit/Http/CustomerOrStaffMiddlewareSessionContractTest.php`
- `tests/Unit/Config/CustomerAuthConfigContractTest.php`
- `tests/Unit/Config/StaffAuthConfigContractTest.php`

## Questions to answer before patching

- Which header proves actor identity on this route?
- Is this surface token-authenticated, session-bound, staff-capability guarded, or a mixed customer-or-staff contract?
- What should `401` versus `403` mean for FE on this path?
- Does the browser need a new request header or exposed response header?
- Do the runbooks and generated artifacts still describe the real auth mode?

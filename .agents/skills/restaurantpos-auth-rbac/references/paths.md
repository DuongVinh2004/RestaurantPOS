# Paths

## Read first

- `AGENTS.md`
- `.codex/AGENTS.md`
- `docs/codex-parallel-agent-prompts.md` (Task A)

## Code hotspots

- `routes/api.php`
- `config/staff_capabilities.php`
- `config/staff_auth.php`
- `config/customer_auth.php`
- `app/Http/Middleware/RequireStaffCapability.php`
- `app/Http/Middleware/StaffApiKeyMiddleware.php`
- `app/Http/Middleware/ResolveCustomerAuthMiddleware.php`
- `app/Http/Middleware/CustomerOrStaffMiddleware.php`
- `app/Support/StaffActorResolver.php`
- `app/Services/Auth/OpaqueProductAuthService.php`
- `app/Services/CustomerAccessSessionService.php`

## Test surface

- `tests/Feature/Auth/*`
- `tests/Feature/Staff/StaffCapabilityHttpGuardTest.php`
- `tests/Unit/Http/Middleware/*`
- `tests/Unit/Config/StaffAuthConfigContractTest.php`
- `tests/Unit/Config/StaffCapabilitiesConfigContractTest.php`
- `tests/Unit/Config/CustomerAuthConfigContractTest.php`
- `tests/Feature/Infrastructure/StaffLegacyRouteAliasContractTest.php`

## Questions to answer before patching

- Which actor types can hit the route now?
- Which capability is expected to guard the mutation?
- Where does branch scope come from for this request?
- What is the deny-path regression test for the change?

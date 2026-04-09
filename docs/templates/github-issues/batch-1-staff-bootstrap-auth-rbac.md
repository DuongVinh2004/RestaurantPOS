# [Batch 1.1] Staff bootstrap contract + operator-ready session shell

## Batch

- [x] Batch 1 - Staff Bootstrap + Auth/RBAC Completion

## Intent

Finish the first operator-ready startup slice for `staff-web`: after staff login or session restore, the app should have one explicit backend-backed bootstrap source for granted capability state plus the minimum branch or readiness context needed to enter the shell without hidden manual fallback.

This issue is intentionally narrower than the full Batch 1 scope. It should produce one reviewable PR or a very small PR stack, not a mega-auth rewrite.

## Priority Alignment

- [x] This work stays inside `Auth / Identity / RBAC`, the top priority lane from `AGENTS.md`
- [x] The batch extends existing auth and session foundations instead of replacing them
- [x] Controller logic should stay thin; contract and auth behavior belong in middleware, support, service, and FE session wiring
- [x] Speculative cleanup and lower-priority operator surfaces are out of scope

## Problem Statement

Current `staff-web` session bootstrap restores `auth/staff/me`, but there is no dedicated staff bootstrap or readiness surface yet. The frontend can gate on granted `capabilities`, but it cannot preload the minimum operator-ready context in one explicit contract without relying on route-local follow-up reads and implicit assumptions.

Today:

- `staff-web/src/app/session.tsx` restores the session through `getCurrentStaffSession()`
- `staff-web/src/features/access/AccessPage.tsx` only distinguishes `capabilities` vs `known_capabilities`
- the backend staff auth flow already returns a useful session envelope, but Batch 1 still lacks the focused bootstrap/readiness shape called out in the roadmap

## In Scope

- [ ] Decide and implement the smallest backend-owned bootstrap contract for staff web startup
- [ ] Keep header-based staff auth intact with `X-Staff-Key`
- [ ] Expose granted capability state plus explicit readiness or branch context needed for startup
- [ ] Keep unauthorized and forbidden behavior on the standardized error envelope with `request_id`
- [ ] Update `staff-web` post-login and session-restore flow to rely on the explicit bootstrap source
- [ ] Keep `AccessPage` and route gating aligned to granted capability state and backend readiness, not FE guesswork
- [ ] Refresh FE contract artifacts and auth harness coverage if the response shape changes

## Out of Scope

- [ ] Kitchen, cashier, settlement, inventory, or reporting page work
- [ ] Rich cross-domain search or reservation lookup improvements
- [ ] Cookie-session auth, browser credential mode, or auth-stack redesign
- [ ] Broad capability remapping outside what is required for the startup surface
- [ ] Admin-side settings work beyond the minimum auth or capability contract needed here

## Likely Changed Files

- `routes/api/auth.php`
- `app/Http/Controllers/Api/Auth/StaffAuthController.php`
- `app/Services/Auth/OpaqueProductAuthService.php`
- `app/Support/StaffCapabilityResolver.php`
- `config/staff_capabilities.php`
- `config/cors.php`
- `config/api_artifacts.php`
- `app/Services/Harness/HarnessSuiteService.php`
- `tests/Feature/Auth/StaffProductAuthHttpFlowTest.php`
- `tests/Feature/Staff/StaffCapabilityHttpGuardTest.php`
- `tests/Feature/CorsContractTest.php`
- `tests/Feature/Console/BookingHarnessWebAuthCommandTest.php`
- `tests/Feature/Console/BookingHarnessFeContractCommandTest.php`
- `tests/Unit/Config/StaffAuthConfigContractTest.php`
- `tests/Unit/Config/StaffCapabilitiesConfigContractTest.php`
- `tests/Unit/Config/ApiArtifactsConfigContractTest.php`
- `tests/Unit/Http/Middleware/RequireStaffCapabilityTest.php`
- `staff-web/src/api/client.ts`
- `staff-web/src/app/session.tsx`
- `staff-web/src/app/router.tsx`
- `staff-web/src/components/Login.tsx`
- `staff-web/src/features/access/AccessPage.tsx`
- `staff-web/src/app/session.test.tsx`
- `staff-web/src/components/Login.test.tsx`

Shared seams, if touched:

- [ ] `routes/api.php`
- [ ] `config/booking.php`
- [x] `config/staff_capabilities.php`
- [ ] `database/schema/mysql-schema.sql`

## Verification

- [ ] `php artisan test tests/Feature/Auth/StaffProductAuthHttpFlowTest.php tests/Feature/Staff/StaffCapabilityHttpGuardTest.php tests/Feature/CorsContractTest.php`
- [ ] `php artisan test tests/Unit/Http/Middleware/RequireStaffCapabilityTest.php tests/Unit/Config/StaffAuthConfigContractTest.php tests/Unit/Config/StaffCapabilitiesConfigContractTest.php tests/Unit/Config/ApiArtifactsConfigContractTest.php`
- [ ] `composer api:artifacts`
- [ ] `php artisan booking:harness:web-auth --json`
- [ ] `php artisan booking:harness:fe-contract --json`
- [ ] `php artisan booking:doctor --json`
- [ ] `npm run test`
- [ ] `npm run build`
- [ ] `npm run smoke:live`

Use `npm run smoke:live` only when local UAT credentials or a safe preview environment are ready. It is evidence for the batch, not a reason to loosen auth or bootstrap behavior.

## Plugin Workflow

- [ ] `@github`: open this issue under the roadmap epic, link the PR, and keep CI or review comments attached to this scope only
- [ ] `@build-web-apps`: review login, access gate, and post-login shell behavior so startup remains operator-first instead of page-fragmented
- [ ] `@vercel`: validate preview login, session restore, and refresh behavior once `staff-web` preview deploys are connected
- [ ] `@sentry`: confirm auth and bootstrap failures surface with `request_id` and group cleanly instead of collapsing into generic network noise
- [ ] `@hugging-face`: out of scope

## Done When

- [ ] Staff login or session restore lands in one explicit operator-ready shell path
- [ ] `staff-web` gates navigation on granted capabilities plus backend-provided readiness or branch context, not on hidden fallback assumptions
- [ ] The bootstrap response shape is covered by auth-flow tests, FE contract checks, and harness output
- [ ] CORS and FE auth header expectations remain correct for split-web delivery
- [ ] Unauthorized or forbidden responses stay standardized and actionable in the UI

## Added or Updated Tests

- [ ] Update allow-path and deny-path coverage for staff login, `me`, refresh, and logout contract behavior
- [ ] Add or update capability guard regression coverage if startup routes or bootstrap reads depend on new capability checks
- [ ] Update FE session tests for bootstrap restore, expiry handling, and access-page gating
- [ ] Update contract or harness tests if the session envelope or generated SDK types change

## Remaining Risks

- [ ] Bootstrap payload scope can creep into a mini-dashboard if the contract is not kept narrow
- [ ] Changes under `config/staff_capabilities.php` can spill into later FOH, order, and finance batches
- [ ] Preview and Sentry evidence stay weaker until the project runs through a real GitHub + Vercel + Sentry loop

## Linked Evidence

- Epic:
- PR:
- Preview:
- Sentry:
- Docs or runbooks:

## Suggested Labels

- `roadmap`
- `batch-1`
- `auth`
- `rbac`
- `staff-web`
- `api-contract`

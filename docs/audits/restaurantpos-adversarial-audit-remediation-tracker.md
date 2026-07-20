# RestaurantPOS Adversarial Audit Remediation Tracker

**Program:** 2026-07-14 adversarial audit remediation

**Program state:** `ACTIVE`

**Production decision:** `NO-GO`

**Current cursor:** `B15 — durable notification delivery and truthful preference semantics (READY). B13 remains dependency-blocked by B09; B07 remains BLOCKED on external sandbox credentials/endpoints, so do not start B08.`

**Roadmap:** [`restaurantpos-adversarial-audit-remediation-roadmap.md`](restaurantpos-adversarial-audit-remediation-roadmap.md)

**Source audit:** [`restaurantpos-exhaustive-adversarial-audit-2026-07-14.md`](restaurantpos-exhaustive-adversarial-audit-2026-07-14.md)

**Last updated:** `2026-07-19`

## 1. How Codex continues the program

At the start of every remediation turn:

1. Read this tracker and the current batch section in the roadmap.
2. Recheck `git status --short` and preserve unrelated user changes.
3. Work only the current cursor or the exact prerequisite that blocks it.
4. Run the smallest focused verification first, then the required runtime gate.
5. Update this tracker with changed files, tests, evidence and remaining risks.
6. Advance the cursor only when the roadmap exit criteria are satisfied.

If an external credential/service blocks the cursor, mark it `BLOCKED` with the exact unblock condition. An independent batch may proceed only when it does not touch the same shared seam or hide the release blocker.

## 2. Status vocabulary

| Status | Meaning |
|---|---|
| `OPEN` | Finding/batch has not started |
| `READY` | Dependencies are satisfied and scope is available |
| `IN_PROGRESS` | Current active batch |
| `PARTIAL` | Existing work/evidence exists but exit criteria are not met |
| `CODE_FIXED` | Focused regression/static checks pass; runtime proof is still missing |
| `RUNTIME_VERIFIED` | Required external/runtime proof passes; closure review remains |
| `BLOCKED` | Exact external prerequisite is recorded |
| `CLOSED` | All exit gates and evidence requirements pass |
| `ACCEPTED_RISK` | Medium/Low only; approved owner and expiry required |

## 3. Current baseline

| Item | Current value |
|---|---|
| Audit target | `main@3080e439e8c715e83fb27aa39ba753a92dffc36f` |
| Execution branch | `chore/repository-evidence-cleanup` |
| Execution HEAD | `215e0e62` (B12 base; closure record follows) |
| Worktree | Dirty by design: 64 baseline paths across 8 owned scopes plus the bounded B01-B10 remediation paths recorded in execution history |
| Path inventory | [`restaurantpos-adversarial-audit-worktree-inventory-2026-07-15.md`](restaurantpos-adversarial-audit-worktree-inventory-2026-07-15.md) |
| Known diff hygiene | Global `git diff --check` reports whitespace in unrelated existing `staff-web` worktree changes; the bounded B10-B12 remediation paths pass targeted whitespace checks |
| Audit SHA-256 | `2710F57B46A6EAC13E9C845DF86D4A7CFE4FAF4C1440D429A5E39CEA608B7C7C` |
| PHP / Composer | PHP `8.4.0`; Composer `2.9.7` |
| Node / npm | Node `v24.15.0`; npm `11.12.1` |
| Docker | Client/server `29.4.0` available |
| MySQL | MySQL 8 client installed under `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe`; not on PATH |
| Redis | CLI not on PATH; Docker/runtime path still available |

## 4. Progress summary

| Severity | Total | Open | Partial | Code fixed | Runtime verified | Closed |
|---|---:|---:|---:|---:|---:|---:|
| Critical | 6 | 1 | 2 | 1 | 0 | 2 |
| High | 15 | 6 | 1 | 1 | 0 | 7 |
| Medium | 8 | 6 | 0 | 0 | 0 | 2 |
| Low | 1 | 1 | 0 | 0 | 0 | 0 |
| **Total** | **30** | **14** | **3** | **2** | **0** | **11** |

`PARTIAL` does not count as remediated or release-safe.

## 5. Batch board

| Batch | Status | Findings | Dependencies | Exit evidence |
|---|---|---|---|---|
| B00 Baseline freeze | `CLOSED` | Program governance | None | 64/64 paths classified; baseline/package/hash evidence recorded |
| B01 Immediate containment | `CLOSED` | C-01, C-03, H-04, H-06 | B00 | Code containment passes; GitHub `Production` requires review and permits only `main`; strict runtime preflight passes with zero errors/warnings |
| B02 Safe fresh bootstrap | `CLOSED` | C-06 containment | B00 | MySQL 8 guard matrix, bounded single-session lease, docs and both wrappers verified |
| B03 Branch scope/customer role | `CLOSED` | C-04, M-05 | B00-B02 | Seven C-04 surfaces and three M-05 customer-eligibility entry points pass the SQLite/MySQL actor/resource matrix |
| B04 Terminal preorder/order | `CLOSED` | C-05 | B03 preorder scope | Terminal/bill/payment guards, canonical locks, deploy reconciliation and MySQL convert-vs-settlement one-winner race pass |
| B05 VND/VNPay money contract | `CLOSED` | H-01, H-02 | B01 | Integer PHP/MySQL/provider/JSON/TypeScript contract, signed VNPay lifecycle proof and disposable MySQL 8 bootstrap pass |
| B06 Provider operation/event model | `CLOSED` | C-01/C-02/C-03 foundation, H-08, M-02 | B05 | Durable Pending/Unknown/Failed/evidence-backed Succeeded operations, immutable ordered events, unapplied funds, reconciliation worker/metrics and MySQL uniqueness/concurrency proof |
| B07 Verified provider confirmation | `BLOCKED` | C-01, H-08 | B06 | Code/MySQL/browser gates pass; real-provider sandbox callback/reorder evidence cannot run until credentials and endpoints are supplied |
| B08 Deposit representation | `OPEN` | C-02 | B06-B07 | Parallel/late capture reconciliation |
| B09 Provider capture/refund | `OPEN` | C-03, M-02 | B06-B08 | Provider sandbox refund/recovery |
| B10 Dependencies/CI | `CLOSED` | H-06 | B00 | Production High/Critical audit is clean; customer/staff mandatory suites and hosted `dependency-security` pass; retained audit/SBOM artifact is verified; `main` requires the exact GitHub Actions check |
| B11 KDS substitution | `CLOSED` | H-03 | B04 | Same-category available pre-dispatch swap passes; queued/fired ticket identity is immutable; item/category/route/station drift is inspectable; original recipe consumption remains aligned |
| B12 Recipe/wastage | `CLOSED` | H-12, M-06 | B11 | Order-time recipe snapshot, auditable retry-safe KDS wastage and parallel MySQL receive/consume/adjust stock equation pass |
| B13 Reporting/date/ETA | `OPEN` | H-09/H-10/H-11, M-04 | B03, B09 | Golden ledger/date/ETA dataset |
| B14 Critical audit | `CLOSED` | H-13, H-14 | B02 | Shared pre-sink sanitizer, keyed session correlation, fail-closed transactional persistence, safe alert capture and disposable MySQL rollback proof |
| B15 Notifications | `READY` | H-07, M-07, M-08 | B02 | Scheduler/worker crash drills |
| B16 Cashier reconciliation | `OPEN` | M-01 | B09 | Independent manager matrix |
| B17 Contract/encoding gates | `OPEN` | M-03, L-01 | B00 | Green truthful parity/encoding gates |
| B18 SQL-safe deployment | `OPEN` | H-04, C-06 final | B02, B10, schema batches | Upgrade/deploy/rollback rehearsal |
| B19 Backup/DR | `OPEN` | H-05, H-15 | B18 design | Streaming backup + isolated restore drill |
| B20 Reassessment | `OPEN` | All | B03-B19 | Clean evidence pack + new verdict |

## 6. Finding ledger

| ID | Severity | Batch | Status | Closure evidence |
|---|---|---|---|---|
| C-01 | Critical | B01/B06/B07 | `CODE_FIXED` | Browser claims cannot capture real-provider funds; signed webhook/authenticated provider responses must match provider/session/scope/merchant/amount/currency and carry provider sequence/time. SQLite/MySQL regressions pass; real-provider sandbox proof is externally blocked |
| C-02 | Critical | B06/B08 | `OPEN` | B06 added durable unapplied-funds records for late verified success; B08 still owns the complete parallel/late capture accounting and operator path |
| C-03 | Critical | B01/B06/B09 | `PARTIAL` | Durable provider operations and refund lineage fields now exist, while production-like staff mutations remain cash-only; provider-aware capture/refund execution remains B09 |
| C-04 | Critical | B03 | `CLOSED` | All seven audited surfaces are actor-scoped; board direct/timeline suggested/best-fit and preorder show/confirm/reject/convert return 404 for branch B, reassert after locks, preserve PII/state snapshots, and pass disposable MySQL 8 proof |
| C-05 | Critical | B04 | `CLOSED` | Preorder conversion and KDS dispatch require a locked in-service, unbilled reservation; conversion locks related orders/items, bill sessions and payments, settlement observes reservation row-version, deploy preflight reconciles terminal-active drift, and the disposable MySQL race proves exactly one conversion/settlement winner |
| C-06 | Critical | B02/B18 | `PARTIAL` | Fresh bootstrap containment is MySQL-verified: 45 focused tests / 670 assertions, 240 release-contract tests / 4,164 assertions, empty/non-empty/production/wrong-target/concurrent matrix and both wrappers pass; B18 forward-only upgrade/deploy proof remains |
| H-01 | High | B05 | `CLOSED` | VNPay validates unsigned provider minor units, requires divisibility by 100 and returns an integer VND amount; a signed callback traverses adapter, lifecycle and ledger exactly once, including replay |
| H-02 | High | B05 | `CLOSED` | VND-only integer rules cover write requests/services, scale-zero MySQL artifacts, generated API/TypeScript contracts and both web clients; non-VND/fractional requests and legacy rows fail closed |
| H-03 | High | B11 | `CLOSED` | Component swap locks and validates its combo parent/target, rejects unavailable or cross-category targets, and fails once any KDS ticket exists. Regression proof covers queued and fired tickets, unchanged failed-mutation snapshots, original-recipe stock consumption, and inspector drift across item/category/route/station |
| H-04 | High | B01/B18 | `PARTIAL` | Main-push deployment removed; manual `production` Environment and candidate preflight added; external protection proof and B18 immutable SQL-safe deploy remain |
| H-05 | High | B19 | `OPEN` | — |
| H-06 | High | B01/B10 | `CLOSED` | Automatic production activation remains removed. Production High/Critical audits are clean; both mandatory frontend lanes pass; hosted run `29651941195` archived audit/policy/CycloneDX evidence for all three workspaces; and `main` requires the exact GitHub Actions `dependency-security` check with strict status checks |
| H-07 | High | B15 | `OPEN` | — |
| H-08 | High | B06/B07 | `CODE_FIXED` | Provider sequence takes precedence over provider time; newer verified success recovers failed/cancelled/expired sessions, delayed lower sequence is ignored, and newer failure after capture preserves funds plus marks `ManualReview`. Real-provider sandbox reorder proof is externally blocked |
| H-09 | High | B13 | `OPEN` | — |
| H-10 | High | B13 | `OPEN` | — |
| H-11 | High | B13 | `OPEN` | — |
| H-12 | High | B12 | `CLOSED` | Every new order item commits an ordered recipe-line JSON snapshot; post-order recipe edits cannot change serve-time stock movement, combo components own their snapshots, pre-dispatch substitution refreshes the replacement snapshot, and replay emits exactly one stable consumption movement |
| H-13 | High | B14 | `CLOSED` | Payment/refund/cashier, inventory ingredient and staff API-key events are centrally classified as critical; their recorder runs inside the business transaction, missing/invalid persistence throws, safe alert evidence is emitted, and SQLite plus disposable MySQL failure injection proves the API-key mutation rolls back |
| H-14 | High | B14 | `CLOSED` | `AuditEvent` sanitizes a single envelope before file and database sinks; HTTP request audit uses the same sanitizer; guest/contact/credential/IP fields are redacted; customer session identifiers and actor keys use keyed HMAC-SHA256; sink-capture tests reject every seeded raw value on SQLite and MySQL |
| H-15 | High | B19 | `OPEN` | — |
| M-01 | Medium | B16 | `OPEN` | — |
| M-02 | Medium | B06/B09 | `OPEN` | B06 added durable refund subject/lineage identifiers and constraints; B09 still owns provider-aware refund recovery semantics |
| M-03 | Medium | B17 | `OPEN` | — |
| M-04 | Medium | B13 | `OPEN` | — |
| M-05 | Medium | B03 | `CLOSED` | Central fail-closed customer role-ID guard is enforced for reservation/waitlist attachment, waitlist seat role drift and loyalty adjust/completion award under locked user rows; Customer/Admin/Staff/deleted/nonexistent matrix is green on SQLite and MySQL |
| M-06 | Medium | B12 | `CLOSED` | `InProgress → Cancelled` records a negative `Wastage` ledger movement from the committed recipe with staff actor and stable reference; direct pre-start cancellation remains movement-free, Served remains terminal, and replay cannot duplicate wastage |
| M-07 | Medium | B15 | `OPEN` | — |
| M-08 | Medium | B15 | `OPEN` | — |
| L-01 | Low | B17 | `OPEN` | — |

## 7. B03 execution checklist

| Slice | Scope | Status | Required proof |
|---|---|---|---|
| B03.1 | Reservation timeline and analytics overview reads | `CLOSED` | Omitted branch is restricted with `whereIn(accessible_branch_ids)`; explicit inaccessible branch returns 404; A visible/B hidden; no PII, row-version or outbox side effects; timeline performance budget green |
| B03.2 | Staff waiting-list list/create/update/cancel/convert mutations | `CLOSED` | List summary/page share one actor scope; create chooses an accessible primary branch; notify/seat/cancel/advance reassert entry, hold and table branches after locks; branch-B and branch-drift denial is 404 with unchanged waitlist, hold, table, reservation, outbox, audit and row-version state |
| B03.3 | Staff reservation create and table-hold override | `CLOSED` | Assigned staff can create/override in A; explicit branch/table/hold B and hold-table/reservation branch drift return 404; staff key takes precedence over supplied session ownership; locked rows are reasserted; reservations, pivots, holds, tables, expired holds, outbox, audit and row versions remain unchanged on denial |
| B03.4 | Reservation board assignment and preorder reads/mutations | `CLOSED` | Reservation/table/order scope at lookup and again after locks; direct/timeline suggested/best-fit fast paths and preorder show/confirm/reject/convert return 404 for branch B with unchanged pivots, rows, outbox, audit and row versions |
| B03.5 | Central customer-role eligibility for reservation, waitlist and loyalty plus full B03 matrix | `CLOSED` | Only eligible Customer identities can be attached or awarded; Admin/Staff/deleted/nonexistent actors are denied without state/PII leakage; all seven C-04 surfaces and three M-05 entry points pass the generated matrix |

B03.1-B03.5 changed only bounded identity/application/query/service, controller and feature-test paths. They did not touch routes, capability configuration, request/resource contracts, config values, schema, SQL artifacts or web clients. C-04, M-05 and B03 are `CLOSED`; B04 is also `CLOSED`; production remains `NO-GO`.

## 8. B02 closure evidence and remaining C-06 gap

Existing worktree implementation paths:

- `tools/mysql/BootstrapTargetSafety.php`
- `tools/mysql/BootstrapReleaseWorkflow.php`
- `tools/mysql/bootstrap_release.php`
- `tools/mysql/bootstrap_release.cmd`
- `tools/mysql/bootstrap_release.sh`
- `tests/Unit/Infrastructure/DatabaseBootstrapSafetyGuardTest.php`
- `tests/Unit/Infrastructure/DatabaseReleaseContractArtifactSyncTest.php`
- bootstrap README/runbook changes

Closure verification recorded on MySQL `8.0.46`:

- focused bootstrap/contract tests: 45 passed, 670 assertions;
- `composer test:release-contract`: 240 passed, 4,164 assertions;
- PHP syntax, targeted Pint, targeted `git diff --check`, and package integrity (52/52 checks) passed;
- disposable empty target imported the canonical schema plus 68 naturally ordered patches and completed `verify_release_contract.sql`;
- a pre-existing sentinel target was denied with `destructive_bootstrap.guard_denied`, preserving one sentinel row and exactly one table;
- production and wrong allowlisted target were denied before database creation;
- two concurrent bootstraps produced one verified winner and one guard denial; the winner contained 74 tables;
- `.cmd` and `.sh` wrappers both forwarded JSON/arguments and failed closed before MySQL resolution in production;
- `booking:doctor --json`, no-write release manifest, and strict deploy preflight passed; strict preflight recorded zero errors/warnings at `storage/app/booking_release/deploy_checks/reports/booking-deploy-check-preflight-strict-20260715t162432z.json`;
- all five disposable `codex_b02_*` databases were removed after verification.

The real MySQL run exposed and this batch fixed two additional runtime gaps: a leading UTF-8 BOM in one patch broke the assembled single-session stream, and the database password was still present in process arguments. Artifact-boundary BOM normalization and `MYSQL_PWD` child-environment handling now have regression coverage. SQL schema/patch/dump contents were not changed.

Remaining C-06 work is intentionally owned by B18: implement and rehearse the separate forward-only upgrade/deploy/rollback path. B02 closes the fresh-bootstrap containment batch but does not make C-06 or production release readiness `CLOSED`.

## 9. Live dependency snapshot

Observed from the current lockfiles and refreshed local B10 evidence on `2026-07-18`:

| Workspace | Production audit | Direct issue |
|---|---|---|
| Root | 0 | None; root has no production dependencies |
| `staff-web` | 0 | `react-router-dom@6.30.4`; no unresolved production advisory |
| `customer-web` | 2 moderate, 0 high/critical | `next@16.2.10`; remaining Moderate report is transitive `postcss`, while the npm force proposal is a breaking downgrade and was rejected |

The local and hosted policy decisions are `pass` because `high + critical = 0` in all three roots. Hosted run [`29651941195`](https://github.com/DuongVinh2004/RestaurantPOS/actions/runs/29651941195) archived `dependency-security-evidence-ci-29651941195` (artifact id `8431761804`, 42,654 bytes, created `2026-07-18T16:30:36Z`, expires `2026-08-17T16:30:35Z`) with the summary plus per-root production audit JSON, policy summary and CycloneDX 1.5 SBOM. The hosted SBOM SHA-256 digests are root `a91cfa9af691e6819f8d59b45fa4da7091b2baef37056bb0ecde2e085ed84542`, customer `3179246598b09385507cdd6061a4fc1df42a96b4bca1ab6a26e6189a46bf57c9`, and staff `684e25721755344eb7415d84cc3cf5a038f60f3fbba9bd07ac357c076ed178ec`. Classic protection for `main` was read back with `strict=true` and required check `dependency-security` bound to GitHub Actions app id `15368`; the existing repository ruleset was preserved.

## 10. Decision log

| Date | Decision | Reason |
|---|---|---|
| 2026-07-15 | Treat the 2026-07-14 audit as production-readiness authority | It contains concrete Critical call chains newer than prior scorecards/roadmaps |
| 2026-07-15 | Preserve the audit report unchanged | Source evidence must remain reproducible |
| 2026-07-15 | Use VND-only and one certified provider for the lean launch | Minimizes H-02/provider scope while protecting core money truth |
| 2026-07-15 | Serialize schema/payment/shared seams | Prevents incomplete SQL-first batches and merge collisions |
| 2026-07-15 | Keep production `NO-GO` until B20 | Unit/SQLite or old launch-readiness evidence cannot close the audit |

## 11. Batch execution history

### 2026-07-15 — Program initialization

- Intent: create a durable in-project roadmap and live execution tracker.
- Changed files: audit index, remediation roadmap, remediation tracker and supersession notices in historical planning documents.
- Added/updated tests: documentation-only batch; no product tests added.
- Verification run: 30/30 finding IDs mapped; B00-B20 present; 11 relevant documents checked with zero broken relative links; audit source/repo hashes match; remediation documents pass targeted whitespace checks.
- Evidence paths: this tracker and the roadmap.
- Shared seams touched: none.
- Remaining risks: dirty worktree still needs B00 ownership classification; unrelated staff-web whitespace remains; C-06 remains partial.
- Next cursor: B00.

### 2026-07-15 — B00 — Freeze and classify the working baseline

- Intent: assign every dirty/untracked path to a reviewable owner without discarding user work.
- Changed files: added the B00 worktree inventory; updated the audit index and live tracker.
- Added/updated tests: documentation/governance batch; no product tests added.
- Verification run: 44 tracked changes plus 20 untracked files classified into 8 scopes; package integrity passed 52 checks with 0 missing/stale/contract failures; audit hashes match; staff-web typecheck passed; staff-web lint failed with 132 errors/4 warnings; CORS contract ran 9 tests with 1 failure at the credentials-disabled invariant.
- Evidence paths: [`restaurantpos-adversarial-audit-worktree-inventory-2026-07-15.md`](restaurantpos-adversarial-audit-worktree-inventory-2026-07-15.md).
- Shared seams touched: none.
- Remaining risks: worktree remains dirty by design; B02 remains partial; CORS and staff-web scopes are quarantined; smoke results and screenshots lack clean-commit provenance.
- Finding status changes: none.
- Next cursor: B01.

### 2026-07-15 — B01 — Immediate production and provider containment

- Intent: stop automatic production deployment and prevent unverified customer/staff external-money paths from claiming success before the structural provider workstreams.
- Changed files: manual/protected production CD workflow; payment rollout config and `.env.example`; VNPay, MoMo and Generic HMAC adapters; one cash-only staff containment policy applied to final capture, deposit capture and refund; CI/deploy/payment runbooks; focused unit and HTTP tests.
- Added/updated tests: added `BookingCdContainmentContractTest`; expanded provider rollout/adapter/config/readiness tests; added negative customer deposit/bill confirm tests and production-like staff capture/deposit/refund tests with zero payment/refund and unchanged financial snapshot assertions.
- Verification run: final changed set passed 101 tests / 641 assertions; shared-config collateral suite passed 36 tests / 401 assertions; `booking:round5-gate --json` passed 15/15 suites; targeted Pint and PHPStan passed; Symfony YAML parser passed; targeted `git diff --check` passed.
- Strict preflight evidence: the initial runtime run exposed stale scheduler/reporting state; after starting the managed runtime, rebuilding 90 days of reporting read models, and reviewing the identified local UAT conversation through the audited staff API, `booking:deploy-check --mode=preflight --strict --json` returned `ok=true` with zero errors and warnings.
- Evidence paths: `.github/workflows/booking-cd.yml`, `docs/runbooks/booking-ci-cd-runbook.md`, `docs/runbooks/booking-deploy-runbook.md`, `tests/Unit/Infrastructure/BookingCdContainmentContractTest.php`, the passing local deploy report at `storage/app/booking_release/deploy_checks/reports/booking-deploy-check-preflight-strict-20260715t160204z.json`, and GitHub Environment `Production` (`environment_id=15071721209`, verified required reviewer `DuongVinh2004`, `main` branch policy id `54733859`).
- Shared seams touched: `config/booking.php` only; the diff is limited to provider activation evidence and the production-like cash-only staff mutation boundary, with config/runtime/readiness collateral tests green.
- Remaining risks: the temporary single-owner environment permits self-review and GitHub reports `can_admins_bypass=true`; independent approval/no-bypass governance plus immutable package deployment remain B18. Provider event/capture/refund state remains B06-B09, and dependency/CI remediation remains B10.
- Finding status changes: C-01, C-03, H-04 and H-06 moved from `OPEN` to `PARTIAL`; none is remediated or release-safe.
- Next cursor: B02; keep production `NO-GO`.

### 2026-07-15 — B01 — Runtime and production-environment closure evidence

- Intent: clear the remaining B01 runtime blockers and prove that the production deployment job is gated by a live GitHub Environment protection contract.
- Changed files: remediation tracker only; runtime reporting read models and one empty local UAT conversation were updated through official commands/APIs; GitHub Environment `Production` was configured externally.
- Added/updated tests: no new product tests; this pass completed runtime and external control-plane verification for the already-tested B01 implementation.
- Verification run: `npm run runtime:up`; scheduler heartbeat touch; reporting snapshot rebuilds for 14 and then 90 days; `booking:doctor --json` passed; `notifications:outbox-health --json` passed; `booking:deploy-check --mode=preflight --strict --json` passed with zero errors/warnings; `npm run runtime:preflight` returned `decision=pass`; `booking:release-manifest --json` returned `ok=true`.
- Evidence paths: `storage/app/booking_release/deploy_checks/reports/booking-deploy-check-preflight-strict-20260715t160204z.json`; `storage/app/booking_release/release_manifest/reports/booking-release-manifest-snapshot-20260715t160221z.json`; GitHub Environment API readback for environment id `15071721209` and branch policy id `54733859`.
- Shared seams touched: none in this closure pass; the earlier B01 `config/booking.php` change remains covered by its recorded collateral tests.
- Remaining risks: C-01/C-03/H-04/H-06 remain `PARTIAL` until B06-B10/B18 complete the durable provider, dependency/CI and immutable deployment architecture. The current single-owner environment allows self-review and administrator bypass so the repository remains `NO-GO`.
- Finding status changes: none; B01 batch moved from `PARTIAL` to `CLOSED` because its temporary-containment exit evidence is now complete.
- Next cursor: B02 — Safe fresh bootstrap.

### 2026-07-15 — B02 — Safe fresh bootstrap closure

- Intent: make fresh SQL-first bootstrap fail closed for production, unknown, unauthorized and non-empty targets while preserving a separate future forward-only upgrade path.
- Changed files: `tools/mysql/BootstrapTargetSafety.php`, `tools/mysql/BootstrapReleaseWorkflow.php`, `tools/mysql/bootstrap_release.php`, `.cmd` and `.sh` delegates, bootstrap/database/root README guidance, the Windows local runbook, two infrastructure contract test files, and this tracker. No schema, patch or dump content was changed.
- Added/updated tests: added `DatabaseBootstrapSafetyGuardTest`; expanded `DatabaseReleaseContractArtifactSyncTest` for single-session ordering, wrapper delegation, BOM normalization, bounded child execution, credential handling, stable guard denial, fail-closed target validation and docs parity.
- Verification run: 45 focused tests / 670 assertions; `composer test:release-contract` 240 tests / 4,164 assertions; targeted Pint; PHP syntax; targeted `git diff --check`; package integrity 52/52; MySQL 8.0.46 disposable matrix for empty success, non-empty denial with sentinel preservation, production denial, wrong-target denial and one-winner concurrent execution; real Windows CMD and Git Bash shell wrapper denial; doctor, no-write release manifest and strict deploy preflight all passed.
- Evidence paths: `storage/app/booking_release/bootstrap_safety/b02-concurrent-a-20260715T162011Z.json`, `storage/app/booking_release/bootstrap_safety/b02-concurrent-b-20260715T162011Z.json`, `storage/app/booking_release/deploy_checks/reports/booking-deploy-check-preflight-strict-20260715t162432z.json`, `storage/app/booking_release/doctor/reports/booking-doctor-default-20260715t162431z.json`, the B02 implementation/tests listed above, and this tracker.
- Shared seams touched: the canonical bootstrap entrypoint and operator documentation only. The shell wrappers now delegate exactly once; SQL schema/patch/dump artifacts remain byte-unchanged by this batch.
- Remaining risks: C-06 remains `PARTIAL` until B18 supplies and rehearses the separate forward-only upgrade, immutable deploy and rollback contract. MySQL DDL remains non-transactional, so partial disposable targets must be discarded after independently confirming their identity. Production remains `NO-GO`.
- Finding status changes: C-06 remains `PARTIAL`; B02 moved from `PARTIAL` to `CLOSED` because its fresh-bootstrap containment exit evidence is complete.
- Next cursor: B03 — Branch scope/customer role.

### 2026-07-15 — B03.1 — Scope reservation timeline and analytics reads

- Intent: close the two read-only C-04 leak surfaces first by making authenticated staff branch entitlement mandatory at the service/query boundary without changing route or schema seams.
- Changed files: `app/Modules/FloorOperations/Http/Controllers/Staff/ReservationTimelineController.php`, `app/Modules/FloorOperations/Application/Queries/Timeline/StaffReservationTimelineService.php`, `app/Modules/Reporting/Application/Queries/Analytics/GetAnalyticsOverviewHandler.php`, `tests/Feature/Staff/StaffReservationTimelineFlowTest.php`, `tests/Feature/Staff/Reporting/AnalyticsOverviewControllerTest.php`, and this tracker.
- Added/updated tests: added timeline and analytics A/B assignment matrices; updated the positive explicit-branch timeline case to establish the required staff assignment. The negative matrix proves 404 for branch B, no branch-B code/name/note exposure, unchanged reservation `row_version`, and unchanged notification outbox count.
- Verification run: focused timeline/analytics suites 15 tests / 104 assertions; final timeline/analytics/branch-context/performance set 29 tests / 185 assertions; `composer test:security` 47 tests / 321 assertions; targeted PHPStan and Pint passed; the full hot-path performance budget file passed, including the timeline candidate-preview ceiling; `booking:doctor --json` returned `ok=true` with database, Redis, scheduler and outbox runtime checks passing.
- Evidence paths: the three implementation files and two feature-test files listed above; `storage/app/booking_release/doctor/reports/booking-doctor-default-20260715t164304z.json`; command output is recorded in the active Codex task.
- Shared seams touched: none. Routes, capability maps, auth middleware, schema/patch/dump artifacts, API contract artifacts and web clients were unchanged.
- Remaining risks: C-04 still has five audited surfaces: waitlist, reservation create, hold override, board assignment and preorder. M-05 central customer eligibility is not yet implemented. Multi-branch candidate preview still uses the existing in-memory reservation/table branch match and remains within its query budget. Production remains `NO-GO`.
- Finding status changes: C-04 moved from `OPEN` to `PARTIAL`; M-05 remains `OPEN`; B03 moved from `READY` to `IN_PROGRESS`; only B03.1 is `CLOSED`.
- Next cursor: B03.2 — staff waiting-list actor/branch scope and transaction reassertion.

### 2026-07-15 — B03.2 — Scope staff waiting-list reads and mutations

- Intent: make branch entitlement mandatory for staff waiting-list list/create/notify/seat/cancel/advance and reassert resource branch after database locks before any state change.
- Changed files: `app/Modules/Waitlist/Http/Controllers/Staff/WaitlistController.php`, `app/Modules/Waitlist/Application/Services/StaffWaitingListService.php`, `app/Modules/Waitlist/Application/Services/WaitingListOperationalOrchestrationService.php`, new `tests/Feature/Staff/StaffWaitingListBranchIsolationTest.php`, updated lifecycle/semi-automation tests, and this tracker.
- Added/updated tests: added assigned-branch A/B list/create/mutation coverage; explicit branch B and branch-B entry/table requests return 404 without PII; a branch-A hold linked to a branch-B released table proves branch drift is rejected before advance mutation; snapshots prove waiting-list rows, holds, tables, reservations, reservation-table pivots, notification outbox, audit log and row versions remain unchanged. Existing non-default-branch positive tests now declare staff assignments explicitly.
- Verification run: final waitlist/lifecycle/semi-automation/performance set 31 tests / 253 assertions; `composer test:security` 47 tests / 321 assertions; targeted PHPStan and Pint passed; the complete hot-path budget file passed with the staff waiting-list ceiling unchanged; `booking:doctor --json` returned `ok=true` with database, Redis, scheduler and outbox checks passing.
- Evidence paths: the implementation and test files listed above; `storage/app/booking_release/doctor/reports/booking-doctor-default-20260715t165939z.json`; command output is recorded in the active Codex task.
- Shared seams touched: none. Routes, capability maps, request/resource contracts, schema/patch/dump artifacts, API contract artifacts and web clients were unchanged.
- Remaining risks: C-04 still has reservation create, staff hold override, board assignment and preorder surfaces. M-05 central customer-role eligibility remains open. The scheduled cross-branch `expireNotifiedEntries` system operation is intentionally actorless and unchanged. Production remains `NO-GO`.
- Finding status changes: C-04 remains `PARTIAL`; M-05 remains `OPEN`; B03 remains `IN_PROGRESS`; B03.2 moved from `READY` to `CLOSED`.
- Next cursor: B03.3 — staff reservation create and table-hold override branch scope.

### 2026-07-16 — B03.3 — Scope staff reservation create and table-hold override

- Intent: require authenticated staff branch entitlement for reservation creation and every sessionless hold read/cancel/refresh, while preserving customer/session ownership and proving denial before maintenance or mutation side effects.
- Changed files: `app/Modules/Reservations/Http/Controllers/Customer/ReservationController.php`, `app/Modules/Reservations/Application/Services/ReservationService.php`, `app/Modules/BranchScheduling/Http/Controllers/Guest/TableHoldController.php`, `app/Modules/BranchScheduling/Application/Services/TableHoldService.php`, new `tests/Feature/Reservation/StaffReservationCreateAndHoldBranchIsolationTest.php`, updated `tests/Feature/Table/TableHoldHttpFlowTest.php`, and this tracker.
- Added/updated tests: added an assigned staff A/B matrix for direct table and hold-backed reservation create; added staff show/refresh/cancel denial for branch-B holds, hold-A/table-B drift and hold-A/reservation-B drift; requests include both a valid staff key and `session_id` to prove session ownership cannot downgrade the staff branch contract. Snapshots cover reservation rows/pivots, holds/details, tables, an unrelated expired hold, notification outbox, audit log and row versions.
- Verification run: regression-first B03.3/table-hold set passed 12 tests / 159 assertions; reservation ownership/shared-detail/hold-row-version/waitlist collateral passed 36 tests / 236 assertions; full `tests/Feature/Reservation` plus `tests/Feature/Table` passed 150 tests / 1,042 assertions; `composer test:security` passed 47 tests / 321 assertions; targeted Pint and PHPStan passed. A guarded SQL-first disposable MySQL 8 database imported the canonical schema plus 68 patches, passed release-contract verification, then passed the B03.3/table-hold set again at 12 tests / 159 assertions and was removed. `booking:doctor --json` returned `ok=true` with database, Redis, scheduler and outbox checks passing.
- Evidence paths: the implementation and test files listed above; `storage/app/booking_release/doctor/reports/booking-doctor-default-20260715t172038z.json`; command output is recorded in the active Codex task.
- Shared seams touched: none. Routes, capability maps, auth middleware, request/resource contracts, schema/patch/dump artifacts, API contract artifacts and web clients were unchanged.
- Remaining risks: C-04 still has board assignment and preorder surfaces. M-05 central customer-role eligibility remains open. The full repository suite and a parallel branch-assignment-revocation race harness were not run; the focused domain/security suites and disposable MySQL lock/query execution are green. Production remains `NO-GO`.
- Finding status changes: C-04 remains `PARTIAL`; M-05 remains `OPEN`; B03 remains `IN_PROGRESS`; B03.3 moved from `READY` to `CLOSED`; B03.4 moved from `OPEN` to `READY`.
- Next cursor: B03.4 — reservation board assignment and preorder branch scope.

### 2026-07-16 — B03.4 — Scope reservation board assignment and staff preorder

- Intent: make staff branch entitlement mandatory for direct/timeline suggested and best-fit board assignment plus preorder show/confirm/reject/convert, including idempotent fast paths and post-lock linkage revalidation.
- Changed files: `StaffReservationBoardAssignmentService`, `StaffReservationPreorderService`, `StaffReservationPreorderController`, updated `StaffReservationBoardAssignmentFlowTest`, new board/preorder branch-isolation feature tests, and this tracker.
- Added/updated tests: added an A-only actor matrix for all four board routes and four preorder routes; positive branch-A assignment/show/confirm/reject/convert; branch-B reservation IDs, selected table IDs and already-assigned fast paths; simulated assignment revocation between board preview and transaction; preorder plus shadow reservation-order linkage snapshots. Denials assert 404/no PII and unchanged reservations/row versions, reservation pivots, table status/row versions, preorders/items, reservation orders/items, holds, notification outbox and audit logs.
- Verification run: regression-first run reproduced seven branch-B failures returning 200; final new regression set passed 9 tests / 240 assertions. Existing board/preorder/read collateral passed 46 / 363 and board/order-lifecycle collateral passed 49 / 250, for 104 focused tests / 853 assertions including the new set. `composer test:security` passed 47 / 321; targeted Pint and PHPStan passed. A guarded SQL-first disposable MySQL 8 database imported the canonical schema plus 68 patches, passed release-contract verification, reran the new set at 9 / 240, and was removed. `booking:doctor --json` returned `ok=true`.
- Evidence paths: the implementation/tests listed above; `storage/app/booking_release/doctor/reports/booking-doctor-default-20260715t214546z.json`; disposable database `codex_b034_20260716_044512` was verified removed; command output is recorded in the active Codex task.
- Shared seams touched: none. Routes, capability maps, auth middleware, request/resource contracts, config, schema/patch/dump artifacts, generated API contracts and web clients were unchanged.
- Remaining risks: B04 still owns preorder terminal-state/settlement conversion safety and was intentionally not changed. B03.5 still must centralize M-05 customer-role eligibility and rerun the complete B03 actor/resource matrix. The full repository suite and a true parallel branch-assignment revocation race harness were not run; focused SQLite/MySQL lock/query, collateral, security and runtime gates are green. Production remains `NO-GO`.
- Finding status changes: C-04 moved from `PARTIAL` to `CLOSED`; M-05 remains `OPEN`; B03 remains `IN_PROGRESS`; B03.4 moved from `READY` to `CLOSED`; B03.5 moved from `OPEN` to `READY`.
- Next cursor: B03.5 — customer-role eligibility.

### 2026-07-16 — B03.5 — Centralize customer-role eligibility

- Intent: make the configured customer role-ID contract the single service-boundary answer for whether a user may be attached to reservation/waitlist or receive loyalty awards, including revalidation on locked user rows.
- Changed files: new `CustomerIdentityEligibilityService`; `CustomerAuthTokenResolver`; `ReservationService`; `StaffWaitingListService`; `LoyaltyPointsService`; `StaffServiceSessionService`; new `StaffCustomerIdentityEligibilityTest`; updated the direct-service preorder pricing fixture; and this tracker.
- Added/updated tests: added Customer/Admin/Staff/deleted/nonexistent matrices for staff reservation create, staff waiting-list create and loyalty adjustment; added waitlist seat role-drift rollback and completion-sync denial for an operational account. The regression-first run reproduced five failures: reservation and waitlist accepted Admin, role-drift seat succeeded, loyalty adjustment accepted Admin, and completion awarded 10 points plus a tier to Staff.
- Verification run: the new regression set passed 5 tests / 83 assertions; the generated full B03 matrix passed 79 / 937; auth, customer actor, walk-in and loyalty collateral passed 35 / 207; full Auth/Customer collateral passed 70 / 516; full Reservation/Table/WaitingList collateral passed 173 / 1,391; `composer test:security` passed 47 / 321; targeted Pint, PHPStan and `git diff --check` passed. A guarded SQL-first disposable MySQL 8 database imported the canonical schema plus 68 patches, passed release-contract verification, reran the new set at 5 / 83, and was removed with zero matching schemas remaining. `booking:doctor --json` returned `ok=true`.
- Evidence paths: the implementation/tests listed above; `storage/app/booking_release/doctor/reports/booking-doctor-default-20260716t022023z.json`; disposable database `codex_b035_20260716_021907` was verified removed; command output is recorded in the active Codex task.
- Shared seams touched: the new IdentityAccess eligibility service and the existing customer token resolver now share the same fail-closed `customer_auth.allowed_role_ids` contract. Routes, capability maps, middleware order, request/resource contracts, config values, schema/patch/dump artifacts, generated API contracts and web clients were unchanged.
- Remaining risks: the full repository suite and a true parallel role-change race harness were not run; the locked-row SQLite/MySQL role-drift matrix and broad domain/security collateral are green. B04 still owns preorder terminal-state/settlement conversion safety. Production remains `NO-GO`.
- Finding status changes: M-05 moved from `OPEN` to `CLOSED`; C-04 remains `CLOSED`; B03 and B03.5 moved to `CLOSED`; B04 moved from `OPEN` to `READY`.
- Next cursor: B04 — terminal preorder/order guard.

### 2026-07-16 — B04 — Enforce terminal preorder/order and KDS dispatch guards

- Intent: prevent preorder conversion or KDS dispatch from creating new active, dispatchable or chargeable work once a reservation is outside active service, bill-locked, in payment or terminal; serialize conversion against settlement and make legacy terminal-active drift deployment-visible.
- Changed files: new `app/Modules/Ordering/Domain/Policies/ReservationOperationalWorkPolicy.php`; `app/Modules/Reservations/Application/Services/StaffReservationPreorderService.php`; `app/Modules/KitchenDispatch/Application/Actions/DispatchKitchenOrderAction.php`; `app/Modules/Billing/Application/UseCases/Previews/BillLockService.php`; `app/Modules/Cashiering/Application/Workflows/OrderSettlementWorkflow.php`; `app/Platform/Release/Services/BookingDeploySafetyService.php`; new `tests/Feature/Staff/StaffReservationPreorderTerminalGuardTest.php`, `tests/Feature/Staff/StaffPreorderSettlementMysqlRaceTest.php` and `tests/Support/run_preorder_settlement_race.php`; updated `tests/Feature/Staff/StaffKitchenDispatchFoundationFlowTest.php`, `tests/Unit/Services/BookingDeploySafetyServiceTest.php`; and this tracker.
- Added/updated tests: regression-first conversion coverage reproduced seven failures across the valid row-version bump and six service/terminal/bill/payment barriers; KDS reproduced five terminal/bill-lock dispatch successes that should be denied; deploy safety reproduced one missing reconciliation guard, for 13 intended failures. Final tests prove a serviceable unbilled conversion succeeds, every barrier returns 422 with unchanged reservation/preorder/order/payment/KDS/outbox/audit snapshots, KDS holds canonical `reservation -> order` locks, and the Redis/MySQL subprocess race has exactly one conversion/settlement winner.
- Verification run: final exact B04/collateral set passed 70 tests / 689 assertions with the MySQL-only race skipped on SQLite; `composer test:orders` passed 48 / 227; final `composer test:kitchen` passed 48 / 534; checkout/customer-bill collateral passed 42 / 373; `composer test:security` passed 47 / 321; targeted Pint and PHPStan passed. A guarded SQL-first disposable MySQL 8 database imported the canonical schema plus 68 patches and passed the final B04 runtime set at 14 / 82, including the one-winner race, terminal conversion denial, terminal/bill-locked KDS denial and deploy reconciliation; the database was removed with zero matching schemas remaining.
- Evidence paths: `storage/app/booking_release/doctor/reports/booking-doctor-default-20260716t032831z.json`; deploy preflight artifact `storage/app/booking_release/deploy_checks/reports/booking-deploy-check-preflight-20260716t032738z.json`; disposable database `codex_b04_final_20260716` was verified removed; command output is recorded in the active Codex task.
- Shared seams touched: settlement bill locking and KDS dispatch now share the reservation row-version/lock-order contract with preorder conversion; release preflight adds a read-only, non-PII `terminal_reservation_active_orders` reconciliation guard. Routes, capability maps, middleware, request/resource/API contracts, config values, schema/patch/dump artifacts and web clients were unchanged. A cross-table database constraint was not added because a bill-locked in-service reservation may legitimately retain its existing active order while payment is pending; request-path guards plus deploy reconciliation encode the required boundary without invalidating that state.
- Remaining risks: the full repository suite was not run. The disposable preflight proved every data/artifact/runtime check relevant to B04, including `terminal_reservation_active_orders.invalid_count=0`, but the overall command remained red only because no scheduler heartbeat worker was running in that isolated lane; the later default `booking:doctor` passed DB, Redis, scheduler and outbox probes. Production remains `NO-GO`, and target-environment preflight must still reject any pre-existing terminal-active rows before release.
- Finding status changes: C-05 and B04 moved from `OPEN`/`READY` to `CLOSED`; B05 and B11 moved to `READY` because their dependencies are satisfied.
- Next cursor: B05 — VND-only money contract and VNPay integer amount.

### 2026-07-16 — B05 — Enforce the VND-only integer money contract

- Intent: use one exact VND integer representation across PHP, MySQL, provider adapters, JSON and TypeScript; reject non-VND and fractional write input; and remove the VNPay float/type mismatch that converted valid signed callbacks into `amount_mismatch` failures.
- Changed files: `app/SharedKernel/Money/Money.php`; `VNPayPaymentProviderAdapter.php`; bounded money request/controller/service/resource paths in Billing, Cashiering, Payments, Catalog, BranchScheduling, InventoryProcurement, Promotions, Loyalty, Reservations, Ordering, Conversations, FloorOperations, Notifications and Reporting; API schema/artifact generators; canonical `database/schema/mysql-schema.sql`, `db_all.sql`, new patch `database/patches/2026_07_16_000069_vnd_integer_money_contract.sql`, release inventory/verifier/docs; generated OpenAPI/Postman/TypeScript artifacts and the synced customer SDK; the staff catalog/cashier/checkout inputs; customer billing/deposit/preorder/reservation/voucher state; focused backend/frontend tests; and this tracker.
- Added/updated tests: expanded `MoneyTest` and `VNPayPaymentProviderAdapterTest`; added a signed VNPay webhook-to-lifecycle-to-ledger exactly-once regression and new `VndMoneyWriteBoundaryTest`; updated API schema/artifact/release-contract tests; changed endpoint assertions from decimal strings to integer JSON values; and updated customer-web cart, billing, deposit, reservation and voucher fixtures. The regression-first run reproduced six Money/VNPay failures and all twelve write-boundary cases failed before the service/request fixes.
- Verification run: final focused B05 set passed 114 tests / 2,507 assertions; the primary bill/payment/order response regression passed 106 / 721; collateral endpoint coverage passed 78 / 656; preorder follow-up passed 9 / 50; `composer test:money` passed 78 / 442; `composer test:release-contract` passed 241 / 4,183; `composer test:security` passed 47 / 321. Customer targeted Vitest passed 37/37; customer typecheck and contract governance passed; staff typecheck passed. Targeted Pint, direct targeted PHPStan and targeted `git diff --check` passed. A guarded disposable MySQL 8 database imported the canonical schema plus all 69 patches, verified all 40 scale-zero money column contracts and 12 VND-only currency enums, round-tripped `123456` exactly, rejected a USD insert, passed the release verifier and was removed.
- Evidence paths: `database/patches/2026_07_16_000069_vnd_integer_money_contract.sql`; `storage/app/booking_release/openapi-v1.json`; `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`; `storage/app/booking_release/doctor/reports/booking-doctor-default-20260716t055203z.json`. Disposable database `restaurantpos_b05_vnd_20260716` was verified removed; command output is recorded in the active Codex task.
- Shared seams touched: the shared Money kernel, canonical SQL schema/patch/dump, release verifier/inventory, OpenAPI/consumer artifact generation and both web-client money inputs/types. Routes, middleware, capability maps and route inventory were not changed. The scale-zero patch fails before conversion when legacy fractional or non-VND rows exist.
- Remaining risks: the full repository suite was not run. MySQL itself rounds direct fractional DML into `DECIMAL(...,0)`; supported HTTP/service boundaries reject it and the upgrade patch refuses fractional legacy rows before conversion, so future writers must continue through those guarded boundaries. Full staff frontend parity remains red on 13 pre-existing raw paths owned by B17 and was not hidden by route/allowlist changes. B06-B09 still own durable external provider operations, unapplied funds, verified confirmation and provider-aware capture/refund. Production remains `NO-GO`.
- Finding status changes: H-01 and H-02 moved from `OPEN` to `CLOSED`; B05 moved from `READY` to `CLOSED`; B06 moved from `OPEN` to `READY`.
- Next cursor: B06 — durable provider operation and event model.

### 2026-07-16 — B06 — Build the durable provider operation and event model

- Intent: represent every external money attempt as a durable, idempotent and reconcilable operation whose final success requires provider-verifiable evidence; preserve ambiguous outcomes, ordered immutable provider events and verified funds that cannot yet be applied.
- Changed files: new provider operation/acceptance/evidence enums, `PaymentProviderTransportException`, `PaymentProviderOperation`, `PaymentProviderEvent`, `PaymentUnappliedFund` and their application services; customer deposit/bill payment-session services and controllers; webhook/deposit lifecycle workflows; Generic HMAC and simulated adapters; new `routes/console/payments.php` plus console/scheduler registration; canonical schema/dump, patch `2026_07_16_000070_durable_payment_provider_operation_event_model.sql`, release verifier/config/docs/manifest; generated provider enum artifacts; scenario support and focused tests.
- Added/updated tests: added `DurablePaymentProviderOperationTest` for binding/fingerprint replay, one active subject, timeout-before/after acceptance, evidence-only success, stale-operation reconciliation, mismatch snapshots and event ordering; expanded webhook tests for immutable events and late captured unapplied funds; added durable-operation assertions to deposit/bill session flows; expanded SQL-first artifact sync coverage; aligned two finance collateral expectations with the closed B05 integer-money contract. The regression-first run produced five intended failures before the model existed.
- Verification run: final exact B06 set passed 86 tests / 834 assertions; webhook coverage passed 20 / 175; finance collateral passed 11 / 111; `composer test:security` passed 47 / 321; `booking:round5-gate --json` passed 15/15 suites; targeted Pint and PHPStan passed. Two guarded disposable MySQL 8 bootstraps validated the evolving and final artifacts; the final target imported all 70 patches, passed the release verifier and the B06 runtime set at 27 / 206. The active-subject contention probe blocked the second transaction for 1.986 seconds and rejected it on the unique slot after the first committed. Reconciliation returned `ok=true`; both disposable databases were identity-checked, removed and verified absent. Frozen release manifest, package integrity and runtime API-contract generation passed; the manifest records 70 present / 61 required patches and the API contract remains 219 paths. `booking:doctor --json` returned `ok=true` for database, Redis, scheduler and outbox.
- Evidence paths: `database/patches/2026_07_16_000070_durable_payment_provider_operation_event_model.sql`; `storage/app/booking_release/release_manifest/reports/booking-release-manifest-snapshot-verify-frozen-20260716t090751z.json`; `storage/app/booking_release/doctor/reports/booking-doctor-default-20260716t105357z.json`; command output and the MySQL concurrency JSON are recorded in the active Codex task.
- Shared seams touched: the canonical SQL schema/patch/dump/verifier and release inventory; the payment-session provider-call boundary; verified webhook ingestion; console scheduling; and generated provider enum artifacts. Public HTTP routes, middleware, capability maps, booking provider values, route inventory and the frozen OpenAPI contract were not changed.
- Remaining risks: B07 must prove that real-provider customer capture can only follow signed webhook or authenticated server-to-server evidence and reconcile reordered terminal events. B08 still owns complete parallel/late deposit accounting and its operator path; B09 owns provider-aware staff capture/refund execution and recovery. One pre-existing MySQL-only bill-flow fixture intentionally inserts USD and remains incompatible with the closed VND-only schema, while all B06-relevant MySQL paths and the complete SQLite bill flow pass. The full repository suite was not run. Production remains `NO-GO`.
- Finding status changes: no audit finding closes in this foundation batch. C-01 and C-03 remain `PARTIAL`; C-02, H-08 and M-02 remain `OPEN`; B06 moved from `READY` to `CLOSED`; B07 moved from `OPEN` to `READY`.
- Next cursor: B07 — verified customer confirmation and provider event ordering.

### 2026-07-16 — B07 — Verified customer confirmation and provider event ordering

- Intent: ensure browser input can never attest real-provider capture, require exact provider-verifiable identity and money evidence at the service/transaction boundary, and reconcile signed provider events by provider sequence/time without losing or silently reversing captured funds.
- Changed files: new `VerifiedPaymentProviderEvidence`; provider event/operation services and models; deposit/bill customer payment-session services and lifecycle workflows; webhook ingestion and terminal transition policy; Generic HMAC, VNPay and MoMo adapters; provider payload sanitizer; API metadata plus canonical generated OpenAPI/Postman/TypeScript artifacts and synced customer SDK; customer payment-session state/tests; provider webhook runbook; focused backend tests; and this tracker. Public routes, middleware, capability maps, provider rollout values, schema, SQL patches and route inventory were not changed.
- Added/updated tests: expanded webhook coverage for failure→success recovery, sequence-over-time ordering and success→newer-failure `ManualReview`; added authenticated Generic HMAC confirmation tests proving exact evidence captures once while mismatched merchant/amount/currency returns 422 with unchanged session/reservation/payment/outbox state; expanded Generic/VNPay/MoMo adapter timestamp, merchant, currency and integer-amount contracts; updated customer-web tests so only `simulated` exposes customer confirmation. Regression-first runs reproduced the terminal recovery, sequence reason and browser-override failures before the fix.
- Verification run: final exact payment/provider/customer-session set passed 102 tests / 689 assertions; API contract/artifact gates passed 23 / 1,839; `composer test:security` passed 47 / 321; customer-web targeted Vitest passed 30/30, typecheck and contract governance passed; targeted Pint, PHPStan and `git diff --check` passed. A guarded disposable MySQL 8 database imported the canonical schema plus all 70 patches and passed release verification; the B07 webhook/deposit runtime set passed 41 / 367 and the MoMo browser-confirm containment case passed 1 / 7. The database was identity-checked, removed and verified absent. `booking:doctor --json` returned `ok=true`.
- Evidence paths: `storage/app/booking_release/openapi-v1.json`; `build/api-consumer/sdk/typescript/restaurantpos-sdk.ts`; `customer-web/src/lib/contracts/generated/restaurantpos-sdk.ts`; `storage/app/booking_release/doctor/reports/booking-doctor-default-20260716t112756z.json`. Disposable database `restaurantpos_b07_20260716` was verified removed; command output is recorded in the active Codex task.
- Shared seams touched: verified customer payment-session service boundaries, signed webhook ingestion, immutable provider-event ordering, durable operation reconciliation status, generated API consumer artifacts and customer-web payment action policy. Provider calls remain outside database transactions; evidence is revalidated after the reservation/session locks are reacquired.
- Remaining risks: B07 cannot close without real-provider sandbox evidence. The current environment reports Generic HMAC, VNPay and MoMo disabled, no configured sandbox endpoint/credentials, and empty verified confirmation/reconciliation provider lists. Unblock by provisioning one approved sandbox provider, then retain provider-side IDs/sequence/time for unpaid browser return, exact signed success and duplicate, forged/mismatched evidence, failure→success, delayed lower-sequence failure and success→newer-failure reconciliation. The full MySQL bill suite still contains the pre-existing B06-documented fixture that directly inserts `USD` and is rejected by the closed VND-only schema; all B07 MySQL paths are green. Production remains `NO-GO`.
- Finding status changes: C-01 moved from `PARTIAL` to `CODE_FIXED`; H-08 moved from `OPEN` to `CODE_FIXED`; B07 moved from `READY` to `BLOCKED` on external sandbox proof. No finding is closed and B08 remains `OPEN`.
- Next cursor: B07 — obtain and execute real-provider sandbox callback/reorder proof; do not advance to B08 until the B07 exit evidence is attached.

### 2026-07-16 — B10 — Dependency and CI enforcement

- Intent: remove unresolved production High advisories with the smallest compatible direct upgrades, fail closed on future High/Critical production advisories, run both split-web test/build lanes on PR/main and before production deploy, and archive reproducible npm advisory plus CycloneDX SBOM evidence without touching payment/provider, schema, route or generated API-contract seams.
- Changed files: root, `customer-web` and `staff-web` package manifests/lockfiles; `.github/workflows/booking-ci.yml`; the bounded prerequisite/`needs` addition in `.github/workflows/booking-cd.yml`; new `scripts/ci/dependency-security-gate.mjs`, `scripts/ci/booking-dependency-security-gate.sh` and `scripts/ci/tests/dependency-security-gate.test.mjs`; `scripts/ci/booking-repo-prereq-check.sh`; new `tests/Unit/Infrastructure/DependencySecurityCiContractTest.php`; `docs/runbooks/booking-ci-cd-runbook.md`; and this tracker. `next`/`eslint-config-next` moved from `16.2.4` to `16.2.10`; `react-router-dom` moved from resolved `6.30.3` to `6.30.4`; CLI-only `shadcn` moved from production to dev dependencies; root package identity was added so npm can emit a valid empty-production SBOM.
- Added/updated tests: added three Node policy tests for High/Critical blocking, Moderate visibility and malformed-audit fail-closed behavior; added four PHP workflow/package contract tests covering PR/main install-test-build-audit, deployment `needs`, SBOM/advisory artifacts and minimum fixed versions; retained the B01 CD containment contract. The regression-first PHP run produced four intended failures before the gate existed; the final contract set passed 5 tests / 48 assertions, and the Node policy set passed 3/3.
- Verification run: baseline production audits were root `0`, customer `10` (`1` low, `6` moderate, `3` high) and staff `2` moderate. Final policy evidence passed with root `0`, customer `2` moderate and `0` High/Critical, and staff `0`; the force downgrade proposed for the remaining customer Moderate report was rejected. `composer test:security` passed 47 tests / 321 assertions; targeted Pint, PHPStan, Bash syntax, Symfony workflow YAML parsing, package integrity (52 checked, zero missing/stale/contract failures), targeted diff hygiene and both production builds passed. `booking:doctor --json`, outbox health, frozen release manifest verification and strict deploy preflight passed; the final strict preflight recorded zero errors/warnings. No disposable MySQL database was required because B10 changed no database/runtime semantics.
- Mandatory exit-gate failures: `customer-web verify:release` stopped at existing lint debt with 7 errors / 32 warnings; the direct customer test lane reported 247 tests with 213 passed / 34 failed across 53 files. The staff inventory contains 378 tests across 89 files; the full lane reported 376 passed / 2 failed: `AdminBenefitsPage` timed out and `OrderWorkspacePage` could not find `kitchen-destination`. These failures are in pre-existing dirty UI/test paths outside the bounded B10 dependency patch; they were not overwritten or broadened into this batch. Both customer and staff production builds still passed.
- Evidence paths: `build/booking-ci/dependency-security/summary.json`; per-root `npm-audit-production.json`, `policy-summary.json` and `sbom.cyclonedx.json` beneath that directory; CycloneDX 1.5 component counts/digests are root `0` / `23e94cbf0d693869af6f0cd78173dd5305443e6fa193eaff008d4c9e764bdef0`, customer `99` / `b37770036295dbe0ba8a9daf1947326c0cf8feb5c591f09278507ffb077456c9`, and staff `108` / `279b0c55c78f5103c699bb23ca7b1c5344f3ed6b26abf735424faa9b78918c96`. Runtime evidence: `storage/app/booking_release/doctor/reports/booking-doctor-default-20260716t135319z.json` and `storage/app/booking_release/deploy_checks/reports/booking-deploy-check-preflight-strict-20260716t135416z.json`.
- Hosted control-plane evidence: read-only GitHub API verification returned `404 Branch not protected` for `main`, so `dependency-security` cannot yet be a required PR check. Environment `Production` has required reviewer `DuongVinh2004` and a custom deployment branch policy allowing only `main`, but `prevent_self_review=false`. The most recent existing `booking-ci.yml` run was successful at `https://github.com/DuongVinh2004/RestaurantPOS/actions/runs/29322779379`, but it predates the uncommitted B10 job and is not claimed as B10 evidence. No commit, push, branch, PR or repository-policy mutation was performed.
- Shared seams touched: only the two GitHub workflows, package lockfiles, repository prerequisite check and CI/CD runbook. The existing B01 manual-only dispatch, `production` Environment, concurrency and strict candidate preflight-before-restart behavior in `booking-cd.yml` were preserved. Public routes, API contracts/artifacts, capability maps, Payments/Billing/Cashiering, provider config/adapters/lists, schema, SQL patches and release-gate workflow were not changed.
- Remaining risks: B10 cannot close until the customer/staff mandatory suites are green, the uncommitted workflow is reviewed and run on GitHub, `main` branch protection requires the exact `dependency-security` check, and hosted artifact metadata proves audit/SBOM retention. Independent/no-self approval and administrator bypass governance remain later production-control work. B07 remains externally blocked; production remains `NO-GO`.
- Finding status changes: B10 moved from `READY` to `IN_PROGRESS`; H-06 remains `PARTIAL`. B07 remains `BLOCKED`, C-01/H-08 remain `CODE_FIXED`, and B08 remains `OPEN`.
- Next cursor: B10 — resolve or explicitly assign the existing customer/staff suite failures, then obtain a hosted green `dependency-security` run and owner-approved branch-protection required-check evidence. Do not start B08. After B10 closes, B11 is the preferred independent cursor if it does not collide with active dirty paths.

### 2026-07-18 — B10 — Mandatory frontend gate remediation

- Intent: clear the customer/staff mandatory-suite failures recorded by the initial B10 run without broadening into payment, provider, schema or API-contract work, then regenerate the exact local dependency-security evidence from clean lockfile installs.
- Changed files: `customer-web/src/lib/config/env.ts` and `env.test.ts`; preorder panel/cart implementation and tests; menu page and test; table-booking page, booking-preorder page and table-booking test; sticky booking summary; reservation detail and reservation-create/guest-session tests; home page typing cleanup; `customer-web/e2e/customer-smoke.spec.ts`; `staff-web/src/workspaces/ops/pages/orders/OrderWorkspacePage.tsx`; this tracker; and `docs/audits/README.md`. The customer changes restore preorder management/recovery behavior, make production rollout-only flags fail closed, align accessible names/copy and cover the current booking-preorder step. The staff change routes a successful kitchen dispatch to the KDS journey with order/station context.
- Added/updated tests: production feature-flag default coverage in `env.test.ts`; typed preorder-panel regression coverage; current menu/table-booking/reservation copy and accessibility expectations; Playwright hold/preorder journey coverage including the intermediate `/booking/preorder` step; and the existing 13-case OrderWorkspace suite now proves the Order → KDS destination/query contract.
- Verification run: `npm --prefix customer-web run verify:release` passed with lint `0` errors / `26` warnings, typecheck, 53 Vitest files / 247 tests, production build/contract governance and 5/5 Chromium smoke journeys. Staff targeted OrderWorkspace passed 13/13; the full lane passed 89 files / 378 tests and production build/package integrity. The exact `scripts/ci/booking-dependency-security-gate.sh` passed from clean `npm ci` installs using Git Bash; policy evidence is root `0`, customer `2` Moderate and staff `0`, with zero High/Critical in every workspace. `composer test:security` passed 47 / 321, `DependencySecurityCiContractTest` passed 4 / 38, and the Node fail-closed policy tests passed 3/3.
- Evidence paths: refreshed `build/booking-ci/dependency-security/summary.json` has `decision=pass` and `generated_at_utc=2026-07-18T15:15:24.190Z`; per-root audit, policy and CycloneDX files remain beneath the same directory. Current CycloneDX component counts/digests are root `0` / `f823ef15b4e7fd3d376a162388fe83e0a25bd9596e26eea932ee074882193a3b`, customer `99` / `f2ac4ed8b76ea8d7abd1095bf0a939443428d8559828c169e9046a4673b20b52`, and staff `108` / `373d005669c8d263e6f63aa75b56a758b4f21678f5b7fff34e45c69bcea3c564`.
- Shared seams touched: customer rollout flags and booking/preorder UI state plus the staff Order → KDS navigation seam only. Public/backend routes, generated API artifacts, capability maps, payment/provider logic, schema, SQL patches and GitHub workflows were not changed in this follow-up. Existing dirty generated contract artifacts were preserved; customer contract governance and staff package-integrity checks passed.
- Remaining risks: B10 still cannot close until the uncommitted workflow is reviewed and run on GitHub, hosted artifact metadata proves advisory/SBOM retention, and `main` branch protection requires the exact `dependency-security` check. No commit, push, PR or repository-policy mutation was performed because the shared worktree contains unrelated user changes. Customer lint retains 26 non-blocking warnings; staff tests retain non-blocking Ant Design deprecation/chart-size warnings. B07 remains externally blocked on real-provider sandbox evidence, and production remains `NO-GO`.
- Finding status changes: the recorded mandatory frontend exit-gate failures are resolved. B10 remains `IN_PROGRESS` and H-06 remains `PARTIAL` solely on hosted control-plane evidence; no finding closes in this follow-up.
- Next cursor: B10 — obtain a reviewed hosted green `dependency-security` run, archived artifact metadata and owner-approved required-check protection for `main`. Do not start B08. After B10 closes, B11 remains the preferred independent cursor if it does not collide with active dirty paths.

### 2026-07-18 — B10 — Hosted enforcement closure

- Intent: publish the bounded B10 implementation, prove the exact dependency gate in GitHub Actions, retain its advisory/SBOM evidence, and enforce that check on `main` before advancing the remediation cursor.
- Changed files: the reviewed B10 dependency/frontend scope was committed as `8a3b85c2775c605cb6f9ad06389203f677542e83`; `staff-web/scripts/local-runtime-preflight.test.mjs` received the cross-platform path expectation fix in `f67ad505d0f4b67aac79068f587657dbe4d5b0a4`; this tracker and `docs/audits/README.md` record closure. Existing unrelated worktree changes were preserved through selective staging.
- Added/updated tests: the runtime-preflight test now derives the expected Windows-style fixture path with Node `path.resolve`, retaining all four assertions on Windows and Linux. No product behavior changed in the hosted-fix/closure commits.
- Verification run: staged-snapshot dependency security passed from clean installs; customer release verification passed 53 Vitest files / 246 tests, lint with 0 errors / 26 warnings, typecheck, production build and 5/5 Chromium journeys; staff passed 89 files / 378 tests plus production build/package integrity; PHP workflow contracts passed 5 tests / 48 assertions; Node fail-closed policy passed 3/3. The first hosted run exposed the platform-specific preflight test expectation; the focused 4/4 fix passed locally, and hosted `dependency-security` job [`88099653110`](https://github.com/DuongVinh2004/RestaurantPOS/actions/runs/29651941195/job/88099653110) then passed in 4m57s.
- Evidence paths: draft PR [`#47`](https://github.com/DuongVinh2004/RestaurantPOS/pull/47); hosted run `29651941195`; artifact `dependency-security-evidence-ci-29651941195` / id `8431761804`, containing 10 expected files and retained through `2026-08-17T16:30:35Z`; branch-protection readback `strict=true`, context `dependency-security`, GitHub Actions app id `15368`.
- Shared seams touched: GitHub Actions CI/CD dependency prerequisites, package manifests/lockfiles, mandatory customer/staff gates, the runtime-preflight test portability boundary and `main` protection. The pre-existing repository ruleset, public routes, API contracts, capability maps, payment/provider behavior and SQL schema were not altered by closure.
- Remaining risks: B10/H-06 are closed, but customer lint retains 26 non-blocking warnings and the customer production audit retains two Moderate advisories. Independent/no-self approval and administrator-bypass governance remain later production-control work. B07 is still externally blocked, B08 remains prohibited, and production remains `NO-GO` until the full remediation program and B20 reassessment close.
- Finding status changes: B10 moved from `IN_PROGRESS` to `CLOSED`; H-06 moved from `PARTIAL` to `CLOSED`. Totals are now 6 closed, 2 code-fixed, 3 partial and 19 open.
- Next cursor: B11 — KDS substitution. Keep B07 `BLOCKED` and do not start B08 until real-provider sandbox evidence is attached.

### 2026-07-18 — B11 — KDS-safe component substitution

- Intent: prevent a combo component mutation from splitting the order item, dispatched KDS snapshot and inventory recipe while retaining a narrow, valid pre-dispatch substitution path for the lean launch.
- Changed files: `app/Modules/Ordering/Application/UseCases/OrderItems/StaffOrderItemLifecycleService.php`; `app/Modules/KitchenDispatch/Application/Workflows/KitchenTicketConsistencyInspector.php`; `app/Modules/KitchenDispatch/Application/Workflows/KitchenTicketReconciliationService.php`; `app/Modules/KitchenDispatch/Application/Workflows/KitchenRoutingService.php`; new `tests/Feature/Staff/KdsComponentSubstitutionSafetyTest.php`; this tracker; and `docs/audits/README.md`. No schema, route, config, controller, request, resource or generated API artifact changed.
- Added/updated tests: the new five-case suite proves available same-category swap succeeds before dispatch; unavailable and cross-category targets fail without mutation; both queued-after-dispatch and fired/in-progress tickets make the component immutable; failed swaps preserve order item, ticket, stock and audit snapshots; serving afterward consumes only the original item recipe; and legacy item/category/route/station drift is surfaced by the inspector and reconciliation scan.
- Verification run: regression-first execution reproduced four intended failures while the existing valid pre-dispatch case passed. Final B11 suite passed 5 tests / 51 assertions; combined combo coverage passed 7 / 70; `composer test:orders` passed 48 / 227; `composer test:kitchen` passed 48 / 534; targeted Pint and PHPStan passed with zero errors; bounded diff hygiene passed. The verification recommender identified only order-lifecycle and kitchen/KDS domains with no schema/runtime escalation.
- Evidence paths: `tests/Feature/Staff/KdsComponentSubstitutionSafetyTest.php`; the transaction guard in `StaffOrderItemLifecycleService::swapComponent`; snapshot truth in `KitchenTicketConsistencyInspector::describe`; and the B11 output recorded in the active Codex task.
- Shared seams touched: none. Existing dirty B04 changes in `DispatchKitchenOrderAction`/`StaffKitchenDispatchFoundationFlowTest` and B05 money-contract changes in the order-item request/lifecycle test were preserved and excluded from the B11 patch.
- Remaining risks: B12 still owns immutable order-time recipe snapshots, wastage and MySQL stock-equation proof; B11 intentionally does not build a broad post-dispatch rerouting workflow. A dedicated parallel MySQL dispatch-vs-swap race was not added because B11 changes no schema and both mutations serialize on the existing order transaction lock; the required sequential queued/fired and inventory invariants pass. B07 remains externally blocked, B08 remains prohibited, and production remains `NO-GO`.
- Finding status changes: B11 moved from `READY` to `CLOSED`; H-03 moved from `OPEN` to `CLOSED`. Totals are now 7 closed, 2 code-fixed, 3 partial and 18 open; B12 moved from `OPEN` to `READY`.
- Next cursor: B12 — immutable recipe consumption and kitchen wastage. Keep B07 `BLOCKED` and do not start B08 until real-provider sandbox evidence is attached.

### 2026-07-19 — B12 — immutable recipe consumption and kitchen wastage

- Intent: make the recipe sold with an order item the immutable source for inventory movement, and give post-start kitchen cancellation an auditable, replay-safe stock consequence.
- Changed files: new `app/Modules/Ordering/Application/Services/OrderItemRecipeSnapshotService.php`; order-item model, table-order and lifecycle services; inventory consumption and stock-movement services; SQL patch `2026_07_19_000071_order_item_recipe_snapshot.sql`; canonical schema/dump, release config/verifier/bootstrap docs; new immutable-recipe/wastage and MySQL stock-equation tests plus their runner; focused fixture/schema updates; this tracker; and `docs/audits/README.md`. No route, controller, request, response resource or generated API contract changed.
- Added/updated tests: the new HTTP/service regression suite proves recipe edits before order commit are captured, edits after commit/dispatch/serve cannot change consumption, Served replay is a no-op, and `InProgress → Cancelled` records exactly one actor-attributed wastage movement from the committed snapshot. Existing direct-cancel and Served-terminal cases complete the KDS cancellation matrix. The MySQL-only race launches real procurement receive, snapshot consumption and adjustment concurrently, replays the receive/consume calls, and proves `120 + 50 - 80 - 30 = 60` with one movement per reference.
- Verification run: regression-first execution reproduced the missing snapshot and missing wastage failures. Final B12 suite passed 2 tests / 19 assertions; `composer test:inventory` passed 27 / 276; `composer test:orders` passed 48 / 227; `composer test:kitchen` passed 48 / 534; focused release parity passed 29 / 344; `composer test:release-contract` passed 246 / 4,250; bounded Pint and production PHPStan passed. Guarded bootstrap of disposable MySQL 8 database `restaurantpos_b12_20260719220155` applied the integrated schema, all 71 present patches and release verifier; the MySQL race passed 1 / 27 and the B12 plus existing consumption HTTP lane passed 5 / 44. A second isolated SQL bootstrap from clean commit `0e12b094` applied the base schema, its 69 present patches (the prior 68 plus patch 71) and verifier to `restaurantpos_b12_clean_20260719223300`. Both disposable databases were removed after proof. The frozen release manifest was then regenerated in a separate isolated B12 worktree with a local dependency tree and passed `--verify-frozen --no-write` with zero issues or mismatches.
- Evidence paths: `reservation_order_items.recipe_snapshot`; stable `ReservationOrderItemConsumption`/`ReservationOrderItemWastage` references in the inventory ledger; the B12 patch and release verifier; `tests/Feature/Staff/ImmutableRecipeSnapshotAndKitchenWastageTest.php`; `tests/Feature/Inventory/InventoryStockEquationMysqlConcurrencyTest.php`; and this completion record. The immutable source audit remains unchanged at SHA-256 `2710F57B46A6EAC13E9C845DF86D4A7CFE4FAF4C1440D429A5E39CEA608B7C7C`.
- Shared seams touched: SQL-first schema/dump/config/verifier/docs, the frozen release manifest and `BuildsBookingScenario` were updated only for the B12 snapshot contract. Existing dirty B01-B07 money, provider, bootstrap and test-fixture edits remain outside the B12 patch. No generated API artifact changed because B12 does not alter the HTTP contract.
- Remaining risks: pre-existing legacy order items can only be backfilled with the recipe visible at deployment because no historical version exists; the patch documents this approximation and freezes it before new code deploys. Runtime proof covered MySQL and the touched inventory/order/KDS slice, not Redis or scheduler because B12 adds no Redis/scheduled behavior. B07 remains externally blocked, B08 remains prohibited, B13 remains dependency-blocked by B09, and production remains `NO-GO`.
- Finding status changes: B12 moved from `READY` to `CLOSED`; H-12 and M-06 moved from `OPEN` to `CLOSED`. Totals are now 9 closed, 2 code-fixed, 3 partial and 16 open; B14 moved from `OPEN` to `READY`.
- Next cursor: B14 — durable and sanitized critical audit. Keep B07 `BLOCKED`, do not start B08, and leave B13 open until B09 closes.

### 2026-07-19 — B14 — durable and sanitized critical audit

- Intent: prevent critical financial, inventory and staff credential mutations from committing without durable audit evidence, while ensuring no audit file/database/alert sink receives the raw guest PII, credential, IP or customer-session values identified by H-13/H-14.
- Changed files: new central audit config, durability policy, keyed identifier hasher, payload sanitizer, safe failure reporter and critical persistence exception; `AuditEvent`, `AuditTrailRecorder`, actor resolution and legacy payload mapping; request audit middleware and logging config; staff API-key and ingredient transaction boundaries; `.env.example`, `README.md`, `docs/audit-trail.md`; focused audit/auth fixture tests; this tracker and `docs/audits/README.md`. No route, capability map, HTTP response contract, SQL schema/patch, generated API artifact or immutable source-audit content changed.
- Added/updated tests: new sink-capture coverage writes real audit file and database sinks, rejects seeded guest name/phone/email, bearer token, raw IP and session identifiers, and requires the keyed digest in both sinks; new failure injection points the recorder at a missing table, proves a staff API-key mutation rolls back, captures a payload-safe critical alert, proves best-effort failure remains non-throwing, and locks the finance/inventory/API-key classification. Existing staff product-auth/API-key fixtures now include the production-required audit foundation, and actor resolution expects keyed HMAC rather than unkeyed SHA-1.
- Verification run: regression-first sink capture reproduced the raw session/guest/token leak. Final B14 SQLite lane passed 5 tests / 34 assertions. Auth/RBAC passed 77 / 993; inventory/purchasing passed 35 / 369; audit/notifications/ops passed 54 / 497; checkout/refund passed 34 / 201; existing order/cashier/inventory audit coverage remained green. Whole-repo Pint passed. PHPStan on all 12 changed application files passed with zero errors; the broader all-app run exceeded the local five-minute runner limit without emitting a diagnostic. SQL-first bootstrap completed on disposable MySQL 8 database `restaurantpos_b14_audit_7191715`, including schema, all present patches, release verifier and site bootstrap; the same B14 lane then passed 5 / 33 with `VERIFICATION_DB_DRIVER=mysql`. The disposable database was removed after proof. After the first draft-PR full gate exposed two SQLite console fixtures without audit tables, the fixtures were aligned with the critical audit contract; the focused console pair passed 12 / 104 and the complete Console plus Infrastructure lane passed 142 / 2,669. `booking:doctor --json` confirmed MySQL and staff-auth configuration healthy but remained non-green on the existing local Redis refusal, Redis-blocked scheduler heartbeat, one due outbox row and missing customer JWT secret.
- Evidence paths: `app/Support/AuditEvent.php`; `app/Support/AuditTrail/AuditPayloadSanitizer.php`; `app/Support/AuditTrail/AuditDurabilityPolicy.php`; `app/Support/AuditTrail/AuditFailureReporter.php`; `app/Support/AuditTrail/AuditTrailRecorder.php`; `tests/Feature/Infrastructure/AuditSinkSanitizationTest.php`; `tests/Feature/Infrastructure/CriticalAuditDurabilityTest.php`; and `docs/audit-trail.md`. The immutable source audit remains unchanged at SHA-256 `2710F57B46A6EAC13E9C845DF86D4A7CFE4FAF4C1440D429A5E39CEA608B7C7C`.
- Shared seams touched: the shared `AuditEvent`/request-audit path, logging config and test audit fixtures. Sanitization now happens before both sinks, critical events persist synchronously on the caller's database transaction, and no transactional outbox/schema change was needed. Existing public routes, actor authorization/capability decisions, branch scope, business ledger math and API artifacts were preserved.
- Remaining risks: operators must route `AUDIT_ALERT_LOG_STACK` into their production log/alert collector and treat `critical_audit_persistence_failed` as actionable; rotating `AUDIT_HASH_KEY` intentionally breaks correlation with prior hashes. Best-effort legacy telemetry remains non-durable by design. The full all-app PHPStan process exceeded the local five-minute limit, although changed-file PHPStan and every mandatory domain suite passed. Local doctor remains non-green on Redis/scheduler/outbox/customer-JWT prerequisites that B14 did not change; B15 owns notification durability, while runtime secret/service readiness remains part of the go-live program. B07 remains externally blocked, B08 remains prohibited, B13 remains dependency-blocked by B09, and production remains `NO-GO`.
- Finding status changes: B14 moved from `READY` to `CLOSED`; H-13 and H-14 moved from `OPEN` to `CLOSED`. Totals are now 11 closed, 2 code-fixed, 3 partial and 14 open; B15 moved from `OPEN` to `READY`.
- Next cursor: B15 — durable notification delivery and truthful preference semantics. Keep B07 `BLOCKED`, do not start B08, and leave B13 open until B09 closes.

## 12. Completion record template

Copy this block after each batch:

```markdown
### YYYY-MM-DD — Bxx — title

- Intent:
- Changed files:
- Added/updated tests:
- Verification run:
- Evidence paths:
- Shared seams touched:
- Remaining risks:
- Finding status changes:
- Next cursor:
```

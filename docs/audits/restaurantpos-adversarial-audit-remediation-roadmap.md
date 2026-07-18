# RestaurantPOS Adversarial Audit Remediation Roadmap

**Program state:** `ACTIVE`

**Production decision:** `NO-GO`

**Source audit:** [`restaurantpos-exhaustive-adversarial-audit-2026-07-14.md`](restaurantpos-exhaustive-adversarial-audit-2026-07-14.md)

**Live tracker:** [`restaurantpos-adversarial-audit-remediation-tracker.md`](restaurantpos-adversarial-audit-remediation-tracker.md)

**Audit baseline:** `main@3080e439e8c715e83fb27aa39ba753a92dffc36f`

**Roadmap created:** `2026-07-15`

## 1. Purpose and authority

This is the canonical execution plan for closing the 2026-07-14 adversarial production audit. It supersedes older launch plans wherever they conflict with the new audit.

The roadmap answers:

- which batch must run next;
- which findings each batch owns;
- which files and shared seams it may touch;
- which dependencies must be merged first;
- which tests and runtime proofs are required;
- when a finding may move from open to closed.

The live tracker, not this roadmap, records the current cursor and evidence. The audit report remains immutable.

## 2. Program outcome

The program is complete only when:

- all 6 Critical and 15 High findings are `CLOSED`;
- every Medium and Low finding is `CLOSED`, or has explicit product/security acceptance with an owner and expiry date;
- payment-provider, MySQL 8, Redis, scheduler/worker, browser, backup/restore and deploy/rollback evidence is archived from a clean commit;
- no money, authorization, audit, schema/deploy or disaster-recovery finding is accepted as residual risk;
- the new adversarial reassessment returns production `GO`.

## 3. Execution rules

1. Work from the tracker cursor. Do not select a convenient later batch while the cursor has an unmet exit gate.
2. One logical invariant per PR. Never combine payment-state redesign, branch authorization and deploy-pipeline changes.
3. Read the owning service and adjacent tests before editing.
4. Keep controllers thin; put orchestration and invariants in services/domain logic.
5. Add a failing regression before or with every meaningful fix.
6. A passing SQLite suite is not MySQL, Redis, provider or release proof.
7. Preserve user-owned worktree changes. Isolate unrelated work instead of resetting or rewriting it.
8. Update the live tracker in the same batch that changes code or evidence.
9. End each batch report with Intent, Changed files, Added/updated tests, Verification and Remaining risks.
10. Stop production deployment throughout remediation.

## 4. Shared-seam ownership

These files are serialized integration seams:

- `routes/api.php`
- `config/booking.php`
- `config/staff_capabilities.php`
- `database/schema/mysql-schema.sql`

These generated chains are also serialized:

- `build/api-consumer/**`
- `customer-web/src/lib/contracts/generated/**`
- `storage/app/booking_release/**`

Only one schema-sensitive batch may be active at a time. A batch that changes database behavior must keep the canonical schema, forward patch, `db_all.sql` where applicable, release verifier and bootstrap contract aligned.

## 5. Lean launch decisions

Use these defaults unless the user explicitly changes product scope:

- launch with VND only; reject all other currencies at every boundary;
- certify one real payment provider before enabling another;
- disallow combo-component substitution after KDS dispatch instead of building a broad rerouting workflow;
- keep inventory remediation limited to recipe truth, stock integrity and wastage required by the audited launch path;
- do not add speculative ERP, analytics or Wave 2 customer features;
- keep real-provider self-pay and non-cash instant capture/refund disabled until their owning batch closes.

## 6. Phase map and estimate

Estimates are engineering days and exclude waiting for credentials, provider support or operational approval.

| Phase | Batches | Outcome | Estimate |
|---|---|---|---:|
| 0. Baseline and containment | B00-B02, B10 containment | Clean execution baseline, deployment/payment blast radius reduced, destructive bootstrap guarded | 2-3 |
| 1. Identity and terminal state | B03-B04 | Cross-branch access closed; terminal reservation cannot gain active work | 4-6 |
| 2. Financial kernel | B05-B09 | Provider-verifiable, integer, reconcilable capture/refund/deposit lifecycle | 12-18 |
| 3. Operational truth | B10-B17 | Dependency, KDS, inventory, reporting, audit, notification, cashier and contract correctness | 12-17 |
| 4. Release and DR | B18-B19 | Forward-only deployment, immutable artifacts, verified backup/restore and rollback | 7-10 |
| 5. Integration and reassessment | B20 | Reproducible evidence pack and new GO/NO-GO decision | 5-7 |

Expected total: 42-61 engineering days. With three carefully isolated lanes, the calendar path is approximately 5-7 weeks; one engineer should plan for roughly 9-13 weeks.

## 7. Dependency and merge order

Mandatory critical path:

```text
B00 -> B01 -> B02
             |
             +-> B03 -> B04 -> B11 -> B12
             |
             +-> B05 -> B06 -> B07 -> B08 -> B09 -> B16
             |
             +-> B10 -> B14/B15/B17

B12 + B13 + B14 + B15 + B16 + B17 -> B18 -> B19 -> B20
```

Additional constraints:

- B03 must patch preorder branch scope before B04 edits preorder terminal-state rules.
- B05 must settle the currency contract before B06-B09 create provider-ledger state.
- B06-B09 are sequential because they share the financial state model and schema.
- B13 reporting requires signed refund/capture semantics from B09.
- B16 cashier reconciliation requires authoritative provider/refund truth from B09.
- B18 may be designed earlier but cannot activate production deployment before B19 and B20 pass.

## 8. Batch catalog

### Phase 0 — Baseline and containment

#### B00 — Freeze and classify the working baseline

- Findings: program governance for all findings.
- Intent: turn the current dirty branch into reviewable ownership groups without discarding user changes.
- Owned paths: Git metadata and `docs/audits/**`; no product behavior changes.
- Required work:
  - inventory every modified/untracked path;
  - group current work into audit report, C-06/bootstrap, UI/smoke, CORS and skill-pack scopes;
  - preserve the audit report byte-for-byte;
  - record the exact HEAD, branch, audit hash, toolchain and known test results;
  - decide which existing changes belong to the first remediation batch and which must remain isolated.
- Verification:
  - `git status --short`
  - `git diff --stat`
  - audit SHA-256 comparison
  - `node scripts/release/check-package-integrity.mjs --json`
- Exit: every path has an owner/scope; no ambiguous mixed commit is required; tracker cursor can advance.

#### B01 — Immediate production and provider containment

- Findings: containment for C-01, C-03, H-04 and H-06.
- Intent: remove the immediate exploit/deployment blast radius before structural remediation.
- Primary paths: `.github/workflows/booking-cd.yml`, existing provider rollout/config guards, environment/runbook contract.
- Required work:
  - stop automatic production activation from every `main` push;
  - require a protected/manual production environment while the audit is open;
  - verify real-provider self-pay cannot be enabled without verified confirm/reconciliation capability;
  - fail closed for non-cash staff capture/refund paths that currently only mutate MySQL;
  - record the containment settings and rollback procedure.
- Verification:
  - workflow syntax/static tests;
  - config and feature-boundary tests;
  - negative request tests proving zero payment/refund side effects;
  - `php artisan booking:deploy-check --mode=preflight --strict --json`.
- Exit: the audited dangerous paths cannot reach production or claim external success.

#### B02 — Safe fresh bootstrap

- Findings: C-06 containment; prerequisite for H-04.
- Intent: fresh bootstrap can target only an authorized, empty, disposable database.
- Primary paths: `tools/mysql/**`, bootstrap wrappers, release-contract tests and bootstrap runbooks.
- Required work:
  - reject production, unknown environment, unapproved target and non-empty database;
  - use one bounded guarded MySQL session and a cooperating bootstrap lease;
  - redact credentials and bound child processes;
  - reconcile `dev:*:reset` and README behavior with the new guard;
  - document that forward-only upgrade is owned by B18.
- Verification:
  - `php artisan test tests/Unit/Infrastructure/DatabaseBootstrapSafetyGuardTest.php tests/Unit/Infrastructure/DatabaseReleaseContractArtifactSyncTest.php`
  - `composer test:release-contract`
  - targeted Pint/PHP syntax checks;
  - disposable MySQL: empty allowed, non-empty denied, production denied, wrong target denied, concurrent bootstrap denied/audited.
- Exit: MySQL evidence exists and no documented wrapper bypasses the guard. C-06 remains partial until B18 supplies the upgrade path.

### Phase 1 — Identity and terminal state

#### B03 — Mandatory branch scope and customer eligibility

- Findings: C-04, M-05.
- Intent: capability controls what staff may do; branch entitlement controls where, inside every transaction.
- Primary paths: identity/branch scope support, Reservations, Waitlist, FloorOperations, preorder/analytics services and focused tests.
- Required work:
  - create/reuse one mandatory `StaffBranchScope` contract;
  - propagate actor scope into timeline, waitlist, reservation create, hold override, board assignment, preorder and analytics;
  - apply scoped reads and repeat branch assertion after mutation locks;
  - centralize customer-role eligibility for reservation, waitlist and loyalty;
  - generate actor branch A against resource branch A/B route cases.
- Verification:
  - `composer test:security`
  - targeted reservation/waitlist/board/preorder tests;
  - assert 404, no PII, no row/outbox/table-state mutation and unchanged row versions for branch B.
- Exit: all C-04 surfaces pass the deny matrix; M-05 role matrix is enforced.

#### B04 — Terminal reservation, preorder and KDS guard

- Findings: C-05.
- Intent: a billed, locked or terminal reservation cannot gain active, dispatchable, chargeable work.
- Primary paths: Reservations preorder service, Ordering settlement/bill-lock services, Kitchen dispatch and schema/reconciliation tests.
- Required work:
  - lock reservation, preorder, order, bill/payment session and payment facts in one transaction;
  - reject conversion after bill lock, checkout, active payment or final capture;
  - reject KDS dispatch for terminal reservation orders;
  - add a reconciliation query/constraint strategy for invalid active-order/terminal-reservation combinations.
- Verification:
  - `composer test:orders`
  - `composer test:kitchen`
  - MySQL concurrent conversion versus settlement truth table.
- Exit: exactly one concurrent operation wins and no terminal reservation owns an active KDS-dispatchable order.

### Phase 2 — Financial kernel

#### B05 — VND-only money contract and VNPay integer amount

- Findings: H-01, H-02.
- Intent: one exact integer representation across PHP, MySQL, provider adapters, JSON and TypeScript.
- Primary paths: shared Money value object, checkout/payment requests, provider adapters, schema checks and web clients.
- Required work:
  - reject non-VND currency at all write boundaries;
  - normalize VNPay provider amount to integer minor units;
  - remove unsafe float/decimal comparisons;
  - make API and frontend types express integer VND amounts.
- Verification:
  - Money property/round-trip tests;
  - signed VNPay adapter -> lifecycle -> ledger test;
  - `composer test:money`;
  - customer/staff typecheck and contract verification.
- Exit: valid VNPay amount settles exactly once; non-VND requests cannot enter the system.

#### B06 — Durable provider operation and event model

- Findings: foundation for C-01, C-02, C-03, H-08 and M-02.
- Intent: external money movement is represented as durable operations/events, not caller-selected final state.
- Primary paths: Payments module, canonical schema/patches, provider interfaces and reconciliation services.
- Required work:
  - define immutable provider operation/event IDs and event occurrence ordering;
  - introduce Pending, Unknown, Failed and verified Succeeded semantics;
  - add unapplied-funds representation;
  - enforce idempotency and active-session/lineage constraints;
  - create reconciliation worker/read model and mismatch metrics.
- Verification:
  - schema/patch/bootstrap sync tests;
  - duplicate/different-fingerprint operation tests;
  - MySQL uniqueness and concurrency tests;
  - provider stub timeout-before/after acceptance matrix.
- Exit: every external operation has a durable, reconcilable identity and no request can directly select final success.

#### B07 — Verified customer confirmation and provider event ordering

- Findings: C-01, H-08.
- Intent: browser input cannot attest provider capture; conflicting events reconcile against provider truth.
- Primary paths: customer payment-session services/controllers, provider adapters, webhook ingestion and customer-web/API artifacts.
- Required work:
  - remove or restrict client confirm for real providers;
  - require signed webhook or authenticated server-to-server status query;
  - validate merchant, session, currency and amount;
  - persist provider sequence/time and reconcile terminal conflicts;
  - update frontend flow and generated consumer contracts.
- Verification:
  - unpaid browser confirm creates zero payments;
  - valid signed callback creates exactly one payment;
  - forged, duplicate, delayed, failure->success and success->failure matrices;
  - customer-web contract and journey tests;
  - real provider sandbox evidence.
- Exit: only provider-verifiable evidence can create captured state.

#### B08 — Duplicate and late deposit representation

- Findings: C-02.
- Intent: no provider-captured amount disappears when another session settles first.
- Primary paths: deposit session creation/lifecycle, payments/unapplied-funds schema and reconciliation views.
- Required work:
  - enforce one active session per reservation/scope or an equivalent DB-safe policy;
  - represent every verified success as applied, overpaid/unapplied or pending reconciliation;
  - handle late provider success after final settlement;
  - expose operator reconciliation/refund path.
- Verification:
  - parallel two-key create/pay test;
  - late callback after final capture;
  - provider capture total equals ledger capture plus unapplied funds.
- Exit: every verified provider capture is visible and reconcilable.

#### B09 — Provider-aware staff capture and refund

- Findings: C-03, M-02.
- Intent: non-cash capture/refund is a recoverable distributed operation with source lineage.
- Primary paths: Payments, Billing, Cashiering, provider interfaces, schema guards and reconciliation jobs.
- Required work:
  - derive method/provider from source payment;
  - model requested/pending/unknown/failed/verified-refunded states;
  - add idempotent provider capture/refund operations;
  - separate manager-approved manual cash correction;
  - restore DB-compatible lineage/cap defense or mandatory guard plus invariant monitor.
- Verification:
  - provider failure/timeout/unknown tests;
  - provider success then DB failure recovery;
  - duplicate click and concurrent-manager refund;
  - source-method mismatch rejection;
  - provider sandbox refund and settlement reconciliation.
- Exit: ledger, provider and cashier truth reconcile; no request writes external refund as final.

### Phase 3 — Operational truth

#### B10 — Dependency and CI enforcement

- Findings: H-06.
- Intent: vulnerable production locks cannot reach deployment.
- Primary paths: root/staff/customer package manifests and locks, CI workflows and SBOM/advisory policy.
- Required work:
  - upgrade vulnerable direct Next and React Router dependencies;
  - run customer install, test, build and production audit on PR/main;
  - run staff production audit;
  - make required CI a deployment prerequisite;
  - generate/archive SBOM and advisory evidence.
- Verification:
  - `npm audit --omit=dev` in all three workspaces;
  - `npm --prefix customer-web run verify:release`;
  - `npm --prefix staff-web run test`;
  - `npm --prefix staff-web run build`.
- Exit: no unresolved production High advisory; policy blocks regression.

#### B11 — KDS-safe component substitution

- Findings: H-03.
- Intent: order item, kitchen ticket and inventory recipe cannot diverge.
- Primary paths: Ordering item lifecycle, Kitchen dispatch/consistency inspector and related tests.
- Required work:
  - disallow swap after dispatch for the lean launch;
  - validate substitutions before dispatch against allowed/available choices;
  - expand consistency inspection to item/category/route/station snapshot truth.
- Verification:
  - `composer test:orders`;
  - `composer test:kitchen`;
  - pre-dispatch allowed and post-dispatch/fire denied tests.
- Exit: no supported action can split the item, ticket and recipe identities.

#### B12 — Immutable recipe consumption and kitchen wastage

- Findings: H-12, M-06.
- Intent: stock movement uses the recipe sold and accounts for post-start cancellation.
- Primary paths: Ordering snapshots, Inventory consumption, schema/patches and KDS cancellation policy.
- Required work:
  - version/snapshot recipe components at order commit;
  - consume the committed snapshot exactly once;
  - define and implement `InProgress -> Cancelled` wastage/controlled return policy;
  - preserve retry-safe stock references.
- Verification:
  - `composer test:inventory`;
  - recipe edit before/after order/dispatch/serve;
  - every KDS cancellation state;
  - parallel receive/consume/adjust stock equation on MySQL.
- Exit: recipe edits cannot change open-order consumption and cancellation has an auditable stock consequence.

#### B13 — Refund-aware reporting, branch business date and scoped ETA

- Findings: H-09, H-10, H-11, M-04.
- Intent: reporting and ETA reflect signed financial movement and branch-local operations.
- Primary paths: Reporting, BranchScheduling, Kitchen routing/ETA, schema snapshots and golden datasets.
- Required work:
  - centralize timezone + cutoff business-date resolution;
  - store gross capture and refunds separately; derive signed net without clamping;
  - correct cross-day/month/year aggregation;
  - scope ETA by accessible branch, station and rolling window with outlier handling.
- Verification:
  - golden ledger: full/partial, same-day/cross-day and refund-only day;
  - local midnight/month/year boundaries and DST-capable timezone;
  - branch/station ETA isolation, query plan and load budget.
- Exit: financial snapshots reconcile to the ledger and all business dates/ETA are branch-correct.

#### B14 — Durable and sanitized critical audit

- Findings: H-13, H-14.
- Intent: critical evidence cannot disappear and no sink receives raw PII/session secrets.
- Primary paths: audit recorder/event support, logging config, transactional outbox/schema and audit documentation.
- Required work:
  - sanitize once before every sink;
  - hash session identifiers with a keyed scheme and minimize guest fields;
  - classify critical versus best-effort events;
  - persist critical audit in the business transaction or guaranteed transactional outbox;
  - alert on audit persistence/processing failure.
- Verification:
  - sink-capture tests reject PII/token/session patterns;
  - failure injection rolls back or durably queues critical mutation;
  - MySQL transaction/outbox recovery test.
- Exit: critical mutations always have durable sanitized evidence.

#### B15 — Reminder schedule identity, catch-up and delivery crash safety

- Findings: H-07, M-07, M-08.
- Intent: reschedules, scheduler downtime and worker crashes cannot lose or duplicate notification intent.
- Primary paths: Notifications module, outbox schema/config, scheduler commands and channel drivers.
- Required work:
  - include normalized schedule version/time in reminder identity;
  - cancel superseded pending rows transactionally;
  - recheck schedule version before delivery;
  - implement bounded past-due catch-up and lag metrics;
  - use provider idempotency/receipt where available.
- Verification:
  - sent/pending/failed reminder then reschedule;
  - scheduler downtime beyond window;
  - crash after provider acceptance and before DB acknowledgement;
  - `php artisan notifications:outbox-health --json`.
- Exit: current reminder is eventually delivered once and stale reminders cannot send.

#### B16 — Independent cashier reconciliation

- Findings: M-01.
- Intent: a materially discrepant drawer cannot become final through cashier self-approval.
- Primary paths: Cashiering, capability map, payment/refund allocation, schema and reports.
- Required work:
  - add PendingReconciliation and Reconciled states;
  - define discrepancy thresholds and mandatory reason;
  - require separate manager capability and actor;
  - reconcile external refund truth and closed-origin allocations.
- Verification:
  - cashier cannot self-approve;
  - threshold/reason/manager matrix;
  - provider/refund/cash-drawer golden dataset;
  - `composer test:money`.
- Exit: discrepant shifts remain pending until independent approval and reports preserve that state.

#### B17 — Contract parity and encoding evidence quality

- Findings: M-03, L-01.
- Intent: quality gates are quiet, deterministic and truthful.
- Primary paths: frontend parity scanner/tests, tracked JSON fixtures and encoding guard.
- Required work:
  - parse template routes with AST and normalize dynamic segments;
  - add valid dynamic and invalid unknown-route cases;
  - fix UAT fixture as UTF-8 without BOM/mojibake;
  - scan every tracked JSON fixture.
- Verification:
  - `npm run contract:frontend-parity:test`;
  - `npm run contract:frontend-parity`;
  - `node scripts/ui-text-encoding-guard.mjs`;
  - strict JSON parse scan.
- Exit: the baseline gate is green and still rejects real contract/encoding drift.

### Phase 4 — Release and disaster recovery

#### B18 — Forward-only SQL-safe immutable deployment

- Findings: H-04 and final closure of C-06.
- Intent: compatible schema and release evidence are proven before traffic activation.
- Primary paths: CD/release workflows, release services, `tools/mysql/**`, patch ledger and deploy runbooks.
- Required work:
  - create a separate forward-only upgrade command/process;
  - build immutable artifact only after required CI/release gates;
  - take/verify backup and patch ledger before activation;
  - apply expand/contract changes in safe order;
  - run postflight and retain one-command rollback;
  - protect production environment and approvals.
- Verification:
  - stale schema blocks before activation;
  - failed patch/postflight rolls back without fresh bootstrap;
  - release-contract and deployment simulation;
  - `composer test:release-contract`;
  - `php artisan booking:deploy-check --mode=preflight --strict --json`.
- Exit: production cannot activate code against an incompatible schema; C-06 and H-04 are closed.

#### B19 — Streaming backup and verified isolated restore

- Findings: H-05, H-15.
- Intent: backup fails closed and restore is integrity-checked before atomic cutover.
- Primary paths: `scripts/ops/backup-to-s3.sh`, restore scripts, native MySQL backup tools and DR runbooks.
- Required work:
  - stream dump -> gzip -> file/object under a memory ceiling;
  - keep credentials out of argv;
  - fail when required offsite upload is unavailable;
  - require authenticated manifest/checksum;
  - restore into an isolated database, validate schema/count/ledger, then switch;
  - record real RPO/RTO.
- Verification:
  - missing AWS CLI/offsite target fails;
  - missing/incorrect checksum fails;
  - large dataset stays within memory budget;
  - midstream failure cannot corrupt the active target;
  - full restore drill and application postflight.
- Exit: signed evidence proves recoverability and bounded RPO/RTO.

### Phase 5 — Integration and reassessment

#### B20 — Full adversarial integration and production decision

- Findings: all 30 findings plus all audit `UNVERIFIED` matrices.
- Intent: generate a clean, reproducible evidence pack and a new honest verdict.
- Primary paths: focused regression fixes only, evidence outputs and the live tracker.
- Required work:
  - run full PHP/static/frontend/contract gates on a clean commit;
  - run MySQL race/deadlock/idempotency matrices;
  - run Redis unavailable, scheduler downtime and worker crash drills;
  - run provider sandbox capture/refund/reorder/replay reconciliation;
  - run backup/restore and deploy/rollback rehearsals;
  - reassess every finding and publish a new audit result.
- Verification baseline:

```text
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php artisan test
composer test:critical
composer test:release-contract
composer test:runtime-smoke

npm run contract:frontend-parity
npm --prefix staff-web run test
npm --prefix staff-web run build
npm --prefix customer-web run verify:release
npm audit --omit=dev

php artisan booking:doctor --json
php artisan notifications:outbox-health --json
php artisan booking:core-ops-gate --json
php artisan booking:round5-gate --json
php artisan booking:deploy-check --mode=preflight --strict --json
php artisan booking:release-manifest --verify-frozen --json
php artisan booking:launch-readiness --target=staging --json
php artisan booking:launch-readiness --target=limited-production --json
```

- Exit: the tracker contains closure evidence for every finding and the reassessment returns production `GO`.

## 9. Finding closure contract

A finding moves through these states:

```text
OPEN -> IN_PROGRESS -> CODE_FIXED -> RUNTIME_VERIFIED -> CLOSED
                     \-> BLOCKED
```

- `CODE_FIXED`: focused regression and static checks pass on the implementation.
- `RUNTIME_VERIFIED`: required MySQL/Redis/provider/browser/DR proof passes.
- `CLOSED`: evidence is linked from the tracker and no mandatory gate remains.
- `BLOCKED`: exact prerequisite, owner and unblock condition are recorded.
- `ACCEPTED_RISK`: permitted only for Medium/Low, with product/security approval and expiry.

Critical and High findings may never be closed or accepted on static reasoning alone.

## 10. Batch completion report

Every completed batch appends this record to the tracker:

```markdown
### YYYY-MM-DD — Bxx — title

- Intent:
- Changed files:
- Added/updated tests:
- Verification run:
- Evidence paths:
- Shared seams touched:
- Remaining risks:
- Next cursor:
```

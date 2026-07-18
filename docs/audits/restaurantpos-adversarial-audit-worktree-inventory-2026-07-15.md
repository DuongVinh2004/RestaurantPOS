# RestaurantPOS Adversarial Audit Worktree Inventory

**Captured:** `2026-07-15`

**Branch:** `chore/repository-evidence-cleanup`

**HEAD:** `9e4ff0457e7c933036043a1d18f010517db38902`

**Main/audit code baseline:** `3080e439e8c715e83fb27aa39ba753a92dffc36f`

**Concrete changed/untracked paths:** 64 total: 44 tracked changes and 20 untracked files. The inventory document itself is included in I-A.

**Purpose:** close B00 by assigning every path to one reviewable owner without resetting, discarding or silently merging user work.

## 1. Scope board

| Scope | Count | Owner | Disposition | Readiness |
|---|---:|---|---|---|
| I-A Audit program | 13 | B00 / `docs/audits` | Eligible as a standalone audit-program change | Document checks pass |
| I-B Bootstrap safety | 11 | B02 / ops-release contract | Keep together; never mix with UI/CORS/smoke | `PARTIAL`; focused tests pass, MySQL proof missing |
| I-C CORS credentials | 1 | Web auth/session contract | `QUARANTINE`; separate security review | CORS contract fails 1/9 tests |
| I-D Runtime smoke refresh | 9 | Runtime/UAT evidence | Separate smoke-harness/evidence batch | Needs clean runtime replay and provenance review |
| I-E Staff E2E lint edits | 6 | Staff-web verification | `QUARANTINE`; do not mix with audit remediation | Repository lint is not green |
| I-F Staff operator UI edits | 12 | Staff-web UI | `QUARANTINE`; incomplete cleanup | Typecheck passes; lint/whitespace issues remain |
| I-G UI screenshot evidence | 4 | QA evidence | Separate evidence decision | 4 PNG files, approximately 1.33 MB |
| I-H Executor-loop skill pack | 8 | Skill-pack quality | Separate plugin/skill batch | Requires skill-pack validation |
| **Total** | **64** |  |  |  |

## 2. Scope I-A — Audit program

These files are the current production-readiness source of truth or minimal supersession notices:

- `docs/audits/README.md`
- `docs/audits/restaurantpos-adversarial-audit-worktree-inventory-2026-07-15.md`
- `docs/audits/restaurantpos-adversarial-audit-remediation-roadmap.md`
- `docs/audits/restaurantpos-adversarial-audit-remediation-tracker.md`
- `docs/audits/restaurantpos-exhaustive-adversarial-audit-2026-07-14.md`
- `docs/audits/controlled-staging-rehearsal-report.md`
- `docs/audits/project-elevation-roadmap-2026-05-23.md`
- `docs/audits/project-quality-backlog.md`
- `docs/audits/project-quality-scorecard.md`
- `docs/audits/strict-production-readiness-audit.md`
- `docs/codex-accelerated-execution-roadmap.md`
- `docs/codex-execution-pack.md`
- `docs/codex-parallel-agent-prompts.md`

Decision: keep as a standalone documentation scope. The audit report stays byte-identical to the source download.

## 3. Scope I-B — B02 bootstrap safety

- `README.md`
- `database/README_release_bootstrap.md`
- `docs/runbooks/booking-local-windows-vscode-cmd-runbook.md`
- `tests/Unit/Infrastructure/DatabaseBootstrapSafetyGuardTest.php`
- `tests/Unit/Infrastructure/DatabaseReleaseContractArtifactSyncTest.php`
- `tools/mysql/bootstrap_release.cmd`
- `tools/mysql/bootstrap_release.php`
- `tools/mysql/bootstrap_release.sh`
- `tools/mysql/BootstrapReleaseWorkflow.php`
- `tools/mysql/BootstrapTargetSafety.php`
- `tools/mysql/README_bootstrap_release.md`

Decision: this is one B02 implementation scope. It may not be committed with CORS, staff-web, smoke evidence or skill files.

Known evidence:

- 42 focused tests pass with 641 assertions;
- PHP syntax and targeted Pint pass;
- target validation, one-session guard, timeout and credential redaction have regression coverage.

Remaining before B02 closure:

- disposable MySQL 8 matrix;
- README/reset guidance reconciliation;
- wider release-contract gate;
- wrapper behavior on Windows and shell;
- B18 forward-only upgrade before final C-06 closure.

## 4. Scope I-C — CORS credentials quarantine

- `config/cors.php`

Observed change: default `CORS_SUPPORTS_CREDENTIALS` changed from `false` to `true`.

Why quarantined:

- the project documents header-based auth with credentials disabled by default;
- `Tests\Feature\CorsContractTest::credentials_not_supported` fails because the response now emits `Access-Control-Allow-Credentials: true`;
- the change needs the RestaurantPOS web auth/session contract, exact-origin and browser delivery review before it can be accepted.

Decision: do not include in B00, B01 or B02. Preserve the user change, but treat it as non-merge-ready.

## 5. Scope I-D — Runtime smoke refresh

- `docs/runbooks/cashier-shift-close-smoke-result.md`
- `docs/runbooks/payment-reconciliation-smoke-result.md`
- `docs/runbooks/refund-flow-smoke-result.md`
- `docs/runbooks/staff-ops-runtime-smoke-result.md`
- `scripts/e2e/cashier-shift-close-smoke.mjs`
- `scripts/e2e/payment-reconciliation-smoke.mjs`
- `scripts/e2e/refund-flow-smoke.mjs`
- `scripts/e2e/staff-ops-runtime-smoke.mjs`
- `scripts/ops/dev-smoke.mjs`

Observed intent:

- use current UAT accounts/branch/scenario manifest;
- use deterministic next-day booking windows;
- refresh result documents with local runtime IDs and amounts;
- expect Vietnamese customer login copy.

Decision: keep scripts and result artifacts in one separate runtime-smoke review. Result documents contain volatile IDs and must not be treated as release evidence without commit/runtime provenance.

## 6. Scope I-E — Staff E2E lint edits

- `staff-web/e2e/admin-master-data-deep-audit.spec.ts`
- `staff-web/e2e/checkout-finance-deep-audit.spec.ts`
- `staff-web/e2e/golden-path-audit.spec.ts`
- `staff-web/e2e/kitchen-kds-deep-audit.spec.ts`
- `staff-web/e2e/order-lifecycle-deep-audit.spec.ts`
- `staff-web/e2e/reporting-analytics-deep-audit.spec.ts`

Observed intent: change mutable declarations to `const` as lint cleanup.

Decision: quarantine as a staff-web verification scope. Several declarations remain unused and the full lint command is red; these edits do not establish a clean lint baseline.

## 7. Scope I-F — Staff operator UI quarantine

- `staff-web/src/workspaces/ops/pages/dashboard/DashboardPage.tsx`
- `staff-web/src/workspaces/ops/pages/dashboard/components/DashboardCharts.tsx`
- `staff-web/src/workspaces/ops/pages/dashboard/components/ShiftHealthCard.tsx`
- `staff-web/src/workspaces/ops/pages/dashboard/dashboard-view-model.ts`
- `staff-web/src/workspaces/ops/pages/finance/FinanceReviewPage.tsx`
- `staff-web/src/workspaces/ops/pages/orders/OrderItemDetailDrawer.tsx`
- `staff-web/src/workspaces/ops/pages/orders/OrderMenuDrawer.tsx`
- `staff-web/src/workspaces/ops/pages/orders/OrderWorkspacePage.tsx`
- `staff-web/src/workspaces/ops/pages/reservations/ReservationsPage.tsx`
- `staff-web/src/workspaces/ops/pages/waiting/WaitingDetailDrawer.tsx`
- `staff-web/src/workspaces/ops/pages/waiting/WaitingListPage.test.tsx`
- `staff-web/src/workspaces/ops/pages/waiting/WaitingListPage.tsx`

Observed risks:

- `DashboardPage.tsx` contains an empty import block and removes multiple dashboard projections;
- several files add blanket `no-explicit-any` disables instead of typed fixes;
- `WaitingListPage.test.tsx` removes a value without replacing the assertion;
- multiple files have trailing whitespace or extra blank EOF lines;
- touched files still report lint failures.

Verification snapshot:

- `npm --prefix staff-web run typecheck`: pass;
- `npm --prefix staff-web run lint`: fail with 132 errors and 4 warnings across the current repository, including touched files.

Decision: preserve but do not merge. It requires its own staff-web review and focused tests after the audit critical path is contained.

## 8. Scope I-G — UI screenshot evidence

- `docs/qa/ui-business-flow-audit/evidence/inventory-run-1783952719373/final-state.png`
- `docs/qa/ui-business-flow-audit/evidence/run-1783952719373/01-homepage.png`
- `docs/qa/ui-business-flow-audit/evidence/run-1783952719373/02-booking-form.png`
- `docs/qa/ui-business-flow-audit/evidence/run-1783952719373/03-booking-filled.png`

Decision: keep separate from source changes. Before commit, record producing command, commit SHA, environment and retention policy; otherwise treat as local evidence only.

## 9. Scope I-H — Executor-loop skill pack

- `.agents/skills/orchestrate-executor-loop/SKILL.md`
- `.agents/skills/orchestrate-executor-loop/agents/openai.yaml`
- `.agents/skills/orchestrate-executor-loop/references/context-efficiency.md`
- `.agents/skills/orchestrate-executor-loop/references/handoff-review.md`
- `.agents/skills/orchestrate-executor-loop/references/remediation-state.md`
- `.agents/skills/orchestrate-executor-loop/references/restaurantpos-sequence.md`
- `.agents/skills/orchestrate-executor-loop/references/work-order-protocol.md`
- `.agents/skills/orchestrate-executor-loop/scripts/validate_packet.py`

Decision: separate skill-pack batch. Validate with `restaurantpos-skill-pack-quality`; do not combine with product remediation.

## 10. Baseline verification

Verified on `2026-07-15`:

- package integrity: pass, 52 checked, 0 missing, 0 stale, 0 contract failures;
- audit source/repository SHA-256 match:
  `2710F57B46A6EAC13E9C845DF86D4A7CFE4FAF4C1440D429A5E39CEA608B7C7C`;
- all 64 concrete paths are classified; no unclassified path remains;
- current branch/HEAD/main are recorded above;
- CORS contract: 8 pass, 1 fail at credentials-disabled invariant;
- staff-web typecheck: pass;
- staff-web lint: fail, so staff-web scopes remain quarantined.

## 11. Safe continuation order

1. Keep I-A as the audit-program documentation scope.
2. Execute B01 containment in a new isolated branch/worktree or an explicitly bounded path set.
3. Return to I-B/B02 for real MySQL verification and runbook reconciliation.
4. Review I-C separately with the web auth/session contract; do not inherit `true` as the default.
5. Keep I-D through I-H outside the audit critical path until their owning review is scheduled.

No path in I-C through I-H is authorized for deletion, reset or silent inclusion in another batch.

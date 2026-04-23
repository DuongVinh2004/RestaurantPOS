# Booking launch readiness

## Intent

`booking:launch-readiness` là canonical preflight verification gate để trả lời:

- build hiện tại đã sẵn sàng cho serious staging rollout chưa
- build hiện tại đã sẵn sàng cho limited production rollout chưa

Gate này không thay thế hoàn toàn full test suite hoặc postflight checks. Nó gom các source of truth release-readiness hiện có vào một readiness matrix thống nhất, sinh artifact JSON/Markdown, và phân tách rõ:

- blocking failures
- major warnings
- informational findings

## Inventory and source of truth

Integrated automated sources:

- `booking:doctor`
  - source of truth cho environment validation và runtime probes
- `booking:deploy-check --mode=preflight`
  - source of truth cho deploy preflight guardrails
- `config/feature_flags.php`
  - source of truth for day-1 feature flag posture
- `booking:route-gate`
  - source of truth cho locked runtime API surface drift
- `booking:core-ops-gate`
  - source of truth cho booking core flow verification
- `booking:round5-gate`
  - source of truth cho payment/checkout financial flow verification
- `booking:alert-check`
  - source of truth cho operational alert snapshot
- `booking:release-manifest`
  - source of truth cho release artifact completeness và frozen OpenAPI contract presence
- `booking:package-release --verify-frozen`
  - source of truth cho immutable package integrity

Manual source used by the matrix:

- UAT/demo scenario pack
  - source of truth cho manual operator evidence khi cần limited-production attestation

Related runbooks already in repo:

- `docs/runbooks/booking-deploy-runbook.md`
- `docs/runbooks/booking-release-packaging-runbook.md`
- `docs/runbooks/booking-alerting-runbook.md`
- `docs/runbooks/uat-demo-scenario-pack.md`

## Overlap and gaps

Current overlap intentionally preserved:

- `booking:doctor` và `booking:deploy-check` đều nhìn vào environment/runtime safety nhưng ở hai độ sâu khác nhau.
- `booking:round5-gate` và `booking:alert-check` đều có tín hiệu về payment safety, nhưng một bên là regression suite, một bên là live operational signal.
- `booking:release-manifest` và `booking:package-release` đều chạm release artifact integrity, nhưng manifest kiểm tính đầy đủ còn package kiểm tính buildable/immutable.

Current gaps not fully automated:

- canonical UAT/demo scenario pack vẫn là manual evidence
- candidate-specific `booking:performance-verify` report vẫn là manual evidence
- real third-party payment callback rehearsal vẫn là manual evidence
- real external notification delivery rehearsal vẫn là manual evidence
- multi-process Redis/MySQL concurrency rehearsal vẫn là manual evidence

When `booking:doctor` already reports missing runtime dependencies, `booking:launch-readiness` now records those blocking findings first and marks heavier downstream suites such as core-ops, round5, alert snapshot, and immutable package build as `SKIP`. That keeps missing MySQL/Redis from surfacing as misleading stack traces or unrelated downstream drift. A skipped downstream check in this state is still `not_ready`, not `runtime-green`.

## Canonical matrix

| Group | Check | Source | Blocking | Pass criteria |
| --- | --- | --- | --- | --- |
| Environment/runtime | Booking doctor baseline | `booking:doctor` | yes | No validation errors and runtime DB/Redis/scheduler/outbox probes all pass |
| Environment/runtime | Deploy preflight guardrail | `booking:deploy-check --mode=preflight` | yes | No blocking preflight errors across environment/migration/data/artifact/ops guards |
| Feature flag posture | Day-1 feature flag posture | `config/feature_flags.php` | yes | Target-required day-1 flags are registered, kill-switchable, and keep the expected production-like wildcard default |
| API surface/contract | Locked route inventory | `booking:route-gate` | yes | Runtime routes match the locked inventory |
| API surface/contract | Frozen OpenAPI contract artifact | `booking:release-manifest` | yes | OpenAPI release artifact exists and satisfies required fragments |
| Booking core flows | Core ops flow suite | `booking:core-ops-gate` | yes | Canonical core booking suite passes |
| Payment/checkout financial flows | Round 5 financial suite | `booking:round5-gate` | yes | Canonical checkout/refund/payment suite passes |
| Notifications/alerts | Operational alert snapshot | `booking:alert-check` | critical only | No critical alerts; warnings are surfaced as major warnings |
| Release artifact integrity | Release manifest snapshot health | `booking:release-manifest` | yes | No missing artifacts, missing fragments, or required SQL patch gaps |
| Release artifact integrity | Frozen manifest freshness | `booking:release-manifest --verify-frozen` | yes | Live manifest matches the frozen snapshot |
| Release artifact integrity | Immutable release package build | `booking:package-release --verify-frozen` | yes | Immutable package and sidecars build successfully |

Manual checks tracked by the artifact:

- `uat_scenario_pack_replay`
  - staging: major warning if missing
  - limited-production: blocking if missing
- `performance_verification_report`
  - staging: major warning if missing
  - limited-production: blocking if missing
- `payment_provider_external_e2e`
  - limited-production: blocking if missing
- `notification_provider_external_e2e`
  - staging: major warning if missing
  - limited-production: blocking if missing
  - only the `Email` channel currently qualifies because it is the repo's `production_lean` + `real` delivery lane
- `concurrency_rehearsal`
  - limited-production: blocking if missing

## Day-1 feature flag posture

The launch-readiness gate treats these flags as the day-1 release contract for staging and limited production. They must stay registered, kill-switchable, and disabled by the wildcard production-like default in `config/feature_flags.php` unless a later release ticket explicitly moves the feature into launch scope with evidence.

| Feature key | Day-1 state | Reason |
| --- | --- | --- |
| `customer.bill_self_payment` | off | Customer bill self-payment stays off for day 1. Bill preview and active-order reads may remain contract-visible, but settlement stays staff-owned until real provider evidence promotes customer self-pay. |
| `waiting_list.advanced_automation` | off | Customer waiting-list stays off by default and staff waiting-list remains manual on day 1; advanced automation must stay disabled. |
| `staff.kitchen_dispatch` | off | Kitchen/KDS dispatch and ticket mutations are not part of the day-1 launch promise, even if read-only kitchen routes remain visible. |
| `inventory.uplift` | off | Advanced inventory and purchasing workflows are not part of the day-1 limited-production scope. |
| `staff.conversation_inbox` | off | Conversation inbox may remain contract-visible, but it is held back from the day-1 operator promise until dedicated evidence promotes it. |
| `staff.conversation_ai_assist` | off | AI assist is optional and must not be enabled by default for day-1. |

Local and automated test defaults can still enable these flags for coverage. Production-like targets should start from the wildcard default and use audited DB overrides only when the release ticket names the flag, branch scope, reason, and rollback owner.

## True day-1 scope

Treat the launch-readiness result as a narrower promise than the full route contract.

Customer day-1 ON:

- auth and session restore
- menu browse
- table availability and holds
- reservation create, list, and detail

Customer day-1 OFF:

- waiting-list owner surfaces
- loyalty and voucher benefits
- privacy requests
- data export
- preorder
- customer bill self-payment as a default launch path

Staff day-1 ON:

- login
- table board
- reservation handling
- waiting-list manual notify, seat, and cancel flows where branches use them
- walk-in or service-session handling
- active order
- checkout or finalize
- refund or refund-cancel
- cashier shift
- finance review reads needed to finish service

Staff day-1 OFF:

- kitchen or KDS dispatch and ticket mutations
- advanced inventory and purchasing uplift
- conversation inbox
- conversation AI assist

Contract-visible but not launch-promised:

- reservation cancel and reschedule
- deposit preview and deposit payment-session routes
- bill preview or detail, active-order visibility, and bill payment-session routes
- kitchen landing or board read surfaces
- audit, reporting, admin settings, and admin inventory routes that may remain mounted for operator context

Payment stance:

- production-proven day-1 path: staff settlement, refund, and cashier-shift flow
- contract-ready only: customer deposit and bill payment-session routes, including any simulated-provider or local UAT proof

## Staff-web live smoke evidence

Use `staff-web` live smoke as the split-web operator evidence layer on top of the backend gates:

```bash
cd staff-web
npm run smoke:live
```

Default mode is read-only and should stay stable on preview/staging by only proving auth, board, lookup, preview, cashier show, and conversation reads.

When conversation detail succeeds, the smoke harness also records a non-blocking `conversation ai assist` step. That step is expected to pass with `status=ready|disabled|unavailable`, and it should never be treated as permission to skip the canonical timeline review.

Recommended preview/staging evidence env:

```bash
STAFF_WEB_SMOKE_TARGET=staging
STAFF_WEB_SMOKE_EVIDENCE_DIR=../storage/app/booking_release/staff_web_smoke
STAFF_WEB_SMOKE_PREVIEW_URL=https://preview.example.com
STAFF_WEB_SMOKE_PREVIEW_LABEL=vercel-preview
```

Those variables only annotate the smoke artifact. They do not create preview-proof by themselves. Treat preview evidence as complete only when the same candidate also has a real deployed URL plus runtime-log or release-tag evidence from the chosen platform.

Enable mutations only through explicit allowlist flags or manifest-backed mutation gates when the target environment is prepared for those writes:

- `STAFF_WEB_SMOKE_ALLOW_SETTLEMENT_FINALIZE=1`
- `STAFF_WEB_SMOKE_ALLOW_REFUND_MUTATION=1`
- `STAFF_WEB_SMOKE_ALLOW_CASHIER_OPEN=1`
- `STAFF_WEB_SMOKE_ALLOW_CASHIER_CLOSE=1`
- `STAFF_WEB_SMOKE_ALLOW_ORDER_CREATE=1`
- `STAFF_WEB_SMOKE_ALLOW_ORDER_ADD_ITEM=1`

The smoke summary now prints `decision=pass|block` and per-action mutation gate state. When `STAFF_WEB_SMOKE_EVIDENCE_DIR` is set, the smoke harness also writes JSON/Markdown evidence plus `latest-<target>` pointers so launch-readiness and release-loop artifacts can archive the exact split-web result.

## Usage

Serious staging rollout:

```bash
php artisan booking:launch-readiness --target=staging --json
```

Full backend + `staff-web` release loop:

```bash
php artisan booking:release-loop \
  --target=staging \
  --manifest-path=storage/app/uat/scenario-pack.json \
  --base-url=http://127.0.0.1:8000 \
  --bootstrap-uat
```

Limited production rollout with manual evidence:

```bash
php artisan booking:launch-readiness \
  --target=limited-production \
  --manual-evidence=storage/app/booking_release/manual_evidence/limited-production-20260405.json \
  --json
```

Use an operator-owned candidate evidence file under `storage/app/booking_release/manual_evidence/` or the release artifact store. Do not point limited-production sign-off at `tests/fixtures/*`; those fixtures exist for automated tests, not rollout attestation.

That manual evidence file is only complete for limited production when all five target-specific checks are recorded as `pass`: `uat_scenario_pack_replay`, `performance_verification_report`, `payment_provider_external_e2e`, `notification_provider_external_e2e`, and `concurrency_rehearsal`.

Scaffold an operator-owned template before the rehearsal:

```bash
php artisan booking:manual-evidence:init --target=staging --candidate=20260420 --json
php artisan booking:manual-evidence:init --target=limited-production --candidate=20260420 --json
```

By default the command writes to:

- `storage/app/booking_release/manual_evidence/staging-20260420.json`
- `storage/app/booking_release/manual_evidence/limited-production-20260420.json`

Use `--output=<path>` when the release ticket or artifact store expects a different candidate filename, and `--overwrite` only when you intentionally want to replace an older operator template.

Optional package override:

```bash
php artisan booking:launch-readiness \
  --target=staging \
  --package-id=staging-20260405-r1 \
  --overwrite-package \
  --json
```

## Follow-up action plan

When `booking:launch-readiness` returns `ready_with_warnings` or `not_ready`, the JSON and Markdown artifacts now emit a `follow_up_actions` section.

Use that section as the operator checklist for the next pass:

- scaffold the operator-owned manual evidence template when `--manual-evidence` was not supplied
- jump directly to the owning runbook for each missing manual check
- copy the exact command examples for UAT replay, performance verification, notification rehearsal, and concurrency probes
- rerun `booking:launch-readiness` with the recorded manual evidence path after the operator proof is captured

`booking:release-loop` now carries those same launch-readiness follow-up actions forward and adds separate actions for missing preview proof and missing Sentry release/runtime context.

The same JSON/Markdown artifacts now also emit a `release_handoff` section. Use that section to copy the exact promotion candidate and archive set into the release ticket:

- `release_handoff.candidate.package_basename`
- `release_handoff.candidate.package_path`
- `release_handoff.candidate.sidecars.*`
- `release_handoff.candidate.release_manifest_snapshot_path`
- `release_handoff.manual_evidence.path`
- `release_handoff.archive_paths[]`

`release_handoff` is the fast path for reviewer/operator handoff. It summarizes the exact immutable package + sidecars + manual evidence bundle for the candidate and repeats the rollback rule that the previous known-good package must stay archived separately from `latest-package.json`.

## Manual evidence JSON shape

```json
{
  "checks": {
    "uat_scenario_pack_replay": {
      "status": "pass",
      "performed_by": "qa.release",
      "performed_at_utc": "2026-04-05T08:30:00Z",
      "notes": "Ran canonical scenario pack against the candidate build."
    },
    "performance_verification_report": {
      "status": "pass",
      "performed_by": "qa.release",
      "performed_at_utc": "2026-04-05T09:18:00Z",
      "notes": "Archived the candidate-specific booking:performance-verify staging report with the automated scenarios and operator-assisted probe notes."
    },
    "payment_provider_external_e2e": {
      "status": "pass",
      "performed_by": "qa.release",
      "performed_at_utc": "2026-04-05T09:10:00Z",
      "notes": "Validated one payment-provider callback rehearsal in staging against the target credentials."
    },
    "notification_provider_external_e2e": {
      "status": "pass",
      "performed_by": "qa.release",
      "performed_at_utc": "2026-04-05T09:25:00Z",
      "notes": "Validated one real email delivery rehearsal in staging and captured outbox attempt evidence plus recipient confirmation."
    },
    "concurrency_rehearsal": {
      "status": "pass",
      "performed_by": "qa.release",
      "performed_at_utc": "2026-04-05T09:45:00Z",
      "notes": "Completed the Redis/MySQL multi-process contention rehearsal for table-hold and checkout flows."
    }
  }
}
```

Supported statuses:

- `pass`
- `fail`
- missing entry means no passing evidence yet

## Exit codes

- `0`
  - ready
  - no blocking failures
  - no major warnings
- `2`
  - ready with warnings
  - no blocking failures, but major warnings remain
- `1`
  - not ready
  - at least one blocking failure exists

## Artifact locations

Every run writes JSON and Markdown evidence under:

- `storage/app/booking_release/launch_readiness/reports/`

Artifacts written per target:

- `launch-readiness-<target>-<timestamp>.json`
- `launch-readiness-<target>-<timestamp>.md`
- `latest-<target>.json`
- `latest-<target>.md`

The JSON artifact contains:

- group summary
- per-check matrix status
- blocking failures
- major warnings
- informational findings
- manual checks
- release handoff summary for package + sidecars + manual evidence
- automation gaps
- raw source payloads from the integrated commands/services

## How to read the result

1. Check `decision` and `exit_code`.
2. Read group statuses to find the failing domain.
3. Read `blocking_failures` first.
4. If `decision=ready_with_warnings`, review `major_warnings` before promotion.
5. For limited production, confirm all manual checks are `pass`.

## Remediation flow

1. Fix the failing source command or suite directly.
2. Re-run that source command in isolation for faster feedback.
3. Re-run `booking:launch-readiness`.
4. If frozen manifest changed intentionally, refresh the frozen release evidence before packaging/promoting.
5. Archive the resulting JSON/Markdown artifacts with the release evidence bundle.

Runtime-baseline shortcut:

- if `booking:doctor` is already failing on DB/Redis/scheduler/outbox prerequisites, clear those live blockers before expecting `booking:launch-readiness` to execute the heavier downstream suites
- `notifications:outbox-health --json`, `booking:deploy-check --mode=preflight --strict --json`, and `npm run smoke:live` should be used to capture the exact blocker while the environment is still down
- interpret `runtime.db` and `runtime.redis` as root environment blockers
- interpret `runtime.scheduler` as dependency-blocked when the message says it is blocked by `runtime.redis`; restore Redis first before treating scheduler heartbeat as a separate issue
- interpret `runtime.outbox` as dependency-blocked when the message says it is blocked by `runtime.db`; restore MySQL first before treating outbox counts as queue drift
- `booking:deploy-check --mode=preflight` will continue to prove artifact truth, but DB-dependent migration/data/ops sections are expected to stay warning-only until the target MySQL runtime is reachable

## Harness shortcut

Use the combined harness shortcut when you want the canonical launch-readiness result plus the golden-flow and runtime-gate context in one payload:

```bash
php artisan booking:harness:release-readiness --target=staging --json
```

Use `booking:release-loop` instead when the release candidate also needs `staff-web` test/build/live-smoke evidence in the same artifact family.

`booking:release-loop` keeps collecting later safe evidence steps even after an earlier blocking step fails, so the final report can still archive backend and `staff-web` diagnostics in one artifact family for triage.

# Booking backend final audit snapshot

## Current status
- Working level: pre-production hardening / near production-ready
- Mandatory hardening rounds completed through patch 15
- Remaining work is optional or environment-specific, not blocking for a serious staging rollout
- Latest local release-candidate evidence: 2026-04-29 remains no-go for staging and limited production because MySQL/Redis runtime blockers, outbox dependency blocking, full-suite timeout, dirty worktree, and missing manual UAT/performance/payment/DR evidence are still open. See `docs/runbooks/go-live-candidate-checklist.md#release-candidate-evidence---2026-04-29`.
- Batch 2 release-verification evidence after cashier-shift reconciliation hardening is still BLOCKED locally: SQL/release/package/static gates passed, but MySQL/Redis/scheduler runtime smoke and the monolithic PHPUnit suite are not proven in this environment.

## What is now covered
- Schema drift cleanup for pre-order and loyalty tier row version
- Financial integrity DB checks for reservation/order item/payment/voucher consistency
- Reservation lifecycle consistency checks
- Voucher and loyalty refund preview plus side-effect visibility
- Idempotency replay and in-progress lock test scaffold
- Environment validation, deploy preflight/postflight, outbox health, doctor commands
- API contract aliases and actor-aware response redaction
- Frozen release manifest freshness verification
- Immutable release packaging with inventory, checksums, and machine-readable sidecars
- Cashier-shift reconciliation hardening:
  - `payments.cashier_shift_id` is a nullable FK to `cashier_shifts`
  - staff payment, refund, and deposit flows persist the open shift id for new money rows
  - cashier-shift summaries use FK-first reconciliation and only fall back to cashier/branch/time-window/currency matching for legacy rows where `cashier_shift_id` is `NULL`
  - generic HMAC payment webhooks reject missing timestamp headers when replay-window enforcement is enabled

## Remaining known gaps
1. Full end-to-end PHPUnit/CI execution still depends on the complete project root, composer dependencies, and environment bootstrap in your real repository.
2. True concurrency stress testing for multi-process booking races should still be run in staging with Redis + MySQL under load.
3. API deprecation cleanup is still optional: the remaining rollout-safety aliases are `close`, `checkout`, `voucher/release`, `loyalty/release`, and `table-board`; `X-Idempotency-Key` plus body field `idempotency_key` also remain accepted for compatibility. Removal now requires `booking:route-gate --json` evidence plus one release cycle of zero audit hits for `api_deprecated_alias_used` / `idempotency_compatibility_key_used`.
4. Experimental reporting remains operator-context only and must not be presented as certified accounting unless a later release adds explicit certification.

## Recommended release gate
Use the manual GitHub Actions workflow `.github/workflows/booking-release-gate.yml` as the canonical release evidence boundary whenever the repository is hosted on GitHub.

Run these in order:
1. `php artisan booking:launch-readiness --target=staging --json`
2. for limited production, re-run with `--target=limited-production --manual-evidence=<path>` where the manual evidence file records schema-valid `pass` evidence for `uat_scenario_pack_replay`, `disaster_recovery_restore_evidence`, `performance_verification_report`, `payment_provider_external_e2e`, `notification_provider_external_e2e`, and `concurrency_rehearsal`
3. smoke tests from `scripts/ci/booking-full-gate.sh`
4. deploy + migrate
5. `php artisan booking:deploy-check --mode=postflight --strict`
6. `php artisan notifications:outbox-health --json`

If the aggregated gate is not clean, use the underlying source commands as drill-down tools:

- `php artisan booking:doctor --strict`
- `php artisan booking:deploy-check --mode=preflight --strict`
- `php artisan booking:route-gate --json`
- `php artisan booking:core-ops-gate --json`
- `php artisan booking:round5-gate --json`
- `php artisan booking:release-manifest --verify-frozen --json`
- `php artisan booking:package-release --verify-frozen --json`

Limited-production manual evidence must be safe to commit/share when intended: no real provider secrets, webhook secrets, bearer tokens, API keys, connection strings, demo passwords, or production credentials. Customer self-pay may stay disabled for day 1, but that disabled posture still needs explicit evidence that staff settlement remains the safe payment path.

## Recommended deprecation plan
- Keep the remaining rollout-safety aliases for one release cycle
- Update FE/integrations to canonical endpoints
- Collect `booking:route-gate --json` and retain `meta.alias_deprecation_plan` with the release evidence package
- Query the audit channel for `api_deprecated_alias_used` by alias key and `idempotency_compatibility_key_used` by source
- After one release cycle, remove aliases only if logs show zero usage and `staff-web npm run integrity:check` plus `customer-web npm run verify:contracts` still pass

See also:
- `docs/runbooks/booking-alerting-runbook.md`
- `docs/runbooks/booking-release-packaging-runbook.md`

# Booking backend final audit snapshot

## Current status
- Working level: pre-production hardening / near production-ready
- Mandatory hardening rounds completed through patch 15
- Remaining work is optional or environment-specific, not blocking for a serious staging rollout

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

## Remaining known gaps
1. Full end-to-end PHPUnit/CI execution still depends on the complete project root, composer dependencies, and environment bootstrap in your real repository.
2. True concurrency stress testing for multi-process booking races should still be run in staging with Redis + MySQL under load.
3. API deprecation cleanup is still optional: legacy aliases such as `close`, `checkout`, `voucher/remove`, and `loyalty/redeem/release` are intentionally kept for compatibility.
4. Business reporting / BI reconciliation jobs are still optional and were not added in these patches.

## Recommended release gate
Use the manual GitHub Actions workflow `.github/workflows/booking-release-gate.yml` as the canonical release evidence boundary whenever the repository is hosted on GitHub.

Run these in order:
1. `php artisan booking:launch-readiness --target=staging --json`
2. for limited production, re-run with `--target=limited-production --manual-evidence=<path>` where the manual evidence file records `pass` for `uat_scenario_pack_replay`, `performance_verification_report`, `payment_provider_external_e2e`, `notification_provider_external_e2e`, and `concurrency_rehearsal`
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

## Recommended deprecation plan
- Keep legacy aliases for one release cycle
- Update FE/integrations to canonical endpoints
- After one release cycle, remove aliases only if logs show zero usage

See also:
- `docs/runbooks/booking-alerting-runbook.md`
- `docs/runbooks/booking-release-packaging-runbook.md`

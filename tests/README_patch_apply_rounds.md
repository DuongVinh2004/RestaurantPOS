# Patch apply order (round 1 -> round 6)

Use this checklist when applying the hardening patches on a real project copy.

## 1) Apply patches in order

1. round 1: authz + integrity + reliability core
2. round 2: deploy safety + ops snapshot
3. round 3: release artifact manifest + financial regression locks
4. round 4: staff API key lifecycle hardening
5. round 5: consistency + legacy fallback cleanup
6. round 6: operability + release checks + ops visibility

Do not skip earlier rounds if the target tree is still on the original upload state.

## 2) Recommended local apply flow

1. extract each patch over the project root in order
2. review changed files with `git diff --stat`
3. run DB hardening SQL from round 1
4. clear framework caches
5. run deploy and ops checks
6. run focused PHPUnit suites for booking flows

## 3) Suggested commands

```bash
php artisan optimize:clear
php artisan booking:deploy-check --mode=preflight --strict
php artisan booking:ops-snapshot --json
php artisan booking:doctor --json --strict
php artisan booking:release-manifest --json
php artisan booking:backfill-confirmed-hold-linkage --dry-run
vendor/bin/phpunit --filter StaffCheckout
vendor/bin/phpunit --filter Voucher
vendor/bin/phpunit --filter Loyalty
vendor/bin/phpunit --filter ReservationFinancialSyncServiceFeatureTest
```

## 4) Production / staging smoke list

Verify these after deploy:

- a normal staff user cannot call refund / loyalty adjust / voucher manage routes without the required capability
- customer reservation lookup still works with exact linkage records
- legacy session fallback remains disabled by default
- `booking:deploy-check` returns clean preflight and postflight summaries
- `booking:ops-snapshot` reports no suspicious active unlinked holds
- partial deposit refund still lands in `PartiallyRefunded`
- voucher removal unlocks the user voucher row
- loyalty redemption release restores user points correctly

## 5) Data migration follow-up

After the code is live, run the linkage backfill on a safe copy first:

```bash
php artisan booking:backfill-confirmed-hold-linkage --dry-run
php artisan booking:backfill-confirmed-hold-linkage
```

Only remove legacy fallback code entirely after the backfill result is verified.

- Round 7: complete staff mutation row_version contract and add release/ops regression checks for optimistic-locking coverage.

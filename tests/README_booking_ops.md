# Booking ops / CI smoke notes

This round adds a lightweight operability smoke layer on top of the earlier booking tests.

## Suggested CI / pre-deploy order

1. `php artisan config:clear`
2. `php artisan booking:doctor --json`
3. `php artisan route:list --path=v1`
4. `vendor/bin/phpunit --group booking-smoke`
5. `bash scripts/ci/booking-ops-smoke.sh`

## What `booking:doctor` checks

- static booking configuration invariants
- database reachability
- redis set/get + lock capability
- scheduler heartbeat freshness
- notification outbox backlog summary

## Exit code behavior

- returns `0` when validation passes and runtime dependencies are healthy
- returns `1` on validation errors, runtime failures, or when `--strict` is used and warnings exist

## Recommended production thresholds

Set these explicitly in `.env` instead of relying on defaults:

- `SCHEDULER_HEARTBEAT_TTL_SECONDS`
- `SCHEDULER_HEARTBEAT_STALE_SECONDS`
- `NOTIFICATIONS_OUTBOX_LOCK_SECONDS`
- `NOTIFICATIONS_OUTBOX_HEALTH_FAILED_THRESHOLD`
- `NOTIFICATIONS_OUTBOX_HEALTH_OLDEST_PENDING_SECONDS`
- `RESERVATION_LOCK_TTL_SECONDS`
- `RESERVATION_LOCK_WAIT_SECONDS`

# Booking deploy safety

Useful commands:

- `php artisan booking:deploy-check --mode=preflight --strict`
- `php artisan booking:deploy-check --mode=postflight --strict`
- `scripts/ci/booking-deploy-preflight.sh`
- `scripts/ci/booking-deploy-postflight.sh`

What this checks:

- environment/config validation summary
- migration repository and pending migration visibility
- fail-fast data guards for deposit status, payment refund lineage, reservation lifecycle, order item totals, and voucher state
- postflight mode additionally fails when the release still has pending migrations

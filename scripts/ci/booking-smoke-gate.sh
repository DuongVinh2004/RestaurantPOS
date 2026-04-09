#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build/booking-ci storage/logs

php artisan config:clear
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
# cache:clear flushes the Redis-backed ops heartbeat, so refresh it before
# runtime smoke checks verify scheduler freshness.
php artisan booking:ops-heartbeat:touch scheduler --json >/dev/null

php artisan booking:doctor --json | tee build/booking-ci/booking-doctor-smoke.json >/dev/null
php artisan booking:deploy-check --mode=preflight --json | tee build/booking-ci/booking-deploy-check-smoke-preflight.json >/dev/null
php artisan booking:release-manifest --verify-frozen --json | tee build/booking-ci/booking-release-manifest-smoke.json >/dev/null

php artisan booking:doctor --strict
php artisan booking:deploy-check --mode=preflight --strict

bash scripts/ci/booking-smoke.sh
bash scripts/ci/booking-ops-smoke.sh
bash scripts/ci/booking-reliability-smoke.sh

echo "booking smoke gate completed"

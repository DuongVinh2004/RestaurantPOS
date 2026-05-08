#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

source scripts/ci/booking-ci-lib.sh

mkdir -p build/booking-ci storage/logs

booking_ci_run_step \
  "smoke-config-clear" \
  "booking smoke config clear" \
  120 \
  "build/booking-ci/smoke-config-clear.log" \
  "php artisan config:clear --ansi && (php artisan cache:clear || true) && (php artisan route:clear || true) && (php artisan view:clear || true)"

# cache:clear flushes the Redis-backed ops heartbeat, so refresh it before
# runtime smoke checks verify scheduler freshness.
booking_ci_run_step \
  "smoke-heartbeat-touch" \
  "booking smoke scheduler heartbeat touch" \
  120 \
  "build/booking-ci/smoke-heartbeat-touch.log" \
  "php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/booking-ops-heartbeat-touch.json"

booking_ci_run_step \
  "smoke-doctor" \
  "booking doctor smoke" \
  180 \
  "build/booking-ci/smoke-doctor.log" \
  "php artisan booking:doctor --json | tee build/booking-ci/booking-doctor-smoke.json"

booking_ci_run_step \
  "smoke-outbox-health" \
  "booking outbox health smoke" \
  180 \
  "build/booking-ci/smoke-outbox-health.log" \
  "php artisan notifications:outbox-health --json | tee build/booking-ci/booking-outbox-health-smoke.json"

booking_ci_run_step \
  "smoke-reporting-snapshots-rebuild" \
  "booking reporting snapshots rebuild smoke" \
  300 \
  "build/booking-ci/smoke-reporting-snapshots-rebuild.log" \
  "php artisan booking:reporting-snapshots:rebuild --json | tee build/booking-ci/booking-reporting-snapshots-rebuild-smoke.json"

booking_ci_run_step \
  "smoke-deploy-check" \
  "booking deploy preflight smoke" \
  240 \
  "build/booking-ci/smoke-deploy-check.log" \
  "php artisan booking:deploy-check --mode=preflight --json | tee build/booking-ci/booking-deploy-check-smoke-preflight.json"

booking_ci_run_step \
  "smoke-release-manifest" \
  "booking release manifest smoke" \
  180 \
  "build/booking-ci/smoke-release-manifest.log" \
  "php artisan booking:release-manifest --verify-frozen --json | tee build/booking-ci/booking-release-manifest-smoke.json"

booking_ci_run_step \
  "smoke-doctor-strict" \
  "booking doctor smoke strict" \
  180 \
  "build/booking-ci/smoke-doctor-strict.log" \
  "php artisan booking:doctor --strict --json | tee build/booking-ci/booking-doctor-smoke-strict.json"

booking_ci_run_step \
  "smoke-outbox-health-strict" \
  "booking outbox health smoke strict" \
  180 \
  "build/booking-ci/smoke-outbox-health-strict.log" \
  "php artisan notifications:outbox-health --json | tee build/booking-ci/booking-outbox-health-smoke-strict.json"

booking_ci_run_step \
  "smoke-deploy-check-strict" \
  "booking deploy preflight smoke strict" \
  240 \
  "build/booking-ci/smoke-deploy-check-strict.log" \
  "php artisan booking:deploy-check --mode=preflight --strict --json | tee build/booking-ci/booking-deploy-check-smoke-preflight-strict.json"

booking_ci_run_step \
  "smoke-runtime-smoke" \
  "booking runtime smoke" \
  "${BOOKING_SMOKE_GATE_RUNTIME_TIMEOUT_SECONDS:-1200}" \
  "build/booking-ci/smoke-runtime-smoke.log" \
  "bash scripts/ci/booking-runtime-smoke.sh"

booking_ci_run_step \
  "smoke-fast-booking" \
  "booking fast smoke tests" \
  "${BOOKING_SMOKE_GATE_FAST_TIMEOUT_SECONDS:-1200}" \
  "build/booking-ci/smoke-fast-booking.log" \
  "bash scripts/ci/booking-smoke.sh"

# Runtime and idempotency smoke tests intentionally flush Redis state. Refresh
# the scheduler heartbeat again before downstream ops checks assert freshness.
booking_ci_run_step \
  "smoke-heartbeat-touch-before-ops" \
  "booking smoke scheduler heartbeat touch before ops" \
  120 \
  "build/booking-ci/smoke-heartbeat-touch-before-ops.log" \
  "php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/booking-ops-heartbeat-touch-before-ops.json"

booking_ci_run_step \
  "smoke-ops" \
  "booking ops smoke tests" \
  "${BOOKING_SMOKE_GATE_OPS_TIMEOUT_SECONDS:-1200}" \
  "build/booking-ci/smoke-ops.log" \
  "bash scripts/ci/booking-ops-smoke.sh"

booking_ci_run_step \
  "smoke-heartbeat-touch-before-reliability" \
  "booking smoke scheduler heartbeat touch before reliability" \
  120 \
  "build/booking-ci/smoke-heartbeat-touch-before-reliability.log" \
  "php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/booking-ops-heartbeat-touch-before-reliability.json"

booking_ci_run_step \
  "smoke-reliability" \
  "booking reliability smoke tests" \
  "${BOOKING_SMOKE_GATE_RELIABILITY_TIMEOUT_SECONDS:-1200}" \
  "build/booking-ci/smoke-reliability.log" \
  "bash scripts/ci/booking-reliability-smoke.sh"

echo "booking smoke gate completed"

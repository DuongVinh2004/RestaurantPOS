#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

source scripts/ci/booking-ci-lib.sh

mkdir -p build/booking-ci storage/logs

booking_ci_env_value() {
  local key="$1"
  local fallback="${2:-}"
  local line=""

  if [[ -f .env ]]; then
    line="$(grep -E "^${key}=" .env 2>/dev/null | tail -n 1 || true)"
  fi

  if [[ -n "$line" ]]; then
    local value="${line#*=}"
    value="${value%$'\r'}"
    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"
    printf '%s' "$value"
    return
  fi

  printf '%s' "$fallback"
}

export APP_ENV="${APP_ENV:-testing}"
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-$(booking_ci_env_value DB_HOST 127.0.0.1)}"
export DB_PORT="${DB_PORT:-$(booking_ci_env_value DB_PORT 3306)}"
export DB_DATABASE="${DB_DATABASE:-$(booking_ci_env_value DB_DATABASE restaurantdb)}"
export DB_USERNAME="${DB_USERNAME:-$(booking_ci_env_value DB_USERNAME root)}"
export DB_PASSWORD="${DB_PASSWORD:-$(booking_ci_env_value DB_PASSWORD '')}"
export CACHE_STORE="${CACHE_STORE:-redis}"
export REDIS_HOST="${REDIS_HOST:-$(booking_ci_env_value REDIS_HOST 127.0.0.1)}"
export REDIS_PORT="${REDIS_PORT:-$(booking_ci_env_value REDIS_PORT 6379)}"
export REDIS_PASSWORD="${REDIS_PASSWORD:-$(booking_ci_env_value REDIS_PASSWORD null)}"
export REQUIRE_REDIS_FOR_BOOKING_API="${REQUIRE_REDIS_FOR_BOOKING_API:-true}"
export BOOKING_REALTIME_ENABLED="${BOOKING_REALTIME_ENABLED:-true}"
export BOOKING_REALTIME_CACHE_STORE="${BOOKING_REALTIME_CACHE_STORE:-redis}"

runtime_timeout="${BOOKING_RUNTIME_SMOKE_TIMEOUT_SECONDS:-1200}"

booking_ci_run_step \
  "runtime-config-clear" \
  "runtime config clear" \
  120 \
  "build/booking-ci/runtime-config-clear.log" \
  "php artisan config:clear --ansi"

booking_ci_run_step \
  "runtime-heartbeat-touch" \
  "runtime scheduler heartbeat touch" \
  120 \
  "build/booking-ci/runtime-heartbeat-touch.log" \
  "php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/runtime-ops-heartbeat-touch.json"

booking_ci_run_step \
  "runtime-doctor-strict" \
  "runtime doctor strict" \
  180 \
  "build/booking-ci/runtime-doctor-strict.log" \
  "php artisan booking:doctor --strict --json | tee build/booking-ci/runtime-doctor-strict.json"

booking_ci_run_step \
  "runtime-outbox-health" \
  "runtime notification outbox health" \
  180 \
  "build/booking-ci/runtime-outbox-health.log" \
  "php artisan notifications:outbox-health --json | tee build/booking-ci/runtime-outbox-health.json"

booking_ci_run_step \
  "runtime-reporting-snapshots-rebuild" \
  "runtime reporting snapshots rebuild" \
  300 \
  "build/booking-ci/runtime-reporting-snapshots-rebuild.log" \
  "php artisan booking:reporting-snapshots:rebuild --json | tee build/booking-ci/runtime-reporting-snapshots-rebuild.json"

booking_ci_run_step \
  "runtime-deploy-check-strict" \
  "runtime deploy preflight strict" \
  240 \
  "build/booking-ci/runtime-deploy-check-strict.log" \
  "php artisan booking:deploy-check --mode=preflight --strict --json | tee build/booking-ci/runtime-deploy-check-strict.json"

booking_ci_run_step \
  "runtime-smoke-tests" \
  "runtime MySQL Redis smoke tests" \
  "$runtime_timeout" \
  "build/booking-ci/runtime-smoke-tests.log" \
  "php artisan test tests/Feature/Runtime --log-junit build/booking-ci/runtime-smoke-junit.xml"

printf '%s\n' "[booking-runtime-smoke] runtime smoke completed."

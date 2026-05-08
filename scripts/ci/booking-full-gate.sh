#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

source scripts/ci/booking-ci-lib.sh

mkdir -p build/booking-ci storage/logs

run_full_gate_step() {
  local step_id="$1"
  local step_name="$2"
  local timeout_seconds="$3"
  local log_path="$4"
  local command_string="$5"

  set +e
  booking_ci_run_step "$step_id" "$step_name" "$timeout_seconds" "$log_path" "$command_string"
  local status="$?"
  set -e

  if [[ "$status" -ne 0 ]]; then
    {
      printf 'step_id=%s\n' "$step_id"
      printf 'step_name=%s\n' "$step_name"
      printf 'exit_code=%s\n' "$status"
      printf 'command=%s\n' "$command_string"
      printf 'log=%s\n' "$log_path"
    } > build/booking-ci/full-gate-first-failure.txt

    printf '[booking-full-gate] first failing command: %s (%s), exit code %s. See %s\n' \
      "$step_name" "$command_string" "$status" "$log_path" >&2
    exit "$status"
  fi
}

run_full_gate_step \
  "full-smoke-gate" \
  "booking smoke gate" \
  "${BOOKING_FULL_GATE_SMOKE_TIMEOUT_SECONDS:-1800}" \
  "build/booking-ci/full-smoke-gate.log" \
  "bash scripts/ci/booking-smoke-gate.sh"

run_full_gate_step \
  "full-phpunit" \
  "full PHPUnit" \
  "${BOOKING_FULL_GATE_PHPUNIT_TIMEOUT_SECONDS:-3600}" \
  "build/booking-ci/full-phpunit.log" \
  "php artisan test --log-junit build/booking-ci/phpunit-full-junit.xml"

run_full_gate_step \
  "full-phpstan" \
  "phpstan evidence lane" \
  "${BOOKING_FULL_GATE_PHPSTAN_TIMEOUT_SECONDS:-2400}" \
  "build/booking-ci/full-phpstan.log" \
  "BOOKING_PHPSTAN_MEMORY_LIMIT=${BOOKING_FULL_GATE_PHPSTAN_MEMORY_LIMIT:-3G} bash scripts/ci/booking-phpstan.sh"

run_full_gate_step \
  "full-pint" \
  "pint style check" \
  "${BOOKING_FULL_GATE_PINT_TIMEOUT_SECONDS:-600}" \
  "build/booking-ci/full-pint.log" \
  "vendor/bin/pint --test"

run_full_gate_step \
  "full-route-gate" \
  "booking route gate" \
  300 \
  "build/booking-ci/full-route-gate.log" \
  "php artisan booking:route-gate --json | tee build/booking-ci/booking-route-gate.json"

run_full_gate_step \
  "full-core-ops-gate" \
  "booking core ops gate" \
  300 \
  "build/booking-ci/full-core-ops-gate.log" \
  "php artisan booking:core-ops-gate --json | tee build/booking-ci/booking-core-ops-gate.json"

run_full_gate_step \
  "full-round5-gate" \
  "booking round five gate" \
  300 \
  "build/booking-ci/full-round5-gate.log" \
  "php artisan booking:round5-gate --json | tee build/booking-ci/booking-round5-gate.json"

echo "booking full gate completed"

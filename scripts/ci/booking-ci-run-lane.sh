#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

source scripts/ci/booking-ci-lib.sh

lane_command="${BOOKING_CI_LANE_COMMAND:-}"
lane_id="${BOOKING_CI_LANE_ID:-lane}"
lane_name="${BOOKING_CI_LANE_NAME:-$lane_id}"
lane_timeout="${BOOKING_CI_LANE_TIMEOUT_SECONDS:-1800}"

if [[ -z "$lane_command" ]]; then
  echo "[booking-ci-lane] BOOKING_CI_LANE_COMMAND is required." >&2
  exit 1
fi

mkdir -p build/booking-ci storage/logs
lane_log="build/booking-ci/${lane_id}.log"

printf '[booking-ci-lane] running %s: %s\n' "$lane_name" "$lane_command"

booking_ci_run_step "$lane_id" "$lane_name" "$lane_timeout" "$lane_log" "$lane_command"

printf '[booking-ci-lane] %s completed.\n' "$lane_name"

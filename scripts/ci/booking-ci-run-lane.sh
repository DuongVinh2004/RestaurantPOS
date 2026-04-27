#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

escape_github_annotation() {
  local data="$1"
  data="${data//'%'/'%25'}"
  data="${data//$'\r'/'%0D'}"
  data="${data//$'\n'/'%0A'}"
  printf '%s' "$data"
}

lane_command="${BOOKING_CI_LANE_COMMAND:-}"
lane_id="${BOOKING_CI_LANE_ID:-lane}"
lane_name="${BOOKING_CI_LANE_NAME:-$lane_id}"

if [[ -z "$lane_command" ]]; then
  echo "[booking-ci-lane] BOOKING_CI_LANE_COMMAND is required." >&2
  exit 1
fi

mkdir -p build/booking-ci storage/logs
lane_log="build/booking-ci/${lane_id}.log"

printf '[booking-ci-lane] running %s: %s\n' "$lane_name" "$lane_command"

set +e
bash -o pipefail -c "$lane_command" 2>&1 | tee "$lane_log"
lane_status="${PIPESTATUS[0]}"
set -e

if [[ "$lane_status" -ne 0 ]]; then
  lane_tail="$(sed -n '/FAILED/,$p' "$lane_log" 2>/dev/null | tail -n 100 || true)"
  if [[ -z "$lane_tail" ]]; then
    lane_tail="$(tail -n 120 "$lane_log" 2>/dev/null || true)"
  fi

  if [[ -n "$lane_tail" && -n "${GITHUB_ACTIONS:-}" ]]; then
    printf '::error title=Booking CI lane failed (%s)::%s\n' "$lane_name" "$(escape_github_annotation "$lane_tail")"
  fi

  exit "$lane_status"
fi

printf '[booking-ci-lane] %s completed.\n' "$lane_name"

#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build/booking-ci

ARGS=("$@")
if [[ ${#ARGS[@]} -eq 0 && -n "${BOOKING_VERIFY_BASE:-}" ]]; then
  ARGS+=("--base=${BOOKING_VERIFY_BASE}")
fi

php artisan booking:verify-select --json "${ARGS[@]}" | tee build/booking-ci/booking-verify-select.json >/dev/null

echo "booking verify selector completed"

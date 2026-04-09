#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build/booking-ci storage/logs

bash scripts/ci/booking-smoke-gate.sh

php artisan booking:route-gate --json | tee build/booking-ci/booking-route-gate.json >/dev/null
php artisan booking:core-ops-gate --json | tee build/booking-ci/booking-core-ops-gate.json >/dev/null
php artisan booking:round5-gate --json | tee build/booking-ci/booking-round5-gate.json >/dev/null

echo "booking full gate completed"

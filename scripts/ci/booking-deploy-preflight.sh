#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build/booking-ci

php artisan booking:doctor --strict --json | tee build/booking-ci/booking-doctor-preflight-strict.json
php artisan notifications:outbox-health --json | tee build/booking-ci/booking-outbox-health-preflight.json
php artisan booking:deploy-check --mode=preflight --strict --json | tee build/booking-ci/booking-deploy-check-preflight-strict.json

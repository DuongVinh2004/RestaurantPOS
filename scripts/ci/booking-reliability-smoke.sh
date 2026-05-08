#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build/booking-ci

php artisan booking:ops-heartbeat:touch scheduler --json | tee build/booking-ci/reliability-ops-heartbeat-touch.json
php artisan booking:doctor --strict
php artisan notifications:outbox-health --json >/dev/null

bash scripts/ci/booking-test-runner.sh --group booking-smoke --filter NotificationOutboxServiceSmokeTest
bash scripts/ci/booking-test-runner.sh --group booking-ops --filter NotificationOutboxHealthServiceTest
bash scripts/ci/booking-test-runner.sh --group booking-ops --filter NotificationOpsCommandsTest

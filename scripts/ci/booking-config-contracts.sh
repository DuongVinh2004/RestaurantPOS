#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build/booking-ci storage/logs

php artisan config:clear
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan config:cache
php artisan config:clear

bash scripts/ci/booking-test-runner.sh \
  tests/Unit/Config \
  tests/Unit/Services/BookingEnvironmentValidatorTest.php \
  tests/Unit/Services/BookingReleaseConfigContractTest.php \
  tests/Unit/Services/CoreOpsGateServiceTest.php \
  tests/Unit/Services/RoundFiveGateServiceTest.php \
  tests/Unit/Services/RouteInventoryGateServiceTest.php \
  tests/Unit/Services/ReleasePackageDefinitionContractTest.php \
  tests/Feature/Infrastructure/ApiRouteSurfaceIntegrityTest.php

echo "booking config/contracts gate completed"

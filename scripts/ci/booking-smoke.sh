#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

export APP_ENV="${APP_ENV:-testing}"
export CACHE_STORE="${CACHE_STORE:-array}"

bash scripts/ci/booking-test-runner.sh \
  tests/Feature/Http/IdempotencyMiddlewareFeatureTest.php \
  tests/Unit/Http/Middleware/IdempotencyMiddlewareLockingTest.php \
  tests/Feature/Staff/StaffCheckoutIdempotencyReplayServiceTest.php \
  tests/Feature/Staff/StaffTableOrderIdempotencyReplayServiceTest.php \
  tests/Feature/Staff/StaffCheckoutRefundAndCancelServiceTest.php \
  tests/Feature/Staff/StaffReservationVoucherServiceGuardTest.php \
  tests/Feature/Loyalty/LoyaltyPointsServiceGuardTest.php \
  tests/Unit/Support/PaymentSummaryTest.php \
  tests/Unit/Support/VoucherRedemptionSupportTest.php

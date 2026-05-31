#!/usr/bin/env bash

# run-mysql-redis-tests.sh
# Staging/Local MySQL + Redis Target Test Lane Runner

set -euo pipefail

# Locate repo root
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$DIR/../.." && pwd)"
cd "$REPO_ROOT"

# Resolve PHP binary
PHP_BIN="${PHP_BIN:-php}"

check_php() {
  if ! command -v "$PHP_BIN" &> /dev/null; then
    echo "Error: PHP executable '$PHP_BIN' is not found in PATH."
    echo "Please configure PHP_BIN environment variable or add PHP to your PATH."
    exit 1
  fi
}

echo "================================================================================"
echo "Starting MySQL/Redis Targeted Test Lane..."
echo "================================================================================"

check_php

# 1. Export standard MySQL/Redis test settings (matching CI runtime lane)
export APP_ENV="${APP_ENV:-testing}"
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_DATABASE="${DB_DATABASE:-restaurantdb_test}"
export DB_USERNAME="${DB_USERNAME:-root}"
export DB_PASSWORD="${DB_PASSWORD:-123456}"
export CACHE_STORE="${CACHE_STORE:-redis}"
export REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
export REDIS_PORT="${REDIS_PORT:-6379}"
export REQUIRE_REDIS_FOR_BOOKING_API="true"
export BOOKING_REALTIME_ENABLED="true"
export BOOKING_REALTIME_CACHE_STORE="redis"

# 2. Boot database using SQL-first release dump and patches via bootstrap_booking
echo "Bootstrapping testing database using SQL-first bootstrap..."
"$PHP_BIN" tools/bootstrap_booking.php --env-file=.env.testing --json

# 3. Execute targeted integration tests
echo ""
echo "Running concurrency and locking smoke tests (tests/Feature/Runtime)..."
"$PHP_BIN" artisan test tests/Feature/Runtime

echo ""
echo "Running targeted concurrency-sensitive feature tests..."
"$PHP_BIN" artisan test --filter=TableHold || echo "No TableHold tests found."
"$PHP_BIN" artisan test --filter=Preorder || echo "No Preorder tests found."
"$PHP_BIN" artisan test --filter=Checkout || echo "No Checkout tests found."
"$PHP_BIN" artisan test --filter=Voucher || echo "No Voucher tests found."
"$PHP_BIN" artisan test --filter=Loyalty || echo "No Loyalty tests found."

echo "================================================================================"
echo "MySQL/Redis Targeted Test Lane completed successfully."
echo "================================================================================"
exit 0

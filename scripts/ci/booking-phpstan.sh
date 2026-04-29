#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

source scripts/ci/booking-ci-lib.sh

mkdir -p build/booking-ci storage/logs

phpstan_memory_limit="${BOOKING_PHPSTAN_MEMORY_LIMIT:-1G}"
phpstan_timeout="${BOOKING_PHPSTAN_TIMEOUT_SECONDS:-1200}"

booking_ci_run_step \
  "php-version" \
  "PHP version for phpstan" \
  60 \
  "build/booking-ci/phpstan-php-version.log" \
  "php -v"

booking_ci_run_step \
  "phpstan-version" \
  "phpstan version" \
  60 \
  "build/booking-ci/phpstan-version.log" \
  "vendor/bin/phpstan --version"

booking_ci_run_step \
  "phpstan-analyse" \
  "phpstan analyse" \
  "$phpstan_timeout" \
  "build/booking-ci/phpstan-analyse.log" \
  "vendor/bin/phpstan analyse --memory-limit=${phpstan_memory_limit} --no-progress"

printf '%s\n' "[booking-phpstan] phpstan completed."

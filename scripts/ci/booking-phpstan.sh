#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

source scripts/ci/booking-ci-lib.sh

mkdir -p build/booking-ci storage/logs

phpstan_memory_limit="${BOOKING_PHPSTAN_MEMORY_LIMIT:-3G}"
phpstan_timeout="${BOOKING_PHPSTAN_TIMEOUT_SECONDS:-2400}"

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

phpstan_targets=(app)

if [[ -d tests/Unit ]]; then
  phpstan_targets+=(tests/Unit)
fi

if [[ -d tests/Feature ]]; then
  while IFS= read -r path; do
    phpstan_targets+=("$path")
  done < <(find tests/Feature -maxdepth 1 -type f -name '*.php' | sort)

  while IFS= read -r path; do
    phpstan_targets+=("$path")
  done < <(find tests/Feature -mindepth 1 -maxdepth 1 -type d | sort)
fi

printf '%s\n' "${phpstan_targets[@]}" > build/booking-ci/phpstan-targets.txt

for target in "${phpstan_targets[@]}"; do
  target_slug="${target//\//-}"
  target_slug="${target_slug//\\/-}"

  booking_ci_run_step \
    "phpstan-${target_slug}" \
    "phpstan analyse ${target}" \
    "$phpstan_timeout" \
    "build/booking-ci/phpstan-${target_slug}.log" \
    "php -d memory_limit=${phpstan_memory_limit} vendor/bin/phpstan analyse --memory-limit=${phpstan_memory_limit} --no-progress ${target}"
done

printf '%s\n' "[booking-phpstan] phpstan completed."

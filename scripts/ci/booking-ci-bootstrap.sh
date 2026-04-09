#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

bash scripts/ci/booking-repo-prereq-check.sh --profile=ci-bootstrap

if [[ ! -d vendor ]]; then
  if ! command -v composer >/dev/null 2>&1; then
    echo "[booking-ci-bootstrap] composer is required but was not found in PATH." >&2
    exit 1
  fi

  composer install --no-interaction --no-progress --prefer-dist
fi

if [[ "${BOOKING_CI_BOOTSTRAP_DATABASE:-true}" == "true" ]]; then
  # GitHub Actions pre-creates the CI database for the app user, so bootstrap
  # must skip CREATE DATABASE and only import the SQL-first release contract.
  php tools/mysql/bootstrap_release.php --skip-create-db
fi

php artisan db:seed --class=ReferenceDataSeeder --force
php artisan config:clear
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
# CI lanes verify scheduler freshness without running `schedule:work`, so
# prime the heartbeat after bootstrap completes.
php artisan booking:ops-heartbeat:touch scheduler --json >/dev/null

if [[ "${BOOKING_CI_BOOTSTRAP_SITE:-false}" == "true" ]]; then
  php artisan booking:bootstrap-site --json
fi

if [[ "${BOOKING_CI_BOOTSTRAP_REPORTING:-false}" == "true" ]]; then
  php artisan booking:reporting-snapshots:rebuild --days=7 --json
fi

if [[ "${BOOKING_CI_BOOTSTRAP_STAFF_WEB:-false}" == "true" ]]; then
  if ! command -v npm >/dev/null 2>&1; then
    echo "[booking-ci-bootstrap] npm is required when BOOKING_CI_BOOTSTRAP_STAFF_WEB=true." >&2
    exit 1
  fi

  if [[ ! -d staff-web/node_modules ]]; then
    (cd staff-web && npm ci --no-audit --no-fund)
  fi
fi

printf '%s\n' "[booking-ci-bootstrap] bootstrap completed."

#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[booking-ops-smoke] php artisan booking:doctor --json"
php artisan booking:doctor --json

echo "[booking-ops-smoke] php artisan route:list | grep booking/health/metrics"
php artisan route:list --path=v1 | grep -E 'health|metrics|staff/.+refund|staff/.+checkout' || true

echo "[booking-ops-smoke] phpunit booking-smoke subset"
if [ -x "vendor/bin/phpunit" ]; then
  vendor/bin/phpunit --group booking-smoke
else
  php artisan test --group booking-smoke
fi

echo "[booking-ops-smoke] php artisan booking:alert-check --dry-run --json"
php artisan booking:alert-check --dry-run --json

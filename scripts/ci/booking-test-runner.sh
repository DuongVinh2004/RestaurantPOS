#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

if [[ -x vendor/bin/phpunit ]]; then
  exec vendor/bin/phpunit "$@"
fi

if [[ -f artisan ]]; then
  exec php artisan test "$@"
fi

echo "[booking-test-runner] neither vendor/bin/phpunit nor artisan is available." >&2
exit 1

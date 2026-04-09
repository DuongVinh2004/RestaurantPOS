#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

if [[ -x scripts/ci/booking-repo-prereq-check.sh ]]; then
  bash scripts/ci/booking-repo-prereq-check.sh --profile=package-release
fi

args=(booking:release-build)
for arg in "$@"; do
  args+=("$arg")
done

php artisan "${args[@]}"

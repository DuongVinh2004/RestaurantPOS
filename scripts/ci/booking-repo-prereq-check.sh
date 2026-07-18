#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

profile="ci-bootstrap"
for arg in "$@"; do
  case "$arg" in
    --profile=*)
      profile="${arg#--profile=}"
      ;;
  esac
done

required_files=(
  artisan
  composer.json
  bootstrap/app.php
  routes/api.php
  routes/console.php
  app/Platform/Release/Services/ReleaseArtifactManifestService.php
  app/Platform/Release/Services/ReleasePackageService.php
  app/Platform/Release/Services/BookingDeploySafetyService.php
  scripts/ci/booking-dependency-security-gate.sh
  scripts/ci/dependency-security-gate.mjs
  scripts/ci/booking-full-gate.sh
  scripts/ci/booking-release-gate.sh
  scripts/release/package_release.sh
  scripts/ci/booking-deploy-preflight.sh
  scripts/ci/booking-deploy-postflight.sh
  storage/app/booking_release/release_manifest_snapshot.json
)

if [[ "$profile" == "ci-bootstrap" && "${BOOKING_CI_BOOTSTRAP_DATABASE:-true}" != "false" ]]; then
  required_files+=(tools/mysql/bootstrap_release.sh)
fi

missing=()
for path in "${required_files[@]}"; do
  if [[ ! -e "$path" ]]; then
    missing+=("$path")
  fi
done

if [[ ${#missing[@]} -gt 0 ]]; then
  echo "[booking-repo-prereq-check] missing required repository files for profile [$profile]:" >&2
  printf ' - %s\n' "${missing[@]}" >&2
  echo "Apply this patch in the full backend repository root, not only in a source-only export bundle." >&2
  exit 1
fi

if ! grep -q "inspectFrozenSnapshot" app/Platform/Release/Services/ReleaseArtifactManifestService.php; then
  echo "[booking-repo-prereq-check] patch 1 release manifest freeze support is missing." >&2
  echo "Apply patch 1 before running patch 2 CI/CD workflows." >&2
  exit 1
fi

mkdir -p \
  build/booking-ci \
  bootstrap/cache \
  storage/logs \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views

printf '%s\n' "[booking-repo-prereq-check] repository prerequisites look ready for profile [$profile]."

if ! grep -q "booking:release-build" routes/console/ops_release.php; then
  echo "[booking-repo-prereq-check] canonical release build command is missing." >&2
  exit 1
fi

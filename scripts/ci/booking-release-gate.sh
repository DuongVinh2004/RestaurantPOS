#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build/booking-ci storage/logs

php artisan config:clear
bash scripts/ci/booking-full-gate.sh

server_log="build/booking-ci/booking-php-server.log"
php artisan serve --host=127.0.0.1 --port=8000 >"$server_log" 2>&1 &
server_pid=$!
cleanup() {
  if kill -0 "$server_pid" >/dev/null 2>&1; then
    kill "$server_pid" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

for i in {1..30}; do
  if curl --silent --fail http://127.0.0.1:8000/api/v1/health >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

if ! curl --silent --fail http://127.0.0.1:8000/api/v1/health >/dev/null 2>&1; then
  echo "[booking-release-gate] backend HTTP server did not become ready in time." >&2
  exit 1
fi

release_loop_args=(booking:release-loop --json --target=staging)
uat_manifest_path="${BOOKING_UAT_MANIFEST_PATH:-storage/app/uat/scenario-pack.json}"
if [[ -n "$uat_manifest_path" ]]; then
  release_loop_args+=(--manifest-path="$uat_manifest_path")
fi
if [[ -n "${BOOKING_RELEASE_PACKAGE_ID:-}" ]]; then
  release_loop_args+=(--package-id="${BOOKING_RELEASE_PACKAGE_ID}")
fi
if [[ "${BOOKING_RELEASE_PACKAGE_OVERWRITE:-false}" == "true" ]]; then
  release_loop_args+=(--overwrite-package)
fi
if [[ "${BOOKING_RELEASE_LOOP_BOOTSTRAP_UAT:-true}" == "true" ]]; then
  release_loop_args+=(--bootstrap-uat --base-url=http://127.0.0.1:8000)
fi
if [[ -n "${BOOKING_RELEASE_PREVIEW_COMMAND:-}" ]]; then
  release_loop_args+=(--preview-command="${BOOKING_RELEASE_PREVIEW_COMMAND}")
elif [[ -n "${BOOKING_RELEASE_PREVIEW_URL:-}" ]]; then
  release_loop_args+=(--preview-url="${BOOKING_RELEASE_PREVIEW_URL}")
else
  release_loop_args+=(--skip-preview)
fi
if [[ -n "${BOOKING_RELEASE_PREVIEW_LABEL:-}" ]]; then
  release_loop_args+=(--preview-label="${BOOKING_RELEASE_PREVIEW_LABEL}")
fi

php artisan "${release_loop_args[@]}" | tee build/booking-ci/booking-release-loop.json
php artisan booking:deploy-check --mode=postflight --json | tee build/booking-ci/booking-deploy-check-postflight.json

#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

bash scripts/ci/booking-repo-prereq-check.sh --profile=ci-bootstrap

if ! command -v composer >/dev/null 2>&1; then
  echo "[booking-ci-bootstrap] composer is required but was not found in PATH." >&2
  exit 1
fi

if [[ ! -d vendor ]]; then
  composer install --no-interaction --no-progress --prefer-dist
fi

bootstrap_args=()
if [[ "${BOOKING_CI_BOOTSTRAP_DATABASE:-true}" == "true" ]]; then
  # GitHub Actions pre-creates the CI database for the app user, so bootstrap
  # must skip CREATE DATABASE while still proving the canonical wrapper.
  bootstrap_args+=(--skip-create-db)
else
  bootstrap_args+=(--skip-db-bootstrap)
fi

if [[ "${BOOKING_CI_BOOTSTRAP_SITE:-false}" != "true" ]]; then
  bootstrap_args+=(--skip-site-bootstrap)
fi

if [[ "${BOOKING_CI_BOOTSTRAP_REPORTING:-false}" != "true" ]]; then
  bootstrap_args+=(--skip-reporting)
fi

escape_github_annotation() {
  local data="$1"
  data="${data//'%'/'%25'}"
  data="${data//$'\r'/'%0D'}"
  data="${data//$'\n'/'%0A'}"
  printf '%s' "$data"
}

bootstrap_log="build/booking-ci/bootstrap.log"
mkdir -p "$(dirname "$bootstrap_log")"

if ! composer bootstrap:booking -- "${bootstrap_args[@]}" 2>&1 | tee "$bootstrap_log"; then
  bootstrap_status="${PIPESTATUS[0]}"
  bootstrap_tail="$(tail -n 40 "$bootstrap_log" 2>/dev/null || true)"
  if [[ -n "$bootstrap_tail" && -n "${GITHUB_ACTIONS:-}" ]]; then
    printf '::error title=Booking CI bootstrap failed::%s\n' "$(escape_github_annotation "$bootstrap_tail")"
  fi

  exit "$bootstrap_status"
fi

bootstrap_node_workspace() {
  local flag_name="$1"
  local workspace_dir="$2"

  if [[ "${!flag_name:-false}" != "true" ]]; then
    return
  fi

  if ! command -v npm >/dev/null 2>&1; then
    echo "[booking-ci-bootstrap] npm is required when ${flag_name}=true." >&2
    exit 1
  fi

  if [[ ! -d "${workspace_dir}/node_modules" ]]; then
    (cd "$workspace_dir" && npm ci --no-audit --no-fund)
  fi
}

bootstrap_node_workspace BOOKING_CI_BOOTSTRAP_STAFF_WEB staff-web
bootstrap_node_workspace BOOKING_CI_BOOTSTRAP_CUSTOMER_WEB customer-web

printf '%s\n' "[booking-ci-bootstrap] bootstrap completed."

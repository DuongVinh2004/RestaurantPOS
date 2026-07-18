#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

npm ci --no-fund
npm --prefix customer-web ci --no-fund
npm --prefix staff-web ci --no-fund

node scripts/ci/dependency-security-gate.mjs --all

failures=()
run_check() {
  local label="$1"
  shift

  if "$@"; then
    return
  else
    local status=$?
    failures+=("${label}:${status}")
  fi
}

run_check customer-test npm --prefix customer-web run test
run_check customer-build npm --prefix customer-web run build
run_check staff-test npm --prefix staff-web run test
run_check staff-build npm --prefix staff-web run build

if [[ ${#failures[@]} -gt 0 ]]; then
  printf '[dependency-security] failed checks: %s\n' "${failures[*]}" >&2
  exit 1
fi

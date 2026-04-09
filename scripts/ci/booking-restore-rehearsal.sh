#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
BUILD_DIR="${REPO_ROOT}/build/booking-ci"
mkdir -p "${BUILD_DIR}"

TARGET_DB="${BOOKING_RESTORE_REHEARSAL_DB:-${DB_DATABASE:-}_restore_rehearsal}"
if [[ -z "${TARGET_DB}" || "${TARGET_DB}" == "_restore_rehearsal" ]]; then
  echo "DB_DATABASE or BOOKING_RESTORE_REHEARSAL_DB is required for restore rehearsal." >&2
  exit 1
fi

export RESTORE_DB_HOST="${RESTORE_DB_HOST:-${DB_HOST:-127.0.0.1}}"
export RESTORE_DB_PORT="${RESTORE_DB_PORT:-${DB_PORT:-3306}}"
export RESTORE_DB_USERNAME="${RESTORE_DB_USERNAME:-${DB_USERNAME:-root}}"
export RESTORE_DB_PASSWORD="${RESTORE_DB_PASSWORD:-${DB_PASSWORD:-}}"
export RESTORE_DB_DATABASE="${RESTORE_DB_DATABASE:-${TARGET_DB}}"

PHP_BIN="${PHP_BINARY_PATH:-php}"
"${PHP_BIN}" "${REPO_ROOT}/tools/mysql/restore_release.php" \
  --drop-target-first \
  --json \
  > "${BUILD_DIR}/booking-restore-rehearsal.json"

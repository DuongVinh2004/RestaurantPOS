#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

PHP_BIN="${PHP_BINARY_PATH:-php}"
exec "${PHP_BIN}" "${SCRIPT_DIR}/restore_release.php" "$@"

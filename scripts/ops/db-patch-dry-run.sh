#!/usr/bin/env bash
set -euo pipefail

PATCH_FILE="${1:-}"

if [ -z "$PATCH_FILE" ]; then
    echo "Usage: $0 path/to/patch.sql"
    exit 1
fi

if [ ! -f "$PATCH_FILE" ]; then
    echo "[ERROR] Patch file '$PATCH_FILE' does not exist."
    exit 1
fi

echo "=== Database Patch Dry-Run Validator ==="

# Add common MySQL paths to PATH for Windows/Git Bash compatibility
for mysql_path in "/c/Program Files/MySQL/MySQL Server 8.0/bin" "C:/Program Files/MySQL/MySQL Server 8.0/bin"; do
    if [ -d "$mysql_path" ]; then
        export PATH="$mysql_path:$PATH"
    fi
done

# Check dependencies
dependencies=(mysql mysqldump php)
for dep in "${dependencies[@]}"; do
    if ! command -v "$dep" &> /dev/null; then
        echo "[ERROR] Required dependency '$dep' is missing. Exiting."
        exit 1
    fi
done

# Load environment configuration with safe defaults
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USERNAME:-${DB_USER:-root}}"
DB_PASS="${DB_PASSWORD:-}"
PROD_DB_NAME="${PROD_DB_NAME:-restaurantpos_prod}"
STAGING_DB_NAME="${STAGING_DB_NAME:-restaurantpos_staging}"

TEMP_DIR=$(mktemp -d -t patch-dryrun-XXXXXXXXXX)
cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

PROD_DUMP_FILE="${TEMP_DIR}/prod_schema_dump.sql"

echo "Parameters:"
echo "  Host         : ${DB_HOST}:${DB_PORT}"
echo "  Prod DB Name : ${PROD_DB_NAME}"
echo "  Staging DB   : ${STAGING_DB_NAME}"
echo "  Patch file   : ${PATCH_FILE}"

# Step 1: Dump production database schema and data
echo "[1] Dumping production database schema and data..."
export MYSQL_PWD="${DB_PASS}"

if ! mysqldump --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" --single-transaction --routines --triggers "${PROD_DB_NAME}" > "$PROD_DUMP_FILE"; then
    echo "[ERROR] Production database dump failed."
    unset MYSQL_PWD
    exit 1
fi

# Step 2: Restore to staging database
echo "[2] Recreating staging database and restoring dump..."
if ! mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" -e "DROP DATABASE IF EXISTS \`${STAGING_DB_NAME}\`; CREATE DATABASE \`${STAGING_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; then
    echo "[ERROR] Recreating staging database failed."
    unset MYSQL_PWD
    exit 1
fi

if ! mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" "${STAGING_DB_NAME}" < "$PROD_DUMP_FILE"; then
    echo "[ERROR] Restoring production dump into staging failed."
    unset MYSQL_PWD
    exit 1
fi

# Step 3: Apply patch to staging database
echo "[3] Applying patch to staging database..."
if ! mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" "${STAGING_DB_NAME}" < "$PATCH_FILE"; then
    echo "[ERROR] Applying patch to staging database failed."
    unset MYSQL_PWD
    exit 1
fi
unset MYSQL_PWD

# Step 4: Run validation
echo "[4] Running Laravel doctor validation..."
# Overriding DB configs for artisan process so it validates staging target
export DB_HOST="${DB_HOST}"
export DB_PORT="${DB_PORT}"
export DB_DATABASE="${STAGING_DB_NAME}"
export DB_USERNAME="${DB_USER}"
export DB_PASSWORD="${DB_PASS}"

if ! php artisan booking:doctor --json; then
    echo "[ERROR] Laravel booking:doctor validation failed."
    exit 1
fi

echo "✅ Patch dry-run completed successfully! Restored Staging DB matches the patch schema."
exit 0

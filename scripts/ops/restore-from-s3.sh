#!/usr/bin/env bash
set -euo pipefail

# Print usage instructions
usage() {
    echo "Usage: $0 [options] <S3_PATH_OR_LOCAL_PATH>"
    echo "Options:"
    echo "  --dry-run        Simulate download and verification without importing into MySQL"
    echo "  --metadata-only  Download and parse/verify checksum/metadata without full restore"
    echo "  -h, --help       Show this help message"
    echo ""
    echo "Required Env Variables for execution:"
    echo "  CONFIRM_RESTORE=yes"
    echo "  RESTORE_TARGET_DB=<target_db_name>"
    echo "  ALLOW_DESTRUCTIVE_RESTORE=yes"
    exit 0
}

# Parse options
DRY_RUN=false
METADATA_ONLY=false
SOURCE_PATH=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --metadata-only)
            METADATA_ONLY=true
            shift
            ;;
        -h|--help)
            usage
            ;;
        *)
            if [ -z "$SOURCE_PATH" ]; then
                SOURCE_PATH="$1"
            else
                echo "[ERROR] Multiple paths specified. Only one path/URI allowed."
                exit 1
            fi
            shift
            ;;
    esac
done

if [ -z "$SOURCE_PATH" ]; then
    echo "[ERROR] Missing backup path/URI."
    usage
fi

echo "=== RestaurantPOS Hardened S3 Restore Script ==="

# Add common MySQL paths to PATH for Windows/Git Bash compatibility
for mysql_path in "/c/Program Files/MySQL/MySQL Server 8.0/bin" "C:/Program Files/MySQL/MySQL Server 8.0/bin"; do
    if [ -d "$mysql_path" ]; then
        export PATH="$mysql_path:$PATH"
    fi
done

# Check dependencies
dependencies=(mysql gunzip)
for dep in "${dependencies[@]}"; do
    if ! command -v "$dep" &> /dev/null; then
        echo "[ERROR] Required dependency '$dep' is missing. Exiting."
        exit 1
    fi
done

# Resolve sha256sum dependency or standard fallback
SHA256_CMD=""
if command -v sha256sum &> /dev/null; then
    SHA256_CMD="sha256sum"
elif command -v shasum &> /dev/null; then
    SHA256_CMD="shasum -a 256"
elif command -v openssl &> /dev/null; then
    SHA256_CMD="openssl dgst -sha256"
else
    echo "[ERROR] No suitable sha256 checksum command found (sha256sum, shasum, or openssl). Exiting."
    exit 1
fi

# Load config from environment variables
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USERNAME:-${DB_USER:-root}}"
DB_PASS="${DB_PASSWORD:-}"
DB_TARGET="${RESTORE_TARGET_DB:-${DB_DATABASE:-}}"
APP_ENV="${APP_ENV:-local}"

# 1. Enforce Production Guardrails
IS_PRODUCTION=false
if [ "$APP_ENV" = "production" ] || [ "${RESTORE_TARGET:-}" = "production" ] || [[ "$DB_TARGET" =~ "prod" ]] || [[ "$DB_TARGET" =~ "production" ]]; then
    IS_PRODUCTION=true
fi

if [ "$IS_PRODUCTION" = true ]; then
    echo "[WARNING] Target environment detected as PRODUCTION-level database: '$DB_TARGET'"
    echo "[WARNING] Destruction of production data is strictly restricted."
fi

# 2. Enforce Multi-Step Confirmation Flags
if [ "${CONFIRM_RESTORE:-}" != "yes" ]; then
    echo "[ERROR] CONFIRM_RESTORE=yes is required to run this script."
    exit 1
fi

if [ -z "$DB_TARGET" ]; then
    echo "[ERROR] RESTORE_TARGET_DB=<dbname> must be explicitly configured."
    exit 1
fi

if [ "${ALLOW_DESTRUCTIVE_RESTORE:-}" != "yes" ]; then
    echo "[ERROR] ALLOW_DESTRUCTIVE_RESTORE=yes is required to confirm overwriting '$DB_TARGET'."
    exit 1
fi

# Multi-step safety gate for production
if [ "$IS_PRODUCTION" = true ]; then
    if [ "${I_AM_SURE_I_WANT_TO_DESTROY_PRODUCTION:-}" != "yes" ]; then
        echo "[CRITICAL] Pointing restore at a production-like target requires I_AM_SURE_I_WANT_TO_DESTROY_PRODUCTION=yes."
        exit 1
    fi
fi

TEMP_DIR=$(mktemp -d -t restore-XXXXXXXXXX)
# Cleanup handler
cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

LOCAL_FILE="${TEMP_DIR}/restore_backup.sql.gz"
LOCAL_CHECKSUM="${TEMP_DIR}/restore_backup.sha256"

# Print operational parameters (masking secrets)
echo "Source Artifact   : ${SOURCE_PATH}"
echo "Target Host/DB    : ${DB_HOST}:${DB_PORT}/${DB_TARGET} (User: ${DB_USER})"
echo "App Environment   : ${APP_ENV}"
if [ "$DRY_RUN" = true ]; then
    echo "Mode              : DRY RUN"
fi

# Download/Resolve the source backup file
if [[ "$SOURCE_PATH" =~ ^s3:// ]]; then
    if ! command -v aws &> /dev/null; then
        echo "[ERROR] 'aws' CLI is required for S3 source: ${SOURCE_PATH}"
        exit 1
    fi

    echo "Downloading backup from S3..."
    if ! aws s3 cp "$SOURCE_PATH" "$LOCAL_FILE"; then
        echo "[ERROR] Failed to download backup from S3."
        exit 1
    fi

    # Try downloading checksum file
    CHECKSUM_S3_PATH="${SOURCE_PATH%.sql.gz}.sha256"
    echo "Downloading checksum from S3..."
    if ! aws s3 cp "$CHECKSUM_S3_PATH" "$LOCAL_CHECKSUM" 2>/dev/null; then
        echo "[WARNING] Checksum file not found at ${CHECKSUM_S3_PATH}. Skipping checksum verification."
    fi
else
    # Local file path resolution
    if [ ! -f "$SOURCE_PATH" ]; then
        echo "[ERROR] Source local file does not exist: ${SOURCE_PATH}"
        exit 1
    fi
    echo "Resolving local backup file..."
    cp "$SOURCE_PATH" "$LOCAL_FILE"

    # Try copying local checksum file
    LOCAL_CHECKSUM_SOURCE="${SOURCE_PATH%.sql.gz}.sha256"
    if [ -f "$LOCAL_CHECKSUM_SOURCE" ]; then
        cp "$LOCAL_CHECKSUM_SOURCE" "$LOCAL_CHECKSUM"
    fi
fi

# Validate backup file has non-zero size
if [ ! -s "$LOCAL_FILE" ]; then
    echo "[ERROR] Downloaded backup file is empty or missing."
    exit 1
fi

# Checksum Verification
if [ -f "$LOCAL_CHECKSUM" ]; then
    echo "Verifying SHA-256 checksum..."
    EXPECTED_HASH=$(awk '{print $1}' "$LOCAL_CHECKSUM")
    
    if [ "$SHA256_CMD" = "openssl dgst -sha256" ]; then
        ACTUAL_HASH=$($SHA256_CMD "$LOCAL_FILE" | awk '{print $2}')
    else
        ACTUAL_HASH=$($SHA256_CMD "$LOCAL_FILE" | awk '{print $1}')
    fi

    if [ "$EXPECTED_HASH" != "$ACTUAL_HASH" ]; then
        echo "[ERROR] Checksum integrity mismatch!"
        echo "Expected: $EXPECTED_HASH"
        echo "Actual  : $ACTUAL_HASH"
        exit 1
    fi
    echo "Checksum validation passed: $ACTUAL_HASH"
fi

# Gzip format verification
echo "Validating gzip format..."
if ! gunzip -t "$LOCAL_FILE" 2>/dev/null; then
    echo "[ERROR] Backup is not a valid gzip file."
    exit 1
fi

if [ "$METADATA_ONLY" = true ]; then
    echo "✅ [METADATA ONLY] Backup verification passed successfully."
    exit 0
fi

if [ "$DRY_RUN" = true ]; then
    echo "[DRY RUN] Would import de-compressed backup into '${DB_TARGET}' database."
    echo "✅ [DRY RUN] Restore completed successfully."
    exit 0
fi

# Restore import
echo "Importing backup into database '${DB_TARGET}'..."
export MYSQL_PWD="${DB_PASS}"
# Ensure target database exists
mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" -e "CREATE DATABASE IF NOT EXISTS \`${DB_TARGET}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if ! gunzip -c "$LOCAL_FILE" | mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" "${DB_TARGET}"; then
    echo "[ERROR] Database import failed."
    unset MYSQL_PWD
    exit 1
fi
unset MYSQL_PWD

echo "✅ Hardened S3 Restore completed successfully."
exit 0

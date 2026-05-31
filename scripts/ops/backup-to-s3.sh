#!/usr/bin/env bash
set -euo pipefail

# Print usage instructions
usage() {
    echo "Usage: $0 [options]"
    echo "Options:"
    echo "  --dry-run      Simulate the backup without writing files or uploading to S3"
    echo "  --local-only   Perform backup and generate checksums/metadata locally without S3 upload"
    echo "  -h, --help     Show this help message"
    exit 0
}

# Parse options
DRY_RUN=false
LOCAL_ONLY=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --local-only)
            LOCAL_ONLY=true
            shift
            ;;
        -h|--help)
            usage
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

echo "=== RestaurantPOS Hardened S3 Backup Script ==="

# Add common MySQL paths to PATH for Windows/Git Bash compatibility
for mysql_path in "/c/Program Files/MySQL/MySQL Server 8.0/bin" "C:/Program Files/MySQL/MySQL Server 8.0/bin"; do
    if [ -d "$mysql_path" ]; then
        export PATH="$mysql_path:$PATH"
    fi
done

# Check dependencies
dependencies=(mysqldump gzip)
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

# S3 dependency check (if not local-only)
if [ "$LOCAL_ONLY" = false ]; then
    if ! command -v aws &> /dev/null; then
        echo "[WARNING] S3 upload is requested but 'aws' CLI is not found. Switching to --local-only mode."
        LOCAL_ONLY=true
    fi
fi

# Load config from environment variables with safe defaults
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-${DB_NAME:-}}"
DB_USER="${DB_USERNAME:-${DB_USER:-root}}"
DB_PASS="${DB_PASSWORD:-}"
S3_BUCKET="${BACKUP_S3_BUCKET:-}"
S3_PREFIX="${BACKUP_S3_PREFIX:-db-backups/}"
AWS_REGION="${AWS_REGION:-ap-southeast-1}"

if [ -z "$DB_DATABASE" ]; then
    echo "[ERROR] DB_DATABASE (or DB_NAME) is required."
    exit 1
fi

if [ "$LOCAL_ONLY" = false ] && [ -z "$S3_BUCKET" ]; then
    echo "[ERROR] BACKUP_S3_BUCKET is required when --local-only is not set."
    exit 1
fi

TIMESTAMP=$(date -u +"%Y%m%dT%H%M%SZ")
BACKUP_NAME="${DB_DATABASE}_${TIMESTAMP}"
TEMP_DIR=$(mktemp -d -t backup-XXXXXXXXXX)

# Cleanup handler
cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

BACKUP_FILE="${TEMP_DIR}/${BACKUP_NAME}.sql.gz"
CHECKSUM_FILE="${TEMP_DIR}/${BACKUP_NAME}.sha256"
METADATA_FILE="${TEMP_DIR}/${BACKUP_NAME}.metadata.json"

# Print operational parameters (masking secrets)
echo "Database Target : ${DB_HOST}:${DB_PORT}/${DB_DATABASE} (User: ${DB_USER})"
if [ "$LOCAL_ONLY" = true ]; then
    echo "Backup Mode     : Local Only"
else
    echo "Backup Mode     : S3 Upload to ${S3_BUCKET}/${S3_PREFIX} (Region: ${AWS_REGION})"
fi
if [ "$DRY_RUN" = true ]; then
    echo "Mode            : DRY RUN"
fi

if [ "$DRY_RUN" = true ]; then
    echo "[DRY RUN] Would dump database to gzip, compute checksum, generate metadata, and upload."
    echo "✅ [DRY RUN] Backup completed successfully."
    exit 0
fi

echo "Dumping database..."
# Dump using mysqldump and pipe into gzip safely. Do not echo password.
export MYSQL_PWD="${DB_PASS}"
if ! mysqldump --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" --single-transaction --routines --triggers "${DB_DATABASE}" | gzip > "$BACKUP_FILE"; then
    echo "[ERROR] mysqldump or compression failed."
    exit 1
fi
unset MYSQL_PWD

# Validate backup file exists and has non-zero size
if [ ! -s "$BACKUP_FILE" ]; then
    echo "[ERROR] Backup compression produced an empty or missing file."
    exit 1
fi

FILE_SIZE=$(wc -c < "$BACKUP_FILE" | tr -d ' ')
echo "Backup file created: $(basename "$BACKUP_FILE") ($FILE_SIZE bytes)"

# Calculate checksum
echo "Calculating sha256 checksum..."
if [ "$SHA256_CMD" = "openssl dgst -sha256" ]; then
    SHA256_HASH=$($SHA256_CMD "$BACKUP_FILE" | awk '{print $2}')
else
    SHA256_HASH=$($SHA256_CMD "$BACKUP_FILE" | awk '{print $1}')
fi
echo "$SHA256_HASH  $(basename "$BACKUP_FILE")" > "$CHECKSUM_FILE"

# Create metadata JSON (no secrets!)
cat <<EOF > "$METADATA_FILE"
{
  "backup_name": "${BACKUP_NAME}",
  "database": "${DB_DATABASE}",
  "timestamp_utc": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "file_name": "${BACKUP_NAME}.sql.gz",
  "file_size_bytes": ${FILE_SIZE},
  "sha256_checksum": "${SHA256_HASH}",
  "environment": "${APP_ENV:-local}",
  "local_only": ${LOCAL_ONLY}
}
EOF

# Copy backup to a reliable local location under storage/app/booking_backups if it exists
LOCAL_DEST_DIR="storage/app/booking_backups/${TIMESTAMP}-${DB_DATABASE}"
mkdir -p "$LOCAL_DEST_DIR"
cp "$BACKUP_FILE" "$LOCAL_DEST_DIR/"
cp "$CHECKSUM_FILE" "$LOCAL_DEST_DIR/"
cp "$METADATA_FILE" "$LOCAL_DEST_DIR/"
echo "Backup files copied locally to ${LOCAL_DEST_DIR}"

# Upload to S3
if [ "$LOCAL_ONLY" = false ]; then
    S3_URI="s3://${S3_BUCKET}/${S3_PREFIX}${BACKUP_NAME}.sql.gz"
    S3_CHECKSUM_URI="s3://${S3_BUCKET}/${S3_PREFIX}${BACKUP_NAME}.sha256"
    S3_METADATA_URI="s3://${S3_BUCKET}/${S3_PREFIX}${BACKUP_NAME}.metadata.json"

    echo "Uploading artifacts to S3..."
    if ! aws s3 cp "$BACKUP_FILE" "$S3_URI" --region "$AWS_REGION"; then
        echo "[ERROR] S3 upload failed for backup file."
        exit 1
    fi

    if ! aws s3 cp "$CHECKSUM_FILE" "$S3_CHECKSUM_URI" --region "$AWS_REGION"; then
        echo "[ERROR] S3 upload failed for checksum file."
        exit 1
    fi

    if ! aws s3 cp "$METADATA_FILE" "$S3_METADATA_URI" --region "$AWS_REGION"; then
        echo "[ERROR] S3 upload failed for metadata file."
        exit 1
    fi

    # Verify object exists in S3
    echo "Verifying S3 upload integrity..."
    if ! aws s3api head-object --bucket "$S3_BUCKET" --key "${S3_PREFIX}${BACKUP_NAME}.sql.gz" --region "$AWS_REGION" &>/dev/null; then
        echo "[ERROR] Verification failed. Backup file was not found in S3."
        exit 1
    fi
    echo "✅ Verification successful. Object exists in S3."
fi

echo "✅ Hardened S3 Backup completed successfully."
exit 0

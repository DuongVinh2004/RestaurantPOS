#!/usr/bin/env bash
set -e
set -o pipefail

# Check dependencies
if ! command -v aws &> /dev/null; then
    echo "Error: aws cli could not be found."
    exit 1
fi

# Configuration
DB_USER="root"
DB_NAME="restaurantpos_prod"
TIMESTAMP=$(date +"%F_%H-%M-%S")
BACKUP_FILE="/tmp/${DB_NAME}_${TIMESTAMP}.sql.gz"
S3_BUCKET="${BACKUP_S3_BUCKET:-s3://your-backup-bucket/db-backups/}"

echo "Starting backup of ${DB_NAME} at ${TIMESTAMP}..."

# Create dump and compress
mysqldump -u "${DB_USER}" "${DB_NAME}" | gzip > "${BACKUP_FILE}"

# Check dump file exists and size > 0
if [ ! -s "${BACKUP_FILE}" ]; then
    echo "Error: Backup file is empty or missing."
    exit 1
fi

# Upload to S3
aws s3 cp "${BACKUP_FILE}" "${S3_BUCKET}"

# Clean up local file
rm "${BACKUP_FILE}"

echo "✅ Backup uploaded successfully to ${S3_BUCKET}"

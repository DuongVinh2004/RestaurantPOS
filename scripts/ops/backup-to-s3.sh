#!/usr/bin/env bash
set -e

# Configuration
DB_USER="root"
DB_NAME="restaurantpos_prod"
TIMESTAMP=$(date +"%F_%H-%M-%S")
BACKUP_FILE="/tmp/${DB_NAME}_${TIMESTAMP}.sql.gz"
S3_BUCKET="s3://your-backup-bucket/db-backups/"

echo "Starting backup of ${DB_NAME} at ${TIMESTAMP}..."

# Create dump and compress
mysqldump -u "${DB_USER}" -p "${DB_NAME}" | gzip > "${BACKUP_FILE}"

# Upload to S3
aws s3 cp "${BACKUP_FILE}" "${S3_BUCKET}"

# Clean up local file
rm "${BACKUP_FILE}"

echo "✅ Backup uploaded successfully to ${S3_BUCKET}"

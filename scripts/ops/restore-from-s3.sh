#!/usr/bin/env bash
set -e

# Configuration
DB_USER="root"
DB_NAME="restaurantpos_staging"

if [ -z "$1" ]; then
    echo "Usage: $0 s3://your-backup-bucket/db-backups/filename.sql.gz"
    exit 1
fi

S3_PATH=$1
LOCAL_FILE="/tmp/restore_db.sql.gz"

echo "Downloading backup from ${S3_PATH}..."
aws s3 cp "${S3_PATH}" "${LOCAL_FILE}"

echo "Restoring into database: ${DB_NAME}..."
gunzip < "${LOCAL_FILE}" | mysql -u "${DB_USER}" -p "${DB_NAME}"

# Clean up local file
rm "${LOCAL_FILE}"

echo "✅ Restore completed successfully."

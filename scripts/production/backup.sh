#!/bin/bash
# Backup MySQL Database inside Docker Container

set -e

BACKUP_DIR="./storage/backups"
DB_CONTAINER="restaurantpos-mysql-1" # Tên container MySQL (phụ thuộc vào thư mục gốc docker-compose)
DB_USER="pos_user" # Hoặc load từ .env
DB_NAME="restaurantdb"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="${BACKUP_DIR}/db_backup_${DATE}.sql.gz"

mkdir -p "$BACKUP_DIR"

echo "Starting database backup..."

# Yêu cầu container mysql dump dữ liệu và nén gzip
docker exec "$DB_CONTAINER" /usr/bin/mysqldump -u "$DB_USER" -p"${DB_PASSWORD:-}" "$DB_NAME" | gzip > "$BACKUP_FILE"

echo "Backup completed locally: $BACKUP_FILE"

# Bắt buộc upload lên Storage/AWS S3 để phòng chống Disaster
if [ -z "$S3_BACKUP_BUCKET" ]; then
    echo "CRITICAL ERROR: S3_BACKUP_BUCKET is not set. Off-site backup is mandatory."
    exit 1
fi

echo "Uploading to S3 bucket: $S3_BACKUP_BUCKET..."
aws s3 cp "$BACKUP_FILE" "s3://$S3_BACKUP_BUCKET/backups/$(basename $BACKUP_FILE)"

if [ $? -eq 0 ]; then
    echo "Successfully uploaded to S3."
    # Xoá file trên disk để tiết kiệm dung lượng, vì S3 đã lưu trữ an toàn
    rm "$BACKUP_FILE"
else
    echo "CRITICAL ERROR: Failed to upload backup to S3."
    exit 1
fi

# Giữ lại các bản backup local trong vòng 7 ngày
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +7 -delete
echo "Old local backups cleaned up."

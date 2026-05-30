#!/usr/bin/env bash
set -e

# Configuration
PROD_DB_NAME="restaurantpos_prod"
PROD_DB_USER="root"
STAGING_DB_NAME="restaurantpos_staging"
PATCH_FILE=$1

if [ -z "$PATCH_FILE" ]; then
    echo "Usage: $0 path/to/patch.sql"
    exit 1
fi

echo "[1] Dumping production database schema and data..."
mysqldump -u "$PROD_DB_USER" -p "$PROD_DB_NAME" > /tmp/prod_dump.sql

echo "[2] Restoring to staging database..."
mysql -u "$PROD_DB_USER" -p -e "DROP DATABASE IF EXISTS $STAGING_DB_NAME; CREATE DATABASE $STAGING_DB_NAME;"
mysql -u "$PROD_DB_USER" -p "$STAGING_DB_NAME" < /tmp/prod_dump.sql

echo "[3] Applying patch to staging database..."
mysql -u "$PROD_DB_USER" -p "$STAGING_DB_NAME" < "$PATCH_FILE"

echo "[4] Running validation..."
php artisan booking:doctor --json

echo "✅ Dry-run completed successfully! Staging DB is now up to date with the patch."

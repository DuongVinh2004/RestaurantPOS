#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SCHEMA_SQL="${ROOT_DIR}/database/schema/mysql-schema.sql"
PATCH_DIR="${ROOT_DIR}/database/patches"
VERIFY_SQL="${ROOT_DIR}/tools/mysql/verify_release_contract.sql"

DB_HOST="${DB_HOST:-${MYSQL_HOST:-127.0.0.1}}"
DB_PORT="${DB_PORT:-${MYSQL_PORT:-3306}}"
DB_USERNAME="${DB_USERNAME:-${MYSQL_USER:-root}}"
DB_PASSWORD="${DB_PASSWORD:-${MYSQL_PASSWORD:-}}"
DB_DATABASE="${DB_DATABASE:-${MYSQL_DATABASE:-}}"

if [[ -z "${DB_DATABASE}" ]]; then
  echo "DB_DATABASE (or MYSQL_DATABASE) is required." >&2
  exit 1
fi

mysql_cmd=(mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --default-character-set=utf8mb4)
if [[ -n "$DB_PASSWORD" ]]; then
  mysql_cmd+=(--password="$DB_PASSWORD")
fi

"${mysql_cmd[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
"${mysql_cmd[@]}" "$DB_DATABASE" < "$SCHEMA_SQL"

while IFS= read -r patch_file; do
  "${mysql_cmd[@]}" "$DB_DATABASE" < "$patch_file"
done < <(find "$PATCH_DIR" -maxdepth 1 -type f -name '*.sql' | sort)

"${mysql_cmd[@]}" "$DB_DATABASE" < "$VERIFY_SQL"

echo "Release database bootstrap completed for ${DB_DATABASE} with contract verification via ${VERIFY_SQL}."

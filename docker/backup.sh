#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."
set -a
source .env
set +a

BACKUP_DIR="${BACKUP_DIR:-./backups}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"

# Export the database from the running container.
docker compose exec -T db mysqldump \
  --single-transaction --routines --triggers \
  -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" \
  | gzip > "${BACKUP_DIR}/db-${STAMP}.sql.gz"

# Archive user-uploaded files from the Docker volumes.
docker run --rm \
  -v "royalfamilytz_uploads_data:/source:ro" \
  -v "$(realpath "$BACKUP_DIR"):/backup" \
  alpine:3.20 tar czf "/backup/uploads-${STAMP}.tar.gz" -C /source .

docker run --rm \
  -v "royalfamilytz_storage_data:/source:ro" \
  -v "$(realpath "$BACKUP_DIR"):/backup" \
  alpine:3.20 tar czf "/backup/storage-${STAMP}.tar.gz" -C /source .

# Keep the latest 14 backup sets.
find "$BACKUP_DIR" -type f -mtime +14 -delete
printf 'Backup complete: %s\n' "$STAMP"

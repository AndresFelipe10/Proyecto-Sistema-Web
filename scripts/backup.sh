#!/bin/bash
set -euo pipefail

# Directorio base del proyecto y carpeta de respaldos
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${PROJECT_ROOT}/backups"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
TEMP_DIR="/tmp/backup_$DATE"
ARCHIVE="$BACKUP_DIR/backup_$DATE.tar.gz"

mkdir -p "$BACKUP_DIR"
mkdir -p "$TEMP_DIR"

ENV_FILE="${PROJECT_ROOT}/.env"
if [ -f "$ENV_FILE" ]; then
    export $(grep -v '^#' "$ENV_FILE" | grep -E "DB_DATABASE|DB_USERNAME|DB_PASSWORD|DB_ROOT_PASSWORD" | xargs)
fi

DB_NAME="${DB_DATABASE:-sistema_web}"
DB_PASS="${DB_ROOT_PASSWORD:-${DB_PASSWORD:-sistema_password}}"
DB_USER="${DB_USERNAME:-root}"

# Detectar contenedores activos (producción o desarrollo)
if docker ps --format '{{.Names}}' | grep -q "^sistema_db_prod$"; then
    DB_CONTAINER="sistema_db_prod"
    APP_CONTAINER="sistema_app_prod"
elif docker ps --format '{{.Names}}' | grep -q "^sistema_db$"; then
    DB_CONTAINER="sistema_db"
    APP_CONTAINER="sistema_app"
else
    DB_CONTAINER="sistema_db_prod"
    APP_CONTAINER="sistema_app_prod"
fi

echo "Iniciando respaldo de base de datos '${DB_NAME}' desde '${DB_CONTAINER}'..."
docker exec "${DB_CONTAINER}" mysqldump --no-tablespaces -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" > "$TEMP_DIR/database.sql"

echo "Iniciando respaldo de adjuntos privados (app_storage) desde '${APP_CONTAINER}'..."
docker exec "${APP_CONTAINER}" sh -c "mkdir -p /var/www/html/storage/app/private && tar -czf - -C /var/www/html/storage/app private" > "$TEMP_DIR/storage_private.tar.gz"

echo "Empaquetando archivo consolidado de respaldo..."
tar -czf "$ARCHIVE" -C "$TEMP_DIR" database.sql storage_private.tar.gz

rm -rf "$TEMP_DIR"

echo "Respaldo consolidado completado: $ARCHIVE"

# Política de retención: eliminar respaldos con más de 7 días
find "$BACKUP_DIR" -type f \( -name "backup_*.tar.gz" -o -name "backup_*.sql.gz" \) -mtime +7 -delete


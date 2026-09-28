#!/bin/bash
BACKUP_DIR="/home/ubuntu/Proyecto/backups"
DATE=$(date +"%Y-%m-%d_%H-%M-%S")
FILENAME="$BACKUP_DIR/backup_$DATE.sql.gz"

ENV_FILE="/home/ubuntu/Proyecto/.env"
if [ -f "$ENV_FILE" ]; then
    export $(grep -v '^#' "$ENV_FILE" | grep -E "DB_DATABASE|DB_USERNAME|DB_PASSWORD" | xargs)
fi

docker compose -f /home/ubuntu/Proyecto/docker-compose.prod.yml exec -T db mysqldump --no-tablespaces -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" | gzip > "$FILENAME"

find "$BACKUP_DIR" -type f -name "backup_*.sql.gz" -mtime +7 -delete

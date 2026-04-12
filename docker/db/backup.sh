#!/bin/bash
set -e

# Database backup script for phpKanMaster
BACKUP_DIR="/backups"
RETENTION_DAYS=7
DATABASE="kanban"
DB_USER="kanban"
DB_HOST="db"

# Create timestamp for backup file
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${DATABASE}_${TIMESTAMP}.sql.gz"

echo "[$(date)] Starting database backup..."

# Create backup using pg_dump
pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DATABASE" | gzip > "$BACKUP_FILE"

# Verify backup was created and has content
if [ -f "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
    echo "[$(date)] Backup created successfully: $BACKUP_FILE"
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "[$(date)] Backup size: $SIZE"
else
    echo "[$(date)] ERROR: Backup failed or is empty!"
    exit 1
fi

# Remove old backups beyond retention period
echo "[$(date)] Cleaning up backups older than $RETENTION_DAYS days..."
find "$BACKUP_DIR" -name "${DATABASE}_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete
echo "[$(date)] Cleanup complete"

# List current backups
echo "[$(date)] Current backups:"
ls -lh "$BACKUP_DIR"/${DATABASE}_*.sql.gz 2>/dev/null || echo "No backups found"

echo "[$(date)] Backup operation finished"

#!/bin/bash

# Backup Rotation Script
# Automatically removes backup files older than 90 days
# Run this daily via cron job
#
# Installation:
# 1. Copy to Ubuntu server: /usr/local/bin/cleanup_old_backups.sh
# 2. Make executable: sudo chmod +x /usr/local/bin/cleanup_old_backups.sh
# 3. Add to cron: sudo crontab -e
#    Add line: 0 2 * * * /usr/local/bin/cleanup_old_backups.sh >> /var/log/backup_cleanup.log 2>&1

# Configuration
BACKUP_DIR="/home/ftpuser"
RETENTION_DAYS=90
LOG_FILE="/var/log/backup_cleanup.log"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Start cleanup
log_message "=========================================="
log_message "Starting backup rotation cleanup"
log_message "Retention period: $RETENTION_DAYS days"
log_message "=========================================="

# Check if backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    log_message "ERROR: Backup directory $BACKUP_DIR does not exist"
    exit 1
fi

# Counters
total_files=0
deleted_files=0
deleted_size=0

# Loop through each router directory
for router_dir in "$BACKUP_DIR"/*; do
    if [ -d "$router_dir" ]; then
        router_name=$(basename "$router_dir")
        
        # Skip special directories
        if [ "$router_name" = "active_connections" ]; then
            continue
        fi
        
        log_message "Processing router: $router_name"
        
        # Find and delete backup files older than retention period
        while IFS= read -r -d '' file; do
            total_files=$((total_files + 1))
            filename=$(basename "$file")
            filesize=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
            filedate=$(stat -f%Sm -t '%Y-%m-%d %H:%M:%S' "$file" 2>/dev/null || stat -c%y "$file" 2>/dev/null | cut -d'.' -f1)
            
            # Delete the file
            if rm "$file"; then
                deleted_files=$((deleted_files + 1))
                deleted_size=$((deleted_size + filesize))
                log_message "  ✓ Deleted: $filename ($(numfmt --to=iec-i --suffix=B $filesize)) - Last modified: $filedate"
            else
                log_message "  ✗ Failed to delete: $filename"
            fi
        done < <(find "$router_dir" -type f \( -name "*.backup" -o -name "*.rsc" \) -mtime +$RETENTION_DAYS -print0)
    fi
done

# Convert deleted size to human readable
if command -v numfmt &> /dev/null; then
    deleted_size_human=$(numfmt --to=iec-i --suffix=B $deleted_size)
else
    deleted_size_human="$deleted_size bytes"
fi

# Summary
log_message "=========================================="
log_message "Cleanup Summary:"
log_message "  Total old files found: $total_files"
log_message "  Files deleted: $deleted_files"
log_message "  Space freed: $deleted_size_human"
log_message "=========================================="

# Optional: Send notification if many files were deleted
if [ $deleted_files -gt 100 ]; then
    log_message "WARNING: More than 100 files were deleted. Please verify backup schedule."
fi

log_message "Backup rotation completed successfully"
echo ""

exit 0

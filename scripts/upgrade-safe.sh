#!/bin/bash
# Production-safe upgrade script with validation and automatic rollback
# This script wraps the standard upgrade script with safety checks

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATE=$(date +%Y-%m-%d-%H-%M-%S)
LOGFILE="/opt/wharftales/logs/upgrade-safe-${DATE}.log"
BACKUP_DIR="/opt/wharftales/backups"

# Ensure log directory exists
mkdir -p /opt/wharftales/logs

# Function to log both to console and file
log() {
    echo "$1" | tee -a "$LOGFILE"
}

# Function to perform rollback
perform_rollback() {
    log ""
    log "========================================="
    log "PERFORMING AUTOMATIC ROLLBACK"
    log "========================================="
    
    LATEST_BACKUP=$(ls -t "$BACKUP_DIR"/wharftales-backup-*.tar.gz 2>/dev/null | head -1)
    
    if [ -z "$LATEST_BACKUP" ]; then
        log "✗ ERROR: No backup found for rollback"
        return 1
    fi
    
    log "Restoring from: $LATEST_BACKUP"
    
    cd /opt/wharftales
    
    # Stop containers
    log "Stopping containers..."
    docker-compose down 2>&1 | tee -a "$LOGFILE"
    
    # Extract backup
    log "Extracting backup..."
    tar -xzf "$LATEST_BACKUP" 2>&1 | tee -a "$LOGFILE"
    
    # Start containers
    log "Starting containers..."
    docker-compose up -d --force-recreate 2>&1 | tee -a "$LOGFILE"
    
    # Wait for containers
    sleep 10
    
    # Verify rollback
    if docker ps | grep -q wharftales_gui; then
        log "✓ Rollback successful"
        
        # Clear update flag
        docker exec wharftales_gui php -r "
        \$db = new PDO('sqlite:/app/data/database.sqlite');
        \$stmt = \$db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
        \$stmt->execute(['update_in_progress', '0']);
        " 2>&1 | tee -a "$LOGFILE"
        
        return 0
    else
        log "✗ Rollback failed - manual intervention required"
        return 1
    fi
}

# Main upgrade process
log "========================================="
log "Production-Safe WharfTales Upgrade"
log "Started: $(date)"
log "Log file: $LOGFILE"
log "========================================="
log ""

# Stage 1: Pre-update validation
log "========================================="{
log "STAGE 1: Pre-Update Validation"
log "========================================="
if bash "$SCRIPT_DIR/pre-update-checks.sh" 2>&1 | tee -a "$LOGFILE"; then
    log "✓ Pre-update validation passed"
else
    log "✗ Pre-update validation failed"
    log "Aborting update without making changes"
    exit 1
fi
log ""

# Stage 2: Backup current version
log "========================================="
log "STAGE 2: Recording Current State"
log "========================================="
CURRENT_VERSION=$(cat /opt/wharftales/VERSION 2>/dev/null || echo "unknown")
log "Current version: $CURRENT_VERSION"
log "Backup directory: $BACKUP_DIR"
log ""

# Stage 3: Perform update
log "========================================="
log "STAGE 3: Executing Update"
log "========================================="
log "Running upgrade script..."
log ""

if bash "$SCRIPT_DIR/upgrade.sh" 2>&1 | tee -a "$LOGFILE"; then
    log ""
    log "✓ Upgrade script completed"
    UPDATE_SUCCESS=true
else
    log ""
    log "✗ Upgrade script failed"
    UPDATE_SUCCESS=false
fi
log ""

# Stage 4: Post-update validation
log "========================================="
log "STAGE 4: Post-Update Validation"
log "========================================="

if [ "$UPDATE_SUCCESS" = true ]; then
    if bash "$SCRIPT_DIR/post-update-checks.sh" 2>&1 | tee -a "$LOGFILE"; then
        log ""
        log "✓ Post-update validation passed"
        VALIDATION_SUCCESS=true
    else
        log ""
        log "✗ Post-update validation failed"
        VALIDATION_SUCCESS=false
    fi
else
    VALIDATION_SUCCESS=false
fi
log ""

# Stage 5: Rollback if needed
if [ "$VALIDATION_SUCCESS" = false ]; then
    log "========================================="
    log "STAGE 5: Automatic Rollback"
    log "========================================="
    log "Update validation failed, initiating rollback..."
    log ""
    
    if perform_rollback; then
        NEW_VERSION=$(cat /opt/wharftales/VERSION 2>/dev/null || echo "unknown")
        log ""
        log "========================================="
        log "UPDATE ROLLED BACK"
        log "Version restored: $NEW_VERSION"
        log "Completed: $(date)"
        log "========================================="
        exit 1
    else
        log ""
        log "========================================="
        log "CRITICAL: ROLLBACK FAILED"
        log "Manual intervention required immediately"
        log "Contact system administrator"
        log "Log file: $LOGFILE"
        log "========================================="
        exit 2
    fi
fi

# Success!
NEW_VERSION=$(cat /opt/wharftales/VERSION 2>/dev/null || echo "unknown")
log "========================================="
log "UPDATE COMPLETED SUCCESSFULLY"
log "Previous version: $CURRENT_VERSION"
log "New version: $NEW_VERSION"
log "Completed: $(date)"
log "Log file: $LOGFILE"
log "========================================="

exit 0

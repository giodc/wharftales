#!/bin/bash
#
# Watch database for update triggers
# Run as systemd service or cron job every minute
#

DB_FILE="/opt/wharftales/data/database.sqlite"
UPGRADE_SCRIPT="/opt/wharftales/scripts/upgrade.sh"

echo "[$(date)] Checking database for update trigger..."

# Check if database exists
if [ ! -f "$DB_FILE" ]; then
    echo "[$(date)] ERROR: Database not found at $DB_FILE"
    exit 1
fi

# Check if update is requested in database
UPDATE_REQUESTED=$(sqlite3 "$DB_FILE" "SELECT value FROM settings WHERE key='trigger_update_now' LIMIT 1;" 2>/dev/null)

echo "[$(date)] Update flag value: '$UPDATE_REQUESTED'"

if [ "$UPDATE_REQUESTED" = "1" ]; then
    echo "[$(date)] Update requested via database, starting upgrade..."
    
    # Clear the flag first
    sqlite3 "$DB_FILE" "UPDATE settings SET value='0' WHERE key='trigger_update_now';"
    
    # Run upgrade directly without nohup (since we're already running as systemd service)
    # The upgrade script will handle its own logging
    echo "[$(date)] Executing upgrade script..."
    $UPGRADE_SCRIPT
    EXIT_CODE=$?
    
    echo "[$(date)] Upgrade script completed with exit code: $EXIT_CODE"
    
    # Update database to clear update_in_progress flag
    if [ $EXIT_CODE -eq 0 ]; then
        sqlite3 "$DB_FILE" "UPDATE settings SET value='0' WHERE key='update_in_progress';" 2>/dev/null || true
        echo "[$(date)] Upgrade successful!"
    else
        sqlite3 "$DB_FILE" "UPDATE settings SET value='0' WHERE key='update_in_progress';" 2>/dev/null || true
        echo "[$(date)] Upgrade failed with exit code $EXIT_CODE"
    fi
fi

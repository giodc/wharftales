#!/bin/bash
#
# This script runs on the HOST and watches for upgrade trigger files
# It should be run as a systemd service or cron job
#

TRIGGER_FILE="/opt/wharftales/.upgrade-trigger"
UPGRADE_SCRIPT="/opt/wharftales/scripts/upgrade.sh"
LOCK_FILE="/opt/wharftales/.upgrade-watcher.lock"

# Check if already running
if [ -f "$LOCK_FILE" ]; then
    PID=$(cat "$LOCK_FILE")
    if ps -p "$PID" > /dev/null 2>&1; then
        echo "Upgrade watcher already running (PID: $PID)"
        exit 0
    fi
fi

# Create lock file
echo $$ > "$LOCK_FILE"

# Cleanup function
cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT

# Check for trigger file
if [ -f "$TRIGGER_FILE" ]; then
    echo "==================================="
    echo "Upgrade triggered at: $(cat $TRIGGER_FILE)"
    echo "==================================="
    
    # Remove trigger file
    rm -f "$TRIGGER_FILE"
    
    # Run upgrade script
    if [ -x "$UPGRADE_SCRIPT" ]; then
        bash "$UPGRADE_SCRIPT"
    else
        echo "ERROR: Upgrade script not found or not executable: $UPGRADE_SCRIPT"
        exit 1
    fi
fi

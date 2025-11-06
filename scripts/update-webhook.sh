#!/bin/bash
#
# Simple webhook to trigger updates
# Run with: nohup /opt/wharftales/scripts/update-webhook.sh > /opt/wharftales/logs/webhook.log 2>&1 &
#

PORT=9099
TRIGGER_SCRIPT="/opt/wharftales/scripts/upgrade.sh"

echo "Starting WharfTales Update Webhook on port $PORT"

while true; do
    # Listen for HTTP requests
    RESPONSE=$(echo -e "HTTP/1.1 200 OK\nContent-Type: application/json\n\n{\"success\":true,\"message\":\"Update triggered\"}" | nc -l -p $PORT -q 1)
    
    # Check if request is POST to /trigger-update
    if echo "$RESPONSE" | grep -q "POST /trigger-update"; then
        echo "[$(date)] Update triggered via webhook"
        
        # Run upgrade script in background
        nohup $TRIGGER_SCRIPT > /opt/wharftales/logs/upgrade-$(date +%Y-%m-%d-%H-%M-%S).log 2>&1 &
        
        echo "[$(date)] Upgrade script launched (PID: $!)"
    fi
done

#!/bin/bash
#
# Manually trigger an upgrade immediately (for testing)
#

echo "Manually triggering upgrade..."
/opt/wharftales/scripts/trigger-upgrade-from-container.sh

echo ""
echo "Waiting for host watcher to pick up trigger..."
sleep 2

echo "Running upgrade watcher now..."
sudo systemctl start wharftales-upgrade-watcher.service

echo ""
echo "Check logs:"
echo "  tail -f /opt/wharftales/logs/upgrade-*.log"
echo "  tail -f /opt/wharftales/logs/upgrade-watcher.log"

#!/bin/bash
#
# This script triggers an upgrade using Docker commands
# It can be run from inside the container since it uses the mounted docker socket
#

set -e

LOG_FILE="/opt/wharftales/logs/upgrade-$(date +%Y-%m-%d-%H-%M-%S).log"

echo "========================================" | tee -a "$LOG_FILE"
echo "WharfTales Upgrade Started: $(date)" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"

# Pull latest code using a temporary container with host filesystem access
echo "Pulling latest code from git..." | tee -a "$LOG_FILE"
docker run --rm \
    -v /opt/wharftales:/repo \
    -w /repo \
    alpine/git:latest \
    sh -c "git config --global --add safe.directory /repo && git stash && git pull origin main" 2>&1 | tee -a "$LOG_FILE"

# Pull latest docker images
echo "Pulling latest Docker images..." | tee -a "$LOG_FILE"
docker-compose -f /opt/wharftales/docker-compose.yml pull 2>&1 | tee -a "$LOG_FILE"

# Recreate containers
echo "Recreating containers..." | tee -a "$LOG_FILE"
docker-compose -f /opt/wharftales/docker-compose.yml up -d --force-recreate 2>&1 | tee -a "$LOG_FILE"

# Clear update flag
echo "Clearing update flag..." | tee -a "$LOG_FILE"
sleep 5  # Wait for container to be ready
docker exec wharftales_gui php -r "\$db = new PDO('sqlite:/app/data/database.sqlite'); \$stmt = \$db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)'); \$stmt->execute(['update_in_progress', '0']); echo 'Update flag cleared' . PHP_EOL;" 2>&1 | tee -a "$LOG_FILE"

echo "========================================" | tee -a "$LOG_FILE"
echo "Upgrade completed successfully!" | tee -a "$LOG_FILE"
echo "Time: $(date)" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"

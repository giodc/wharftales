#!/bin/bash
# Post-update validation checks
# This script validates that the update was successful and the system is healthy

echo "========================================="
echo "WharfTales Post-Update Validation"
echo "========================================="

EXIT_CODE=0

# Wait for containers to stabilize
echo "Waiting for containers to stabilize (10 seconds)..."
sleep 10

# Check 1: GUI container running
echo -n "Checking GUI container status... "
if ! docker ps | grep -q wharftales_gui; then
    echo "✗ FAILED"
    echo "  ERROR: GUI container not running"
    EXIT_CODE=1
else
    CONTAINER_STATUS=$(docker inspect wharftales_gui --format='{{.State.Status}}')
    if [ "$CONTAINER_STATUS" != "running" ]; then
        echo "✗ FAILED"
        echo "  ERROR: Container status is $CONTAINER_STATUS"
        EXIT_CODE=1
    else
        echo "✓ OK"
    fi
fi

# Check 2: Web interface responding
echo -n "Checking web interface (port 9000)... "
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 http://localhost:9000 2>&1)
if [ "$HTTP_CODE" != "200" ]; then
    echo "✗ FAILED"
    echo "  ERROR: Web interface returned HTTP $HTTP_CODE"
    EXIT_CODE=1
else
    echo "✓ OK (HTTP $HTTP_CODE)"
fi

# Check 3: Database integrity
echo -n "Checking database integrity... "
DB_CHECK=$(docker exec wharftales_gui php -r "
try {
    \$db = new PDO('sqlite:/app/data/database.sqlite');
    \$result = \$db->query('SELECT COUNT(*) FROM settings');
    \$count = \$result->fetchColumn();
    if (\$count > 0) {
        echo 'OK:' . \$count;
    } else {
        echo 'FAILED:No settings found';
    }
} catch (Exception \$e) {
    echo 'FAILED:' . \$e->getMessage();
}
" 2>&1)

if [[ "$DB_CHECK" == OK:* ]]; then
    SETTING_COUNT=$(echo "$DB_CHECK" | cut -d: -f2)
    echo "✓ OK ($SETTING_COUNT settings)"
else
    echo "✗ FAILED"
    echo "  ERROR: $DB_CHECK"
    EXIT_CODE=1
fi

# Check 4: Version updated
echo -n "Checking version... "
NEW_VERSION=$(cat /opt/wharftales/VERSION 2>/dev/null || echo "unknown")
echo "Current version: $NEW_VERSION"

# Check 5: Docker containers health
echo -n "Checking all containers... "
UNHEALTHY_CONTAINERS=$(docker ps --filter "name=wharftales" --format "{{.Names}} {{.Status}}" | grep -v "Up" || true)
if [ -n "$UNHEALTHY_CONTAINERS" ]; then
    echo "✗ FAILED"
    echo "  ERROR: Some containers are not healthy:"
    echo "$UNHEALTHY_CONTAINERS" | sed 's/^/    /'
    EXIT_CODE=1
else
    echo "✓ OK"
fi

# Check 6: Check for critical errors in recent logs
echo -n "Checking container logs for errors... "
ERROR_LOGS=$(docker-compose -f /opt/wharftales/docker-compose.yml logs --tail=50 2>&1 | \
    grep -iE "fatal|critical|emergency" | \
    grep -v "update_check" | \
    grep -v "deprecated" || true)

if [ -n "$ERROR_LOGS" ]; then
    echo "⚠ WARNING"
    echo "  WARNING: Critical errors found in recent logs:"
    echo "$ERROR_LOGS" | head -5 | sed 's/^/    /'
    # Don't fail on log warnings, just alert
else
    echo "✓ OK"
fi

# Check 7: API endpoint test
echo -n "Checking API endpoint... "
API_TEST=$(curl -s --max-time 10 "http://localhost:9000/api.php?action=ping" 2>&1)
if echo "$API_TEST" | grep -q "pong\|success" 2>/dev/null; then
    echo "✓ OK"
else
    echo "⚠ WARNING"
    echo "  WARNING: API endpoint test failed (may not be implemented)"
    # Don't fail on this, it's just a nice-to-have
fi

# Check 8: File permissions
echo -n "Checking critical file permissions... "
if [ ! -r "/opt/wharftales/VERSION" ]; then
    echo "✗ FAILED"
    echo "  ERROR: Cannot read VERSION file"
    EXIT_CODE=1
elif [ ! -x "/opt/wharftales/scripts/upgrade.sh" ]; then
    echo "✗ FAILED"
    echo "  ERROR: upgrade.sh is not executable"
    EXIT_CODE=1
else
    echo "✓ OK"
fi

# Summary
echo "========================================="
if [ $EXIT_CODE -eq 0 ]; then
    echo "✓ All post-update checks passed"
    echo "Update validated successfully"
    
    # Clear update_in_progress flag
    docker exec wharftales_gui php -r "
    \$db = new PDO('sqlite:/app/data/database.sqlite');
    \$stmt = \$db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
    \$stmt->execute(['update_in_progress', '0']);
    " > /dev/null 2>&1
    echo "Update status flag cleared"
else
    echo "✗ Post-update validation failed"
    echo "System may not be functioning correctly"
    echo "RECOMMENDATION: Review logs and consider rollback"
fi
echo "========================================="

exit $EXIT_CODE

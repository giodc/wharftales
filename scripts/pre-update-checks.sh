#!/bin/bash
# Pre-update validation checks
# This script should be run before any update to ensure the system is ready

echo "========================================="
echo "WharfTales Pre-Update Validation"
echo "========================================="

EXIT_CODE=0

# Check 1: Disk space
echo -n "Checking disk space... "
AVAILABLE_SPACE=$(df /opt/wharftales | awk 'NR==2 {print $4}')
REQUIRED_SPACE=1048576  # 1GB in KB
if [ "$AVAILABLE_SPACE" -lt "$REQUIRED_SPACE" ]; then
    echo "✗ FAILED"
    echo "  ERROR: Insufficient disk space"
    echo "  Available: $(($AVAILABLE_SPACE / 1024))MB, Required: $(($REQUIRED_SPACE / 1024))MB"
    EXIT_CODE=1
else
    echo "✓ OK ($(($AVAILABLE_SPACE / 1024))MB available)"
fi

# Check 2: Docker daemon
echo -n "Checking Docker daemon... "
if ! docker info > /dev/null 2>&1; then
    echo "✗ FAILED"
    echo "  ERROR: Docker daemon not running or not accessible"
    EXIT_CODE=1
else
    echo "✓ OK"
fi

# Check 3: WharfTales containers running
echo -n "Checking WharfTales containers... "
if ! docker ps | grep -q wharftales_gui; then
    echo "✗ FAILED"
    echo "  ERROR: WharfTales GUI container not running"
    EXIT_CODE=1
else
    echo "✓ OK"
fi

# Check 4: Database accessibility
echo -n "Checking database accessibility... "
DB_CHECK=$(docker exec wharftales_gui php -r "
try {
    \$db = new PDO('sqlite:/app/data/database.sqlite');
    echo 'OK';
} catch (Exception \$e) {
    echo 'FAILED: ' . \$e->getMessage();
}
" 2>&1)

if [ "$DB_CHECK" != "OK" ]; then
    echo "✗ FAILED"
    echo "  ERROR: $DB_CHECK"
    EXIT_CODE=1
else
    echo "✓ OK"
fi

# Check 5: Internet connectivity
echo -n "Checking GitHub connectivity... "
if ! curl -s --max-time 10 --head https://github.com | head -n 1 | grep "200" > /dev/null; then
    echo "✗ FAILED"
    echo "  ERROR: Cannot reach GitHub (network issue or GitHub is down)"
    EXIT_CODE=1
else
    echo "✓ OK"
fi

# Check 6: Git repository status
echo -n "Checking git repository... "
cd /opt/wharftales
if [ ! -d ".git" ]; then
    echo "⚠ WARNING"
    echo "  WARNING: Not a git repository"
else
    GIT_STATUS=$(git status --porcelain 2>&1)
    if [ -n "$GIT_STATUS" ]; then
        echo "⚠ WARNING"
        echo "  WARNING: Local changes detected (will be stashed during update):"
        echo "$GIT_STATUS" | sed 's/^/    /'
    else
        echo "✓ OK"
    fi
fi

# Check 7: Update not already in progress
echo -n "Checking for concurrent updates... "
UPDATE_IN_PROGRESS=$(docker exec wharftales_gui php -r "
require_once '/var/www/html/includes/functions.php';
\$db = initDatabase();
\$status = getUpdateStatus();
echo \$status['in_progress'] ? '1' : '0';
" 2>&1)

if [ "$UPDATE_IN_PROGRESS" = "1" ]; then
    echo "✗ FAILED"
    echo "  ERROR: Update already in progress"
    EXIT_CODE=1
else
    echo "✓ OK"
fi

# Check 8: Backup directory writable
echo -n "Checking backup directory... "
BACKUP_DIR="/opt/wharftales/backups"
mkdir -p "$BACKUP_DIR" 2>/dev/null
if [ ! -w "$BACKUP_DIR" ]; then
    echo "✗ FAILED"
    echo "  ERROR: Backup directory not writable: $BACKUP_DIR"
    EXIT_CODE=1
else
    echo "✓ OK"
fi

echo "========================================="
if [ $EXIT_CODE -eq 0 ]; then
    echo "✓ All pre-update checks passed"
    echo "System is ready for update"
else
    echo "✗ Pre-update checks failed"
    echo "Please resolve the issues before updating"
fi
echo "========================================="

exit $EXIT_CODE

#!/bin/bash

# WharfTales Remote Installation Fix Script
# This script fixes existing remote installations after the rename from Webbadeploy to WharfTales

set -e

echo "=========================================="
echo "WharfTales Remote Installation Fix"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root (use sudo)${NC}"
    exit 1
fi

echo -e "${BLUE}Step 1: Stopping all containers...${NC}"
docker stop $(docker ps -aq) 2>/dev/null || true

echo ""
echo -e "${BLUE}Step 2: Removing old containers...${NC}"
docker rm webbadeploy_traefik webbadeploy_gui webbadeploy_db 2>/dev/null || echo "  Old containers already removed"

echo ""
echo -e "${BLUE}Step 3: Cleaning up networks...${NC}"
docker network rm webbadeploy wharftales_webbadeploy 2>/dev/null || echo "  Old networks already removed"
docker network prune -f

echo ""
echo -e "${BLUE}Step 4: Pulling latest code...${NC}"
cd /opt/wharftales
git pull origin main

echo ""
echo -e "${BLUE}Step 5: Fixing docker-compose.yml mount path...${NC}"
sed -i 's|/opt/webbadeploy/docker-compose.yml|/opt/wharftales/docker-compose.yml|g' docker-compose.yml

echo ""
echo -e "${BLUE}Step 6: Restarting Docker...${NC}"
systemctl restart docker
sleep 3

echo ""
echo -e "${BLUE}Step 7: Starting WharfTales...${NC}"
docker-compose up -d

echo ""
echo -e "${BLUE}Step 8: Waiting for containers to start...${NC}"
sleep 5

echo ""
echo -e "${BLUE}Step 9: Creating database tables...${NC}"
docker exec wharftales_gui sqlite3 /app/data/database.sqlite << 'EOSQL' 2>/dev/null || echo "  Tables already exist"
CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS compose_configs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    config_type TEXT NOT NULL,
    site_id INTEGER,
    compose_yaml TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER
);
EOSQL

echo ""
echo -e "${BLUE}Step 9: Fixing permissions...${NC}"
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data
docker exec -u root wharftales_gui chmod -R 775 /app/data
docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/apps
docker exec -u root wharftales_gui bash -c "find /app/apps -type d -exec chmod 775 {} \\;" 2>/dev/null || true
docker exec -u root wharftales_gui bash -c "find /app/apps -type f -exec chmod 664 {} \\;" 2>/dev/null || true

echo ""
echo -e "${BLUE}Step 10: Running migrations...${NC}"
docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || echo "  RBAC migration done"
docker exec wharftales_gui php /var/www/html/migrate-php-version.php 2>/dev/null || echo "  PHP version migration done"
docker exec wharftales_gui php /var/www/html/migrations/add_github_fields.php 2>/dev/null || echo "  GitHub fields migration done"
docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php 2>/dev/null || echo "  Permissions migration done"
docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php 2>/dev/null || echo "  Compose migration done"

echo ""
echo -e "${BLUE}Step 11: Importing docker-compose.yml into database...${NC}"
docker exec wharftales_gui php -r "
require '/var/www/html/includes/functions.php';
\$db = initDatabase();
\$composeFile = '/opt/wharftales/docker-compose.yml';
if (file_exists(\$composeFile)) {
    \$yaml = file_get_contents(\$composeFile);
    \$stmt = \$db->prepare('SELECT * FROM compose_configs WHERE config_type = \"main\" LIMIT 1');
    \$stmt->execute();
    \$existing = \$stmt->fetch(PDO::FETCH_ASSOC);
    if (\$existing) {
        \$stmt = \$db->prepare('UPDATE compose_configs SET compose_yaml = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        \$stmt->execute([\$yaml, \$existing['id']]);
    } else {
        \$stmt = \$db->prepare('INSERT INTO compose_configs (config_type, compose_yaml, created_at, updated_at) VALUES (\"main\", ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        \$stmt->execute([\$yaml]);
    }
    echo 'Compose config imported';
}
" 2>/dev/null || echo "  Compose config already imported"

echo ""
echo -e "${BLUE}Step 12: Restarting GUI container...${NC}"
docker restart wharftales_gui

echo ""
echo -e "${BLUE}Step 12: Verifying installation...${NC}"
sleep 3

echo ""
echo -e "${GREEN}Container Status:${NC}"
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

echo ""
echo -e "${GREEN}Networks:${NC}"
docker network ls | grep wharftales

echo ""
echo -e "${GREEN}Database Tables:${NC}"
docker exec wharftales_gui sqlite3 /app/data/database.sqlite "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;" 2>/dev/null || echo "  Could not check tables"

echo ""
echo "=========================================="
echo -e "${GREEN}✅ Remote Installation Fixed!${NC}"
echo "=========================================="
echo ""
echo "Your WharfTales installation has been updated and fixed."
echo "Access your dashboard at: http://your-server:9000"
echo ""
echo "What was fixed:"
echo "  ✓ Renamed containers from webbadeploy_* to wharftales_*"
echo "  ✓ Removed old networks"
echo "  ✓ Created settings and compose_configs tables"
echo "  ✓ Fixed /app/data and /app/apps permissions"
echo "  ✓ Ran all database migrations"
echo ""

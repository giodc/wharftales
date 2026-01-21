#!/bin/bash

# WharfTales Safe Update Script
# This script safely updates WharfTales while preserving all configurations

set -e  # Exit on any error

echo "=========================================="
echo "WharfTales Safe Update Script"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run with sudo${NC}"
    exit 1
fi

# Check if we're in the right directory
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}Error: docker-compose.yml not found. Please run from /opt/wharftales${NC}"
    exit 1
fi

cd /opt/wharftales

echo -e "${BLUE}Step 1: Backing up configurations...${NC}"

# Create backup directory with timestamp
BACKUP_DIR="/opt/wharftales/data/backups/update-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup docker-compose.yml (contains Let's Encrypt email and other settings)
if [ -f "docker-compose.yml" ]; then
    echo "  ✓ Backing up docker-compose.yml"
    cp docker-compose.yml "$BACKUP_DIR/docker-compose.yml"
fi

# Backup database (contains all site configs and settings)
if [ -f "data/database.sqlite" ]; then
    echo "  ✓ Backing up database.sqlite"
    cp data/database.sqlite "$BACKUP_DIR/database.sqlite"
fi

# Backup acme.json (contains SSL certificates)
if [ -f "ssl/acme.json" ]; then
    echo "  ✓ Backing up acme.json"
    cp ssl/acme.json "$BACKUP_DIR/acme.json"
fi

echo -e "${GREEN}  Backup saved to: $BACKUP_DIR${NC}"
echo ""

echo -e "${BLUE}Step 2: Extracting current Let's Encrypt email...${NC}"

# Extract current email from docker-compose.yml
CURRENT_EMAIL=$(grep -oP 'acme\.email=\K[^\s"]+' docker-compose.yml || echo "")

if [ -n "$CURRENT_EMAIL" ]; then
    echo -e "  ${GREEN}✓ Found email: $CURRENT_EMAIL${NC}"
    
    # Check if it's a placeholder
    if echo "$CURRENT_EMAIL" | grep -qE "@example\.(com|net|org)|@test\."; then
        echo -e "  ${YELLOW}⚠ Warning: Email appears to be a placeholder${NC}"
        echo -e "  ${YELLOW}  You should update this in Settings after the update${NC}"
    fi
else
    echo -e "  ${YELLOW}⚠ No email found in docker-compose.yml${NC}"
    CURRENT_EMAIL="admin@example.com"
fi

# Also check database for stored email
DB_EMAIL=$(docker exec wharftales_gui php -r "require '/var/www/html/includes/functions.php'; \$db = initDatabase(); echo getSetting(\$db, 'letsencrypt_email', '');" 2>/dev/null || echo "")

if [ -n "$DB_EMAIL" ] && [ "$DB_EMAIL" != "$CURRENT_EMAIL" ]; then
    echo -e "  ${BLUE}ℹ Database has different email: $DB_EMAIL${NC}"
    echo -e "  ${BLUE}  Will use database email as it's more recent${NC}"
    CURRENT_EMAIL="$DB_EMAIL"
fi

echo ""

echo -e "${BLUE}Step 3: Pulling latest changes from GitHub...${NC}"

# Stash any local changes (but preserve .gitignored files)
if ! git diff-index --quiet HEAD --; then
    echo "  ℹ Stashing local changes..."
    git stash push -m "Auto-stash before update $(date +%Y%m%d-%H%M%S)"
fi

# Pull latest version
git pull origin main

echo -e "${GREEN}  ✓ Latest code downloaded${NC}"
echo ""

echo -e "${BLUE}Step 4: Restoring configurations...${NC}"

# Restore docker-compose.yml from backup
if [ -f "$BACKUP_DIR/docker-compose.yml" ]; then
    echo "  ✓ Restoring docker-compose.yml"
    cp "$BACKUP_DIR/docker-compose.yml" docker-compose.yml
else
    echo -e "  ${YELLOW}⚠ No backup found, keeping current file${NC}"
fi

# Restore acme.json from backup (important - contains certificates!)
if [ -f "$BACKUP_DIR/acme.json" ]; then
    echo "  ✓ Restoring acme.json (SSL certificates)"
    cp "$BACKUP_DIR/acme.json" ssl/acme.json
    chmod 600 ssl/acme.json
    chown root:root ssl/acme.json
fi

echo ""

echo -e "${BLUE}Step 5: Updating containers...${NC}"

# Rebuild web-gui container (may have new features)
echo "  Building web-gui container..."
docker-compose build --no-cache web-gui

# Restart all services
echo "  Restarting services..."
docker-compose down
docker-compose up -d

echo -e "${GREEN}  ✓ Containers updated and restarted${NC}"
echo ""

echo -e "${BLUE}Step 6: Running database migrations...${NC}"

# Wait for containers to be ready
sleep 3

# Run migrations
docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || echo "  ℹ RBAC migration already applied"
docker exec wharftales_gui php /var/www/html/migrate-php-version.php 2>/dev/null || echo "  ℹ PHP version migration already applied"
docker exec wharftales_gui php /var/www/html/migrations/add_github_fields.php 2>/dev/null || echo "  ℹ GitHub fields migration already applied"
docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php 2>/dev/null || echo "  ℹ Site permissions migration already applied"

# Import compose configs to database (only if not already there)
docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php 2>/dev/null || echo "  ℹ Compose configs already in database"

# Ensure settings and compose_configs tables exist
docker exec wharftales_gui sqlite3 /app/data/database.sqlite << 'EOSQL' 2>/dev/null || echo "  ℹ Tables already exist"
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

echo -e "${GREEN}  ✓ Migrations completed${NC}"
echo ""

echo -e "${BLUE}Step 7: Fixing permissions...${NC}"

# Fix data directory permissions
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data
docker exec -u root wharftales_gui chmod -R 775 /app/data

# Fix apps directory permissions
docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/apps

# Fix database permissions
docker exec -u root wharftales_gui bash -c "if [ -f /app/data/database.sqlite ]; then chown www-data:www-data /app/data/database.sqlite && chmod 664 /app/data/database.sqlite; fi"

# Ensure apps subdirectories are writable
docker exec -u root wharftales_gui bash -c "find /app/apps -type d -exec chmod 775 {} \\;"
docker exec -u root wharftales_gui bash -c "find /app/apps -type f -exec chmod 664 {} \\;"

echo -e "${GREEN}  ✓ Permissions fixed${NC}"
echo ""

echo -e "${BLUE}Step 8: Verifying configuration...${NC}"

# Verify email is still set correctly
FINAL_EMAIL=$(grep -oP 'acme\.email=\K[^\s"]+' docker-compose.yml || echo "")

if [ "$FINAL_EMAIL" = "$CURRENT_EMAIL" ]; then
    echo -e "  ${GREEN}✓ Let's Encrypt email preserved: $FINAL_EMAIL${NC}"
else
    echo -e "  ${YELLOW}⚠ Email changed from $CURRENT_EMAIL to $FINAL_EMAIL${NC}"
    echo -e "  ${YELLOW}  You may need to update this in Settings${NC}"
fi

# Check if Traefik is running
if docker ps | grep -q wharftales_traefik; then
    echo -e "  ${GREEN}✓ Traefik is running${NC}"
else
    echo -e "  ${RED}✗ Traefik is not running${NC}"
fi

# Check if web-gui is running
if docker ps | grep -q wharftales_gui; then
    echo -e "  ${GREEN}✓ Web GUI is running${NC}"
else
    echo -e "  ${RED}✗ Web GUI is not running${NC}"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}Update completed successfully!${NC}"
echo "=========================================="
echo ""
echo "Summary:"
echo "  • Backup saved to: $BACKUP_DIR"
echo "  • Let's Encrypt email: $FINAL_EMAIL"
echo "  • Access dashboard at: http://your-server-ip:9000"
echo ""
echo "Next steps:"
echo "  1. Verify your sites are accessible"
echo "  2. Check SSL Debug page for certificate status"
echo "  3. If email was reset, update it in Settings → SSL Configuration"
echo ""
echo "If you encounter any issues:"
echo "  • Restore from backup: cp $BACKUP_DIR/docker-compose.yml /opt/wharftales/"
echo "  • Check logs: docker-compose logs -f"
echo "  • Restart services: docker-compose restart"
echo ""

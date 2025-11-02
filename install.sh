#!/bin/bash

# Exit on error, but allow some commands to fail gracefully
set -e

echo "WharfTales Installation Script"
echo "==============================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (use sudo)"
    exit 1
fi

# Check if this is an update (wharftales already exists)
if [ -d "/opt/wharftales/.git" ]; then
    echo "Existing installation detected. Running update mode..."
    UPDATE_MODE=true
else
    echo "New installation mode..."
    UPDATE_MODE=false
fi

# Update system
echo "Updating system packages..."
apt update && apt upgrade -y

# Install git (needed for cloning)
echo "Installing git..."
apt install -y git

# Install Docker
echo "Installing Docker..."
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
    systemctl enable docker
    systemctl start docker
fi

# Install Docker Compose
echo "Installing Docker Compose..."
if ! command -v docker-compose &> /dev/null; then
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
fi

# Create wharftales user
echo "Creating wharftales user..."
if ! id "wharftales" &>/dev/null; then
    useradd -m -s /bin/bash wharftales
    usermod -aG docker wharftales
fi

# Set up directories
if [ "$UPDATE_MODE" = true ]; then
    echo "Updating existing installation..."
    cd /opt/wharftales
    
    # Backup critical files before update
    echo "Backing up configurations..."
    BACKUP_DIR="/opt/wharftales/data/backups/update-$(date +%Y%m%d-%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    # Backup docker-compose.yml (contains Let's Encrypt email)
    if [ -f "docker-compose.yml" ]; then
        cp docker-compose.yml "$BACKUP_DIR/docker-compose.yml"
        echo "  ✓ Backed up docker-compose.yml"
    fi
    
    # Backup database
    if [ -f "data/database.sqlite" ]; then
        cp data/database.sqlite "$BACKUP_DIR/database.sqlite"
        echo "  ✓ Backed up database"
    fi
    
    # Backup acme.json (SSL certificates)
    if [ -f "ssl/acme.json" ]; then
        cp ssl/acme.json "$BACKUP_DIR/acme.json"
        echo "  ✓ Backed up acme.json"
    fi
    
    # Stash any local changes
    git stash
    
    # Pull latest version
    echo "Pulling latest version from GitHub..."
    git pull origin master
    
    # Preserve user settings from old docker-compose.yml
    if [ -f "$BACKUP_DIR/docker-compose.yml" ]; then
        echo "Preserving user email from old docker-compose.yml..."
        # Extract email from backup
        OLD_EMAIL=$(grep -oP 'letsencrypt\.acme\.email=\K[^"]+' "$BACKUP_DIR/docker-compose.yml" || echo "")
        if [ ! -z "$OLD_EMAIL" ]; then
            echo "Updating email to: $OLD_EMAIL"
            sed -i "s/letsencrypt\.acme\.email=info@giodc\.com/letsencrypt.acme.email=$OLD_EMAIL/" docker-compose.yml
        fi
    fi

    # Apply installer overrides for domain/email and standardize ports if provided
    # DASHBOARD_DOMAIN: sets Host(`...`) for web-gui routers
    # LE_EMAIL: updates Traefik ACME email
    if [ -f "docker-compose.yml" ]; then
        if [ -n "${LE_EMAIL:-}" ]; then
            echo "Applying Let's Encrypt email from LE_EMAIL: $LE_EMAIL"
            sed -i "s/--certificatesresolvers\.letsencrypt\.acme\.email=[^"]*/--certificatesresolvers.letsencrypt.acme.email=$LE_EMAIL/" docker-compose.yml
        fi
        if [ -n "${DASHBOARD_DOMAIN:-}" ]; then
            echo "Applying dashboard domain from DASHBOARD_DOMAIN: $DASHBOARD_DOMAIN"
            sed -i "s#traefik\.http\.routers\.webgui\.rule=Host\(`[^`]*`\)#traefik.http.routers.webgui.rule=Host(`$DASHBOARD_DOMAIN`)#" docker-compose.yml || true
            sed -i "s#traefik\.http\.routers\.webgui-secure\.rule=Host\(`[^`]*`\)#traefik.http.routers.webgui-secure.rule=Host(`$DASHBOARD_DOMAIN`)#" docker-compose.yml || true
            sed -i "s#traefik\.http\.routers\.webgui-alt\.rule=Host\(`[^`]*`\)#traefik.http.routers.webgui-alt.rule=Host(`$DASHBOARD_DOMAIN`)#" docker-compose.yml || true
        fi
        # Ensure correct internal service port and host mapping for web-gui
        sed -i 's/traefik\.http\.services\.webgui\.loadbalancer\.server\.port=[0-9]\+/traefik.http.services.webgui.loadbalancer.server.port=8080/' docker-compose.yml || true
        sed -i 's/\(\s*-\s*"\)9000:80\(\"\)/\19000:8080\2/' docker-compose.yml || true
    fi
    
    # Restore acme.json from backup (preserve SSL certificates)
    if [ -f "$BACKUP_DIR/acme.json" ]; then
        echo "Restoring acme.json to preserve SSL certificates..."
        cp "$BACKUP_DIR/acme.json" ssl/acme.json
        chmod 600 ssl/acme.json
        chown root:root ssl/acme.json
    fi
    
    # Set permissions on docker-compose.yml
    echo "Setting permissions on docker-compose.yml..."
    chmod 664 docker-compose.yml
    chown www-data:www-data docker-compose.yml
    
    # Set Docker socket permissions (use docker group instead of world-writable)
    echo "Setting Docker socket permissions..."
    groupadd -f docker
    usermod -aG docker www-data
    chmod 660 /var/run/docker.sock
    chown root:docker /var/run/docker.sock
    
    # Create backup directory if it doesn't exist
    echo "Ensuring backup directory exists..."
    mkdir -p /opt/wharftales/data/backups
    chown -R www-data:www-data /opt/wharftales/data/backups
    
    # Create traefik-dns.env if it doesn't exist
    if [ ! -f "/opt/wharftales/data/traefik-dns.env" ]; then
        echo "Creating traefik-dns.env file..."
        touch /opt/wharftales/data/traefik-dns.env
        chmod 600 /opt/wharftales/data/traefik-dns.env
    fi
    
    # Detect and update Docker GID
    echo "Detecting Docker group ID..."
    DOCKER_GID=$(getent group docker | cut -d: -f3)
    if [ -z "$DOCKER_GID" ]; then
        echo "Warning: Could not detect Docker GID, using default 999"
        DOCKER_GID=999
    else
        echo "Detected Docker GID: $DOCKER_GID"
    fi
    
    echo "Updating docker-compose.yml with correct Docker GID..."
    sed -i "s/DOCKER_GID: [0-9]*/DOCKER_GID: $DOCKER_GID/" docker-compose.yml
    
    # Rebuild and restart containers
    echo "Rebuilding containers..."
    docker-compose build --no-cache web-gui
    
    echo "Restarting services..."
    docker-compose down
    docker-compose up -d
    
    # Install MySQL extensions manually (in case build fails)
    echo "Installing MySQL extensions..."
    docker exec -u root wharftales_gui docker-php-ext-install pdo_mysql mysqli 2>/dev/null || true
    docker exec wharftales_gui apache2ctl restart 2>/dev/null || true
    
    # Fix data directory permissions
    echo "Fixing data directory permissions..."
    docker exec -u root wharftales_gui chown -R www-data:www-data /app/data
    docker exec -u root wharftales_gui chmod -R 775 /app/data
    
    echo "Fixing apps directory permissions..."
    docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
    docker exec -u root wharftales_gui chmod -R 775 /app/apps
    docker exec -u root wharftales_gui bash -c "find /app/apps -type d -exec chmod 775 {} \\;" 2>/dev/null || true
    docker exec -u root wharftales_gui bash -c "find /app/apps -type f -exec chmod 664 {} \\;" 2>/dev/null || true
    
    echo "Ensuring database file exists with proper permissions..."
    docker exec -u root wharftales_gui bash -c "touch /app/data/database.sqlite && chown www-data:www-data /app/data/database.sqlite && chmod 664 /app/data/database.sqlite"
    
    # Run database migrations
    echo "Running database migrations..."
    sleep 2
    docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || echo "Migration completed or already applied"
    docker exec wharftales_gui php /var/www/html/migrate-php-version.php 2>/dev/null || echo "PHP version migration completed or already applied"
    docker exec wharftales_gui php /var/www/html/migrations/add_github_fields.php 2>/dev/null || echo "GitHub fields migration completed or already applied"
    docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php 2>/dev/null || echo "Site permissions migration completed or already applied"
    
    echo "Importing docker-compose configurations to database..."
    docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php 2>/dev/null || echo "Compose migration completed or already applied"
    
    echo "Initializing database tables..."
    docker exec wharftales_gui sqlite3 /app/data/database.sqlite << 'EOSQL' 2>/dev/null || echo "Database tables already exist"
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
    echo "==============================="
    echo "Update completed successfully!"
    echo "==============================="
    echo "WharfTales has been updated to the latest version."
    echo "Access the dashboard at: http://your-server-ip:9000"
    exit 0
else
    echo "Setting up directories..."
    
    # Check if we're running from /opt/wharftales already
    if [ "$PWD" = "/opt/wharftales" ]; then
        echo "Already in /opt/wharftales, skipping clone..."
    else
        # Clone from GitHub if not already present
        if [ ! -d "/opt/wharftales/.git" ]; then
            echo "Cloning WharfTales from GitHub..."
            git clone https://github.com/giodc/wharftales.git /opt/wharftales
        fi
    fi
    
    cd /opt/wharftales
    chown -R wharftales:wharftales /opt/wharftales
    
    # Create required directories
    mkdir -p /opt/wharftales/{data,nginx/sites,ssl,apps,web}
    
    # Create empty traefik-dns.env file (used for DNS challenge credentials)
    echo "Creating traefik-dns.env file..."
    touch /opt/wharftales/data/traefik-dns.env
    chmod 600 /opt/wharftales/data/traefik-dns.env
    
    # Create ACME file for SSL certificates
    echo "Creating ACME file for SSL certificates..."
    cat > /opt/wharftales/ssl/acme.json << 'ACME_EOF'
{
  "letsencrypt": {
    "Account": {
      "Email": "",
      "Registration": null,
      "PrivateKey": null,
      "KeyType": ""
    },
    "Certificates": null
  }
}
ACME_EOF
    chmod 600 /opt/wharftales/ssl/acme.json
    chown root:root /opt/wharftales/ssl/acme.json
    
    # Set proper permissions for data directory (needs to be writable by www-data in container)
    chown -R www-data:www-data /opt/wharftales/data
    chmod -R 775 /opt/wharftales/data
    
    # Set proper permissions for apps directory (needs to be writable by www-data in container)
    chown -R www-data:www-data /opt/wharftales/apps
    chmod -R 775 /opt/wharftales/apps
    
    chown -R wharftales:wharftales /opt/wharftales
    
    # Re-apply www-data ownership to data and apps after setting wharftales ownership
    chown -R www-data:www-data /opt/wharftales/data
    chown -R www-data:www-data /opt/wharftales/apps
    
    # Create docker-compose.yml from template if it doesn't exist
    if [ ! -f "/opt/wharftales/docker-compose.yml" ]; then
        if [ -f "/opt/wharftales/docker-compose.yml.template" ]; then
            echo "Creating docker-compose.yml from template..."
            cp /opt/wharftales/docker-compose.yml.template /opt/wharftales/docker-compose.yml
            echo "⚠️  IMPORTANT: Edit docker-compose.yml to configure:"
            echo "   - Email address (search for CHANGE_ME@example.com)"
            echo "   - Dashboard domain (search for CHANGE_ME.example.com)"
        else
            echo "Warning: docker-compose.yml.template not found"
        fi
    fi
    
    # Set permissions on docker-compose.yml
    if [ -f "/opt/wharftales/docker-compose.yml" ]; then
        echo "Setting permissions on docker-compose.yml..."
        chmod 664 /opt/wharftales/docker-compose.yml
        chown www-data:www-data /opt/wharftales/docker-compose.yml
        # Apply installer overrides for domain/email and standardize ports if provided
        if [ -n "${LE_EMAIL:-}" ]; then
            echo "Applying Let's Encrypt email from LE_EMAIL: $LE_EMAIL"
            sed -i "s/--certificatesresolvers\.letsencrypt\.acme\.email=[^"]*/--certificatesresolvers.letsencrypt.acme.email=$LE_EMAIL/" /opt/wharftales/docker-compose.yml
        fi
        if [ -n "${DASHBOARD_DOMAIN:-}" ]; then
            echo "Applying dashboard domain from DASHBOARD_DOMAIN: $DASHBOARD_DOMAIN"
            sed -i "s#traefik\.http\.routers\.webgui\.rule=Host\(`[^`]*`\)#traefik.http.routers.webgui.rule=Host(`$DASHBOARD_DOMAIN`)#" /opt/wharftales/docker-compose.yml || true
            sed -i "s#traefik\.http\.routers\.webgui-secure\.rule=Host\(`[^`]*`\)#traefik.http.routers.webgui-secure.rule=Host(`$DASHBOARD_DOMAIN`)#" /opt/wharftales/docker-compose.yml || true
            sed -i "s#traefik\.http\.routers\.webgui-alt\.rule=Host\(`[^`]*`\)#traefik.http.routers.webgui-alt.rule=Host(`$DASHBOARD_DOMAIN`)#" /opt/wharftales/docker-compose.yml || true
        fi
        # Ensure correct internal service port and host mapping for web-gui
        sed -i 's/traefik\.http\.services\.webgui\.loadbalancer\.server\.port=[0-9]\+/traefik.http.services.webgui.loadbalancer.server.port=8080/' /opt/wharftales/docker-compose.yml || true
        sed -i 's/\(\s*-\s*"\)9000:80\(\"\)/\19000:8080\2/' /opt/wharftales/docker-compose.yml || true
    fi
    
    # Set Docker socket permissions (use docker group instead of world-writable)
    echo "Setting Docker socket permissions..."
    groupadd -f docker
    usermod -aG docker www-data
    chmod 660 /var/run/docker.sock
    chown root:docker /var/run/docker.sock
    
    # Create backup directory
    echo "Creating backup directory..."
    mkdir -p /opt/wharftales/data/backups
    chown -R www-data:www-data /opt/wharftales/data/backups
fi

# Note: SSL certificates are handled by Traefik (no certbot needed)
# Traefik automatically requests and renews Let's Encrypt certificates

# Set up firewall
echo "Configuring firewall..."
if command -v ufw &> /dev/null; then
    ufw allow 22/tcp
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw --force enable
fi

# Verify ACME file exists
if [ ! -f "/opt/wharftales/ssl/acme.json" ]; then
    echo "Creating ACME file for SSL certificates..."
    cat > /opt/wharftales/ssl/acme.json << 'ACME_EOF'
{
  "letsencrypt": {
    "Account": {
      "Email": "",
      "Registration": null,
      "PrivateKey": null,
      "KeyType": ""
    },
    "Certificates": null
  }
}
ACME_EOF
    chmod 600 /opt/wharftales/ssl/acme.json
    chown root:root /opt/wharftales/ssl/acme.json
fi

echo "Detecting Docker group ID..."
DOCKER_GID=$(getent group docker | cut -d: -f3)
if [ -z "$DOCKER_GID" ]; then
    echo "Warning: Could not detect Docker GID, using default 999"
    DOCKER_GID=999
else
    echo "Detected Docker GID: $DOCKER_GID"
fi

echo "Updating docker-compose.yml with correct Docker GID..."
sed -i "s/DOCKER_GID: [0-9]*/DOCKER_GID: $DOCKER_GID/" docker-compose.yml

echo "Building containers..."
cd /opt/wharftales
docker-compose build --no-cache web-gui

echo "Starting services..."
docker-compose down 2>/dev/null || true
docker-compose up -d

echo "Waiting for container to be ready..."
sleep 5

echo "Installing MySQL extensions..."
docker exec -u root wharftales_gui docker-php-ext-install pdo_mysql mysqli 2>/dev/null || echo "MySQL extensions already installed"
docker exec wharftales_gui apache2ctl restart 2>/dev/null || echo "Apache restart skipped"

echo "Fixing data directory permissions..."
docker exec -u root wharftales_gui chown -R www-data:www-data /app/data
docker exec -u root wharftales_gui chmod -R 775 /app/data

echo "Fixing apps directory permissions..."
docker exec -u root wharftales_gui chown -R www-data:www-data /app/apps
docker exec -u root wharftales_gui chmod -R 775 /app/apps
docker exec -u root wharftales_gui bash -c "find /app/apps -type d -exec chmod 775 {} \\;" 2>/dev/null || true
docker exec -u root wharftales_gui bash -c "find /app/apps -type f -exec chmod 664 {} \\;" 2>/dev/null || true

echo "Creating database file with proper permissions..."
docker exec -u root wharftales_gui bash -c "touch /app/data/database.sqlite && chown www-data:www-data /app/data/database.sqlite && chmod 664 /app/data/database.sqlite"

echo "Initializing database..."
sleep 2
docker exec wharftales_gui php -r "
require '/var/www/html/includes/functions.php';
\$db = initDatabase();
echo 'Database initialized successfully\n';
" || echo "Database initialization failed - will be created on first access"

echo "Verifying database permissions..."
docker exec -u root wharftales_gui bash -c "chown www-data:www-data /app/data/database.sqlite && chmod 664 /app/data/database.sqlite"

echo "Running database migrations..."
docker exec wharftales_gui php /var/www/html/migrate-rbac-2fa.php 2>/dev/null || echo "Migration will run on first access"
docker exec wharftales_gui php /var/www/html/migrate-php-version.php 2>/dev/null || echo "PHP version migration will run on first access"
docker exec wharftales_gui php /var/www/html/migrations/add_github_fields.php 2>/dev/null || echo "GitHub fields migration will run on first access"
docker exec wharftales_gui php /var/www/html/migrations/fix-site-permissions-database.php 2>/dev/null || echo "Site permissions migration will run on first access"

echo "Importing docker-compose configurations to database..."
docker exec wharftales_gui php /var/www/html/migrate-compose-to-db.php 2>/dev/null || echo "Compose migration will run on first settings update"

echo "Importing docker-compose.yml into database..."
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
    echo 'Docker compose configuration imported successfully\n';
}
" 2>/dev/null || echo "Compose config will be imported on first settings save"

echo ""
echo "==============================="
echo "Installation completed!"
echo "==============================="
echo "Access the web GUI at http://your-server-ip:9000"
echo "Default credentials will be created on first access"
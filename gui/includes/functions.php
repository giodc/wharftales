<?php

function initDatabase() {
    $dbPath = $_ENV['DB_PATH'] ?? '/app/data/database.sqlite';
    $dir = dirname($dbPath);
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    if (!is_writable($dir)) { @chmod($dir, 0775); }
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable WAL mode for better concurrency
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA synchronous = NORMAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    // Create tables if they don't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS sites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        type TEXT NOT NULL,
        domain TEXT UNIQUE NOT NULL,
        ssl INTEGER DEFAULT 0,
        ssl_config TEXT,
        status TEXT DEFAULT 'stopped',
        container_name TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        config TEXT,
        sftp_enabled INTEGER DEFAULT 0,
        sftp_username TEXT,
        sftp_password TEXT,
        sftp_port INTEGER,
        db_password TEXT,
        db_type TEXT DEFAULT 'shared',
        db_host TEXT,
        db_name TEXT,
        db_user TEXT,
        db_port INTEGER DEFAULT 3306,
        owner_id INTEGER DEFAULT 1,
        redis_enabled INTEGER DEFAULT 0,
        redis_host TEXT,
        redis_port INTEGER,
        redis_password TEXT,
        redis_container TEXT,
        php_version TEXT DEFAULT '8.3',
        github_repo TEXT,
        github_branch TEXT DEFAULT 'main',
        github_token TEXT,
        github_last_commit TEXT,
        github_last_pull DATETIME,
        deployment_method TEXT DEFAULT 'manual'
    )");

    // Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create RBAC tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        permission_key TEXT NOT NULL,
        permission_value INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, permission_key)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        site_id INTEGER NOT NULL,
        permission_level TEXT DEFAULT 'view',
        granted_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, site_id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        resource_type TEXT,
        resource_id INTEGER,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create compose_configs table for storing docker-compose YAML
    $pdo->exec("CREATE TABLE IF NOT EXISTS compose_configs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        config_type TEXT NOT NULL,  -- 'main' or 'site'
        site_id INTEGER,            -- NULL for main config, site ID for site configs
        compose_yaml TEXT NOT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_by INTEGER,
        FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
        UNIQUE(config_type, site_id)
    )");

    // Migrate existing databases - add columns if they don't exist
    try {
        $result = $pdo->query("PRAGMA table_info(sites)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'name');
        
        if (!in_array('db_password', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN db_password TEXT");
        }
        if (!in_array('db_type', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN db_type TEXT DEFAULT 'shared'");
        }
        if (!in_array('owner_id', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN owner_id INTEGER DEFAULT 1");
        }
        if (!in_array('redis_enabled', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN redis_enabled INTEGER DEFAULT 0");
        }
        if (!in_array('redis_host', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN redis_host TEXT");
        }
        if (!in_array('redis_port', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN redis_port INTEGER");
        }
        if (!in_array('redis_password', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN redis_password TEXT");
        }
        if (!in_array('redis_container', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN redis_container TEXT");
        }
        if (!in_array('php_version', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN php_version TEXT DEFAULT '8.3'");
        }
        if (!in_array('github_repo', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN github_repo TEXT");
        }
        if (!in_array('github_branch', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN github_branch TEXT DEFAULT 'main'");
        }
        if (!in_array('github_token', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN github_token TEXT");
        }
        if (!in_array('github_last_commit', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN github_last_commit TEXT");
        }
        if (!in_array('github_last_pull', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN github_last_pull DATETIME");
        }
        if (!in_array('deployment_method', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN deployment_method TEXT DEFAULT 'manual'");
        }
        if (!in_array('ssl_cert_issued', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN ssl_cert_issued INTEGER DEFAULT 0");
        }
        if (!in_array('ssl_cert_issued_at', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN ssl_cert_issued_at DATETIME");
        }
        if (!in_array('db_host', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN db_host TEXT");
        }
        if (!in_array('db_name', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN db_name TEXT");
        }
        if (!in_array('db_user', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN db_user TEXT");
        }
        if (!in_array('db_port', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN db_port INTEGER DEFAULT 3306");
        }
        if (!in_array('include_www', $columnNames)) {
            $pdo->exec("ALTER TABLE sites ADD COLUMN include_www INTEGER DEFAULT 0");
        }
    } catch (Exception $e) {
        // Columns might already exist or other error, continue
    }

    return $pdo;
}

function getAllSites($pdo) {
    $stmt = $pdo->query("SELECT * FROM sites ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSiteById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSetting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['value'] : $default;
}

/**
 * Redirect from :9000 to SSL URL if dashboard has SSL enabled
 * This prevents exposing the insecure :9000 port when SSL is configured
 */
function redirectToSSLIfEnabled() {
    // Check if request is coming from port 9000
    $serverPort = $_SERVER['SERVER_PORT'] ?? '80';
    
    // Only redirect if accessing via port 9000
    if ($serverPort != '9000') {
        return;
    }
    
    // Initialize database to check settings
    try {
        $db = initDatabase();
        $dashboardSSL = getSetting($db, 'dashboard_ssl', '0');
        $dashboardDomain = getSetting($db, 'dashboard_domain', '');
        
        // If SSL is enabled and domain is configured, redirect
        // TEMPORARILY DISABLED - uncomment when SSL is working
       
        if ($dashboardSSL === '1' && !empty($dashboardDomain)) {
            $protocol = 'https';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $redirectUrl = $protocol . '://' . $dashboardDomain . $requestUri;
            
            // Perform permanent redirect (301)
            header('Location: ' . $redirectUrl, true, 301);
            exit;
        }
       
    } catch (Exception $e) {
        // If there's an error checking settings, don't redirect
        // This ensures the dashboard remains accessible even if there's a DB issue
        error_log('SSL redirect check failed: ' . $e->getMessage());
    }
}

function setSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
    return $stmt->execute([$key, $value]);
}

function createSite($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO sites (name, type, domain, ssl, ssl_config, container_name, config, db_password, db_type, owner_id, php_version, include_www, github_repo, github_branch, github_token, deployment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $containerName = $data['container_name'] ?? '';
    $config = json_encode($data['config'] ?? []);
    $sslConfig = isset($data['ssl_config']) ? json_encode($data['ssl_config']) : null;
    $dbPassword = $data['db_password'] ?? null;
    $dbType = $data['db_type'] ?? 'shared';
    $ownerId = $data['owner_id'] ?? $_SESSION['user_id'] ?? 1;
    $phpVersion = $data['php_version'] ?? '8.4';
    $includeWww = isset($data['include_www']) ? ($data['include_www'] ? 1 : 0) : 0;
    
    // GitHub deployment fields
    $githubRepo = $data['github_repo'] ?? null;
    $githubBranch = $data['github_branch'] ?? 'main';
    $githubToken = $data['github_token'] ?? null;
    $deploymentMethod = $githubRepo ? 'github' : 'manual';
    
    // Encrypt GitHub token if provided
    if ($githubToken) {
        $githubToken = encryptGitHubToken($githubToken);
    }
    
    return $stmt->execute([
        $data['name'],
        $data['type'],
        $data['domain'],
        $data['ssl'] ? 1 : 0,
        $sslConfig,
        $containerName,
        $config,
        $dbPassword,
        $dbType,
        $ownerId,
        $phpVersion,
        $includeWww,
        $githubRepo,
        $githubBranch,
        $githubToken,
        $deploymentMethod
    ]);
}

function updateSiteStatus($pdo, $id, $status) {
    $stmt = $pdo->prepare("UPDATE sites SET status = ? WHERE id = ?");
    return $stmt->execute([$status, $id]);
}

function deleteSite($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ?");
    return $stmt->execute([$id]);
}

function generateSiteId($name) {
    return preg_replace('/[^a-z0-9]/', '', strtolower($name)) . '_' . time();
}

function generateRandomString($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

function getAppIcon($type) {
    switch ($type) {
        case 'wordpress': return 'wordpress';
        case 'php': return 'code-slash';
        case 'laravel': return 'lightning';
        case 'mariadb': return 'database';
        default: return 'app';
    }
}

function executeDockerCommand($command) {
    $output = [];
    $returnCode = 0;
    exec("docker $command 2>&1", $output, $returnCode);
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'code' => $returnCode
    ];
}

function executeDockerCompose($composePath, $command) {
    $output = [];
    $returnCode = 0;
    // Set HOME to /tmp to avoid permission issues with Docker build cache
    exec("cd " . dirname($composePath) . " && HOME=/tmp docker-compose -f " . basename($composePath) . " $command 2>&1", $output, $returnCode);
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'code' => $returnCode
    ];
}

function createNginxSiteConfig($site) {
    $sslConfig = '';
    if ($site['ssl']) {
        $sslConfig = "
    listen 443 ssl http2;
    ssl_certificate /etc/ssl/certs/{$site['domain']}/fullchain.pem;
    ssl_certificate_key /etc/ssl/certs/{$site['domain']}/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    add_header Strict-Transport-Security \"max-age=63072000\" always;";
    }

    $config = "server {
    listen 80;
    server_name {$site['domain']};

    {$sslConfig}

    location / {
        proxy_pass http://{$site['container_name']};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}";

    return $config;
}

// Legacy certbot functions removed - Traefik now handles all SSL certificates automatically

function getDockerContainerStatus($containerName) {
    // Use docker inspect for exact container name match
    $output = [];
    $returnCode = 0;
    
    // Try with full path first (more reliable)
    $dockerPaths = ['/usr/bin/docker', '/usr/local/bin/docker', 'docker'];
    
    foreach ($dockerPaths as $dockerCmd) {
        exec("$dockerCmd inspect -f '{{.State.Status}}' " . escapeshellarg($containerName) . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $status = trim($output[0]);
            if ($status === 'running') {
                return 'running';
            } elseif ($status === 'exited' || $status === 'created') {
                return 'stopped';
            }
            return $status;
        }
        
        // Reset for next attempt
        $output = [];
        $returnCode = 0;
    }
    
    return 'unknown';
}

function checkContainerSSLLabels($containerName) {
    // Check if container has SSL Traefik labels configured
    $output = [];
    $returnCode = 0;
    
    $dockerPaths = ['/usr/bin/docker', '/usr/local/bin/docker', 'docker'];
    
    foreach ($dockerPaths as $dockerCmd) {
        // Check for the secure router label which indicates SSL is configured
        exec("$dockerCmd inspect -f '{{index .Config.Labels \"traefik.http.routers." . $containerName . "-secure.tls\"}}' " . escapeshellarg($containerName) . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $hasSSL = trim($output[0]) === 'true';
            return $hasSSL;
        }
        
        // Reset for next attempt
        $output = [];
        $returnCode = 0;
    }
    
    return false;
}

function reloadNginx() {
    return executeDockerCommand("exec wharftales_nginx nginx -s reload");
}

function generateSFTPCredentials($siteName) {
    $username = 'sftp_' . preg_replace('/[^a-z0-9]/', '', strtolower($siteName));
    $password = bin2hex(random_bytes(12)); // 24 character password
    return [
        'username' => substr($username, 0, 32), // Limit username length
        'password' => $password
    ];
}

function getNextAvailableSFTPPort($pdo) {
    $stmt = $pdo->query("SELECT MAX(sftp_port) as max_port FROM sites WHERE sftp_enabled = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $maxPort = $result['max_port'] ?? 2221;
    return max(2222, $maxPort + 1);
}

function enableSFTP($pdo, $siteId) {
    $site = getSiteById($pdo, $siteId);
    if (!$site) {
        throw new Exception("Site not found");
    }
    
    // Generate credentials if not exists
    if (empty($site['sftp_username'])) {
        $credentials = generateSFTPCredentials($site['name']);
        $port = getNextAvailableSFTPPort($pdo);
        
        $stmt = $pdo->prepare("UPDATE sites SET sftp_enabled = 1, sftp_username = ?, sftp_password = ?, sftp_port = ? WHERE id = ?");
        $stmt->execute([$credentials['username'], $credentials['password'], $port, $siteId]);
        
        // Reload site data with new credentials
        $site = getSiteById($pdo, $siteId);
    } else {
        $stmt = $pdo->prepare("UPDATE sites SET sftp_enabled = 1 WHERE id = ?");
        $stmt->execute([$siteId]);
        
        // Reload site data
        $site = getSiteById($pdo, $siteId);
    }
    
    // Deploy SFTP container with updated site data
    deploySFTPContainer($site);
    
    return $site;
}

function disableSFTP($pdo, $siteId) {
    $site = getSiteById($pdo, $siteId);
    if (!$site) {
        throw new Exception("Site not found");
    }
    
    // Stop SFTP container
    stopSFTPContainer($site);
    
    $stmt = $pdo->prepare("UPDATE sites SET sftp_enabled = 0 WHERE id = ?");
    $stmt->execute([$siteId]);
    
    return getSiteById($pdo, $siteId);
}

function deploySFTPContainer($site) {
    $containerName = $site['container_name'] . '_sftp';
    
    // Try to find the actual volume name by listing docker volumes
    $volumeSearchPattern = $site['container_name'];
    $result = executeDockerCommand("volume ls --format '{{.Name}}'");
    
    $volumeName = null;
    $useBindMount = false;
    
    if ($result['success'] && !empty($result['output'])) {
        $allVolumes = explode("\n", trim($result['output']));
        foreach ($allVolumes as $vol) {
            if (strpos($vol, $volumeSearchPattern) !== false && strpos($vol, '_data') !== false) {
                $volumeName = $vol;
                break;
            }
        }
    }
    
    // If no volume found, use bind mount to container's /var/www/html
    if (empty($volumeName)) {
        $useBindMount = true;
        // Create a directory for this site's files
        $bindPath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/html";
        if (!is_dir($bindPath)) {
            if (!mkdir($bindPath, 0755, true)) {
                throw new Exception("Failed to create SFTP directory: {$bindPath}");
            }
            // Set proper permissions - 755 instead of 777 for security
            chmod($bindPath, 0755);
            // Ensure correct web user ownership
            $uid = getContainerWebUid($site['container_name']);
            $gid = getContainerWebGid($site['container_name']);
            chown($bindPath, $uid);
            chgrp($bindPath, $gid);
        }
    }
    
    // Create SFTP docker-compose file
    $composePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/sftp-compose.yml";
    $composeContent = createSFTPDockerCompose($site, $containerName, $volumeName, $useBindMount);
    
    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($composePath, $composeContent);
    
    // Start SFTP container
    $result = executeDockerCompose($composePath, "up -d");
    if (!$result['success']) {
        throw new Exception("Failed to start SFTP container: " . $result['output']);
    }
    
    return true;
}

function stopSFTPContainer($site) {
    $composePath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/sftp-compose.yml";
    if (file_exists($composePath)) {
        executeDockerCompose($composePath, "down");
        unlink($composePath);
    }
}

function createSFTPDockerCompose($site, $containerName, $volumeName, $useBindMount = false) {
    $username = $site['sftp_username'];
    $password = $site['sftp_password'];
    $port = $site['sftp_port'];
    
    // Use container's web user UID/GID to ensure permissions match
    $puid = getContainerWebUid($site['container_name']);
    $pgid = getContainerWebGid($site['container_name']);
    
    // Determine volume mount
    if ($useBindMount) {
        $bindPath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/html";
        $volumeMount = "      - {$bindPath}:/config/files";
        $volumeSection = "";
    } else {
        $volumeMount = "      - {$volumeName}:/config/files:rw";
        $volumeSection = "\nvolumes:\n  {$volumeName}:\n    external: true\n";
    }
    
    // SECURITY: Bind to localhost only by default
    // Users should use SSH tunneling or configure firewall rules for remote access
    // Change "127.0.0.1" to "0.0.0.0" only if you have proper firewall rules
    $bindAddress = "127.0.0.1";
    
    return "services:
  {$containerName}:
    image: linuxserver/openssh-server:latest
    container_name: {$containerName}
    hostname: {$containerName}
    ports:
      - \"{$bindAddress}:{$port}:2222\"
    volumes:
{$volumeMount}
    environment:
      - PUID={$puid}
      - PGID={$pgid}
      - TZ=UTC
      - USER_NAME={$username}
      - USER_PASSWORD={$password}
      - PASSWORD_ACCESS=true
      - PUBLIC_KEY_DIR=/config/.ssh/authorized_keys
      - SUDO_ACCESS=false
      - LOG_STDOUT=true
    restart: unless-stopped
    networks:
      - wharftales_wharftales
    security_opt:
      - no-new-privileges:true
    read_only: false
    tmpfs:
      - /tmp
{$volumeSection}
networks:
  wharftales_wharftales:
    external: true";
}

// ============================================================================
// COMPOSE CONFIG DATABASE FUNCTIONS
// ============================================================================

/**
 * Get compose configuration from database
 */
function getComposeConfig($pdo, $siteId = null) {
    if ($siteId === null) {
        // Get main Traefik config
        $stmt = $pdo->prepare("SELECT * FROM compose_configs WHERE config_type = 'main' LIMIT 1");
        $stmt->execute();
    } else {
        // Get site-specific config
        $stmt = $pdo->prepare("SELECT * FROM compose_configs WHERE config_type = 'site' AND site_id = ? LIMIT 1");
        $stmt->execute([$siteId]);
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Save or update compose configuration
 */
function saveComposeConfig($pdo, $yaml, $userId, $siteId = null) {
    $configType = $siteId === null ? 'main' : 'site';
    
    // Check if config exists
    $existing = getComposeConfig($pdo, $siteId);
    
    if ($existing) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE compose_configs SET compose_yaml = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE id = ?");
        $stmt->execute([$yaml, $userId, $existing['id']]);
        return $existing['id'];
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO compose_configs (config_type, site_id, compose_yaml, updated_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$configType, $siteId, $yaml, $userId]);
        return $pdo->lastInsertId();
    }
}

/**
 * Generate docker-compose.yml file from database
 */
function generateComposeFile($pdo, $siteId = null) {
    $config = getComposeConfig($pdo, $siteId);
    
    if (!$config) {
        return null;
    }
    
    if ($siteId === null) {
        // Main Traefik config
        $outputPath = '/opt/wharftales/docker-compose.yml';
    } else {
        // Site-specific config
        $site = getSiteById($pdo, $siteId);
        if (!$site) {
            return null;
        }
        $outputPath = "/app/apps/{$site['type']}/sites/{$site['container_name']}/docker-compose.yml";
        
        // Ensure directory exists
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Write YAML to file
    $result = file_put_contents($outputPath, $config['compose_yaml']);
    
    if ($result === false) {
        error_log("Failed to write compose file to: $outputPath");
        error_log("Directory writable: " . (is_writable(dirname($outputPath)) ? 'yes' : 'no'));
        error_log("File exists: " . (file_exists($outputPath) ? 'yes' : 'no'));
        if (file_exists($outputPath)) {
            error_log("File writable: " . (is_writable($outputPath) ? 'yes' : 'no'));
        }
        throw new Exception("Failed to write compose file to: $outputPath. Check permissions.");
    }
    
    return $outputPath;
}

/**
 * Update a specific parameter in compose config (e.g., Let's Encrypt email)
 */
function updateComposeParameter($pdo, $paramKey, $paramValue, $userId, $siteId = null) {
    $config = getComposeConfig($pdo, $siteId);
    
    if (!$config) {
        // If no config exists, create initial config from docker-compose.yml
        // Try multiple possible paths
        $possiblePaths = [
            '/opt/wharftales/docker-compose.yml',  // Mounted path in container
            '../docker-compose.yml',                // Relative to gui folder
            '/var/www/html/../docker-compose.yml'   // Relative to web root
        ];
        
        $yaml = null;
        $foundPath = null;
        foreach ($possiblePaths as $composeFile) {
            if (file_exists($composeFile)) {
                $yaml = file_get_contents($composeFile);
                $foundPath = $composeFile;
                error_log("Found compose file at: $foundPath");
                break;
            }
        }
        
        if ($yaml) {
            // Save initial config
            error_log("Saving initial config from: $foundPath");
            saveComposeConfig($pdo, $yaml, $userId, $siteId);
            $config = getComposeConfig($pdo, $siteId);
        }
        
        if (!$config) {
            $checkedPaths = [];
            foreach ($possiblePaths as $path) {
                $checkedPaths[] = $path . ' (' . (file_exists($path) ? 'exists' : 'not found') . ')';
            }
            throw new Exception("Compose configuration not found and could not be created. Checked paths: " . implode(', ', $checkedPaths));
        }
    }
    
    $yaml = $config['compose_yaml'];
    
    // Update specific parameters based on key
    switch ($paramKey) {
        case 'letsencrypt_email':
            // Validate email doesn't contain forbidden domains
            if (preg_match('/@(example\.(com|net|org)|test\.com)$/i', $paramValue)) {
                throw new Exception("Email domain is forbidden by Let's Encrypt. Please use a real email address.");
            }
            
            $yaml = preg_replace('/acme\.email=[^\s"]+/', 'acme.email=' . $paramValue, $yaml);
            
            // Clear acme.json when email changes (it caches the old email)
            $acmeFile = '/app/ssl/acme.json';
            if (file_exists($acmeFile)) {
                // Backup old acme.json
                $backupFile = $acmeFile . '.backup.' . date('YmdHis');
                @copy($acmeFile, $backupFile);
                
                // Create fresh acme.json with empty template
                $freshAcme = json_encode([
                    'letsencrypt' => [
                        'Account' => [
                            'Email' => '',
                            'Registration' => null,
                            'PrivateKey' => null,
                            'KeyType' => ''
                        ],
                        'Certificates' => null
                    ]
                ], JSON_PRETTY_PRINT);
                
                file_put_contents($acmeFile, $freshAcme);
                chmod($acmeFile, 0600);
            }
            break;
        case 'dashboard_domain':
            // This would be handled by updateDashboardTraefikConfig
            break;
        default:
            throw new Exception("Unknown parameter: $paramKey");
    }
    
    // Save updated YAML
    saveComposeConfig($pdo, $yaml, $userId, $siteId);
    
    // Regenerate file
    return generateComposeFile($pdo, $siteId);
}

/**
 * Delete compose config (when site is deleted)
 */
function deleteComposeConfig($pdo, $siteId) {
    $stmt = $pdo->prepare("DELETE FROM compose_configs WHERE config_type = 'site' AND site_id = ?");
    return $stmt->execute([$siteId]);
}

/**
 * Check if a certificate has been issued for a domain (from database)
 */
function hasCertificate($domain) {
    global $db;
    
    if (!$db) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("SELECT ssl_cert_issued FROM sites WHERE domain = ?");
        $stmt->execute([$domain]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['ssl_cert_issued'] == 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mark certificate as issued for a site
 */
function markCertificateIssued($db, $siteId) {
    try {
        $stmt = $db->prepare("UPDATE sites SET ssl_cert_issued = 1, ssl_cert_issued_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$siteId]);
    } catch (Exception $e) {
        error_log("Failed to mark certificate as issued: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark certificate as removed for a site
 */
function markCertificateRemoved($db, $siteId) {
    try {
        $stmt = $db->prepare("UPDATE sites SET ssl_cert_issued = 0, ssl_cert_issued_at = NULL WHERE id = ?");
        return $stmt->execute([$siteId]);
    } catch (Exception $e) {
        error_log("Failed to mark certificate as removed: " . $e->getMessage());
        return false;
    }
}

/**
 * Normalize version string for comparison
 * Strips alpha, beta, rc, dev suffixes
 */
function normalizeVersion($version) {
    // Remove common suffixes like "alpha", "beta", "rc", "dev"
    $normalized = preg_replace('/\s*(alpha|beta|rc|dev).*$/i', '', trim($version));
    return $normalized;
}

/**
 * Get current WharfTales version
 */
function getCurrentVersion() {
    if (file_exists('/var/www/html/../versions.json')) {
        $content = file_get_contents('/var/www/html/../versions.json');
        $json = json_decode($content, true);
        if (isset($json['wharftales']['latest'])) {
            return $json['wharftales']['latest'];
        }
    }

    $versionFile = '/var/www/html/../VERSION';
    if (file_exists($versionFile)) {
        return trim(file_get_contents($versionFile));
    }
    return 'unknown';
}

/**
 * Get the web user for a container (www or www-data)
 * 
 * @param string $containerName Docker container name
 * @return string User name (www or www-data)
 */
function getContainerWebUser($containerName) {
    // Check if 'www' user exists (Laravel containers)
    exec("docker exec " . escapeshellarg($containerName) . " id -u www > /dev/null 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        return 'www';
    }
    
    // Default to www-data
    return 'www-data';
}

/**
 * Get the web user UID for a container
 * 
 * @param string $containerName Docker container name
 * @return int UID
 */
function getContainerWebUid($containerName) {
    $user = getContainerWebUser($containerName);
    exec("docker exec " . escapeshellarg($containerName) . " id -u " . escapeshellarg($user) . " 2>/dev/null", $output, $returnCode);
    
    if ($returnCode === 0 && isset($output[0]) && is_numeric(trim($output[0]))) {
        return intval(trim($output[0]));
    }
    
    // Default to 33 (www-data)
    return 33;
}

/**
 * Get the web user GID for a container
 * 
 * @param string $containerName Docker container name
 * @return int GID
 */
function getContainerWebGid($containerName) {
    $user = getContainerWebUser($containerName);
    exec("docker exec " . escapeshellarg($containerName) . " id -g " . escapeshellarg($user) . " 2>/dev/null", $output, $returnCode);
    
    if ($returnCode === 0 && isset($output[0]) && is_numeric(trim($output[0]))) {
        return intval(trim($output[0]));
    }
    
    // Default to 33 (www-data)
    return 33;
}

/**
 * Check for WharfTales updates
 * Returns array with update information
 */
function checkForUpdates($forceCheck = false) {
    global $db;
    
    if (!$db) {
        return ['error' => 'Database not initialized'];
    }
    
    // Get update check settings
    $updateCheckEnabled = getSetting($db, 'update_check_enabled', '1');
    if ($updateCheckEnabled !== '1' && !$forceCheck) {
        return ['update_available' => false, 'reason' => 'Update check disabled'];
    }
    
    // Check last update check time to avoid too frequent checks
    $lastCheck = getSetting($db, 'last_update_check', '0');
    $checkFrequency = getSetting($db, 'update_check_frequency', '86400'); // Default: 24 hours
    
    if (!$forceCheck && (time() - intval($lastCheck)) < intval($checkFrequency)) {
        // Return cached result if available
        $cachedResult = getSetting($db, 'cached_update_info', null);
        if ($cachedResult) {
            return json_decode($cachedResult, true);
        }
    }
    
    $currentVersion = getCurrentVersion();
    $versionsUrl = getSetting($db, 'versions_url', 'https://raw.githubusercontent.com/giodc/wharftales/refs/heads/master/versions.json');
    
    try {
        $response = false;
        
        // Check if URL is a local file path
        if (strpos($versionsUrl, 'http') !== 0) {
            // Local file
            if (file_exists($versionsUrl)) {
                $response = file_get_contents($versionsUrl);
            }
        } else {
            // Remote URL - Set timeout for the request
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'WharfTales/' . $currentVersion,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);
            
            $response = @file_get_contents($versionsUrl, false, $context);
            
            // Check HTTP response code
            if (isset($http_response_header)) {
                $statusLine = $http_response_header[0];
                if (strpos($statusLine, '404') !== false) {
                    throw new Exception('versions.json not found (404). Please commit and push the file to your repository.');
                } elseif (strpos($statusLine, '200') === false) {
                    throw new Exception('Failed to fetch versions.json: ' . $statusLine);
                }
            }
        }
        
        if ($response === false) {
            throw new Exception('Failed to fetch versions.json from: ' . $versionsUrl);
        }
        
        $versions = json_decode($response, true);
        
        if (!isset($versions['wharftales']['latest'])) {
            throw new Exception('Invalid versions.json format - missing wharftales.latest field');
        }
        
        $latestVersion = $versions['wharftales']['latest'];
        $minSupported = $versions['wharftales']['min_supported'] ?? '0.0.1';
        
        // Update last check time
        setSetting($db, 'last_update_check', (string)time());
        
        // Normalize versions for comparison (strip alpha, beta, rc, dev suffixes)
        $normalizedCurrent = normalizeVersion($currentVersion);
        $normalizedLatest = normalizeVersion($latestVersion);
        $normalizedMinSupported = normalizeVersion($minSupported);
        
        // Compare versions using normalized versions
        $updateAvailable = version_compare($normalizedLatest, $normalizedCurrent, '>');
        $isSupported = version_compare($normalizedCurrent, $normalizedMinSupported, '>=');
        
        $result = [
            'update_available' => $updateAvailable,
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'min_supported_version' => $minSupported,
            'is_supported' => $isSupported,
            'changelog_url' => $versions['wharftales']['changelog_url'] ?? '',
            'release_notes' => $versions['wharftales']['release_notes'] ?? '',
            'released_at' => $versions['wharftales']['released_at'] ?? '',
            'update_url' => $versions['wharftales']['update_url'] ?? '',
            'checked_at' => date('Y-m-d H:i:s')
        ];
        
        // Cache the result
        setSetting($db, 'cached_update_info', json_encode($result));
        
        // Store update notification if available
        if ($updateAvailable) {
            setSetting($db, 'update_notification', '1');
        } else {
            setSetting($db, 'update_notification', '0');
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log('Update check failed: ' . $e->getMessage());
        
        // Update last check time even on failure to avoid hammering the server
        setSetting($db, 'last_update_check', (string)time());
        
        return [
            'update_available' => false,
            'error' => $e->getMessage(),
            'current_version' => $currentVersion
        ];
    }
}

/**
 * Trigger update process
 * Returns array with status information
 */
function triggerUpdate($skipBackup = false) {
    global $db;
    
    if (!$db) {
        return ['success' => false, 'error' => 'Database not initialized'];
    }
    
    // Check if update is already in progress
    $updateInProgress = getSetting($db, 'update_in_progress', '0');
    if ($updateInProgress === '1') {
        return ['success' => false, 'error' => 'Update already in progress'];
    }
    
    // Mark update as in progress
    setSetting($db, 'update_in_progress', '1');
    setSetting($db, 'update_started_at', date('Y-m-d H:i:s'));
    
    // Expected log file path
    $logFile = '/opt/wharftales/logs/upgrade-' . date('Y-m-d-H-i-s') . '.log';
    setSetting($db, 'last_update_log', $logFile);
    
    // Use the wrapper script that uses docker commands (Coolify-style)
    // This script uses the mounted docker socket to run commands
    $upgradeScript = '/var/www/html/trigger-host-upgrade.sh';
    
    if (!file_exists($upgradeScript)) {
        setSetting($db, 'update_in_progress', '0');
        error_log("Upgrade script not found at: $upgradeScript");
        return ['success' => false, 'error' => 'Upgrade script not found. Please ensure the file exists and is executable.'];
    }
    
    // Check if script is executable
    if (!is_executable($upgradeScript)) {
        setSetting($db, 'update_in_progress', '0');
        error_log("Upgrade script is not executable: $upgradeScript");
        return ['success' => false, 'error' => 'Upgrade script is not executable. Run: chmod +x /opt/wharftales/gui/trigger-host-upgrade.sh'];
    }
    
    // Execute upgrade script in background
    $command = 'bash ' . escapeshellarg($upgradeScript) . ' > /dev/null 2>&1 &';
    exec($command, $output, $returnCode);
    
    error_log("Update triggered using wrapper script. Log will be at: $logFile");
    
    return [
        'success' => true,
        'message' => 'Update started in background using Docker commands',
        'log_file' => $logFile
    ];
}

/**
 * Get update status
 */
function getUpdateStatus() {
    global $db;
    
    if (!$db) {
        return ['in_progress' => false];
    }
    
    $inProgress = getSetting($db, 'update_in_progress', '0');
    $startedAt = getSetting($db, 'update_started_at', '');
    $logFile = getSetting($db, 'last_update_log', '');
    
    // Check if update process is still running
    if ($inProgress === '1') {
        // If started more than 10 minutes ago, consider it failed
        if ($startedAt && (time() - strtotime($startedAt)) > 600) {
            setSetting($db, 'update_in_progress', '0');
            $inProgress = '0';
        }
    }
    
    return [
        'in_progress' => $inProgress === '1',
        'started_at' => $startedAt,
        'log_file' => $logFile
    ];
}
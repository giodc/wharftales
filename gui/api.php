<?php
// CRITICAL: Must be first - prevent any output before JSON
error_reporting(0);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Start output buffering immediately
ob_start();

// Set JSON header first
header('Content-Type: application/json');

// Set error handler to catch all errors and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    while (ob_get_level() > 1) { ob_end_clean(); }
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $errstr . ' in ' . $errfile . ' on line ' . $errline
    ]);
    while (ob_get_level() > 0) { ob_end_flush(); }
    exit;
});

// Set exception handler
set_exception_handler(function($exception) {
    while (ob_get_level() > 1) { ob_end_clean(); }
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $exception->getMessage()
    ]);
    while (ob_get_level() > 0) { ob_end_flush(); }
    exit;
});

/**
 * Get the correct apps base path (handles both /app/apps/ and /opt/wharftales/apps/)
 */
function getAppsBasePath() {
    if (file_exists('/app/apps/')) {
        return '/app/apps';
    }
    return '/opt/wharftales/apps';
}

try {
    require_once 'includes/functions.php';
    require_once 'includes/auth.php';
    require_once 'includes/encryption.php';
    require_once 'includes/github-deploy.php';
    require_once 'includes/laravel-helpers.php';
    require_once 'includes/ssl-config.php';
} catch (Throwable $e) {
    while (ob_get_level() > 1) { ob_end_clean(); }
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load dependencies: ' . $e->getMessage()]);
    while (ob_get_level() > 0) { ob_end_flush(); }
    exit;
}

// Require authentication for all API calls
if (!isLoggedIn()) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

try {
    $db = initDatabase();
} catch (Throwable $e) {
    while (ob_get_level() > 1) { ob_end_clean(); }
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database initialization failed: ' . $e->getMessage()]);
    while (ob_get_level() > 0) { ob_end_flush(); }
    exit;
}

$action = $_GET["action"] ?? "";

// Clean ALL output buffer levels before processing any action to prevent JSON corruption
while (ob_get_level() > 1) {
    ob_end_clean();
}
ob_clean();

// Helper function to output JSON safely
function outputJSON($data, $statusCode = 200) {
    while (ob_get_level() > 1) {
        ob_end_clean();
    }
    ob_clean();
    if ($statusCode !== 200) {
        http_response_code($statusCode);
    }
    echo json_encode($data);
    ob_end_flush();
    exit;
}

// Wrap entire switch in try-catch to ensure JSON responses
try {
    switch ($action) {
        case "create_site":
            createSiteHandler($db);
            break;
        
    case "get_site":
        getSiteData($db, $_GET["id"]);
        break;

    case "update_site":
        updateSiteData($db);
        break;
    
    case "update_site_ssl":
        updateSiteSSLConfig($db);
        break;

    case "check_github_updates":
        checkGitHubUpdatesHandler($db, $_GET["id"]);
        break;
    
    case "pull_from_github":
        pullFromGitHubHandler($db, $_GET["id"]);
        break;
    
    case "force_pull_from_github":
        forcePullFromGitHubHandler($db, $_GET["id"]);
        break;
    
    case "build_laravel":
        buildLaravelHandler($db, $_GET["id"]);
        break;
    
    case "fix_laravel_permissions":
        fixLaravelPermissionsHandler($db);
        break;

    case "delete_site":
        deleteSiteById($db, $_GET["id"]);
        break;
        
    case "site_status":
        getSiteStatus($db, $_GET["id"]);
        break;
    
    case "restart_container":
        restartContainer($db, $_GET["id"]);
        break;
    
    case "start_container":
        startContainer($db, $_GET["id"]);
        break;
    
    case "stop_container":
        stopContainer($db, $_GET["id"]);
        break;
    
    case "change_password":
        changePasswordHandler($db);
        break;
    
    case "check_updates":
        checkForUpdatesHandler($db);
        break;
    
    case "perform_update":
        performSystemUpdate();
        break;
    
    case "get_update_info":
        getUpdateInformation();
        break;
    
    case "get_update_logs":
        getUpdateLogsHandler($db);
        break;
    
    case "list_files":
        listContainerFiles($db, $_GET["id"], $_GET["path"] ?? '/var/www/html');
        break;
    
    case "download_file":
        downloadContainerFile($db, $_GET["id"], $_GET["path"]);
        break;
    
    case "delete_file":
        deleteContainerFile($db);
        break;
    
    case "create_file":
        createContainerFile($db);
        break;
    
    case "create_folder":
        createContainerFolder($db);
        break;
    
    case "upload_file":
        uploadContainerFile($db);
        break;
    
    case "read_file":
        readContainerFile($db);
        break;
    
    case "save_file":
        saveContainerFile($db);
        break;
    
    case "get_env_vars":
        getEnvironmentVariables($db, $_GET["id"]);
        break;
    
    case "save_env_vars":
        saveEnvironmentVariables($db);
        break;
    
    case "get_logs":
        getSiteContainerLogs($db, $_GET["id"]);
        break;
    
    case "get_stats":
        getContainerStats($db, $_GET["id"]);
        break;
    
    case "enable_sftp":
        enableSFTPHandler($db, $_GET["id"]);
        break;
    
    case "disable_sftp":
        disableSFTPHandler($db, $_GET["id"]);
        break;
    
    case "regenerate_sftp_password":
        regenerateSFTPPassword($db, $_GET["id"]);
        break;
    
    case "get_dashboard_stats":
        getDashboardStats($db, $_GET["id"]);
        break;
    
    case "restart_traefik":
        restartTraefik();
        break;
    
    case "restart_webgui":
        restartWebGui();
        break;
    
    case "test_telemetry_ping":
        testTelemetryPingHandler();
        break;
    
    case "execute_docker_command":
        executeDockerCommandAPI();
        break;
    
    case "execute_laravel_command":
        executeLaravelCommandAPI($db);
        break;

    case "execute_shell_command":
        executeShellCommandAPI($db);
        break;
    
    case "reload_supervisor":
        reloadSupervisorAPI($db);
        break;
    
    case "reload_phpfpm":
        reloadPhpFpmAPI($db);
        break;
        
    case "install_nodejs":
        installNodeJsHandler($db, $_GET["id"]);
        break;
    
    case "get_container_logs":
        getContainerLogs();
        break;
    
    case "export_database":
        exportDatabase($db);
        break;
    
    case "get_database_stats":
        getDatabaseStats($db);
        break;
    
    case "get_site_containers":
        getSiteContainers($db, $_GET["id"]);
        break;
    
    case "generate_db_token":
        generateDbToken($db);
        break;
    
    // User Management API endpoints
    case "create_user":
        createUserHandler();
        break;
    
    case "get_user":
        getUserHandler();
        break;
    
    case "update_user":
        updateUserHandler();
        break;
    
    case "delete_user":
        deleteUserHandler();
        break;
    
    case "grant_site_permission":
        grantSitePermissionHandler();
        break;
    
    case "revoke_site_permission":
        revokeSitePermissionHandler();
        break;
    
    case "get_user_permissions":
        getUserPermissionsHandler($db);
        break;
    
    // 2FA API endpoints
    case "setup_2fa":
        setup2FAHandler();
        break;
    
    case "enable_2fa":
        enable2FAHandler();
        break;
    
    case "disable_2fa":
        disable2FAHandler();
        break;
    
    // Redis Management
    case "enable_redis":
        enableRedisHandler($db);
        break;
    
    case "disable_redis":
        disableRedisHandler($db);
        break;
    
    case "flush_redis":
        flushRedisHandler($db);
        break;
    
    case "restart_redis":
        restartRedisHandler($db);
        break;
    
    case "update_setting":
        updateSettingHandler($db);
        break;
    
    case "rebuild_container":
        rebuildContainerHandler($db);
        break;
    
    case "verify_2fa_setup":
        verify2FASetupHandler();
        break;
    
    case "change_php_version":
        changePHPVersionHandler($db);
        break;
    
    case "trigger_update":
        triggerUpdateHandler($db);
        break;
    
    case "check_update_status":
        checkUpdateStatusHandler($db);
        break;
    
    case "reset_update_lock":
        resetUpdateLockHandler($db);
        break;
    
    case "update_custom_database":
        updateCustomDatabaseHandler($db);
        break;
    
    case "toggle_mariadb_external_access":
        toggleMariaDBExternalAccessHandler($db);
        break;
        
    default:
        while (ob_get_level() > 1) { ob_end_clean(); }
        ob_clean();
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Invalid action: " . $action]);
    }
} catch (Throwable $e) {
    while (ob_get_level() > 1) { ob_end_clean(); }
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

function createSiteHandler($db) {
    // Increase time limit for site creation (docker pull/build)
    set_time_limit(0); ini_set("memory_limit", "512M");
    ini_set('memory_limit', '512M');
    
    try {
        // Check if user has permission to create sites
        if (!canCreateSites($_SESSION['user_id'])) {
            http_response_code(403);
            throw new Exception("You don't have permission to create sites. Contact an administrator.");
        }
        
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            throw new Exception("Invalid JSON data");
        }

        $data = $input;

        // Validate required fields (domain not required for MariaDB)
        if (empty($data["name"]) || empty($data["type"])) {
            throw new Exception("Missing required fields");
        }
        
        // Domain is required for web apps but not for database services
        if ($data["type"] !== "mariadb" && empty($data["domain"])) {
            throw new Exception("Domain is required for web applications");
        }

        // Generate site configuration
        $containerName = $data["type"] . "_" . preg_replace("/[^a-z0-9]/", "", strtolower($data["name"])) . "_" . time();

        // Determine final domain (not needed for MariaDB)
        $domain = "";
        if ($data["type"] !== "mariadb") {
            $domain = $data["domain"];
            if (isset($data["domain_suffix"]) && $data["domain_suffix"] !== "custom") {
                $domain = $data["domain"] . $data["domain_suffix"];
            } else if (isset($data["custom_domain"])) {
                $domain = $data["custom_domain"];
            }
        } else {
            // For MariaDB, use container name as "domain" for internal reference
            $domain = $containerName;
        }

        // Prepare SSL configuration
        $sslConfig = null;
        if ($data["ssl"]) {
            $sslConfig = [
                "challenge" => $data["ssl_challenge"] ?? "http",
                "provider" => $data["dns_provider"] ?? null,
                "credentials" => []
            ];
            
            // Store DNS provider credentials if using DNS challenge
            if ($sslConfig["challenge"] === "dns" && !empty($sslConfig["provider"])) {
                switch ($sslConfig["provider"]) {
                    case "cloudflare":
                        $sslConfig["credentials"] = [
                            "cf_api_token" => $data["cf_api_token"] ?? ""
                        ];
                        break;
                    case "route53":
                        $sslConfig["credentials"] = [
                            "aws_access_key" => $data["aws_access_key"] ?? "",
                            "aws_secret_key" => $data["aws_secret_key"] ?? "",
                            "aws_region" => $data["aws_region"] ?? "us-east-1"
                        ];
                        break;
                    case "digitalocean":
                        $sslConfig["credentials"] = [
                            "do_auth_token" => $data["do_auth_token"] ?? ""
                        ];
                        break;
                }
                
                // Update Traefik configuration for DNS challenge
                $updateResult = updateTraefikForDNSChallenge($sslConfig["provider"], $sslConfig["credentials"]);
                if (!$updateResult) {
                    error_log("Warning: Failed to update Traefik for DNS challenge");
                }
            }
        }
        
        // Determine database type based on site type
        $dbType = 'shared';
        if ($data["type"] === 'wordpress') {
            $dbType = $data["wp_db_type"] ?? 'shared';
        } elseif ($data["type"] === 'php') {
            $dbType = $data["php_db_type"] ?? 'none';
        } elseif ($data["type"] === 'laravel') {
            $dbType = $data["laravel_db_type"] ?? 'mysql';
        }
        
        // Extract GitHub deployment info based on site type
        $githubRepo = null;
        $githubBranch = 'main';
        $githubToken = null;
        
        if ($data["type"] === 'php' && !empty($data["php_github_repo"])) {
            $githubRepo = $data["php_github_repo"];
            $githubBranch = $data["php_github_branch"] ?? 'main';
            $githubToken = $data["php_github_token"] ?? null;
        } elseif ($data["type"] === 'laravel' && !empty($data["laravel_github_repo"])) {
            $githubRepo = $data["laravel_github_repo"];
            $githubBranch = $data["laravel_github_branch"] ?? 'main';
            $githubToken = $data["laravel_github_token"] ?? null;
        }
        
        $siteConfig = [
            "name" => $data["name"],
            "type" => $data["type"],
            "domain" => $domain,
            "ssl" => $data["ssl"] ?? false,
            "ssl_config" => $sslConfig,
            "container_name" => $containerName,
            "config" => $data,
            "db_type" => $dbType,
            "include_www" => $data["include_www"] ?? false,
            "github_repo" => $githubRepo,
            "github_branch" => $githubBranch,
            "github_token" => $githubToken
        ];

        // Create site record
        $createResult = createSite($db, $siteConfig);
        if (!$createResult) {
            throw new Exception("Failed to create site record");
        }

        // Get the site ID
        $siteId = $db->lastInsertId();
        
        // Get the site record
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Failed to retrieve created site");
        }
        
        // Ensure container_name is set in the site array (bypass database issues)
        $site['container_name'] = $containerName;
        
        // Also update the database with the container_name
        $stmt = $db->prepare("UPDATE sites SET container_name = ? WHERE id = ?");
        $stmt->execute([$containerName, $siteId]);
        
        // Verify the container_name is set - this should always pass now
        if (empty($site['container_name'])) {
            throw new Exception("IMPOSSIBLE: Container name is empty after setting it to: [$containerName]");
        }
        
        
        // Deploy the application based on type
        $deploymentSuccess = false;
        $deploymentError = null;
        
        try {
            switch ($data["type"]) {
                case "wordpress":
                    deployWordPress($db, $site, $data);
                    break;
                case "php":
                    deployPHP($site, $data, $db);
                    break;
                case "laravel":
                    deployLaravel($site, $data, $db);
                    break;
                case "mariadb":
                    deployMariaDB($db, $site, $data);
                    break;
            }
            $deploymentSuccess = true;
        } catch (Exception $deployError) {
            $deploymentError = $deployError->getMessage();
            // Don't throw, we'll report it but keep the site record
        }

        // Traefik will automatically discover the container via labels
        // SSL certificates are automatically requested by Traefik when the container starts
        // No manual configuration needed!

        if ($deploymentSuccess) {
            updateSiteStatus($db, $siteId, "running");
            echo json_encode([
                "success" => true,
                "message" => "Site created and deployed successfully",
                "site" => $site
            ]);
        } else {
            updateSiteStatus($db, $siteId, "stopped");
            echo json_encode([
                "success" => true,
                "warning" => true,
                "message" => "Site created but deployment failed. You can try redeploying from the dashboard.",
                "error_details" => $deploymentError,
                "site" => $site
            ]);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function deployPHP($site, $config, $db) {
    // Ensure container_name is set
    if (empty($site['container_name'])) {
        throw new Exception("CRITICAL: deployPHP received empty container_name for site: " . $site["name"]);
    }
    
    // Create PHP application container
    $basePath = getAppsBasePath();
    $composePath = "$basePath/php/sites/{$site['container_name']}/docker-compose.yml";
    $generatedPassword = null;
    $phpCompose = createPHPDockerCompose($site, $config, $generatedPassword);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($composePath, $phpCompose);
    
    // Save to database
    saveComposeConfig($db, $phpCompose, $site['owner_id'] ?? 1, $site['id']);

    // Save database password if generated
    if ($generatedPassword) {
        $stmt = $db->prepare("UPDATE sites SET db_password = ? WHERE id = ?");
        $stmt->execute([$generatedPassword, $site['id']]);
    }

    // Start the container first to create the volume
    $result = executeDockerCompose($composePath, "up -d");
    if (!$result["success"]) {
        throw new Exception("Failed to start PHP application: " . $result["output"]);
    }
    
    // Add default index.php to the Docker volume
    $containerName = $site['container_name'];
    
    // Wait a moment for container to be fully ready
    sleep(2);
    
    // Check if GitHub deployment is configured
    if (!empty($site['github_repo'])) {
        // Deploy from GitHub
        $deployResult = deployFromGitHub($site, $containerName);
        
        if ($deployResult['success']) {
            // Update site with commit hash
            if (!empty($deployResult['commit_hash'])) {
                $db->prepare("UPDATE sites SET github_last_commit = ?, github_last_pull = CURRENT_TIMESTAMP WHERE id = ?")
                   ->execute([$deployResult['commit_hash'], $site['id']]);
            }
            
            // Run composer install if composer.json exists
            runComposerInstall($containerName);
        } else {
            throw new Exception("GitHub deployment failed: " . $deployResult['message']);
        }
    } else {
        // Manual deployment - create welcome page
        // Check if index.php already exists
        $checkCmd = "docker exec {$containerName} test -f /var/www/html/index.php 2>/dev/null";
        exec($checkCmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // File doesn't exist, create it from template
            $templatePath = __DIR__ . '/templates/php-welcome.php';
            $template = file_get_contents($templatePath);
            
            // Replace placeholders
            $siteName = htmlspecialchars($site['name'], ENT_QUOTES);
            $content = str_replace('{{SITE_NAME}}', $siteName, $template);
            
            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'php_welcome_');
            file_put_contents($tempFile, $content);
            
            // Copy to container
            exec("docker cp {$tempFile} {$containerName}:/var/www/html/index.php");
            
            // Set proper permissions
            // Determine correct web user
            $webUser = getContainerWebUser($containerName);
            
            // PHP containers run as www-data (or www), but we still need root to chown after docker cp
            exec("docker exec -u root {$containerName} chown {$webUser}:{$webUser} /var/www/html/index.php");
            exec("docker exec -u root {$containerName} chmod 644 /var/www/html/index.php");
            
            // Clean up temp file
            unlink($tempFile);
        }
    }
    
    // Create Redis container if requested
    if (isset($config['php_redis']) && $config['php_redis']) {
        createRedisContainer($site, $db);
    }
}

/**
 * Generate Traefik host rule with optional www subdomain
 * @param string $domain The primary domain
 * @param bool $includeWww Whether to include www subdomain
 * @return string Traefik host rule
 */
function generateHostRule($domain, $includeWww = false) {
    if ($includeWww) {
        return "Host(`{$domain}`) || Host(`www.{$domain}`)";
    }
    return "Host(`{$domain}`)";
}

function createPHPDockerCompose($site, $config, &$generatedPassword = null) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    $phpVersion = $site["php_version"] ?? '8.3';
    $includeWww = $site["include_www"] ?? 0;
    
    // Ensure container name is not empty
    if (empty($containerName)) {
        $containerName = "php_" . preg_replace("/[^a-z0-9]/", "", strtolower($site["name"])) . "_" . time();
    }
    
    // Final safety check - if still empty, use a default
    if (empty($containerName)) {
        $containerName = "php_app_" . time();
    }
    
    // Check database type
    $dbType = $config['php_db_type'] ?? 'none';
    $useDedicatedDb = in_array($dbType, ['mysql', 'postgresql']);
    
    // Generate random database password
    $dbPassword = bin2hex(random_bytes(16));
    $generatedPassword = $dbPassword;
    
    // Check if pre-built image exists
    $imageName = "wharftales/php:{$phpVersion}-apache";
    exec("docker image inspect {$imageName} 2>/dev/null", $output, $imageExists);
    
    $appsPath = getAppsBasePath();
    
    $compose = "version: '3.8'
services:
  {$containerName}:";
    
    if ($imageExists === 0) {
        // Use pre-built image for faster deployment
        $compose .= "
    image: {$imageName}";
    } else {
        // Fallback to building from Dockerfile
        $compose .= "
    build:
      context: {$appsPath}/php
      dockerfile: Dockerfile
      args:
        PHP_VERSION: {$phpVersion}";
    }
    
    $compose .= "
    container_name: {$containerName}
    volumes:
      - {$containerName}_data:/var/www/html
    environment:";
    
    if ($useDedicatedDb) {
        // Dedicated database
        $compose .= "
      - DB_HOST={$containerName}_db
      - DB_DATABASE=appdb
      - DB_USERNAME=appuser
      - DB_PASSWORD={$dbPassword}";
    } else {
        // No database
        $compose .= "
      - DB_HOST=
      - DB_DATABASE=
      - DB_USERNAME=
      - DB_PASSWORD=";
    }
    
    $hostRule = generateHostRule($domain, $includeWww);
    $compose .= "
    labels:
      - traefik.enable=true
      - traefik.http.routers.{$containerName}.rule={$hostRule}
      - traefik.http.routers.{$containerName}.entrypoints=web
      - traefik.http.services.{$containerName}.loadbalancer.server.port=8080";
    
    // Add SSL labels if SSL is enabled
    if ($site['ssl']) {
        // Determine certificate resolver based on SSL config
        $certResolver = 'letsencrypt'; // Default to HTTP challenge
        if (!empty($site['ssl_config'])) {
            $sslConfig = is_string($site['ssl_config']) ? json_decode($site['ssl_config'], true) : $site['ssl_config'];
            if (isset($sslConfig['challenge']) && $sslConfig['challenge'] === 'dns') {
                $certResolver = 'letsencrypt-dns'; // Use DNS challenge resolver
            }
        }
        
        $compose .= "
      - traefik.http.routers.{$containerName}-secure.rule={$hostRule}
      - traefik.http.routers.{$containerName}-secure.entrypoints=websecure
      - traefik.http.routers.{$containerName}-secure.tls=true
      - traefik.http.routers.{$containerName}-secure.tls.certresolver={$certResolver}
      - traefik.http.routers.{$containerName}.middlewares=redirect-to-https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.permanent=true";
    }
    
    $compose .= "
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
    
    // Add database service if needed
    if ($useDedicatedDb) {
        if ($dbType === 'mysql') {
            $compose .= "
  {$containerName}_db:
    image: mariadb:latest
    container_name: {$containerName}_db
    environment:
      - MYSQL_ROOT_PASSWORD={$dbPassword}
      - MYSQL_DATABASE=appdb
      - MYSQL_USER=appuser
      - MYSQL_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/mysql
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
        } elseif ($dbType === 'postgresql') {
            $compose .= "
  {$containerName}_db:
    image: postgres:15
    container_name: {$containerName}_db
    environment:
      - POSTGRES_DB=appdb
      - POSTGRES_USER=appuser
      - POSTGRES_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/postgresql/data
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
        }
    }
    
    $compose .= "
volumes:
  {$containerName}_data:";
    
    if ($useDedicatedDb) {
        $compose .= "
  {$containerName}_db_data:";
    }
    
    $compose .= "

networks:
  wharftales_wharftales:
    external: true";
    
    return $compose;
}

function deployLaravel($site, $config, $db) {
    // Create Laravel application container (use Apache HTTP to avoid FastCGI 502)
    $basePath = getAppsBasePath();
    $composePath = "$basePath/laravel/sites/{$site['container_name']}/docker-compose.yml";
    $generatedPassword = null;
    $laravelCompose = createLaravelDockerCompose($site, $config, $generatedPassword);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($composePath, $laravelCompose);
    
    // Save to database
    saveComposeConfig($db, $laravelCompose, $site['owner_id'] ?? 1, $site['id']);

    // Save database password if generated
    if ($generatedPassword) {
        $stmt = $db->prepare("UPDATE sites SET db_password = ? WHERE id = ?");
        $stmt->execute([$generatedPassword, $site['id']]);
    }

    // Start the container first to create the volume
    $result = executeDockerCompose($composePath, "up -d");
    if (!$result["success"]) {
        throw new Exception("Failed to start Laravel application: " . $result["output"]);
    }
    
    // Add default index.php to the Docker volume
    $containerName = $site['container_name'];
    
    // Wait a moment for container to be fully ready
    sleep(2);
    
    // Check if index.php already exists in public directory (Laravel standard)
    $checkCmd = "docker exec {$containerName} test -f /var/www/html/public/index.php 2>/dev/null";
    exec($checkCmd, $output, $returnCode);
    
    if ($returnCode !== 0) {
        // File doesn't exist, create it from template
        $templatePath = __DIR__ . '/templates/laravel-welcome.php';
        $template = file_get_contents($templatePath);
        
        // Replace placeholders
        $siteName = htmlspecialchars($site['name'], ENT_QUOTES);
        $content = str_replace('{{SITE_NAME}}', $siteName, $template);
        
        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'laravel_welcome_');
        file_put_contents($tempFile, $content);
        
        // Create public directory if it doesn't exist
        exec("docker exec -u root {$containerName} mkdir -p /var/www/html/public");
        
        // Copy to container (Laravel's public directory)
        exec("docker cp {$tempFile} {$containerName}:/var/www/html/public/index.php");
        
        // Set proper permissions (Laravel uses www:www user, not www-data)
        // Determine correct web user
        $webUser = getContainerWebUser($containerName);
        
        // Must run as root to change ownership
        exec("docker exec -u root {$containerName} chown -R {$webUser}:{$webUser} /var/www/html/public");
        exec("docker exec -u root {$containerName} chmod 644 /var/www/html/public/index.php");
        
        // Clean up temp file
        unlink($tempFile);
        
    }
    
    // Create Redis container if requested
    if (isset($config['laravel_redis']) && $config['laravel_redis']) {
        createRedisContainer($site, $db);
    }
}

function deployMariaDB($db, $site, $config) {
    // Ensure container_name is set
    if (empty($site['container_name'])) {
        throw new Exception("CRITICAL: deployMariaDB received empty container_name for site: " . $site["name"]);
    }
    
    // Generate passwords if not provided
    $rootPassword = $config['mariadb_root_password'] ?? bin2hex(random_bytes(16));
    $userPassword = $config['mariadb_password'] ?? bin2hex(random_bytes(12));
    $database = $config['mariadb_database'] ?? 'defaultdb';
    $user = $config['mariadb_user'] ?? 'dbuser';
    $port = $config['mariadb_port'] ?? 3306;
    
    // Check if expose is explicitly enabled (checkbox sends 'on' when checked, nothing when unchecked)
    $expose = isset($config['mariadb_expose']) && ($config['mariadb_expose'] === 'on' || $config['mariadb_expose'] === true || $config['mariadb_expose'] === '1');
    
    // Check for port conflicts if exposing
    if ($expose) {
        $portConflict = checkMariaDBPortConflict($db, $port, null);
        if ($portConflict) {
            throw new Exception("Port $port is already in use by another MariaDB instance: {$portConflict['name']}. Please choose a different port.");
        }
    }
    
    // Create MariaDB docker-compose
    $basePath = getAppsBasePath();
    $composePath = "$basePath/mariadb/sites/{$site['container_name']}/docker-compose.yml";
    $mariadbCompose = createMariaDBDockerCompose($site, $config, $rootPassword, $userPassword);
    
    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($composePath, $mariadbCompose);
    
    // Save to database
    saveComposeConfig($db, $mariadbCompose, $site['owner_id'] ?? 1, $site['id']);
    
    // Save database credentials - only save port if expose is enabled
    $stmt = $db->prepare("UPDATE sites SET db_password = ?, db_type = ?, db_host = ?, db_port = ?, db_name = ?, db_user = ? WHERE container_name = ?");
    $stmt->execute([
        json_encode(['root' => $rootPassword, 'user' => $userPassword]),
        'mariadb',
        $site['container_name'],
        $expose ? $port : null,
        $database,
        $user,
        $site['container_name']
    ]);
    
    $result = executeDockerCompose($composePath, "up -d");
    if (!$result["success"]) {
        throw new Exception("Failed to start MariaDB: " . $result["output"]);
    }
}

function createMariaDBDockerCompose($site, $config, $rootPassword, $userPassword) {
    $containerName = $site["container_name"];
    $database = $config['mariadb_database'] ?? 'defaultdb';
    $user = $config['mariadb_user'] ?? 'dbuser';
    $port = $config['mariadb_port'] ?? 3306;
    $expose = isset($config['mariadb_expose']) && $config['mariadb_expose'];
    
    $compose = "version: '3.8'
services:
  {$containerName}:
    image: mariadb:latest
    container_name: {$containerName}
    environment:
      - MYSQL_ROOT_PASSWORD={$rootPassword}
      - MYSQL_DATABASE={$database}
      - MYSQL_USER={$user}
      - MYSQL_PASSWORD={$userPassword}
    volumes:
      - {$containerName}_data:/var/lib/mysql";
    
    // Add port mapping if expose is enabled
    if ($expose) {
        $compose .= "
    ports:
      - \"{$port}:3306\"";
    }
    
    $compose .= "
    networks:
      - wharftales_wharftales
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - CHOWN
      - SETUID
      - SETGID
      - DAC_OVERRIDE
    healthcheck:
      test: [\"CMD\", \"mysqladmin\", \"ping\", \"-h\", \"localhost\", \"-u\", \"root\", \"-p{$rootPassword}\"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 30s

volumes:
  {$containerName}_data:

networks:
  wharftales_wharftales:
    external: true";
    
    return $compose;
}

function createLaravelDockerCompose($site, $config, &$generatedPassword = null) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    $phpVersion = $site["php_version"] ?? '8.3';
    $includeWww = $site["include_www"] ?? 0;
    
    // Check database type
    $dbType = $config['laravel_db_type'] ?? 'mysql';
    $useDedicatedDb = in_array($dbType, ['mysql', 'postgresql']);
    
    // Generate random database password
    $dbPassword = bin2hex(random_bytes(16));
    $generatedPassword = $dbPassword;
    
    // Check if pre-built image exists
    $imageName = "wharftales/laravel:{$phpVersion}-fpm";
    exec("docker image inspect {$imageName} 2>/dev/null", $output, $imageExists);
    
    $appsPath = getAppsBasePath();
    
    $compose = "version: '3.8'
services:
  {$containerName}:";
    
    if ($imageExists === 0) {
        // Use pre-built image for faster deployment
        $compose .= "
    image: {$imageName}";
    } else {
        // Fallback to building from Dockerfile
        $compose .= "
    build:
      context: {$appsPath}/laravel
      dockerfile: Dockerfile
      args:
        PHP_VERSION: {$phpVersion}";
    }
    
    $compose .= "
    container_name: {$containerName}
    volumes:
      - {$containerName}_data:/var/www/html
    environment:";
    
    if ($useDedicatedDb) {
        // Dedicated database
        $compose .= "
      - DB_HOST={$containerName}_db
      - DB_DATABASE=appdb
      - DB_USERNAME=appuser
      - DB_PASSWORD={$dbPassword}";
    } else {
        // No database
        $compose .= "
      - DB_HOST=
      - DB_DATABASE=
      - DB_USERNAME=
      - DB_PASSWORD=";
    }
    
    $hostRule = generateHostRule($domain, $includeWww);
    $compose .= "
    labels:
      - traefik.enable=true
      - traefik.http.routers.{$containerName}.rule={$hostRule}
      - traefik.http.routers.{$containerName}.entrypoints=web
      - traefik.http.services.{$containerName}.loadbalancer.server.port=8080";
    
    // Add SSL labels if SSL is enabled
    if ($site['ssl']) {
        // Determine certificate resolver based on SSL config
        $certResolver = 'letsencrypt'; // Default to HTTP challenge
        if (!empty($site['ssl_config'])) {
            $sslConfig = is_string($site['ssl_config']) ? json_decode($site['ssl_config'], true) : $site['ssl_config'];
            if (isset($sslConfig['challenge']) && $sslConfig['challenge'] === 'dns') {
                $certResolver = 'letsencrypt-dns'; // Use DNS challenge resolver
            }
        }
        
        $compose .= "
      - traefik.http.routers.{$containerName}-secure.rule={$hostRule}
      - traefik.http.routers.{$containerName}-secure.entrypoints=websecure
      - traefik.http.routers.{$containerName}-secure.tls=true
      - traefik.http.routers.{$containerName}-secure.tls.certresolver={$certResolver}
      - traefik.http.routers.{$containerName}.middlewares=redirect-to-https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.permanent=true";
    }
    
    $compose .= "
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
    
    // Add database service if needed
    if ($useDedicatedDb) {
        if ($dbType === 'mysql') {
            $compose .= "
  {$containerName}_db:
    image: mariadb:latest
    container_name: {$containerName}_db
    environment:
      - MYSQL_ROOT_PASSWORD={$dbPassword}
      - MYSQL_DATABASE=appdb
      - MYSQL_USER=appuser
      - MYSQL_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/mysql
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
        } elseif ($dbType === 'postgresql') {
            $compose .= "
  {$containerName}_db:
    image: postgres:15
    container_name: {$containerName}_db
    environment:
      - POSTGRES_DB=appdb
      - POSTGRES_USER=appuser
      - POSTGRES_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/postgresql/data
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
        }
    }
    
    $compose .= "
volumes:
  {$containerName}_data:";
    
    if ($useDedicatedDb) {
        $compose .= "
  {$containerName}_db_data:";
    }
    
    $compose .= "

networks:
  wharftales_wharftales:
    external: true";
    
    return $compose;
}

function createWordPressDockerCompose($site, $config, &$generatedPassword = null) {
    $containerName = $site["container_name"];
    $domain = $site["domain"];
    $phpVersion = $site["php_version"] ?? '8.3';
    
    // WordPress doesn't support PHP 8.4 yet, fallback to 8.3
    if ($phpVersion === '8.4') {
        $phpVersion = '8.3';
    }
    
    $includeWww = $site["include_www"] ?? 0;
    
    // Check database type (dedicated or custom)
    $dbType = $config['wp_db_type'] ?? 'dedicated';
    $useDedicatedDb = ($dbType === 'dedicated');
    $useCustomDb = ($dbType === 'custom');
    
    // Generate random database password for dedicated database
    $dbPassword = bin2hex(random_bytes(16)); // 32 character random password
    
    // Return the password via reference parameter
    $generatedPassword = $dbPassword;
    
    // Check if optimizations are enabled
    $wpOptimize = $config['wp_optimize'] ?? false;
    
    // Check if pre-built image exists
    $imageName = "wharftales/wordpress:{$phpVersion}-fpm";
    exec("docker image inspect {$imageName} 2>/dev/null", $output, $imageExists);
    
    $appsPath = getAppsBasePath();
    
    // Use custom WordPress Dockerfile with security improvements
    $compose = "version: '3.8'
services:
  {$containerName}:";
    
    if ($imageExists === 0) {
        // Use pre-built image for faster deployment
        $compose .= "
    image: {$imageName}";
    } else {
        // Fallback to building from Dockerfile
        $compose .= "
    build:
      context: {$appsPath}/wordpress
      dockerfile: Dockerfile
      args:
        PHP_VERSION: {$phpVersion}";
    }
    
    $compose .= "
    container_name: {$containerName}
    environment:";
    
    if ($useDedicatedDb) {
        // Dedicated database configuration
        $dbName = 'wordpress';
        $dbUser = 'wordpress';
        $compose .= "
      - WORDPRESS_DB_HOST={$containerName}_db
      - WORDPRESS_DB_NAME={$dbName}
      - WORDPRESS_DB_USER={$dbUser}
      - WORDPRESS_DB_PASSWORD={$dbPassword}";
    } elseif ($useCustomDb) {
        // Custom external database configuration
        $dbHost = $config['wp_db_host'] ?? 'localhost';
        $dbPort = $config['wp_db_port'] ?? 3306;
        $dbName = $config['wp_db_name'] ?? 'wordpress';
        $dbUser = $config['wp_db_user'] ?? 'wordpress';
        $dbPassword = $config['wp_db_password'] ?? '';
        
        // Use custom password instead of generated one
        $generatedPassword = $dbPassword;
        
        // Add port to host if not default
        $dbHostWithPort = ($dbPort != 3306) ? "{$dbHost}:{$dbPort}" : $dbHost;
        
        $compose .= "
      - WORDPRESS_DB_HOST={$dbHostWithPort}
      - WORDPRESS_DB_NAME={$dbName}
      - WORDPRESS_DB_USER={$dbUser}
      - WORDPRESS_DB_PASSWORD={$dbPassword}";
    }
    
    // Set WordPress URL based on SSL configuration
    $protocol = ($site['ssl'] ?? false) ? 'https' : 'http';
    $wordpressUrl = "{$protocol}://{$domain}";
    $compose .= "
      - WORDPRESS_URL={$wordpressUrl}";
    
    // Add Redis configuration if optimizations are enabled
    if ($wpOptimize) {
        $compose .= "
      - WORDPRESS_CONFIG_EXTRA=
          define('WP_REDIS_HOST', '{$containerName}_redis');
          define('WP_REDIS_PORT', 6379);
          define('WP_CACHE', true);
          define('WP_CACHE_KEY_SALT', '{$containerName}');";
    }
    
    $compose .= "
    volumes:
      - wp_{$containerName}_data:/var/www/html";
    
    // Add PHP ini customizations for performance if optimizations enabled
    if ($wpOptimize) {
        $compose .= "
      - ./php-custom.ini:/usr/local/etc/php/conf.d/custom.ini:ro";
    }
    
    // Generate Traefik labels with SSL support
    $hostRule = generateHostRule($domain, $includeWww);
    $compose .= "
    labels:
      - traefik.enable=true
      - traefik.http.routers.{$containerName}.rule={$hostRule}
      - traefik.http.routers.{$containerName}.entrypoints=web
      - traefik.http.services.{$containerName}.loadbalancer.server.port=8080";
    
    // Add SSL labels if SSL is enabled
    if ($site['ssl']) {
        // Determine certificate resolver based on SSL config
        $certResolver = 'letsencrypt'; // Default to HTTP challenge
        if (!empty($site['ssl_config'])) {
            $sslConfig = is_string($site['ssl_config']) ? json_decode($site['ssl_config'], true) : $site['ssl_config'];
            if (isset($sslConfig['challenge']) && $sslConfig['challenge'] === 'dns') {
                $certResolver = 'letsencrypt-dns'; // Use DNS challenge resolver
            }
        }
        
        $compose .= "
      - traefik.http.routers.{$containerName}-secure.rule={$hostRule}
      - traefik.http.routers.{$containerName}-secure.entrypoints=websecure
      - traefik.http.routers.{$containerName}-secure.tls=true
      - traefik.http.routers.{$containerName}-secure.tls.certresolver={$certResolver}
      - traefik.http.routers.{$containerName}.middlewares=redirect-to-https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https
      - traefik.http.middlewares.redirect-to-https.redirectscheme.permanent=true";
    }
    
    $compose .= "
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
    
    // Add dedicated database service if selected
    if ($useDedicatedDb) {
        $dbRootPassword = bin2hex(random_bytes(16));
        $compose .= "
  {$containerName}_db:
    image: mariadb:latest
    container_name: {$containerName}_db
    environment:
      - MYSQL_ROOT_PASSWORD={$dbRootPassword}
      - MYSQL_DATABASE=wordpress
      - MYSQL_USER=wordpress
      - MYSQL_PASSWORD={$dbPassword}
    volumes:
      - {$containerName}_db_data:/var/lib/mysql
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
    }
    
    // Add Redis service if optimizations are enabled
    if ($wpOptimize) {
        $compose .= "
  {$containerName}_redis:
    image: redis:7-alpine
    container_name: {$containerName}_redis
    command: redis-server --maxmemory 256mb --maxmemory-policy allkeys-lru
    networks:
      - wharftales_wharftales
    restart: unless-stopped
";
    }
    
    $compose .= "
volumes:
  wp_{$containerName}_data:";
    
    // Add database volume if using dedicated database
    if ($useDedicatedDb) {
        $compose .= "
  {$containerName}_db_data:";
    }
    
    $compose .= "

networks:
  wharftales_wharftales:
    external: true";
    
    return $compose;
}

function deployWordPress($db, $site, $config) {
    // Ensure container_name is set
    if (empty($site['container_name'])) {
        throw new Exception("CRITICAL: deployWordPress received empty container_name for site: " . $site["name"]);
    }
    
    // Generate database credentials
    $siteId = generateSiteId($site['name']);
    $dbName = 'wp_' . $siteId;
    $dbUser = 'wp_' . substr(md5($site['name']), 0, 8);
    $dbPass = generateRandomString(16);
    
    // Create database and user in MariaDB
    // WordPress will use the shared wharftales database with a unique table prefix
    // This avoids the root password authentication issue
    
    // We'll use the existing wharftales database and user
    // WordPress supports table prefixes, so multiple sites can share one database
    $dbName = 'wharftales';  // Use the existing database
    $dbUser = 'wharftales';  // Use the existing user
    $dbPass = 'wharftales_pass';  // Use the existing password
    $tablePrefix = 'wp_' . substr(md5($site['name']), 0, 8) . '_';
    
    // Update the WordPress docker-compose to use these credentials
    // No need to create new database - WordPress will create tables with prefix
    
    // Create WordPress application containers
    $basePath = getAppsBasePath();
    $composePath = "$basePath/wordpress/sites/{$site['container_name']}/docker-compose.yml";
    
    // Generate the docker-compose and get the generated password
    $dbPassword = null;
    $wpCompose = createWordPressDockerCompose($site, $config, $dbPassword);

    $dir = dirname($composePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($composePath, $wpCompose);
    
    // Save to database
    saveComposeConfig($db, $wpCompose, $site['owner_id'] ?? 1, $site['id']);
    
    // Save the database credentials to the database
    $dbType = $config['wp_db_type'] ?? 'dedicated';
    if ($dbType === 'dedicated' && $dbPassword) {
        $stmt = $db->prepare("UPDATE sites SET db_password = ?, db_type = ? WHERE container_name = ?");
        $stmt->execute([$dbPassword, 'dedicated', $site['container_name']]);
    } elseif ($dbType === 'custom') {
        // Save custom database credentials
        $stmt = $db->prepare("UPDATE sites SET db_type = ?, db_host = ?, db_port = ?, db_name = ?, db_user = ?, db_password = ? WHERE container_name = ?");
        $stmt->execute([
            'custom',
            $config['wp_db_host'] ?? '',
            $config['wp_db_port'] ?? 3306,
            $config['wp_db_name'] ?? '',
            $config['wp_db_user'] ?? '',
            $config['wp_db_password'] ?? '',
            $site['container_name']
        ]);
    }
    
    // Create PHP configuration file if optimizations are enabled
    $wpOptimize = $config['wp_optimize'] ?? false;
    if ($wpOptimize) {
        $phpIniPath = $dir . '/php-custom.ini';
        $phpIniContent = "; PHP Performance Optimizations
; OpCache settings
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1

; Memory and execution limits
memory_limit=256M
max_execution_time=300
max_input_time=300
post_max_size=64M
upload_max_filesize=64M

; Performance tuning
max_input_vars=3000
realpath_cache_size=4096K
realpath_cache_ttl=600
";
        file_put_contents($phpIniPath, $phpIniContent);
    }

    $result = executeDockerCompose($composePath, "up -d");
    if (!$result["success"]) {
        throw new Exception("Failed to start WordPress application: " . $result["output"]);
    }
    
    // Install and activate Redis plugin if optimizations are enabled
    if ($wpOptimize) {
        // Wait a few seconds for WordPress to initialize
        sleep(5);
        
        // Install WP-CLI if not already available, then install Redis plugin
        $containerName = $site['container_name'];
        
        // Install Redis Object Cache plugin
        exec("docker exec $containerName wp plugin install redis-cache --activate --allow-root 2>&1", $pluginOutput, $pluginReturn);
        
        // Enable Redis object cache
        if ($pluginReturn === 0) {
            exec("docker exec $containerName wp redis enable --allow-root 2>&1");
        }
    }
}

function getSiteStatus($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $status = getDockerContainerStatus($site["container_name"]);
        
        echo json_encode([
            "success" => true,
            "status" => $status
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getSiteData($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        ob_clean();
        echo json_encode([
            "success" => true,
            "site" => $site
        ]);

    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function updateSiteData($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            throw new Exception("Invalid JSON data");
        }

        $siteId = $input["site_id"];
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $domainChanged = ($site['domain'] !== $input["domain"]);
        $sslChanged = ($site['ssl'] != ($input["ssl"] ? 1 : 0));
        
        // Handle GitHub settings update
        $githubRepo = $input["github_repo"] ?? null;
        $githubBranch = $input["github_branch"] ?? 'main';
        $githubToken = $input["github_token"] ?? null;
        
        // Encrypt new token if provided (empty means keep existing)
        if (!empty($githubToken)) {
            $encryptedToken = encryptGitHubToken($githubToken);
            if ($encryptedToken === null) {
                throw new Exception("Failed to encrypt GitHub token. Please try again.");
            }
            $githubToken = $encryptedToken;
        } else {
            // Keep existing token
            $githubToken = $site['github_token'];
        }
        
        // Update deployment method
        $deploymentMethod = $githubRepo ? 'github' : 'manual';
        
        // Update basic site information including GitHub settings
        $stmt = $db->prepare("UPDATE sites SET name = ?, domain = ?, ssl = ?, github_repo = ?, github_branch = ?, github_token = ?, deployment_method = ? WHERE id = ?");
        $stmt->execute([
            $input["name"],
            $input["domain"], 
            $input["ssl"] ? 1 : 0,
            $githubRepo,
            $githubBranch,
            $githubToken,
            $deploymentMethod,
            $siteId
        ]);

        $message = "Site updated successfully";
        $needsRestart = false;
        
        // If domain or SSL changed, we need to regenerate docker-compose and redeploy
        if ($domainChanged || $sslChanged) {
            $message .= ". ";
            if ($domainChanged) {
                $message .= "Domain changed. ";
            }
            if ($sslChanged) {
                $message .= "SSL " . ($input["ssl"] ? "enabled" : "disabled") . ". ";
            }
            $message .= "Regenerating container configuration...";
            $needsRestart = true;
            
            // Get updated site data
            $updatedSite = getSiteById($db, $siteId);
            
            // Regenerate docker-compose.yml with new settings
            $basePath = getAppsBasePath();
            $composePath = "$basePath/{$updatedSite['type']}/sites/{$updatedSite['container_name']}/docker-compose.yml";
            
            if (file_exists($composePath)) {
                // Generate new compose file based on site type
                $newCompose = '';
                if ($updatedSite['type'] === 'wordpress') {
                    $newCompose = createWordPressDockerCompose($updatedSite, []);
                } elseif ($updatedSite['type'] === 'php') {
                    $newCompose = createPHPDockerCompose($updatedSite, []);
                } elseif ($updatedSite['type'] === 'laravel') {
                    $newCompose = createLaravelDockerCompose($updatedSite, []);
                }
                
                if ($newCompose) {
                    file_put_contents($composePath, $newCompose);
                    
                    // Save to database
                    saveComposeConfig($db, $newCompose, $updatedSite['owner_id'] ?? 1, $updatedSite['id']);
                    
                    // Recreate the container with new configuration
                    executeDockerCompose($composePath, "up -d --force-recreate");
                    
                    $message = "Site updated and redeployed successfully with new configuration!";
                }
            }
        }

        ob_clean();
        echo json_encode([
            "success" => true,
            "message" => $message,
            "needs_restart" => $needsRestart,
            "domain_changed" => $domainChanged,
            "ssl_changed" => $sslChanged
        ]);

    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function updateSiteSSLConfig($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            throw new Exception("Invalid JSON data");
        }

        $siteId = $input["site_id"];
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $domainChanged = ($site['domain'] !== $input["domain"]);
        $sslChanged = ($site['ssl'] != ($input["ssl"] ? 1 : 0));
        
        // Get current SSL config
        $currentSslConfig = $site['ssl_config'] ? json_decode($site['ssl_config'], true) : null;
        $newSslConfig = $input["ssl_config"] ?? [];
        
        // Merge credentials - only update if new values provided
        if (!empty($currentSslConfig) && !empty($currentSslConfig['credentials'])) {
            // Keep existing credentials
            $existingCredentials = $currentSslConfig['credentials'];
            
            // Update only if new credentials provided
            if (!empty($newSslConfig['credentials'])) {
                foreach ($newSslConfig['credentials'] as $key => $value) {
                    if (!empty($value)) {
                        $existingCredentials[$key] = $value;
                    }
                }
                $newSslConfig['credentials'] = $existingCredentials;
            } else {
                $newSslConfig['credentials'] = $existingCredentials;
            }
        }
        
        // Update site with new SSL configuration
        $includeWww = isset($input["include_www"]) ? ($input["include_www"] ? 1 : 0) : 0;
        $stmt = $db->prepare("UPDATE sites SET name = ?, domain = ?, ssl = ?, ssl_config = ?, include_www = ? WHERE id = ?");
        $stmt->execute([
            $input["name"],
            $input["domain"], 
            $input["ssl"] ? 1 : 0,
            json_encode($newSslConfig),
            $includeWww,
            $siteId
        ]);

        $message = "SSL configuration updated successfully";
        
        // If DNS challenge is configured, update Traefik
        if ($input["ssl"] && $newSslConfig['challenge'] === 'dns' && !empty($newSslConfig['provider'])) {
            require_once 'includes/ssl-config.php';
            $updateResult = updateTraefikForDNSChallenge($newSslConfig['provider'], $newSslConfig['credentials']);
            if (!$updateResult) {
                $message .= " (Warning: Traefik DNS configuration may need manual update)";
            }
        }
        
        // Regenerate container configuration
        $updatedSite = getSiteById($db, $siteId);
        $basePath = getAppsBasePath();
        $composePath = "$basePath/{$updatedSite['type']}/sites/{$updatedSite['container_name']}/docker-compose.yml";
        
        if (file_exists($composePath)) {
            // Generate new compose file based on site type
            $newCompose = '';
            if ($updatedSite['type'] === 'wordpress') {
                $newCompose = createWordPressDockerCompose($updatedSite, []);
            } elseif ($updatedSite['type'] === 'php') {
                $newCompose = createPHPDockerCompose($updatedSite, []);
            } elseif ($updatedSite['type'] === 'laravel') {
                $newCompose = createLaravelDockerCompose($updatedSite, []);
            }
            
            if ($newCompose) {
                file_put_contents($composePath, $newCompose);
                
                // Save to database
                saveComposeConfig($db, $newCompose, $updatedSite['owner_id'] ?? 1, $updatedSite['id']);
                
                // Recreate the container with new configuration
                executeDockerCompose($composePath, "up -d --force-recreate");
                
                $message = "SSL configuration updated and container redeployed successfully!";
            }
        }

        ob_clean();
        echo json_encode([
            "success" => true,
            "message" => $message
        ]);

    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function deleteSiteById($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        // Check if user wants to keep data
        $keepData = $_GET['keep_data'] ?? false;
        
        $containerName = $site['container_name'];
        
        // Stop and remove containers using docker-compose
        $basePath = getAppsBasePath();
        $composePath = "$basePath/{$site['type']}/sites/{$containerName}/docker-compose.yml";
        if (file_exists($composePath)) {
            // Use 'down' without -v to preserve volumes, or 'down -v' to delete them
            $command = $keepData ? "down" : "down -v";
            executeDockerCompose($composePath, $command);
        }
        
        // Remove standalone Redis container if it exists (created separately from docker-compose)
        $redisContainerName = $containerName . '_redis';
        exec("docker ps -a --filter name=" . escapeshellarg($redisContainerName) . " --format '{{.Names}}' 2>&1", $redisCheck, $redisCode);
        if ($redisCode === 0 && !empty($redisCheck) && trim($redisCheck[0]) === $redisContainerName) {
            exec("docker rm -f " . escapeshellarg($redisContainerName) . " 2>&1");
        }
        
        // Remove database container if it exists (might be created separately)
        $dbContainerName = $containerName . '_db';
        exec("docker ps -a --filter name=" . escapeshellarg($dbContainerName) . " --format '{{.Names}}' 2>&1", $dbCheck, $dbCode);
        if ($dbCode === 0 && !empty($dbCheck) && trim($dbCheck[0]) === $dbContainerName) {
            exec("docker rm -f " . escapeshellarg($dbContainerName) . " 2>&1");
        }
        
        // Remove main container if it still exists
        exec("docker ps -a --filter name=" . escapeshellarg($containerName) . " --format '{{.Names}}' 2>&1", $mainCheck, $mainCode);
        if ($mainCode === 0 && !empty($mainCheck) && trim($mainCheck[0]) === $containerName) {
            exec("docker rm -f " . escapeshellarg($containerName) . " 2>&1");
        }
        
        // Remove volumes if not keeping data
        if (!$keepData) {
            // Remove all volumes associated with this site
            $volumePatterns = [
                "wp_{$containerName}_data",
                "{$containerName}_data",
                "{$containerName}_db_data"
            ];
            
            foreach ($volumePatterns as $volumeName) {
                exec("docker volume ls --format '{{.Name}}' | grep -x " . escapeshellarg($volumeName) . " 2>&1", $volCheck, $volCode);
                if ($volCode === 0 && !empty($volCheck)) {
                    exec("docker volume rm " . escapeshellarg($volumeName) . " 2>&1");
                }
            }
        }
        
        // Delete the entire site directory
        $basePath = getAppsBasePath();
        $siteDir = "$basePath/{$site['type']}/sites/{$containerName}";
        if (is_dir($siteDir)) {
            // Recursively delete the directory
            exec("rm -rf " . escapeshellarg($siteDir) . " 2>&1");
        }

        // Delete database record
        deleteSite($db, $id);
        
        // With Traefik, no manual configuration cleanup needed
        // Traefik will automatically remove routes when containers stop

        $message = $keepData 
            ? "Site deleted successfully. Data volume preserved for backup/restore."
            : "Site and data deleted successfully.";

        echo json_encode([
            "success" => true,
            "message" => $message,
            "volume_kept" => $keepData
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function restartContainer($db, $id) {
    set_time_limit(0); ini_set("memory_limit", "512M"); // Allow long execution for container restart
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $result = executeDockerCommand("restart {$site['container_name']}");
        
        if ($result['success']) {
            updateSiteStatus($db, $id, "running");
            
            // For Laravel sites, sync environment variables after restart
            if ($site['type'] === 'laravel') {
                // Wait a moment for container to be fully up
                sleep(2);
                
                // Sync Docker env vars to Laravel .env file
                require_once __DIR__ . '/includes/github-deploy.php';
                $syncResult = syncDockerEnvToLaravel($site['container_name']);
                
                if ($syncResult['success']) {
                    $message = "Container restarted successfully. Environment variables synced.";
                } else {
                    $message = "Container restarted successfully. Warning: " . $syncResult['message'];
                }
            } else {
                $message = "Container restarted successfully";
            }
            
            ob_clean();
            echo json_encode([
                "success" => true,
                "message" => $message
            ]);
        } else {
            throw new Exception("Failed to restart container: " . $result['output']);
        }

    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function startContainer($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $result = executeDockerCommand("start {$site['container_name']}");
        
        if ($result['success']) {
            updateSiteStatus($db, $id, "running");
            
            // For Laravel sites, sync environment variables after start
            if ($site['type'] === 'laravel') {
                // Wait a moment for container to be fully up
                sleep(2);
                
                // Sync Docker env vars to Laravel .env file
                require_once __DIR__ . '/includes/github-deploy.php';
                $syncResult = syncDockerEnvToLaravel($site['container_name']);
                
                if ($syncResult['success']) {
                    $message = "Container started successfully. Environment variables synced.";
                } else {
                    $message = "Container started successfully. Warning: " . $syncResult['message'];
                }
            } else {
                $message = "Container started successfully";
            }
            
            ob_clean();
            echo json_encode([
                "success" => true,
                "message" => $message
            ]);
        } else {
            throw new Exception("Failed to start container: " . $result['output']);
        }

    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function stopContainer($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $result = executeDockerCommand("stop {$site['container_name']}");
        
        if ($result['success']) {
            updateSiteStatus($db, $id, "stopped");
            ob_clean();
            echo json_encode([
                "success" => true,
                "message" => "Container stopped successfully"
            ]);
        } else {
            throw new Exception("Failed to stop container: " . $result['output']);
        }

    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getSiteContainerLogs($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        $lines = $_GET['lines'] ?? 100;
        $containerName = escapeshellarg($site['container_name']);
        
        exec("docker logs --tail " . intval($lines) . " {$containerName} 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode([
                "success" => true,
                "logs" => implode("\n", $output)
            ]);
        } else {
            throw new Exception("Failed to get logs: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getContainerStats($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        // Get container uptime
        $uptimeResult = executeDockerCommand("inspect --format='{{.State.StartedAt}}' {$site['container_name']}");
        $startedAt = trim($uptimeResult['output']);
        
        // Get volume size - use a simpler approach
        $volumeName = $site['type'] === 'wordpress' ? "wp_{$site['container_name']}_data" : "{$site['container_name']}_data";
        $volumeSize = 'N/A';
        
        // Try to get volume size using docker volume inspect
        try {
            $volumeInspect = executeDockerCommand("volume inspect {$volumeName} --format '{{.Mountpoint}}'");
            
            if ($volumeInspect['success'] && !empty($volumeInspect['output'])) {
                $mountpoint = trim($volumeInspect['output']);
                // Get directory size
                exec("du -sh {$mountpoint} 2>/dev/null | awk '{print $1}'", $sizeOutput, $sizeReturnCode);
                if ($sizeReturnCode === 0 && !empty($sizeOutput)) {
                    $volumeSize = trim($sizeOutput[0]);
                }
            }
        } catch (Exception $e) {
            // Volume size will remain N/A
        }
        
        // Calculate uptime
        $uptime = 'N/A';
        if (!empty($startedAt) && $startedAt !== 'N/A') {
            try {
                $start = new DateTime($startedAt);
                $now = new DateTime();
                $diff = $now->diff($start);
                
                if ($diff->days > 0) {
                    $uptime = $diff->days . 'd ' . $diff->h . 'h';
                } else if ($diff->h > 0) {
                    $uptime = $diff->h . 'h ' . $diff->i . 'm';
                } else {
                    $uptime = $diff->i . 'm ' . $diff->s . 's';
                }
            } catch (Exception $e) {
                $uptime = 'N/A';
            }
        }
        
        // Get CPU and Memory stats for all related containers
        $cpu = 'N/A';
        $cpuPercent = 0;
        $memory = 'N/A';
        $memPercent = 0;
        $totalCpuPercent = 0;
        $totalMemMB = 0;
        $totalMemPercent = 0;
        $containerCount = 0;
        
        // List of potential containers for this site
        $containers = [$site['container_name']];
        
        // Add database container if it exists
        $dbContainer = $site['container_name'] . '_db';
        $dbCheck = executeDockerCommand("ps -a --filter name=^{$dbContainer}$ --format '{{.Names}}'");
        if ($dbCheck['success'] && trim($dbCheck['output']) === $dbContainer) {
            $containers[] = $dbContainer;
        }
        
        // Add Redis container if it exists
        $redisContainer = $site['container_name'] . '_redis';
        $redisCheck = executeDockerCommand("ps -a --filter name=^{$redisContainer}$ --format '{{.Names}}'");
        if ($redisCheck['success'] && trim($redisCheck['output']) === $redisContainer) {
            $containers[] = $redisContainer;
        }
        
        // Add SFTP container if it exists
        $sftpContainer = $site['container_name'] . '_sftp';
        $sftpCheck = executeDockerCommand("ps -a --filter name=^{$sftpContainer}$ --format '{{.Names}}'");
        if ($sftpCheck['success'] && trim($sftpCheck['output']) === $sftpContainer) {
            $containers[] = $sftpContainer;
        }
        
        // Get stats for all containers (only running ones)
        foreach ($containers as $containerName) {
            // Check if container is running first
            $statusCheck = executeDockerCommand("inspect --format='{{.State.Running}}' {$containerName}");
            if (!$statusCheck['success'] || trim($statusCheck['output']) !== 'true') {
                continue; // Skip stopped containers
            }
            
            $statsResult = executeDockerCommand("stats {$containerName} --no-stream --format '{{.CPUPerc}}|{{.MemUsage}}|{{.MemPerc}}'");
            if ($statsResult['success'] && !empty($statsResult['output'])) {
                $parts = explode('|', trim($statsResult['output']));
                if (count($parts) >= 3) {
                    $containerCpu = floatval(str_replace('%', '', trim($parts[0])));
                    $containerMem = trim($parts[1]);
                    $containerMemPercent = floatval(str_replace('%', '', trim($parts[2])));
                    
                    $totalCpuPercent += $containerCpu;
                    $totalMemPercent += $containerMemPercent;
                    
                    // Parse memory (e.g., "45.5MiB / 1.5GiB")
                    if (preg_match('/([0-9.]+)([A-Za-z]+)/', $containerMem, $matches)) {
                        $memValue = floatval($matches[1]);
                        $memUnit = strtoupper($matches[2]);
                        
                        // Convert to MB
                        if ($memUnit === 'GIB' || $memUnit === 'GB') {
                            $totalMemMB += $memValue * 1024;
                        } else if ($memUnit === 'MIB' || $memUnit === 'MB') {
                            $totalMemMB += $memValue;
                        } else if ($memUnit === 'KIB' || $memUnit === 'KB') {
                            $totalMemMB += $memValue / 1024;
                        }
                    }
                    
                    $containerCount++;
                }
            }
        }
        
        // Format the combined stats
        if ($containerCount > 0) {
            $cpuPercent = round($totalCpuPercent, 2);
            $cpu = $cpuPercent . '%';
            
            // Format memory
            if ($totalMemMB >= 1024) {
                $memory = round($totalMemMB / 1024, 2) . ' GiB';
            } else {
                $memory = round($totalMemMB, 2) . ' MiB';
            }
            
            $memPercent = round($totalMemPercent, 2);
        }
        
        ob_clean();
        echo json_encode([
            "success" => true,
            "stats" => [
                "uptime" => $uptime,
                "volume_size" => $volumeSize,
                "status" => getDockerContainerStatus($site['container_name']),
                "cpu" => $cpu,
                "cpu_percent" => $cpuPercent,
                "memory" => $memory,
                "mem_percent" => $memPercent
            ]
        ]);

    } catch (Exception $e) {
        ob_clean();
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function enableSFTPHandler($db, $id) {
    try {
        $site = enableSFTP($db, $id);
        
        echo json_encode([
            "success" => true,
            "message" => "SFTP enabled successfully",
            "sftp" => [
                "username" => $site['sftp_username'],
                "password" => $site['sftp_password'],
                "port" => $site['sftp_port'],
                "host" => $_SERVER['SERVER_ADDR'] ?? 'localhost'
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function disableSFTPHandler($db, $id) {
    try {
        disableSFTP($db, $id);
        
        echo json_encode([
            "success" => true,
            "message" => "SFTP disabled successfully"
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function regenerateSFTPPassword($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Generate new password
        $newPassword = bin2hex(random_bytes(12));
        
        $stmt = $db->prepare("UPDATE sites SET sftp_password = ? WHERE id = ?");
        $stmt->execute([$newPassword, $id]);
        
        // Redeploy SFTP container with new password
        if ($site['sftp_enabled']) {
            $site['sftp_password'] = $newPassword;
            deploySFTPContainer($site);
        }
        
        echo json_encode([
            "success" => true,
            "message" => "SFTP password regenerated successfully",
            "password" => $newPassword
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function changePasswordHandler($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword)) {
            throw new Exception("Current password and new password are required");
        }
        
        if (strlen($newPassword) < 6) {
            throw new Exception("New password must be at least 6 characters long");
        }
        
        // Get current user with password hash
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("User not logged in");
        }
        
        $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentUser) {
            throw new Exception("User not found");
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $currentUser['password_hash'])) {
            throw new Exception("Current password is incorrect");
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password in database
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $currentUser['id']]);
        
        echo json_encode([
            "success" => true,
            "message" => "Password changed successfully"
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

// ============================================
// UPDATE SYSTEM HANDLERS
// ============================================

function checkForUpdatesHandler($db) {
    try {
        $updateInfo = checkForUpdates(false);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'data' => [
                'update_available' => $updateInfo['update_available'] ?? false,
                'current_version' => $updateInfo['current_version'] ?? 'unknown',
                'latest_version' => $updateInfo['latest_version'] ?? 'unknown',
            ],
            'info' => $updateInfo
        ]);
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function performSystemUpdate() {
    global $db;
    try {
        // Only admins can trigger updates
        if (!isAdmin()) {
            ob_clean();
            http_response_code(403);
            throw new Exception("Only administrators can trigger updates");
        }
        
        $result = triggerUpdate(false);
        
        ob_clean();
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getUpdateInformation() {
    global $db;
    try {
        $updateInfo = checkForUpdates(true);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'info' => $updateInfo,
            'changelog' => []
        ]);
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}


// ============================================
// FILE MANAGER HANDLERS
// ============================================

function listContainerFiles($db, $siteId, $path) {
    try {
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Check if container_name exists
        if (empty($site['container_name'])) {
            throw new Exception("Container name is empty in database for site ID: $siteId");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        if (empty($path)) $path = '/var/www/html';
        
        // List files in container using a more reliable format
        $containerName = $site['container_name'];
        
        // Verify container exists using docker inspect (most reliable)
        exec("docker inspect --format='{{.State.Status}}' " . escapeshellarg($containerName) . " 2>&1", $inspectOutput, $inspectCode);
        
        if ($inspectCode !== 0) {
            // Container doesn't exist - show available containers for debugging
            exec("docker ps -a --format '{{.Names}}' 2>&1", $allContainers);
            $containerList = implode(", ", $allContainers);
            throw new Exception("Container '$containerName' not found. Available containers: " . ($containerList ?: "none"));
        }
        
        $containerStatus = trim($inspectOutput[0] ?? '');
        if ($containerStatus !== 'running') {
            throw new Exception("Container '$containerName' is not running (status: $containerStatus). Please start the container first.");
        }
        
        $output = [];
        
        // Use ls with full path - simpler and more reliable
        $cmd = "docker exec $containerName ls -1A " . escapeshellarg($path) . " 2>&1";
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            
            // Check for specific error types
            if (strpos($errorMsg, 'No such file or directory') !== false) {
                throw new Exception("Directory not found: $path");
            } elseif (strpos($errorMsg, 'is not running') !== false || strpos($errorMsg, 'not found') !== false) {
                throw new Exception("Container '$containerName' is not running or not found");
            } else {
                throw new Exception("Failed to list files: " . $errorMsg);
            }
        }
        
        $files = [];
        foreach ($output as $filename) {
            $filename = trim($filename);
            if (empty($filename) || $filename === '.' || $filename === '..') continue;
            
            // Get file details
            $fullPath = rtrim($path, '/') . '/' . $filename;
            $statCmd = "docker exec $containerName stat -c '%F|%s|%y' " . escapeshellarg($fullPath) . " 2>&1";
            $statOutput = [];
            exec($statCmd, $statOutput, $statReturn);
            
            if ($statReturn === 0 && !empty($statOutput[0])) {
                $parts = explode('|', $statOutput[0]);
                $fileType = $parts[0] ?? '';
                $size = (int)($parts[1] ?? 0);
                $modified = isset($parts[2]) ? date('M d H:i', strtotime($parts[2])) : '';
                
                $isDir = (strpos($fileType, 'directory') !== false);
                
                $files[] = [
                    'name' => $filename,
                    'type' => $isDir ? 'directory' : 'file',
                    'size' => $size,
                    'modified' => $modified,
                    'path' => $fullPath
                ];
            }
        }
        
        // Sort: directories first, then files
        usort($files, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });
        
        echo json_encode([
            'success' => true,
            'files' => $files,
            'path' => $path
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function downloadContainerFile($db, $siteId, $path) {
    try {
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        
        $containerName = $site['container_name'];
        $tempFile = tempnam(sys_get_temp_dir(), 'download_');
        
        // Copy file from container
        exec("docker cp $containerName:$path $tempFile 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to download file");
        }
        
        // Send file to browser
        $filename = basename($path);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tempFile));
        readfile($tempFile);
        unlink($tempFile);
        exit;
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function deleteContainerFile($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        
        if (!$siteId || !$path) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        
        // Don't allow deleting critical files
        $criticalFiles = ['/var/www/html/index.php', '/var/www/html'];
        if (in_array($path, $criticalFiles)) {
            throw new Exception("Cannot delete critical file");
        }
        
        $containerName = $site['container_name'];
        exec("docker exec $containerName rm -rf " . escapeshellarg($path) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to delete file: " . implode("\n", $output));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'File deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function createContainerFile($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        $filename = $input['filename'] ?? null;
        $content = $input['content'] ?? '';
        
        if (!$siteId || !$path || !$filename) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path and filename
        $path = str_replace(['..', '~'], '', $path);
        $filename = basename($filename); // Remove any path components
        $fullPath = rtrim($path, '/') . '/' . $filename;
        
        $containerName = $site['container_name'];
        
        // Create temp file with content
        $tempFile = tempnam(sys_get_temp_dir(), 'wt_');
        file_put_contents($tempFile, $content);
        
        // Copy to container
        exec("docker cp $tempFile $containerName:$fullPath 2>&1", $output, $returnCode);
        unlink($tempFile);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to create file: " . implode("\n", $output));
        }
        
        // Determine correct web user
        $webUser = getContainerWebUser($containerName);
        
        // Set proper permissions
        exec("docker exec $containerName chmod 644 " . escapeshellarg($fullPath) . " 2>&1");
        exec("docker exec -u root $containerName chown {$webUser}:{$webUser} " . escapeshellarg($fullPath) . " 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'File created successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function createContainerFolder($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        $foldername = $input['foldername'] ?? null;
        
        if (!$siteId || !$path || !$foldername) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path and foldername
        $path = str_replace(['..', '~'], '', $path);
        $foldername = basename($foldername); // Remove any path components
        $fullPath = rtrim($path, '/') . '/' . $foldername;
        
        $containerName = $site['container_name'];
        
        // Create directory
        exec("docker exec $containerName mkdir -p " . escapeshellarg($fullPath) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to create folder: " . implode("\n", $output));
        }
        
        // Determine correct web user
        $webUser = getContainerWebUser($containerName);
        
        // Set proper permissions
        exec("docker exec $containerName chmod 755 " . escapeshellarg($fullPath) . " 2>&1");
        // Ensure ownership
        exec("docker exec -u root $containerName chown {$webUser}:{$webUser} " . escapeshellarg($fullPath) . " 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'Folder created successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function uploadContainerFile($db) {
    try {
        $siteId = $_POST['id'] ?? null;
        $path = $_POST['path'] ?? null;
        
        if (!$siteId || !$path) {
            throw new Exception("Missing parameters");
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("No file uploaded or upload error");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        $filename = basename($_FILES['file']['name']);
        $fullPath = rtrim($path, '/') . '/' . $filename;
        
        $containerName = $site['container_name'];
        $tempFile = $_FILES['file']['tmp_name'];
        
        // Copy to container
        exec("docker cp $tempFile $containerName:$fullPath 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to upload file: " . implode("\n", $output));
        }
        
        // Determine correct web user
        $webUser = getContainerWebUser($containerName);
        
        // Set proper permissions
        exec("docker exec $containerName chmod 644 " . escapeshellarg($fullPath) . " 2>&1");
        // Ensure ownership
        exec("docker exec -u root $containerName chown {$webUser}:{$webUser} " . escapeshellarg($fullPath) . " 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function readContainerFile($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? $_GET['id'] ?? null;
        $path = $input['path'] ?? $_GET['path'] ?? null;
        
        if (!$siteId || !$path) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        
        $containerName = $site['container_name'];
        
        // Read file from container
        $output = [];
        exec("docker exec -u root $containerName cat " . escapeshellarg($path) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to read file: " . implode("\n", $output));
        }
        
        $content = implode("\n", $output);
        
        echo json_encode([
            'success' => true,
            'content' => $content,
            'filename' => basename($path)
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function saveContainerFile($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $path = $input['path'] ?? null;
        $content = $input['content'] ?? '';
        
        if (!$siteId || !$path) {
            throw new Exception("Missing parameters");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Sanitize path
        $path = str_replace(['..', '~'], '', $path);
        
        $containerName = $site['container_name'];
        
        // Create temp file with content
        $tempFile = tempnam(sys_get_temp_dir(), 'editfile_');
        file_put_contents($tempFile, $content);
        
        // Copy to container
        exec("docker cp $tempFile $containerName:$path 2>&1", $output, $returnCode);
        unlink($tempFile);
        
        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            // Ensure UTF-8 to prevent json_encode failure
            $errorMsg = mb_convert_encoding($errorMsg, 'UTF-8', 'UTF-8');
            throw new Exception("Failed to save file: " . $errorMsg);
        }
        
        // Determine correct web user
        $webUser = getContainerWebUser($containerName);
        
        // Set proper permissions and ownership as root
        exec("docker exec -u root $containerName chown {$webUser}:{$webUser} " . escapeshellarg($path) . " 2>&1");
        exec("docker exec -u root $containerName chmod 644 " . escapeshellarg($path) . " 2>&1");
        
        echo json_encode([
            'success' => true,
            'message' => 'File saved successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        $errorMsg = $e->getMessage();
        $errorMsg = mb_convert_encoding($errorMsg, 'UTF-8', 'UTF-8');
        
        $json = json_encode([
            'success' => false,
            'error' => $errorMsg
        ]);
        
        if ($json === false) {
            echo json_encode([
                'success' => false,
                'error' => 'JSON encoding failed: ' . json_last_error_msg()
            ]);
        } else {
            echo $json;
        }
    }
}


// ============================================
// ENVIRONMENT VARIABLES HANDLERS
// ============================================

function getEnvironmentVariables($db, $siteId) {
    try {
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        
        // Try both container path and host path
        $possiblePaths = [
            "/app/apps/{$site['type']}/sites/$containerName/docker-compose.yml",
            "/opt/wharftales/apps/{$site['type']}/sites/$containerName/docker-compose.yml"
        ];
        
        $composeFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $composeFile = $path;
                break;
            }
        }
        
        if (!$composeFile) {
            throw new Exception("Docker compose file not found in any location");
        }
        
        $content = file_get_contents($composeFile);
        
        // Parse environment variables from YAML
        $envVars = [];
        
        // Match environment section - handle both proper and malformed YAML
        // This regex matches "environment:" followed by lines starting with "      -"
        if (preg_match('/environment:\s*\n((?:\s+-[^\n]+\n?)+)/m', $content, $matches)) {
            $envBlock = $matches[1];
            // Split by newlines and process each line
            $lines = explode("\n", $envBlock);
            
            foreach ($lines as $line) {
                // Match lines like "      - KEY=value" or "- KEY=value"
                if (preg_match('/^\s*-\s*([^=]+)=(.*)$/', trim($line), $envMatch)) {
                    $key = trim($envMatch[1]);
                    $value = trim($envMatch[2]);
                    
                    if (!empty($key)) {
                        $envVars[] = [
                            'key' => $key,
                            'value' => $value
                        ];
                    }
                }
            }
        }
        
        // Log what we loaded
        error_log("Loaded " . count($envVars) . " environment variables from $composeFile");
        
        echo json_encode([
            'success' => true,
            'env_vars' => $envVars
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function saveEnvironmentVariables($db) {
    // Increase time limit for container restart
    set_time_limit(0); ini_set("memory_limit", "512M");
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $siteId = $input['id'] ?? null;
        $envVars = $input['env_vars'] ?? [];
        
        if (!$siteId) {
            throw new Exception("Missing site ID");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        
        // Try both container path and host path
        $possiblePaths = [
            "/app/apps/{$site['type']}/sites/$containerName/docker-compose.yml",
            "/opt/wharftales/apps/{$site['type']}/sites/$containerName/docker-compose.yml"
        ];
        
        $composeFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $composeFile = $path;
                break;
            }
        }
        
        if (!$composeFile) {
            throw new Exception("Docker compose file not found");
        }
        
        // Read current docker-compose.yml
        $content = file_get_contents($composeFile);
        
        // Create backup before modifying
        $backupFile = $composeFile . '.backup.' . time();
        file_put_contents($backupFile, $content);
        error_log("Created backup: $backupFile");
        
        // Build new environment section with proper indentation
        $envLines = [];
        foreach ($envVars as $env) {
            $key = trim($env['key'] ?? '');
            $value = trim($env['value'] ?? '');
            
            // Skip empty keys
            if (empty($key)) {
                continue;
            }
            
            // Sanitize key - remove spaces and special chars that break YAML
            $key = preg_replace('/[^A-Z0-9_]/', '_', strtoupper($key));
            
            // Escape value if it contains special characters
            // Quote the value if it contains spaces, colons, or special chars
            if (preg_match('/[\s:{}[\]&*#?|<>=!%@`]/', $value)) {
                // Escape quotes in value
                $value = str_replace('"', '\\"', $value);
                $value = '"' . $value . '"';
            }
            
            $envLines[] = "      - $key=$value";
        }
        
        if (empty($envLines)) {
            throw new Exception("No valid environment variables to save. Received: " . json_encode($envVars));
        }
        
        // Log what we're about to write
        error_log("Saving " . count($envLines) . " environment variables for site $siteId");
        
        $newEnvSection = "    environment:\n" . implode("\n", $envLines) . "\n";
        
        // Replace environment section - handle both proper and malformed YAML
        // Pattern 1: Proper formatting with newline before environment
        $pattern1 = '/    environment:\s*\n(?:      - [^\n]+\n?)+/';
        // Pattern 2: No newline before environment (malformed but common)
        $pattern2 = '/environment:\s*\n(?:      - [^\n]+\n?)+/';
        
        $replaced = false;
        
        if (preg_match($pattern1, $content)) {
            $content = preg_replace($pattern1, $newEnvSection, $content, 1);
            $replaced = true;
        } elseif (preg_match($pattern2, $content)) {
            // For malformed YAML, add proper spacing
            $content = preg_replace($pattern2, "    " . $newEnvSection, $content, 1);
            $replaced = true;
        }
        
        if (!$replaced) {
            throw new Exception("Could not find environment section in docker-compose.yml. Content: " . substr($content, 0, 500));
        }
        
        // Write back to file
        file_put_contents($composeFile, $content);
        
        // Restart container using the directory where the compose file is
        $composeDir = dirname($composeFile);
        exec("cd $composeDir && docker-compose restart 2>&1", $output, $returnCode);
        
        // Check if container actually restarted (ignore YAML warnings)
        $outputStr = implode("\n", $output);
        $hasError = $returnCode !== 0 && !preg_match('/Started|Restarted/', $outputStr);
        
        if ($hasError) {
            // Check if container is actually running despite the error
            exec("docker ps -f name=$containerName --format '{{.Status}}'", $statusOutput);
            $isRunning = !empty($statusOutput) && strpos($statusOutput[0], 'Up') !== false;
            
            if (!$isRunning) {
                throw new Exception("Failed to restart container: " . $outputStr);
            }
        }
        
        // For Laravel sites, sync environment variables to .env file
        $syncMessage = '';
        if ($site['type'] === 'laravel') {
            // Convert env vars array to associative array
            $envVarsAssoc = [];
            foreach ($envVars as $env) {
                $key = trim($env['key'] ?? '');
                $value = trim($env['value'] ?? '');
                if (!empty($key)) {
                    $envVarsAssoc[$key] = $value;
                }
            }
            
            $syncResult = syncEnvToLaravel($containerName, $envVarsAssoc);
            if ($syncResult['success']) {
                $syncMessage = ' and synced to Laravel .env file';
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Environment variables saved and container restarted' . $syncMessage,
            'warning' => $returnCode !== 0 ? 'Container restarted but with warnings' : null
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

function getDashboardStats($db, $id) {
    try {
        $site = getSiteById($db, $id);
        if (!$site) {
            throw new Exception("Site not found");
        }

        // Check if container is running
        $status = getDockerContainerStatus($site['container_name']);
        if ($status !== 'running') {
            echo json_encode([
                "success" => true,
                "stats" => [
                    "cpu" => "0%",
                    "memory" => "0 MB",
                    "cpu_percent" => 0,
                    "mem_percent" => 0,
                    "status" => $status
                ]
            ]);
            return;
        }

        // Cache stats for 5 seconds to avoid hammering docker stats
        $cacheFile = "/tmp/stats_cache_{$site['id']}";
        $cacheTime = 5; // seconds
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
            $cachedStats = json_decode(file_get_contents($cacheFile), true);
            echo json_encode([
                "success" => true,
                "stats" => $cachedStats,
                "cached" => true
            ]);
            return;
        }

        // Get container stats
        $stats = ['cpu' => '0%', 'memory' => '0 MB', 'cpu_percent' => 0, 'mem_percent' => 0, 'status' => 'running'];
        
        exec("docker stats --no-stream --format '{{.CPUPerc}}|{{.MemUsage}}' {$site['container_name']} 2>&1", $output);
        if (!empty($output[0]) && strpos($output[0], 'Error') === false) {
            $parts = explode('|', $output[0]);
            $stats['cpu'] = $parts[0] ?? '0%';
            $stats['memory'] = $parts[1] ?? '0 MB';
            
            // Extract percentages for graphs
            $stats['cpu_percent'] = (float)str_replace('%', '', $stats['cpu']);
            
            // Parse memory usage (e.g., "45.5MiB / 1.944GiB")
            if (isset($parts[1])) {
                preg_match('/(\d+\.?\d*)\w+\s*\/\s*(\d+\.?\d*)(\w+)/', $parts[1], $memMatch);
                if (count($memMatch) >= 4) {
                    $used = (float)$memMatch[1];
                    $total = (float)$memMatch[2];
                    
                    // Convert to same unit if needed
                    if (strpos($parts[1], 'MiB') !== false && strpos($parts[1], 'GiB') !== false) {
                        // $used is in MiB, $total is in GiB
                        $total = $total * 1024; // Convert GiB to MiB
                    }
                    
                    $stats['mem_percent'] = $total > 0 ? ($used / $total) * 100 : 0;
                }
            }
        }

        // Cache the results
        file_put_contents($cacheFile, json_encode($stats));

        echo json_encode([
            "success" => true,
            "stats" => $stats
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

// NOTE: restartTraefik() is defined in includes/ssl-config.php
// Removed duplicate to fix "Cannot redeclare restartTraefik()" fatal error

function restartWebGui() {
    try {
        // Use docker-compose up with --force-recreate to apply new labels
        exec("cd /opt/wharftales && docker-compose up -d --force-recreate web-gui 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode([
                "success" => true,
                "message" => "Web-GUI recreated successfully with new configuration"
            ]);
        } else {
            throw new Exception("Failed to restart Web-GUI: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function testTelemetryPingHandler() {
    require_once __DIR__ . '/includes/telemetry.php';
    
    try {
        // Check if telemetry is enabled
        if (!isTelemetryEnabled()) {
            echo json_encode([
                "success" => false,
                "message" => "Telemetry is not enabled. Please enable it first."
            ]);
            return;
        }
        
        // Send the ping
        $result = sendTelemetryPing();
        
        if ($result['success']) {
            echo json_encode([
                "success" => true,
                "message" => "Telemetry ping sent successfully! Check your telemetry server logs."
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => $result['message']
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function executeDockerCommandAPI() {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['command'])) {
            throw new Exception("Command is required");
        }
        
        $command = $input['command'];
        
        // Security: Only allow specific docker commands
        $allowedCommands = ['restart', 'start', 'stop', 'logs'];
        $commandParts = explode(' ', $command);
        
        if (!in_array($commandParts[0], $allowedCommands)) {
            throw new Exception("Command not allowed");
        }
        
        exec("docker " . escapeshellcmd($command) . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode([
                "success" => true,
                "message" => "Command executed successfully",
                "output" => implode("\n", $output)
            ]);
        } else {
            throw new Exception("Command failed: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getContainerLogs() {
    try {
        $container = $_GET['container'] ?? '';
        $lines = $_GET['lines'] ?? 100;
        
        if (empty($container)) {
            throw new Exception("Container name is required");
        }
        
        exec("docker logs --tail " . intval($lines) . " " . escapeshellarg($container) . " 2>&1", $output, $returnCode);
        
        if ($returnCode === 0) {
            echo json_encode([
                "success" => true,
                "logs" => implode("\n", $output)
            ]);
        } else {
            throw new Exception("Failed to get logs: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function exportDatabase($db) {
    // Immediate error logging to debug
    error_log("exportDatabase called with site_id: " . ($_GET['site_id'] ?? 'NONE'));
    
    try {
        $siteId = $_GET['site_id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if (($site['db_type'] ?? 'shared') !== 'dedicated') {
            throw new Exception("This site uses a shared database. Export not available.");
        }
        
        $containerName = $site['container_name'] . '_db';
        $timestamp = date('Y-m-d_H-i-s');
        $siteName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $site['name']);
        $filename = "backup_{$siteName}_{$timestamp}.sql";
        $backupPath = "/app/data/backups/{$filename}";
        
        // Create backups directory if it doesn't exist
        if (!is_dir('/app/data/backups')) {
            mkdir('/app/data/backups', 0755, true);
        }
        
        // Export database using mysqldump
        $password = $site['db_password'] ?? '';
        
        // Build command with proper escaping (use full path to docker)
        // Note: Don't use escapeshellarg on password inside sh -c quotes
        // Note: MariaDB uses 'mariadb-dump' not 'mysqldump'
        $cmd = sprintf(
            "/usr/bin/docker exec %s sh -c \"MYSQL_PWD=%s mariadb-dump -u wordpress wordpress\" > %s 2>&1",
            escapeshellarg($containerName),
            $password, // Don't escape - it's inside double quotes
            escapeshellarg($backupPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        // Debug info (log to server and include in response)
        error_log("Export command: " . $cmd);
        error_log("Return code: " . $returnCode);
        error_log("Output: " . implode("\n", $output));
        error_log("Backup path: " . $backupPath);
        error_log("File exists: " . (file_exists($backupPath) ? 'yes' : 'no'));
        
        $debugCommand = str_replace(escapeshellarg($password), '[PASSWORD]', $cmd);
        
        if ($returnCode === 0 && file_exists($backupPath) && filesize($backupPath) > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Database exported successfully",
                "file" => $filename,
                "size" => filesize($backupPath),
                "download_url" => "/download.php?file=" . urlencode($filename)
            ]);
        } else {
            $errorMsg = "Export failed. ";
            $debugInfo = [
                'return_code' => $returnCode,
                'container' => $containerName,
                'backup_path' => $backupPath,
                'file_exists' => file_exists($backupPath),
                'file_size' => file_exists($backupPath) ? filesize($backupPath) : 0,
                'output' => implode("\n", $output),
                'command' => $debugCommand,
                'has_password' => !empty($password)
            ];
            
            if (!empty($output)) {
                $errorMsg .= "Error: " . implode("\n", $output);
            }
            if (!file_exists($backupPath)) {
                $errorMsg .= " Backup file was not created.";
            } elseif (filesize($backupPath) === 0) {
                $errorMsg .= " Backup file is empty.";
            }
            
            // Return detailed error
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => $errorMsg,
                "debug" => $debugInfo
            ]);
            return;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getDatabaseStats($db) {
    try {
        $siteId = $_GET['site_id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if (($site['db_type'] ?? 'shared') !== 'dedicated') {
            throw new Exception("This site uses a shared database. Stats not available.");
        }
        
        $containerName = $site['container_name'] . '_db';
        
        // Get database size and table count
        $sqlCommand = "SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB',
            COUNT(*) AS 'Tables'
            FROM information_schema.TABLES 
            WHERE table_schema = 'wordpress'";
        
        $password = $site['db_password'] ?? '';
        // Use mariadb instead of mysql, and full path to docker
        $command = sprintf(
            "/usr/bin/docker exec %s sh -c \"MYSQL_PWD=%s mariadb -u wordpress -e %s\" 2>&1",
            escapeshellarg($containerName),
            $password,
            escapeshellarg($sqlCommand)
        );
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            $stats = "Database Statistics:\n\n";
            $stats .= implode("\n", $output);
            
            echo json_encode([
                "success" => true,
                "stats" => $stats
            ]);
        } else {
            throw new Exception("Failed to get database stats: " . implode("\n", $output));
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function getSiteContainers($db, $siteId) {
    try {
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        
        // Get all containers related to this site
        $cmd = "docker ps -a --filter name=" . escapeshellarg($containerName) . " --format '{{.Names}}|{{.Image}}|{{.Status}}|{{.State}}' 2>&1";
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to get containers: " . implode("\n", $output));
        }
        
        $containers = [];
        foreach ($output as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                // Extract uptime from status (e.g., "Up 6 minutes")
                $uptime = '';
                if (preg_match('/Up (.+?)(?:\s+\(|$)/', $parts[2], $matches)) {
                    $uptime = $matches[1];
                }
                
                $containers[] = [
                    'name' => $parts[0],
                    'image' => $parts[1],
                    'status' => strtolower($parts[3]),
                    'uptime' => $uptime
                ];
            }
        }
        
        ob_clean();
        echo json_encode([
            "success" => true,
            "containers" => $containers
        ]);
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

function generateDbToken($db) {
    try {
        require_once 'includes/db-token.php';
        
        $siteId = $_GET['site_id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Check if site has a database
        $dbType = $site['db_type'] ?? 'none';
        $siteType = $site['type'];
        
        // Check if site has database access
        $hasDatabase = false;
        if ($siteType === 'mariadb') {
            // MariaDB instances are databases themselves
            $hasDatabase = true;
        } elseif ($siteType === 'wordpress' && $dbType === 'dedicated') {
            $hasDatabase = true;
        } elseif ($siteType === 'php' && in_array($dbType, ['mysql', 'postgresql'])) {
            $hasDatabase = true;
        } elseif ($siteType === 'laravel' && in_array($dbType, ['mysql', 'postgresql'])) {
            $hasDatabase = true;
        }
        
        if (!$hasDatabase) {
            throw new Exception("This site does not have database access");
        }
        
        // Create tables if they don't exist
        createDatabaseTokenTables($db);
        
        // Clean up old tokens
        cleanupExpiredTokens($db);
        
        // Generate token
        $currentUser = getCurrentUser();
        $token = generateDatabaseToken($db, $siteId, $currentUser['id']);
        
        echo json_encode([
            "success" => true,
            "token" => $token,
            "expires_in" => DB_TOKEN_EXPIRY
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}

// ============================================
// User Management Handlers
// ============================================

function createUserHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $canCreateSites = isset($_POST['can_create_sites']) ? 1 : 0;
        
        if (empty($username) || empty($password)) {
            throw new Exception("Username and password are required");
        }
        
        $result = createUser($username, $password, $email);
        
        if ($result['success']) {
            // Update role and permissions
            updateUser($result['user_id'], [
                'role' => $role,
                'can_create_sites' => $canCreateSites
            ]);
            
            logAudit('user_created', 'user', $result['user_id'], ['username' => $username]);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getUserHandler() {
    try {
        $userId = $_GET['id'] ?? '';
        
        // If no ID provided, return current user
        if (empty($userId)) {
            $user = getCurrentUser();
            if (!$user) {
                throw new Exception("Not logged in");
            }
            echo json_encode(['success' => true, 'user' => $user]);
            return;
        }
        
        // Admin only for viewing other users
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
            return;
        }
        
        $user = getUserById($userId);
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        echo json_encode(['success' => true, 'user' => $user]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateUserHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_POST['user_id'] ?? '';
        $email = $_POST['email'] ?? null;
        $role = $_POST['role'] ?? null;
        $canCreateSites = isset($_POST['can_create_sites']) ? 1 : 0;
        
        if (empty($userId)) {
            throw new Exception("User ID is required");
        }
        
        $data = [];
        if ($email !== null) $data['email'] = $email;
        if ($role !== null) $data['role'] = $role;
        $data['can_create_sites'] = $canCreateSites;
        
        $result = updateUser($userId, $data);
        
        if ($result['success']) {
            logAudit('user_updated', 'user', $userId, $data);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteUserHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_GET['id'] ?? '';
        
        if (empty($userId)) {
            throw new Exception("User ID is required");
        }
        
        $result = deleteUser($userId);
        
        if ($result['success']) {
            logAudit('user_deleted', 'user', $userId);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function grantSitePermissionHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_POST['user_id'] ?? '';
        $siteId = $_POST['site_id'] ?? '';
        $permission = $_POST['permission'] ?? 'view';
        
        if (empty($userId) || empty($siteId)) {
            throw new Exception("User ID and Site ID are required");
        }
        
        $result = grantSitePermission($userId, $siteId, $permission);
        
        if ($result['success']) {
            logAudit('permission_granted', 'site', $siteId, ['user_id' => $userId, 'permission' => $permission]);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function revokeSitePermissionHandler() {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_POST['user_id'] ?? '';
        $siteId = $_POST['site_id'] ?? '';
        
        if (empty($userId) || empty($siteId)) {
            throw new Exception("User ID and Site ID are required");
        }
        
        $result = revokeSitePermission($userId, $siteId);
        
        if ($result['success']) {
            logAudit('permission_revoked', 'site', $siteId, ['user_id' => $userId]);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getUserPermissionsHandler($db) {
    // Admin only
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
        return;
    }
    
    try {
        $userId = $_GET['user_id'] ?? '';
        
        if (empty($userId)) {
            throw new Exception("User ID is required");
        }
        
        // Get all sites with user permissions - use main database
        $stmt = $db->prepare("
            SELECT sp.site_id, sp.permission_level as permission, s.name as site_name, s.domain
            FROM site_permissions sp
            JOIN sites s ON sp.site_id = s.id
            WHERE sp.user_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'permissions' => $permissions]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// 2FA Handlers
// ============================================

function setup2FAHandler() {
    try {
        $totp = new TOTP();
        $secret = $totp->generateSecret();
        $currentUser = getCurrentUser();
        
        $provisioningUri = $totp->getProvisioningUri($currentUser['username'], $secret);
        
        // Generate QR code directly and return as data URL
        $qrCodeDataUrl = generateQRCodeDataUrl($provisioningUri);
        
        // Store secret temporarily in session
        $_SESSION['2fa_setup_secret'] = $secret;
        
        echo json_encode([
            'success' => true,
            'secret' => $secret,
            'qr_code_url' => $qrCodeDataUrl,
            'provisioning_uri' => $provisioningUri
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function generateQRCodeDataUrl($data) {
    $size = 250;
    $encodedData = urlencode($data);
    
    // Try multiple APIs
    $apis = [
        "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encodedData}",
        "https://quickchart.io/qr?text={$encodedData}&size={$size}",
        "https://chart.googleapis.com/chart?chs={$size}x{$size}&cht=qr&chl={$encodedData}"
    ];
    
    foreach ($apis as $url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);
        
        $imageData = @file_get_contents($url, false, $context);
        
        if ($imageData && strlen($imageData) > 100) {
            // Convert to base64 data URL
            $base64 = base64_encode($imageData);
            return 'data:image/png;base64,' . $base64;
        }
    }
    
    // Fallback: return empty data URL
    return '';
}

function verify2FASetupHandler() {
    try {
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['2fa_setup_secret'] ?? '';
        
        if (empty($secret)) {
            throw new Exception("No 2FA setup in progress");
        }
        
        $totp = new TOTP();
        if (!$totp->verifyCode($secret, $code)) {
            throw new Exception("Invalid verification code");
        }
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function enable2FAHandler() {
    try {
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['2fa_setup_secret'] ?? '';
        $currentUser = getCurrentUser();
        
        if (empty($secret)) {
            throw new Exception("No 2FA setup in progress");
        }
        
        $totp = new TOTP();
        if (!$totp->verifyCode($secret, $code)) {
            throw new Exception("Invalid verification code");
        }
        
        // Generate backup codes
        $backupCodes = generateBackupCodes();
        
        // Enable 2FA
        $result = enable2FA($currentUser['id'], $secret, $backupCodes);
        
        if ($result['success']) {
            unset($_SESSION['2fa_setup_secret']);
            echo json_encode([
                'success' => true,
                'backup_codes' => $backupCodes
            ]);
        } else {
            throw new Exception($result['error']);
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function disable2FAHandler() {
    try {
        $password = $_POST['password'] ?? '';
        $currentUser = getCurrentUser();
        
        if (empty($password)) {
            throw new Exception("Password is required to disable 2FA");
        }
        
        // Verify password
        $result = authenticateUser($currentUser['username'], $password);
        if (!$result['success']) {
            throw new Exception("Invalid password");
        }
        
        $result = disable2FA($currentUser['id']);
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// Redis Management Handlers
// ============================================

function createRedisContainer($site, $db) {
    $containerName = $site['container_name'];
    $redisContainerName = $containerName . '_redis';
    $networkName = 'wharftales_wharftales';
    
    // Check if Redis container already exists
    exec("docker ps -a --filter name=" . escapeshellarg($redisContainerName) . " --format '{{.Names}}' 2>&1", $checkOutput, $checkCode);
    
    if ($checkCode === 0 && !empty($checkOutput) && trim($checkOutput[0]) === $redisContainerName) {
        // Already exists, just update database
        $stmt = $db->prepare("UPDATE sites SET redis_enabled = 1, redis_host = ?, redis_port = 6379 WHERE id = ?");
        $stmt->execute([$redisContainerName, $site['id']]);
        return;
    }
    
    // Create Redis container
    $createCommand = sprintf(
        "docker run -d --name %s --network %s --restart unless-stopped redis:alpine",
        escapeshellarg($redisContainerName),
        escapeshellarg($networkName)
    );
    
    exec($createCommand . " 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        // Update database
        $stmt = $db->prepare("UPDATE sites SET redis_enabled = 1, redis_host = ?, redis_port = 6379 WHERE id = ?");
        $stmt->execute([$redisContainerName, $site['id']]);
        
        logAudit('redis_enabled', 'site', $site['id']);
    }
}

function enableRedisHandler($db) {
    try {
        $siteId = $_GET['id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        // Check if user has access to this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $site = getSiteById($db, $siteId);
        
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        $redisContainerName = $containerName . '_redis';
        
        // Check if Redis container already exists
        exec("docker ps -a --filter name=" . escapeshellarg($redisContainerName) . " --format '{{.Names}}' 2>&1", $checkOutput, $checkCode);
        
        if ($checkCode === 0 && !empty($checkOutput) && trim($checkOutput[0]) === $redisContainerName) {
            echo json_encode(['success' => true, 'message' => 'Redis is already enabled']);
            return;
        }
        
        // Create Redis container
        $networkName = 'wharftales_wharftales';
        $createCommand = sprintf(
            "docker run -d --name %s --network %s --restart unless-stopped redis:alpine",
            escapeshellarg($redisContainerName),
            escapeshellarg($networkName)
        );
        
        exec($createCommand . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to create Redis container: " . implode("\n", $output));
        }
        
        // Update database
        $stmt = $db->prepare("UPDATE sites SET redis_enabled = 1, redis_host = ?, redis_port = 6379 WHERE id = ?");
        $stmt->execute([$redisContainerName, $siteId]);
        
        logAudit('redis_enabled', 'site', $siteId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Redis enabled successfully',
            'redis_host' => $redisContainerName
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function disableRedisHandler($db) {
    try {
        $siteId = $_GET['id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        // Check if user has access to this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $site = getSiteById($db, $siteId);
        
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        $redisContainerName = $containerName . '_redis';
        
        // Stop and remove Redis container
        exec("docker stop " . escapeshellarg($redisContainerName) . " 2>&1", $stopOutput, $stopCode);
        exec("docker rm " . escapeshellarg($redisContainerName) . " 2>&1", $rmOutput, $rmCode);
        
        // Update database
        $stmt = $db->prepare("UPDATE sites SET redis_enabled = 0, redis_host = NULL WHERE id = ?");
        $stmt->execute([$siteId]);
        
        logAudit('redis_disabled', 'site', $siteId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Redis disabled successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function flushRedisHandler($db) {
    try {
        $siteId = $_GET['id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        // Check if user has access to this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $site = getSiteById($db, $siteId);
        
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        $redisContainerName = $containerName . '_redis';
        
        // Flush Redis cache
        exec("docker exec " . escapeshellarg($redisContainerName) . " redis-cli FLUSHALL 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to flush Redis: " . implode("\n", $output));
        }
        
        logAudit('redis_flushed', 'site', $siteId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Redis cache flushed successfully',
            'output' => implode("\n", $output)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function restartRedisHandler($db) {
    try {
        $siteId = $_GET['id'] ?? '';
        
        if (empty($siteId)) {
            throw new Exception("Site ID is required");
        }
        
        // Check if user has access to this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        $site = getSiteById($db, $siteId);
        
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        $containerName = $site['container_name'];
        $redisContainerName = $containerName . '_redis';
        
        // Restart Redis container
        exec("docker restart " . escapeshellarg($redisContainerName) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Failed to restart Redis: " . implode("\n", $output));
        }
        
        logAudit('redis_restarted', 'site', $siteId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Redis restarted successfully',
            'output' => implode("\n", $output)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function updateSettingHandler($db) {
    try {
        // Only admins can update settings
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
            return;
        }
        
        $input = json_decode(file_get_contents("php://input"), true);
        $key = $input['key'] ?? '';
        $value = $input['value'] ?? '';
        
        if (empty($key)) {
            throw new Exception("Setting key is required");
        }
        
        $result = setSetting($db, $key, $value);
        
        if ($result) {
            // If dashboard SSL or domain settings changed, update Traefik configuration
            if ($key === 'dashboard_ssl' || $key === 'dashboard_domain') {
                $dashboardDomain = getSetting($db, 'dashboard_domain', '');
                $dashboardSSL = getSetting($db, 'dashboard_ssl', '0');
                
                // Only proceed if both domain AND SSL are configured
                // (no need to restart container if SSL is not enabled)
                if (!empty($dashboardDomain) && $dashboardSSL === '1') {
                    try {
                        updateDashboardTraefikConfig($dashboardDomain, $dashboardSSL === '1');
                        restartTraefik();
                        
                        // Check if setup is completed
                        $setupCompleted = getSetting($db, 'setup_completed', '0');
                        
                        if ($setupCompleted === '1') {
                            // Setup already completed - restart immediately
                            register_shutdown_function(function() {
                                sleep(1);
                                // Stop and remove the container first, then recreate it
                                exec('cd /opt/wharftales && docker-compose stop web-gui && docker-compose rm -f web-gui && docker-compose up -d web-gui > /dev/null 2>&1 &');
                            });
                        } else {
                            // Setup wizard in progress - flag for restart after wizard completes
                            // Only set pending restart if SSL is actually enabled
                            setSetting($db, 'pending_container_restart', '1');
                        }
                        
                        error_log("Dashboard Traefik configuration updated successfully (SSL enabled)");
                    } catch (Exception $e) {
                        error_log("Failed to update Traefik configuration: " . $e->getMessage());
                        // Don't fail the setting update if Traefik update fails
                    }
                } else if (!empty($dashboardDomain) && $dashboardSSL === '0') {
                    // Domain set but SSL not enabled - just update Traefik config without restart
                    try {
                        updateDashboardTraefikConfig($dashboardDomain, false);
                        restartTraefik();
                        error_log("Dashboard Traefik configuration updated successfully (no SSL)");
                    } catch (Exception $e) {
                        error_log("Failed to update Traefik configuration: " . $e->getMessage());
                    }
                }
            }
            
            // If setup is being marked as completed, check if container restart is pending
            if ($key === 'setup_completed' && $value === '1') {
                $pendingRestart = getSetting($db, 'pending_container_restart', '0');
                if ($pendingRestart === '1') {
                    // Clear the flag and schedule restart
                    setSetting($db, 'pending_container_restart', '0');
                    register_shutdown_function(function() {
                        sleep(2); // Wait a bit longer to ensure wizard completion page shows
                        // Stop and remove the container first, then recreate it
                        exec('cd /opt/wharftales && docker-compose stop web-gui && docker-compose rm -f web-gui && docker-compose up -d web-gui > /dev/null 2>&1 &');
                    });
                }
            }
            
            logAudit('setting_updated', 'setting', null, ['key' => $key]);
            
            // Return special message if dashboard SSL/domain was changed
            if ($key === 'dashboard_ssl' || $key === 'dashboard_domain') {
                $setupCompleted = getSetting($db, 'setup_completed', '0');
                echo json_encode([
                    'success' => true, 
                    'message' => 'Setting updated successfully',
                    'requires_restart' => $setupCompleted === '1', // Only true if already completed
                    'restart_delay' => 15,
                    'deferred_restart' => $setupCompleted !== '1' // True during wizard
                ]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
            }
        } else {
            throw new Exception("Failed to update setting");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function changePHPVersionHandler($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!$input) {
            throw new Exception("Invalid JSON data");
        }
        
        $siteId = $input['site_id'] ?? null;
        $newVersion = $input['php_version'] ?? null;
        
        if (!$siteId || !$newVersion) {
            throw new Exception("Site ID and PHP version are required");
        }
        
        // Validate PHP version (only releases with official Docker images)
        $validVersions = ['7.4', '8.0', '8.1', '8.2', '8.3', '8.4'];
        if (!in_array($newVersion, $validVersions)) {
            throw new Exception("Invalid PHP version. Supported: " . implode(', ', $validVersions));
        }
        
        // Get site
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Check permission
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            throw new Exception("You don't have permission to manage this site");
        }
        
        // Update database
        $stmt = $db->prepare("UPDATE sites SET php_version = ? WHERE id = ?");
        $stmt->execute([$newVersion, $siteId]);
        
        // Get updated site
        $site = getSiteById($db, $siteId);
        $site['php_version'] = $newVersion; // Ensure it's set
        
        // Regenerate docker-compose with new PHP version
        $basePath = getAppsBasePath();
        $composePath = "$basePath/{$site['type']}/sites/{$site['container_name']}/docker-compose.yml";
        
        $newCompose = '';
        if ($site['type'] === 'wordpress') {
            $newCompose = createWordPressDockerCompose($site, []);
        } elseif ($site['type'] === 'php') {
            $newCompose = createPHPDockerCompose($site, []);
        } elseif ($site['type'] === 'laravel') {
            $newCompose = createLaravelDockerCompose($site, []);
        } else {
            throw new Exception("PHP version switching not supported for this site type");
        }
        
        if ($newCompose) {
            // Save to database
            saveComposeConfig($db, $newCompose, $_SESSION['user_id'], $siteId);
            
            // Write to file
            file_put_contents($composePath, $newCompose);
            
            // Stop and remove the old container first
            executeDockerCompose($composePath, "down");
            
            // Recreate container with new PHP version (--build forces image rebuild)
            $result = executeDockerCompose($composePath, "up -d --build --force-recreate");
            
            if ($result['success']) {
                logAudit('php_version_changed', 'site', $siteId, [
                    'new_version' => $newVersion,
                    'site_name' => $site['name']
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => "PHP version changed to {$newVersion} successfully!"
                ]);
            } else {
                throw new Exception("Failed to recreate container: " . $result['output']);
            }
        } else {
            throw new Exception("Failed to generate docker-compose configuration");
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Check for GitHub updates
 */
function checkGitHubUpdatesHandler($db, $siteId) {
    try {
        // Check permissions
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            http_response_code(403);
            throw new Exception("You don't have permission to manage this site");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if (empty($site['github_repo'])) {
            throw new Exception("No GitHub repository configured for this site");
        }
        
        $containerName = $site['container_name'];
        $result = checkGitHubUpdates($site, $containerName);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'has_updates' => $result['has_updates'],
                'local_commit' => $result['local_commit'],
                'remote_commit' => $result['remote_commit']
            ]);
        } else {
            throw new Exception($result['message']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Pull latest changes from GitHub
 */
function pullFromGitHubHandler($db, $siteId) {
    try {
        // Check permissions
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            http_response_code(403);
            throw new Exception("You don't have permission to manage this site");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if (empty($site['github_repo'])) {
            throw new Exception("No GitHub repository configured for this site");
        }
        
        $containerName = $site['container_name'];
        $result = deployFromGitHub($site, $containerName);
        
        if ($result['success']) {
            // Update database with new commit hash and timestamp
            if (!empty($result['commit_hash'])) {
                $stmt = $db->prepare("UPDATE sites SET github_last_commit = ?, github_last_pull = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$result['commit_hash'], $siteId]);
            }
            
            // Run composer install if needed
            runComposerInstall($containerName);
            
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'commit_hash' => $result['commit_hash'] ?? null,
                'pull_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            throw new Exception($result['message']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Force pull from GitHub (override all local changes)
 */
function forcePullFromGitHubHandler($db, $siteId) {
    try {
        // Check permissions
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            http_response_code(403);
            throw new Exception("You don't have permission to manage this site");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if (empty($site['github_repo'])) {
            throw new Exception("No GitHub repository configured for this site");
        }
        
        $containerName = $site['container_name'];
        $result = forceDeployFromGitHub($site, $containerName);
        
        if ($result['success']) {
            // Update database with new commit hash and timestamp
            if (!empty($result['commit_hash'])) {
                $stmt = $db->prepare("UPDATE sites SET github_last_commit = ?, github_last_pull = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$result['commit_hash'], $siteId]);
            }
            
            // Run composer install if needed
            runComposerInstall($containerName);
            
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'commit_hash' => $result['commit_hash'] ?? null,
                'pull_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            throw new Exception($result['message']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Build Laravel application
 */
function buildLaravelHandler($db, $siteId) {
    try {
        // Check permissions
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            http_response_code(403);
            throw new Exception("You don't have permission to manage this site");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if ($site['type'] !== 'laravel') {
            throw new Exception("This action is only available for Laravel sites");
        }
        
        $containerName = $site['container_name'];
        $result = runLaravelBuild($containerName, $site['type']);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'details' => $result['details'] ?? ''
            ]);
        } else {
            throw new Exception($result['message']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Fix Laravel permissions
 */
function fixLaravelPermissionsHandler($db) {
    try {
        // Get request body
        $input = json_decode(file_get_contents("php://input"), true);
        $siteId = $input['site_id'] ?? null;
        
        if (!$siteId) {
            throw new Exception("Site ID is required");
        }
        
        // Check permissions
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            http_response_code(403);
            throw new Exception("You don't have permission to manage this site");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        if ($site['type'] !== 'laravel') {
            throw new Exception("This action is only available for Laravel sites");
        }
        
        $containerName = $site['container_name'];
        $result = fixLaravelPermissions($containerName);
        
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'output' => $result['details'] ?? ''
            ]);
        } else {
            throw new Exception($result['message']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Trigger system update
 */
function triggerUpdateHandler($db) {
    try {
        // Only admins can trigger updates
        if (!isAdmin()) {
            ob_clean();
            http_response_code(403);
            throw new Exception("Only administrators can trigger updates");
        }
        
        // Get request body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $skipBackup = $data['skip_backup'] ?? false;
        
        $result = triggerUpdate($skipBackup);
        
        ob_clean();
        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'log_file' => $result['log_file']
            ]);
        } else {
            throw new Exception($result['error']);
        }
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Check update status
 */
function checkUpdateStatusHandler($db) {
    try {
        $status = getUpdateStatus();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'status' => $status
        ]);
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Reset update lock
 */
function resetUpdateLockHandler($db) {
    try {
        // Only admins can reset update lock
        if (!isAdmin()) {
            ob_clean();
            http_response_code(403);
            throw new Exception("Only administrators can reset update lock");
        }
        
        // Reset the update in progress flag
        setSetting($db, 'update_in_progress', '0');
        setSetting($db, 'update_started_at', '');
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Update lock reset successfully'
        ]);
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get update logs
 */
function getUpdateLogsHandler($db) {
    try {
        $logFile = getSetting($db, 'last_update_log', '');
        $logs = '';
        
        if ($logFile && file_exists($logFile)) {
            $logs = file_get_contents($logFile);
        } else {
            // Try to get the most recent log file
            $logDir = '/opt/wharftales/logs';
            if (is_dir($logDir)) {
                $logFiles = glob($logDir . '/upgrade-*.log');
                if (!empty($logFiles)) {
                    // Sort by modification time, newest first
                    usort($logFiles, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $logs = file_get_contents($logFiles[0]);
                    $logFile = $logFiles[0];
                }
            }
        }
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'log_file' => $logFile
        ]);
        
    } catch (Exception $e) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update custom database settings
 */
function updateCustomDatabaseHandler($db) {
    try {
        // Get request body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['id'])) {
            throw new Exception("Missing required data");
        }
        
        $siteId = $data['id'];
        
        // Check if user has permission to manage this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            throw new Exception("You don't have permission to manage this site");
        }
        
        // Get the site
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Validate that this is a custom database site
        if ($site['db_type'] !== 'custom') {
            throw new Exception("This site does not use a custom database");
        }
        
        // Update database credentials
        $stmt = $db->prepare("UPDATE sites SET db_host = ?, db_port = ?, db_name = ?, db_user = ?, db_password = ? WHERE id = ?");
        $stmt->execute([
            $data['db_host'] ?? '',
            $data['db_port'] ?? 3306,
            $data['db_name'] ?? '',
            $data['db_user'] ?? '',
            $data['db_password'] ?? '',
            $siteId
        ]);
        
        // Regenerate docker-compose with new database settings
        $site['db_host'] = $data['db_host'];
        $site['db_port'] = $data['db_port'];
        $site['db_name'] = $data['db_name'];
        $site['db_user'] = $data['db_user'];
        $site['db_password'] = $data['db_password'];
        
        $config = json_decode($site['config'], true) ?: [];
        $config['wp_db_type'] = 'custom';
        $config['wp_db_host'] = $data['db_host'];
        $config['wp_db_port'] = $data['db_port'];
        $config['wp_db_name'] = $data['db_name'];
        $config['wp_db_user'] = $data['db_user'];
        $config['wp_db_password'] = $data['db_password'];
        
        // Regenerate docker-compose
        $dbPassword = null;
        $wpCompose = createWordPressDockerCompose($site, $config, $dbPassword);
        
        $basePath = getAppsBasePath();
        $composePath = "$basePath/wordpress/sites/{$site['container_name']}/docker-compose.yml";
        file_put_contents($composePath, $wpCompose);
        
        // Save to database
        saveComposeConfig($db, $wpCompose, $_SESSION['user_id'], $siteId);
        
        // Restart container
        executeDockerCompose($composePath, "down");
        executeDockerCompose($composePath, "up -d");
        
        echo json_encode([
            'success' => true,
            'message' => 'Database settings updated and container restarted'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Toggle external network access for MariaDB instances
 */
function toggleMariaDBExternalAccessHandler($db) {
    try {
        // Get request body
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data || !isset($data['id']) || !isset($data['enable'])) {
            throw new Exception("Missing required data");
        }
        
        $siteId = $data['id'];
        $enable = $data['enable'];
        $port = $data['port'] ?? 3306;
        
        // Check if user has permission to manage this site
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            http_response_code(403);
            throw new Exception("You don't have permission to manage this site");
        }
        
        // Get the site
        $site = getSiteById($db, $siteId);
        if (!$site) {
            throw new Exception("Site not found");
        }
        
        // Validate that this is a MariaDB instance
        if ($site['type'] !== 'mariadb') {
            throw new Exception("This action is only available for MariaDB instances");
        }
        
        // Update database port
        if ($enable) {
            // Validate port
            if ($port < 1024 || $port > 65535) {
                throw new Exception("Port must be between 1024 and 65535");
            }
            
            // Check for port conflicts
            $portConflict = checkMariaDBPortConflict($db, $port, $siteId);
            if ($portConflict) {
                throw new Exception("Port $port is already in use by another MariaDB instance: {$portConflict['name']}. Please choose a different port.");
            }
            
            // Update site record with port
            $stmt = $db->prepare("UPDATE sites SET db_port = ? WHERE id = ?");
            $stmt->execute([$port, $siteId]);
        } else {
            // Remove port exposure
            $stmt = $db->prepare("UPDATE sites SET db_port = NULL WHERE id = ?");
            $stmt->execute([$siteId]);
        }
        
        // Regenerate docker-compose with or without port exposure
        $config = json_decode($site['config'], true) ?: [];
        $config['mariadb_expose'] = $enable;
        $config['mariadb_port'] = $enable ? $port : null;
        
        $dbCredentials = json_decode($site['db_password'] ?? '{}', true);
        $rootPassword = $dbCredentials['root'] ?? '';
        $userPassword = $dbCredentials['user'] ?? '';
        
        // Update site array for docker-compose generation
        $site['db_port'] = $enable ? $port : null;
        
        $mariadbCompose = createMariaDBDockerCompose($site, $config, $rootPassword, $userPassword);
        
        $basePath = getAppsBasePath();
        $composePath = "$basePath/mariadb/sites/{$site['container_name']}/docker-compose.yml";
        file_put_contents($composePath, $mariadbCompose);
        
        // Save to database
        saveComposeConfig($db, $mariadbCompose, $_SESSION['user_id'], $siteId);
        
        // Restart container
        executeDockerCompose($composePath, "down");
        executeDockerCompose($composePath, "up -d");
        
        echo json_encode([
            'success' => true,
            'message' => $enable ? "External access enabled on port $port" : "External access disabled"
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Check if a port is already in use by another MariaDB instance
 * @param PDO $db Database connection
 * @param int $port Port to check
 * @param int|null $excludeSiteId Site ID to exclude from check (for updates)
 * @return array|false Returns site info if conflict found, false otherwise
 */
function checkMariaDBPortConflict($db, $port, $excludeSiteId = null) {
    try {
        $query = "SELECT id, name, container_name FROM sites WHERE type = 'mariadb' AND db_port = ?";
        $params = [$port];
        
        if ($excludeSiteId !== null) {
            $query .= " AND id != ?";
            $params[] = $excludeSiteId;
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Execute arbitrary Laravel Artisan commands
 */
function executeLaravelCommandAPI($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['id']) || !isset($input['command'])) {
            throw new Exception("Site ID and command are required");
        }
        
        $siteId = $input['id'];
        $artisanCommand = $input['command'];
        
        // Check permission
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            throw new Exception("Permission denied");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site || $site['type'] !== 'laravel') {
            throw new Exception("Invalid site or not a Laravel application");
        }
        
        $containerName = $site['container_name'];
        
        // Basic sanitization - allow alphanumeric, dashes, colons, spaces, dots, equals, slashes
        if (!preg_match('/^[a-zA-Z0-9\-\:\_\s\.\=\/]+$/', $artisanCommand)) {
            throw new Exception("Invalid command characters");
        }
        
        // Use bash -c to ensure we are in the correct directory and environment
        // We also check if artisan exists first to give a better error message
        $wrapperCmd = "cd /var/www/html && if [ -f artisan ]; then php artisan " . $artisanCommand . "; else echo 'Error: artisan file not found in /var/www/html. Please ensure you have installed dependencies (Composer Install) and your application is mounted correctly.'; exit 1; fi";
        
        // Determine correct web user
        $webUser = getContainerWebUser($containerName);
        
        $cmd = "docker exec -u {$webUser} " . escapeshellarg($containerName) . " bash -c " . escapeshellarg($wrapperCmd) . " 2>&1";
        
        exec($cmd, $output, $returnCode);
        
        $outputStr = implode("\n", $output);
        // Ensure UTF-8
        $outputStr = mb_convert_encoding($outputStr, 'UTF-8', 'UTF-8');
        
        $json = json_encode([
            "success" => $returnCode === 0,
            "message" => $returnCode === 0 ? "Command executed successfully" : "Command failed",
            "output" => $outputStr
        ]);
        
        if ($json === false) {
            echo json_encode([
                "success" => false,
                "error" => "JSON encoding failed: " . json_last_error_msg(),
                "output" => "Output contained invalid characters and could not be displayed."
            ]);
        } else {
            echo $json;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        $errorMsg = $e->getMessage();
        $errorMsg = mb_convert_encoding($errorMsg, 'UTF-8', 'UTF-8');
        echo json_encode([
            "success" => false,
            "error" => $errorMsg
        ]);
    }
}

/**
 * Execute arbitrary shell commands for Web Terminal
 */
function executeShellCommandAPI($db) {
    // Increase time limit for potential long commands (npm install, etc)
    set_time_limit(0); ini_set("memory_limit", "512M");
    
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['container']) || !isset($input['command'])) {
            throw new Exception("Container and command are required");
        }
        
        $container = $input['container'];
        $command = $input['command'];
        $siteId = $input['site_id'] ?? null;
        $cwd = $input['cwd'] ?? '/var/www/html';
        
        if (!$siteId) {
             throw new Exception("Site ID is required for security verification");
        }
        
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
             throw new Exception("Permission denied");
        }
        
        // Verify container belongs to site
        $site = getSiteById($db, $siteId);
        if (!$site) throw new Exception("Site not found");
        
        if (strpos($container, $site['container_name']) !== 0) {
             throw new Exception("Container does not belong to the specified site");
        }
        
        // Sanitize CWD to ensure it's a valid path format
        // This is a basic check, the shell command handles the actual directory switching
        if (!str_starts_with($cwd, '/')) {
            $cwd = '/var/www/html';
        }
        
        // Execute command in the container with CWD tracking
        // We wrap the command to capture the exit code and the new CWD
        // Format: (cd $cwd && command) ; echo "___WHARFTALES_CWD:$(pwd)"
        // Note: we don't use -w in docker exec because we want to support 'cd' which changes state for our "session"
        
        // Escape the command but allow it to be a complex shell command
        // We can't use escapeshellarg on the whole thing because we want operators like &&, ||, ;, | to work
        
        // Instead of trying to maintain state inside the container, we simulate it by always starting from $cwd
        // And if the user ran 'cd newpath', we detect that from the output or by checking pwd after
        
        $wrapperCmd = "cd " . escapeshellarg($cwd) . " && " . $command . "; echo \"___WHARFTALES_CWD:$(pwd)\"";
        
        // Determine correct web user
        $webUser = getContainerWebUser($container);
        
        $dockerCmd = "docker exec -u {$webUser} " . escapeshellarg($container) . " bash -c " . escapeshellarg($wrapperCmd) . " 2>&1";
        
        exec($dockerCmd, $output, $returnCode);
        
        $outputStr = implode("\n", $output);
        $newCwd = $cwd; // Default to old CWD if not found
        
        // Extract CWD from output
        if (preg_match('/___WHARFTALES_CWD:(.*)$/m', $outputStr, $matches)) {
            $newCwd = trim($matches[1]);
            // Remove the CWD marker line from output
            $outputStr = str_replace($matches[0], '', $outputStr);
            // Trim trailing newlines
            $outputStr = rtrim($outputStr);
        }
        
        if (strlen($outputStr) > 50000) {
            $outputStr = substr($outputStr, 0, 50000) . "\n... [Output truncated]";
        }
        
        // Ensure UTF-8
        $outputStr = mb_convert_encoding($outputStr, 'UTF-8', 'UTF-8');
        
        $json = json_encode([
            "success" => $returnCode === 0,
            "exit_code" => $returnCode,
            "output" => $outputStr,
            "cwd" => $newCwd
        ]);
        
        if ($json === false) {
             echo json_encode([
                "success" => false,
                "error" => "JSON encoding failed: " . json_last_error_msg(),
                "output" => "Output contained invalid characters and could not be displayed.",
                "cwd" => $cwd
            ]);
        } else {
            echo $json;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        $errorMsg = $e->getMessage();
        $errorMsg = mb_convert_encoding($errorMsg, 'UTF-8', 'UTF-8');
        echo json_encode([
            "success" => false,
            "error" => $errorMsg
        ]);
    }
}

/**
 * Reload PHP-FPM after editing php.ini
 */
function reloadPhpFpmAPI($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['id'])) {
            throw new Exception("Site ID is required");
        }
        
        $siteId = $input['id'];
        
        // Check permission
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            throw new Exception("Permission denied");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site || $site['type'] !== 'laravel') {
            throw new Exception("Invalid site or not a Laravel application");
        }
        
        $containerName = $site['container_name'];
        
        // Determine correct web user
        $webUser = getContainerWebUser($containerName);
        
        // Reload PHP-FPM via supervisorctl (since PHP-FPM runs under supervisor)
        $cmd = "docker exec -u {$webUser} " . escapeshellarg($containerName) . " supervisorctl restart php-fpm 2>&1";
        exec($cmd, $output, $returnCode);
        
        $outputStr = implode("\n", $output);
        $outputStr = mb_convert_encoding($outputStr, 'UTF-8', 'UTF-8');
        
        $success = ($returnCode === 0);
        
        echo json_encode([
            "success" => $success,
            "message" => $success ? "PHP-FPM reloaded successfully" : "Failed to reload PHP-FPM",
            "output" => $outputStr
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        $errorMsg = $e->getMessage();
        $errorMsg = mb_convert_encoding($errorMsg, 'UTF-8', 'UTF-8');
        echo json_encode([
            "success" => false,
            "error" => $errorMsg
        ]);
    }
}

/**
 * Reload Supervisor configuration after editing supervisord.conf
 */
function reloadSupervisorAPI($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($input['id'])) {
            throw new Exception("Site ID is required");
        }
        
        $siteId = $input['id'];
        
        // Check permission
        if (!canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
            throw new Exception("Permission denied");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site || $site['type'] !== 'laravel') {
            throw new Exception("Invalid site or not a Laravel application");
        }
        
        $containerName = $site['container_name'];
        
        // Determine correct web user
        $webUser = getContainerWebUser($containerName);
        
        // Reload supervisor configuration
        $cmd = "docker exec -u {$webUser} " . escapeshellarg($containerName) . " supervisorctl reread 2>&1";
        exec($cmd, $output1, $returnCode1);
        
        $cmd = "docker exec -u {$webUser} " . escapeshellarg($containerName) . " supervisorctl update 2>&1";
        exec($cmd, $output2, $returnCode2);
        
        $output = array_merge($output1, $output2);
        $outputStr = implode("\n", $output);
        $outputStr = mb_convert_encoding($outputStr, 'UTF-8', 'UTF-8');
        
        $success = ($returnCode1 === 0 && $returnCode2 === 0);
        
        echo json_encode([
            "success" => $success,
            "message" => $success ? "Supervisor configuration reloaded successfully" : "Failed to reload Supervisor configuration",
            "output" => $outputStr
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        $errorMsg = $e->getMessage();
        $errorMsg = mb_convert_encoding($errorMsg, 'UTF-8', 'UTF-8');
        echo json_encode([
            "success" => false,
            "error" => $errorMsg
        ]);
    }
}

function installNodeJsHandler($db, $siteId) {
    // Increase time limit
    set_time_limit(0);
    
    try {
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            http_response_code(403);
            throw new Exception("Permission denied");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) throw new Exception("Site not found");
        
        $containerName = $site['container_name'];
        
        // Check if already installed
        exec("docker exec " . escapeshellarg($containerName) . " which npm", $output, $returnVar);
        if ($returnVar === 0) {
             echo json_encode([
                'success' => true,
                'message' => 'Node.js/NPM is already installed'
            ]);
            return;
        }
        
        // Install
        $cmd = "docker exec -u root " . escapeshellarg($containerName) . " sh -c 'curl -fsSL https://deb.nodesource.com/setup_20.x | bash - && apt-get install -y nodejs'";
        exec($cmd . " 2>&1", $output, $returnVar);
        
        $outputStr = implode("\n", $output);
        $outputStr = mb_convert_encoding($outputStr, 'UTF-8', 'UTF-8');
        
        if ($returnVar !== 0) {
            throw new Exception("Installation failed: " . $outputStr);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Node.js & NPM installed successfully',
            'output' => $outputStr
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        $errorMsg = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
        echo json_encode([
            'success' => false,
            'error' => $errorMsg
        ]);
    }
}

/**
 * Rebuild container (docker-compose up -d --build)
 */
function rebuildContainerHandler($db) {
    // Increase time limit for this long operation
    set_time_limit(0); ini_set("memory_limit", "512M");
    ini_set('memory_limit', '512M');
    
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        $siteId = $input['id'] ?? null;
        
        if (!$siteId) {
            throw new Exception("Site ID required");
        }
        
        // Check permission
        if (!canManageSite($_SESSION['user_id'], $siteId)) {
            http_response_code(403);
            throw new Exception("Permission denied");
        }
        
        $site = getSiteById($db, $siteId);
        if (!$site) throw new Exception("Site not found");
        
        // Determine path based on site type - check both possible locations
        $possiblePaths = [
            "/app/apps/" . $site['type'] . "/sites/" . $site['container_name'] . "/docker-compose.yml",
            "/opt/wharftales/apps/" . $site['type'] . "/sites/" . $site['container_name'] . "/docker-compose.yml"
        ];
        
        $composePath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $composePath = $path;
                break;
            }
        }
        
        if (!$composePath) {
            throw new Exception("Configuration file not found. Checked: " . implode(", ", $possiblePaths));
        }
        
        // 1. Stop container first
        $downResult = executeDockerCompose($composePath, "down");
        
        // 2. Rebuild and start
        // We use --build to force rebuild of the image if Dockerfile changed
        $upResult = executeDockerCompose($composePath, "up -d --build");
        
        if ($upResult['success']) {
            $outputStr = "Down: " . $downResult['output'] . "\n\nUp: " . $upResult['output'];
            $outputStr = mb_convert_encoding($outputStr, 'UTF-8', 'UTF-8');
            
            $json = json_encode([
                "success" => true,
                "message" => "Container rebuilt successfully",
                "output" => $outputStr
            ]);
            
            if ($json === false) {
                 echo json_encode([
                    "success" => true,
                    "message" => "Container rebuilt successfully (output hidden due to encoding error)",
                ]);
            } else {
                echo $json;
            }
        } else {
            $errorOutput = $upResult['output'];
            $errorOutput = mb_convert_encoding($errorOutput, 'UTF-8', 'UTF-8');
            throw new Exception("Build failed: " . $errorOutput);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        $errorMsg = $e->getMessage();
        $errorMsg = mb_convert_encoding($errorMsg, 'UTF-8', 'UTF-8');
        echo json_encode([
            "success" => false,
            "error" => $errorMsg
        ]);
    }
}

// Flush ALL output buffer levels to ensure response is sent
while (ob_get_level() > 0) {
    ob_end_flush();
}
?>

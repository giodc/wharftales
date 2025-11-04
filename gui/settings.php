<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/telemetry.php';

// Redirect to SSL URL if dashboard has SSL enabled and request is from :9000
redirectToSSLIfEnabled();

// Require authentication
requireAuth();

$db = initDatabase();
$currentUser = getCurrentUser();

// Get current Let's Encrypt email from settings table (faster) or compose config
$currentEmail = getSetting($db, 'letsencrypt_email', '');

// If not in settings, extract from compose config
if (empty($currentEmail)) {
    $composeConfig = getComposeConfig($db, null); // null = main Traefik config
    
    if ($composeConfig) {
        preg_match('/acme\.email=([^\s"]+)/', $composeConfig['compose_yaml'], $matches);
        $currentEmail = $matches[1] ?? 'admin@example.com';
        
        // Save to settings for next time
        if ($currentEmail && $currentEmail !== 'admin@example.com') {
            setSetting($db, 'letsencrypt_email', $currentEmail);
        }
    } else {
        $currentEmail = 'admin@example.com';
    }
}

// Get custom wildcard domain from settings
$customWildcardDomain = getSetting($db, 'custom_wildcard_domain', '');

// Get dashboard domain settings
$dashboardDomain = getSetting($db, 'dashboard_domain', '');
$dashboardSSL = getSetting($db, 'dashboard_ssl', '0');

// Get telemetry settings
$telemetryEnabled = isTelemetryEnabled();
$installationId = getInstallationId();

$successMessage = '';
$errorMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['letsencrypt_email'])) {
        $newEmail = trim($_POST['letsencrypt_email']);
        
        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                // Check for forbidden domains before attempting update
                if (preg_match('/@(example\.(com|net|org)|test\.com)$/i', $newEmail)) {
                    throw new Exception("Email domain is forbidden by Let's Encrypt (example.com, example.net, example.org, test.com). Please use a real email address.");
                }
                
                // Update email in compose config (database and file)
                updateComposeParameter($db, 'letsencrypt_email', $newEmail, $currentUser['id'], null);
                
                // Also save to settings table for easy access
                setSetting($db, 'letsencrypt_email', $newEmail);
                
                $successMessage = 'Let\'s Encrypt email updated successfully! The acme.json file has been reset. <strong>You must restart Traefik now.</strong>';
                $currentEmail = $newEmail;
                
                // Reload config
                $composeConfig = getComposeConfig($db, null);
            } catch (Exception $e) {
                $errorMessage = 'Failed to update Let\'s Encrypt email: ' . $e->getMessage();
            }
        } else {
            $errorMessage = 'Please enter a valid email address.';
        }
    }
    
    if (isset($_POST['custom_wildcard_domain'])) {
        $newDomain = trim($_POST['custom_wildcard_domain']);
        
        // Validate domain format (should start with a dot for wildcard)
        if (empty($newDomain) || preg_match('/^\.[a-z0-9.-]+$/i', $newDomain)) {
            if (setSetting($db, 'custom_wildcard_domain', $newDomain)) {
                $successMessage = 'Custom wildcard domain updated successfully!';
                $customWildcardDomain = $newDomain;
            } else {
                $errorMessage = 'Failed to update custom wildcard domain.';
            }
        } else {
            $errorMessage = 'Invalid domain format. Use format like: .example.com or .yourdomain.com';
        }
    }
    
    if (isset($_POST['dashboard_domain'])) {
        $newDashboardDomain = trim($_POST['dashboard_domain']);
        $enableSSL = isset($_POST['dashboard_ssl']) ? '1' : '0';
        
        // Validate domain format (should NOT start with a dot, regular domain)
        if (empty($newDashboardDomain) || preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $newDashboardDomain)) {
            // Save settings
            setSetting($db, 'dashboard_domain', $newDashboardDomain);
            setSetting($db, 'dashboard_ssl', $enableSSL);
            
            // Update docker-compose.yml with Traefik labels for web-gui
            $result = updateDashboardTraefikConfig($newDashboardDomain, $enableSSL);
            
            if ($result['success']) {
                $successMessage = 'Dashboard domain updated successfully! <button class="btn btn-sm btn-warning ms-2" onclick="restartWebGui()"><i class="bi bi-arrow-clockwise me-1"></i>Restart Now</button>';
                $dashboardDomain = $newDashboardDomain;
                $dashboardSSL = $enableSSL;
            } else {
                $errorMessage = 'Failed to update dashboard configuration: ' . $result['error'];
            }
        } else {
            $errorMessage = 'Invalid domain format. Use format like: dashboard.example.com';
        }
    }
}

function updateDashboardTraefikConfig($domain, $enableSSL) {
    global $db, $currentUser;
    
    // Get main config from database
    $config = getComposeConfig($db, null);
    
    if (!$config) {
        return ['success' => false, 'error' => 'Main Traefik configuration not found in database'];
    }
    
    $content = $config['compose_yaml'];
    
    if (empty($domain)) {
        // Remove Traefik labels if domain is empty
        $content = preg_replace('/\s+labels:.*?(?=\n\s{4}[a-z]|\n[a-z]|$)/s', '', $content);
        
        // Save to database
        try {
            saveComposeConfig($db, $content, $currentUser['id'], null);
            generateComposeFile($db, null);
            return ['success' => true, 'message' => 'Dashboard domain removed. Restart web-gui to apply changes.'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to save configuration: ' . $e->getMessage()];
        }
    }
    
    // Find the web-gui service section
    if (!preg_match('/web-gui:/', $content)) {
        return ['success' => false, 'error' => 'web-gui service not found in docker-compose.yml'];
    }
    
    // Build Traefik labels
    $labels = "\n    labels:\n";
    $labels .= "      - traefik.enable=true\n";
    $labels .= "      - traefik.http.routers.webgui.rule=Host(`{$domain}`)\n";
    $labels .= "      - traefik.http.routers.webgui.entrypoints=web\n";
    $labels .= "      - traefik.http.services.webgui.loadbalancer.server.port=8080\n";
    
    if ($enableSSL === '1') {
        $labels .= "      - traefik.http.routers.webgui-secure.rule=Host(`{$domain}`)\n";
        $labels .= "      - traefik.http.routers.webgui-secure.entrypoints=websecure\n";
        $labels .= "      - traefik.http.routers.webgui-secure.tls=true\n";
        $labels .= "      - traefik.http.routers.webgui-secure.tls.certresolver=letsencrypt\n";
        $labels .= "      - traefik.http.middlewares.webgui-redirect.redirectscheme.scheme=https\n";
        $labels .= "      - traefik.http.middlewares.webgui-redirect.redirectscheme.permanent=true\n";
        $labels .= "      - traefik.http.routers.webgui.middlewares=webgui-redirect\n";
    }
    
    // Find the web-gui service and add labels before networks
    // First, remove any existing labels section for web-gui
    $pattern = '/(web-gui:.*?)(labels:.*?)(networks:)/s';
    if (preg_match($pattern, $content)) {
        // Labels exist, replace them
        $content = preg_replace(
            '/(web-gui:.*?)(labels:.*?)(networks:)/s',
            '$1' . $labels . '    $3',
            $content
        );
    } else {
        // No labels, add them before networks
        $content = preg_replace(
            '/(web-gui:.*?)(    networks:)/s',
            '$1' . $labels . '$2',
            $content
        );
    }
    
    // Save to database and regenerate file
    try {
        saveComposeConfig($db, $content, $currentUser['id'], null);
        generateComposeFile($db, null);
        return ['success' => true, 'message' => 'Dashboard configuration updated! Restart web-gui container: docker-compose restart web-gui'];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to save configuration: ' . $e->getMessage()];
    }
}

// Handle update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $updateCheckEnabled = isset($_POST['update_check_enabled']) ? '1' : '0';
    $autoUpdateEnabled = isset($_POST['auto_update_enabled']) ? '1' : '0';
    $updateCheckFrequency = $_POST['update_check_frequency'] ?? '86400';
    $versionsUrl = trim($_POST['versions_url'] ?? '');
    
    setSetting($db, 'update_check_enabled', $updateCheckEnabled);
    setSetting($db, 'auto_update_enabled', $autoUpdateEnabled);
    setSetting($db, 'update_check_frequency', $updateCheckFrequency);
    
    if (!empty($versionsUrl)) {
        setSetting($db, 'versions_url', $versionsUrl);
    }
    
    $successMessage = 'Update settings saved successfully!';
}

// Handle manual update check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_updates'])) {
    $updateInfo = checkForUpdates(true);
    if (isset($updateInfo['error'])) {
        $errorMessage = 'Update check failed: ' . $updateInfo['error'];
    } else if ($updateInfo['update_available']) {
        $successMessage = 'Update available! Version ' . $updateInfo['latest_version'] . ' is ready to install.';
    } else {
        $successMessage = 'You are running the latest version (' . $updateInfo['current_version'] . ')';
    }
}

// Handle telemetry toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['telemetry_enabled'])) {
    $enabled = $_POST['telemetry_enabled'] === '1';
    setTelemetryEnabled($enabled);
    
    if ($enabled) {
        // Send immediate ping
        sendTelemetryPing();
        $successMessage = 'Telemetry enabled. Thank you for helping improve WharfTales!';
    } else {
        $successMessage = 'Telemetry disabled.';
    }
    
    // Refresh telemetry status
    $telemetryEnabled = isTelemetryEnabled();
}

// Get update settings
$updateCheckEnabled = getSetting($db, 'update_check_enabled', '1');
$autoUpdateEnabled = getSetting($db, 'auto_update_enabled', '0');
$updateCheckFrequency = getSetting($db, 'update_check_frequency', '86400');
$versionsUrl = getSetting($db, 'versions_url', 'https://raw.githubusercontent.com/giodc/wharftales/refs/heads/master/versions.json');
$currentVersion = getCurrentVersion();

// Check for updates (non-blocking, uses cache if recent)
$updateInfo = checkForUpdates(false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - WharfTales</title>
      <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="WharfTales" />
<link rel="manifest" href="/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-gear me-2"></i>System Settings</h2>
                    <a href="/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= $successMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= $errorMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Domain Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-globe me-2"></i>Domain Configuration
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="custom_wildcard_domain" class="form-label">
                                    Custom Wildcard Domain
                                </label>
                                <input type="text" class="form-control" id="custom_wildcard_domain" name="custom_wildcard_domain" 
                                       value="<?= htmlspecialchars($customWildcardDomain) ?>" placeholder=".example.com">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Set a custom wildcard domain that will appear in the dropdown when creating new sites. 
                                    Format: <code>.example.com</code> or <code>.yourdomain.com</code>. Leave empty to disable.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Custom Domain
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Dashboard Domain Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-house-door me-2"></i>Dashboard Domain
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="dashboard_domain" class="form-label">
                                    Custom Dashboard Domain
                                </label>
                                <input type="text" class="form-control" id="dashboard_domain" name="dashboard_domain" 
                                       value="<?= htmlspecialchars($dashboardDomain) ?>" placeholder="dashboard.example.com">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Set a custom domain to access this dashboard. Make sure the domain points to your server's IP address.
                                    Leave empty to keep using IP:port access.
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="dashboard_ssl" name="dashboard_ssl" 
                                       <?= $dashboardSSL === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="dashboard_ssl">
                                    <i class="bi bi-shield-lock me-1"></i>Enable SSL (Let's Encrypt)
                                </label>
                                <div class="form-text">
                                    Automatically obtain and renew SSL certificate for the dashboard domain.
                                    <strong>Port 80 and 443 must be accessible from the internet.</strong>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Dashboard Settings
                            </button>
                        </form>
                        
                        <?php if (!empty($dashboardDomain)): ?>
                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Current Dashboard URL:</strong><br>
                            <a href="<?= $dashboardSSL === '1' ? 'https' : 'http' ?>://<?= htmlspecialchars($dashboardDomain) ?>" target="_blank">
                                <?= $dashboardSSL === '1' ? 'https' : 'http' ?>://<?= htmlspecialchars($dashboardDomain) ?>
                                <i class="bi bi-box-arrow-up-right ms-1"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SSL Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-shield-lock me-2"></i>SSL Configuration
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="letsencrypt_email" class="form-label">
                                    Let's Encrypt Email Address
                                </label>
                                <input type="email" class="form-control" id="letsencrypt_email" name="letsencrypt_email" 
                                       value="<?= htmlspecialchars($currentEmail) ?>" required>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This email is used for SSL certificate expiration notifications and important security notices.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save SSL Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Docker Compose Editor -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-file-earmark-code me-2"></i>Advanced Configuration
                    </div>
                    <div class="card-body">
                        <p class="mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Edit raw docker-compose YAML configurations for advanced debugging and customization.
                        </p>
                        <a href="/compose-editor.php" class="btn btn-warning">
                            <i class="bi bi-code-slash me-2"></i>Edit Main Traefik Config (YAML)
                        </a>
                        <div class="form-text mt-2">
                            <i class="bi bi-exclamation-triangle me-1 text-warning"></i>
                            <strong>Warning:</strong> Only edit if you know what you're doing. Invalid YAML can break your deployment.
                        </div>
                    </div>
                </div>

                <!-- System Updates -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-arrow-up-circle me-2"></i>System Updates
                    </div>
                    <div class="card-body">
                        <!-- Current Version Info -->
                        <div class="alert alert-<?= ($updateInfo['update_available'] ?? false) ? 'warning' : 'success' ?> mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Current Version:</strong> <?= htmlspecialchars($currentVersion) ?>
                                    <?php if ($updateInfo['update_available'] ?? false): ?>
                                        <br><strong>Latest Version:</strong> <?= htmlspecialchars($updateInfo['latest_version']) ?>
                                        <span class="badge bg-warning text-dark ms-2">Update Available!</span>
                                    <?php else: ?>
                                        <span class="badge bg-success ms-2">Up to date</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($updateInfo['update_available'] ?? false): ?>
                                <button class="btn btn-warning btn-sm" onclick="showUpdateModal()">
                                    <i class="bi bi-download me-1"></i>Update Now
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Update Settings Form -->
                        <form method="POST">
                            <input type="hidden" name="update_settings" value="1">
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="update_check_enabled" 
                                           name="update_check_enabled" <?= $updateCheckEnabled === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="update_check_enabled">
                                        <strong>Enable Update Checks</strong>
                                    </label>
                                </div>
                                <div class="form-text">
                                    Periodically check for new WharfTales versions
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="auto_update_enabled" 
                                           name="auto_update_enabled" <?= $autoUpdateEnabled === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="auto_update_enabled">
                                        <strong>Enable Automatic Updates</strong>
                                    </label>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-exclamation-triangle me-1 text-warning"></i>
                                    Automatically install updates when available (recommended for testing environments only)
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="update_check_frequency" class="form-label">Update Check Frequency</label>
                                <select class="form-select" id="update_check_frequency" name="update_check_frequency">
                                    <option value="3600" <?= $updateCheckFrequency === '3600' ? 'selected' : '' ?>>Every Hour</option>
                                    <option value="21600" <?= $updateCheckFrequency === '21600' ? 'selected' : '' ?>>Every 6 Hours</option>
                                    <option value="43200" <?= $updateCheckFrequency === '43200' ? 'selected' : '' ?>>Every 12 Hours</option>
                                    <option value="86400" <?= $updateCheckFrequency === '86400' ? 'selected' : '' ?>>Daily (Recommended)</option>
                                    <option value="604800" <?= $updateCheckFrequency === '604800' ? 'selected' : '' ?>>Weekly</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="versions_url" class="form-label">Versions URL</label>
                                <input type="url" class="form-control" id="versions_url" name="versions_url" 
                                       value="<?= htmlspecialchars($versionsUrl) ?>">
                                <div class="form-text">
                                    URL to check for version information (advanced users only)
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Save Update Settings
                                </button>
                                <button type="submit" name="check_updates" value="1" class="btn btn-secondary">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Check Now
                                </button>
                            </div>
                        </form>

                        <?php if (isset($updateInfo['checked_at'])): ?>
                        <div class="mt-3 text-muted small">
                            <i class="bi bi-clock me-1"></i>Last checked: <?= htmlspecialchars($updateInfo['checked_at']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>


               

                <!-- Telemetry Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-bar-chart me-2"></i>Usage Statistics (Optional)
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Help us improve WharfTales!</strong> By enabling anonymous usage statistics, you help us understand how the platform is used and prioritize features. 
                            <br><br>
                            <strong>What we collect:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Anonymous installation ID (not linked to you)</li>
                                <li>Version number</li>
                                <li>PHP version</li>
                                <li>Number of sites (count only)</li>
                                <li>Last ping timestamp</li>
                            </ul>
                            <br>
                            <strong>What we DON'T collect:</strong>
                            <ul class="mb-0">
                                <li>❌ IP addresses</li>
                                <li>❌ Domain names</li>
                                <li>❌ Email addresses</li>
                                <li>❌ Any personal information</li>
                            </ul>
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="telemetryEnabled" name="telemetry_enabled" value="1" <?= $telemetryEnabled ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <label class="form-check-label" for="telemetryEnabled">
                                        <strong>Enable anonymous usage statistics</strong>
                                    </label>
                                </div>
                            </div>
                            
                            <?php if ($telemetryEnabled): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Thank you!</strong> Your anonymous installation ID is: <code><?= htmlspecialchars($installationId) ?></code>
                                <br><small class="text-muted">Data is sent once per day automatically.</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="testTelemetryPing()">
                                <i class="bi bi-send me-2"></i>Send Test Ping Now
                            </button>
                            <div id="telemetryTestResult" class="mt-2"></div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                     <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>Debugs
                    </div>
                    <div class="card-body">
                    <ul>
                  
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'traefik-logs.php' ? 'active fw-semibold' : '' ?>" href="/traefik-logs.php">
                        <i class="bi bi-file-text me-1"></i>SSL Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'ssl-debug.php' ? 'active fw-semibold' : '' ?>" href="/ssl-debug.php">
                        <i class="bi bi-shield-lock me-1"></i>SSL Debug
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'debug.php' ? 'active fw-semibold' : '' ?>" href="/debug.php">
                        <i class="bi bi-bug me-1"></i>Debug
                    </a>
                </li>
                        </ul>
                    </div>
                </div>           



                <!-- System Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>System Information
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Version</div>
                            <div class="col-md-8">
                                <?php 
                                    $versionFile = '/var/www/html/../VERSION';
                                    echo file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
                                ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Docker Compose Path</div>
                            <div class="col-md-8"><code><?= $dockerComposePath ?></code></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 text-muted">Current User</div>
                            <div class="col-md-8"><?= htmlspecialchars($currentUser['username']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Restart Traefik -->
                <?php if ($successMessage): ?>
                <div class="card mt-4 border-warning">
                    <div class="card-body">
                        <h5 class="card-title text-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>Action Required
                        </h5>
                        <p class="card-text">
                            To apply the new Let's Encrypt email, you need to restart the Traefik container.
                        </p>
                        <button class="btn btn-warning" onclick="restartTraefik()">
                            <i class="bi bi-arrow-clockwise me-2"></i>Restart Traefik
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/modals.php'; ?>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js?v=3.0.<?= time() ?>"></script>
    <script>
        async function restartTraefik() {
            if (!confirm('Are you sure you want to restart Traefik? This may cause brief downtime for all sites.')) {
                return;
            }

            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Restarting...';
            btn.disabled = true;

            try {
                const response = await fetch('/api.php?action=restart_traefik', {
                    method: 'POST'
                });
                const result = await response.json();

                if (result.success) {
                    alert('Traefik restarted successfully!');
                    location.reload();
                } else {
                    alert('Failed to restart Traefik: ' + (result.error || 'Unknown error'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                alert('Error: ' + error.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        async function checkForUpdates() {
            const checkSection = document.getElementById('updateCheckSection');
            const infoSection = document.getElementById('updateInfoSection');
            
            checkSection.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Checking...</span></div> <span class="ms-2">Checking for updates...</span>';
            
            try {
                const response = await fetch('/api.php?action=get_update_info');
                const result = await response.json();
                
                if (result.success) {
                    displayUpdateInfo(result.info, result.changelog);
                } else {
                    infoSection.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Failed to check for updates: ${result.error || 'Unknown error'}
                        </div>
                    `;
                    infoSection.style.display = 'block';
                    checkSection.style.display = 'none';
                }
            } catch (error) {
                infoSection.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Network error: ${error.message}
                    </div>
                `;
                infoSection.style.display = 'block';
                checkSection.style.display = 'none';
            }
        }

        function displayUpdateInfo(info, changelog) {
            const checkSection = document.getElementById('updateCheckSection');
            const infoSection = document.getElementById('updateInfoSection');
            
            let html = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Current Version:</strong> ${info.current_version}
                    </div>
                    <div class="col-md-6">
                        <strong>Latest Version:</strong> ${info.remote_version || 'Unknown'}
                    </div>
                </div>
            `;
            
            if (info.update_available) {
                html += `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        <strong>Update Available!</strong> A new version is ready to install.
                    </div>
                    <button class="btn btn-success mb-3" onclick="performUpdate()">
                        <i class="bi bi-download me-2"></i>Install Update Now
                    </button>
                `;
            } else {
                html += `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        You are running the latest version.
                    </div>
                `;
            }
            
            if (info.has_local_changes) {
                html += `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Local changes detected. They will be stashed during update.
                    </div>
                `;
            }
            
            if (changelog && changelog.length > 0) {
                html += `
                    <div class="mt-3">
                        <h6>Recent Changes:</h6>
                        <ul class="list-unstyled">
                `;
                changelog.slice(0, 5).forEach(line => {
                    html += `<li><code>${line}</code></li>`;
                });
                html += `
                        </ul>
                    </div>
                `;
            }
            
            html += `
                <button class="btn btn-secondary mt-2" onclick="resetUpdateSection()">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </button>
            `;
            
            infoSection.innerHTML = html;
            infoSection.style.display = 'block';
            checkSection.style.display = 'none';
        }

        async function performUpdate() {
            if (!confirm('Are you sure you want to update? This will pull the latest changes from Git and may restart services.')) {
                return;
            }
            
            const infoSection = document.getElementById('updateInfoSection');
            infoSection.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h5>Installing Update...</h5>
                    <p class="text-muted">This may take a minute...</p>
                </div>
            `;
            
            try {
                const response = await fetch('/api.php?action=perform_update', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    infoSection.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Update Successful!</strong><br>
                            Updated to version ${result.version || 'latest'}<br>
                            <small class="text-muted">Page will reload in 3 seconds...</small>
                        </div>
                    `;
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    infoSection.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Update Failed!</strong><br>
                            ${result.error || 'Unknown error'}
                        </div>
                        <button class="btn btn-secondary" onclick="resetUpdateSection()">
                            <i class="bi bi-arrow-left me-2"></i>Back
                        </button>
                    `;
                }
            } catch (error) {
                infoSection.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Network Error!</strong><br>
                        ${error.message}
                    </div>
                    <button class="btn btn-secondary" onclick="resetUpdateSection()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                `;
            }
        }

        function resetUpdateSection() {
            const checkSection = document.getElementById('updateCheckSection');
            const infoSection = document.getElementById('updateInfoSection');
            
            checkSection.innerHTML = `
                <button class="btn btn-primary" onclick="checkForUpdates()">
                    <i class="bi bi-search me-2"></i>Check for Updates
                </button>
            `;
            checkSection.style.display = 'block';
            infoSection.style.display = 'none';
        }
        
        async function restartWebGui() {
            if (!confirm('Restart the web-gui container? The page will reload after restart.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=restart_webgui', {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Web-GUI is restarting... The page will reload in 5 seconds.');
                    setTimeout(() => {
                        window.location.reload();
                    }, 5000);
                } else {
                    alert('Failed to restart: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function testTelemetryPing() {
            const resultDiv = document.getElementById('telemetryTestResult');
            const button = event.target;
            const originalText = button.innerHTML;
            
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            resultDiv.innerHTML = '';
            
            try {
                const response = await fetch('/api.php?action=test_telemetry_ping', {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Success!</strong> ${result.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Failed!</strong> ${result.message || result.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Error!</strong> ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            } finally {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }

        // Update Modal Functions - Using the one from app.js

        async function startUpdate(skipBackup = false) {
            const btn = document.getElementById('updateBtn');
            const status = document.getElementById('updateStatus');
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Starting update...';
            
            status.innerHTML = `
                <div class="alert alert-info">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    Update process started. This may take a few minutes...
                </div>
            `;
            
            try {
                const response = await fetch('/api.php?action=trigger_update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ skip_backup: skipBackup })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    status.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Update started successfully! The page will reload in 30 seconds...
                        </div>
                    `;
                    
                    // Reload page after 30 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 30000);
                } else {
                    status.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Failed to start update: ${result.error || 'Unknown error'}
                        </div>
                    `;
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (error) {
                status.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Error: ${error.message}
                    </div>
                `;
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
    </script>

    <!-- Update Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-up-circle me-2"></i>Update WharfTales
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($updateInfo['update_available'] ?? false): ?>
                    <div class="alert alert-info">
                        <strong>Current Version:</strong> <?= htmlspecialchars($updateInfo['current_version']) ?><br>
                        <strong>New Version:</strong> <?= htmlspecialchars($updateInfo['latest_version']) ?>
                    </div>
                    
                    <?php if (!empty($updateInfo['release_notes'])): ?>
                    <div class="mb-3">
                        <h6>Release Notes:</h6>
                        <p><?= htmlspecialchars($updateInfo['release_notes']) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($updateInfo['changelog_url'])): ?>
                    <p>
                        <a href="<?= htmlspecialchars($updateInfo['changelog_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-journal-text me-1"></i>View Full Changelog
                        </a>
                    </p>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> The update process will:
                        <ul class="mb-0 mt-2">
                            <li>Create a backup of your current installation</li>
                            <li>Pull the latest code from the repository</li>
                            <li>Restart Docker containers</li>
                            <li>May cause brief downtime (1-2 minutes)</li>
                        </ul>
                    </div>
                    
                    <div id="updateStatus"></div>
                    <?php else: ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        You are already running the latest version!
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if ($updateInfo['update_available'] ?? false): ?>
                    <button type="button" class="btn btn-warning" id="updateBtn" onclick="startUpdate(false)">
                        <i class="bi bi-download me-2"></i>Update Now
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

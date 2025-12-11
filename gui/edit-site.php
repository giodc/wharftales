<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect to SSL URL if dashboard has SSL enabled and request is from :9000
redirectToSSLIfEnabled();

// Require authentication
requireAuth();

$db = initDatabase();
$currentUser = getCurrentUser();

// Get site ID from URL
$siteId = $_GET['id'] ?? null;
if (!$siteId) {
    header('Location: /');
    exit;
}

$site = getSiteById($db, $siteId);
if (!$site) {
    header('Location: /');
    exit;
}

// Check if user has access to this site
if (!canAccessSite($_SESSION['user_id'], $siteId, 'view')) {
    header('Location: /');
    exit;
}

// Determine user's permission level for this site
$userPermission = 'view'; // default
if (canAccessSite($_SESSION['user_id'], $siteId, 'manage')) {
    $userPermission = 'manage';
} elseif (canAccessSite($_SESSION['user_id'], $siteId, 'edit')) {
    $userPermission = 'edit';
}

// Check if user can edit (edit or manage permission required)
$canEdit = ($userPermission === 'edit' || $userPermission === 'manage');

// Get active tab from URL parameter (for bookmarking)
$activeTab = $_GET['tab'] ?? 'overview';

// Get container status
$containerStatus = getDockerContainerStatus($site['container_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Site - <?= htmlspecialchars($site['name']) ?> - WharfTales</title>
          <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="WharfTales" />
<link rel="manifest" href="/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">
    <!-- Xterm.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-webgl@0.16.0/lib/xterm-addon-webgl.js"></script>
    <style>
        .sidebar {
          
            padding: 1.5rem 0;
            min-height: calc(100vh - 56px);
        }
        .sidebar-nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav-item:hover {
            color: #000;
            font-weight: 600;
        }
        .sidebar-nav-item.active {
           
            color: #000;
            font-weight: 600;
        }
        .sidebar-nav-item i {
            width: 20px;
            margin-right: 10px;
        }
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #6b7280;
            width: 180px;
            flex-shrink: 0;
            font-size: 0.875rem;
        }
        .info-value {
            color: #1f2937;
            flex: 1;
        }
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.8125rem;
        }
        .status-running { background: #4b5563; color: #fff; }
        .status-stopped { background: #9ca3af; color: #fff; }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <!-- Site Name Header -->
    <div class="container-fluid mt-4 px-4">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="mb-0">
                <i class="bi bi-<?= getAppIcon($site['type']) ?> me-2"></i><?= htmlspecialchars($site['name']) ?>
                <span class="badge bg-<?= $containerStatus === 'running' ? 'success' : 'secondary' ?> ms-2">
                    <?= ucfirst($containerStatus) ?>
                </span>
                <?php if (!isAdmin()): ?>
                <span class="badge bg-<?= $userPermission === 'manage' ? 'primary' : ($userPermission === 'edit' ? 'info' : 'secondary') ?> ms-2">
                    <i class="bi bi-<?= $userPermission === 'manage' ? 'shield-check' : ($userPermission === 'edit' ? 'pencil' : 'eye') ?> me-1"></i>
                    <?= ucfirst($userPermission) ?> Access
                </span>
                <?php endif; ?>
            </h2>
            <a href="/" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- View-Only Notice -->
    <?php if ($userPermission === 'view'): ?>
    <div class="container-fluid px-4 mt-3">
        <div class="alert alert-info" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <strong>View-Only Access:</strong> You can view this site's details but cannot make changes. Contact an administrator for edit access.
        </div>
    </div>
    <?php endif; ?>

    <?php 
    // Check database configuration for navigation menu
    $dbType = $site['db_type'] ?? 'none';
    $hasDedicatedDb = false;
    
    // Check if site has a database
    if ($site['type'] === 'wordpress' && $dbType === 'dedicated') {
        $hasDedicatedDb = true;
    } elseif ($site['type'] === 'php' && in_array($dbType, ['mysql', 'postgresql'])) {
        $hasDedicatedDb = true;
    } elseif ($site['type'] === 'laravel' && in_array($dbType, ['mysql', 'postgresql'])) {
        $hasDedicatedDb = true;
    }
    
    $dbContainerExists = false;
    if ($hasDedicatedDb) {
        $dbCheck = [];
        exec("docker ps -a --filter name=" . escapeshellarg($site['container_name'] . '_db') . " --format '{{.Names}}' 2>&1", $dbCheck, $returnCode);
        $dbContainerExists = ($returnCode === 0 && !empty($dbCheck) && trim($dbCheck[0]) === $site['container_name'] . '_db');
    }
    ?>

    <!-- Main Content with Two Columns -->
    <div class="container-fluid mt-4 px-4">
        <div class="row">
            <!-- Mobile Navigation Select (visible only on mobile) -->
            <div class="col-12 mb-3 d-md-none">
                <select class="form-select" id="mobile-nav-select">
                    <option value="overview" selected>📊 Overview</option>
                    <option value="settings">⚙️ Settings</option>
                    <?php if ($site['type'] !== 'mariadb'): ?>
                    <option value="domain">🌐 Domain & SSL</option>
                    <?php endif; ?>
                    <option value="container">📦 Container</option>
                    <?php if ($site['type'] !== 'mariadb'): ?>
                    <option value="files">📁 Files & Volumes</option>
                    <?php endif; ?>
                    <option value="logs">💻 Logs</option>
                    <?php if (($hasDedicatedDb && $dbContainerExists) || $site['type'] === 'mariadb'): ?>
                    <option value="database">🗄️ Database</option>
                    <?php endif; ?>
                    <?php if ($site['type'] !== 'mariadb'): ?>
                    <option value="redis">⚡ Redis Cache</option>
                    <option value="sftp">🔌 SFTP Access</option>
                    <?php endif; ?>
                    <option value="backup">💾 Backup & Restore</option>
                    <?php if (isAdmin()): ?>
                    <option value="compose">📝 Docker Compose</option>
                    <?php endif; ?>
                    <option value="danger">⚠️ Danger Zone</option>
                </select>
            </div>
            
            <!-- Left Sidebar (hidden on mobile) -->
            <div class="col-md-3 p-0 d-none d-md-block">
                <div class="sidebar">
                    <nav>
                        <a href="#overview" class="sidebar-nav-item active" data-section="overview">
                            <i class="bi bi-speedometer2"></i>
                            <span>Overview</span>
                        </a>
                        <a href="#settings" class="sidebar-nav-item" data-section="settings">
                            <i class="bi bi-gear"></i>
                            <span>Settings</span>
                        </a>
                        <?php if ($site['type'] === 'laravel'): ?>
                        <a href="#laravel" class="sidebar-nav-item" data-section="laravel">
                            <i class="bi bi-braces"></i>
                            <span>Laravel</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($site['type'] !== 'mariadb'): ?>
                        <a href="#domain" class="sidebar-nav-item" data-section="domain">
                            <i class="bi bi-globe"></i>
                            <span>Domain & SSL</span>
                        </a>
                        <?php endif; ?>
                        <a href="#container" class="sidebar-nav-item" data-section="container">
                            <i class="bi bi-box"></i>
                            <span>Container</span>
                        </a>
                        <?php if ($site['type'] !== 'mariadb'): ?>
                        <a href="#files" class="sidebar-nav-item" data-section="files">
                            <i class="bi bi-folder"></i>
                            <span>Files & Volumes</span>
                        </a>
                        <?php endif; ?>
                        <a href="#logs" class="sidebar-nav-item" data-section="logs">
                            <i class="bi bi-terminal"></i>
                            <span>Logs</span>
                        </a>
                        <a href="#terminal" class="sidebar-nav-item" data-section="terminal">
                            <i class="bi bi-terminal-fill"></i>
                            <span>Web Terminal</span>
                        </a>
                        <?php if (($hasDedicatedDb && $dbContainerExists) || $site['type'] === 'mariadb'): ?>
                        <a href="#database" class="sidebar-nav-item" data-section="database">
                            <i class="bi bi-database"></i>
                            <span>Database</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($site['type'] !== 'mariadb'): ?>
                        <a href="#redis" class="sidebar-nav-item" data-section="redis">
                            <i class="bi bi-lightning-charge"></i>
                            <span>Redis Cache</span>
                        </a>
                        <a href="#sftp" class="sidebar-nav-item" data-section="sftp">
                            <i class="bi bi-hdd-network"></i>
                            <span>SFTP Access</span>
                        </a>
                        <?php endif; ?>
                        <a href="#backup" class="sidebar-nav-item" data-section="backup">
                            <i class="bi bi-cloud-download"></i>
                            <span>Backup & Restore</span>
                        </a>
                        <?php if (isAdmin()): ?>
                        <a href="/compose-editor.php?site_id=<?= $siteId ?>" class="sidebar-nav-item" target="_blank">
                            <i class="bi bi-file-earmark-code"></i>
                            <span>Docker Compose <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 0.8em;"></i></span>
                        </a>
                        <?php endif; ?>
                        <a href="#danger" class="sidebar-nav-item" data-section="danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span>Danger Zone</span>
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Right Content Area -->
            <div class="col-md-9">
            <!-- Overview Section -->
            <div id="overview-section" class="content-section">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-info-circle me-2"></i>Site Information
                            </div>
                            <div class="card-body">
                                <div class="info-row">
                                    <div class="info-label">Site Name</div>
                                    <div class="info-value"><?= htmlspecialchars($site['name']) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Application Type</div>
                                    <div class="info-value">
                                        <i class="bi bi-<?= getAppIcon($site['type']) ?> me-2"></i><?= ucfirst($site['type']) ?>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Domain</div>
                                    <div class="info-value">
                                        <a href="http://<?= htmlspecialchars($site['domain']) ?>" target="_blank">
                                            <?= htmlspecialchars($site['domain']) ?>
                                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">SSL Certificate</div>
                                    <div class="info-value">
                                        <?php if ($site['ssl']): ?>
                                            <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Enabled</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Disabled</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($site['type'] === 'wordpress'): ?>
                                <div class="info-row">
                                    <div class="info-label">Database Type</div>
                                    <div class="info-value">
                                        <?php 
                                        $dbType = $site['db_type'] ?? 'dedicated';
                                        $badgeColor = $dbType === 'dedicated' ? 'info' : ($dbType === 'custom' ? 'warning' : 'secondary');
                                        $dbDescription = $dbType === 'dedicated' ? 'Separate MariaDB container' : ($dbType === 'custom' ? 'External database connection' : 'Shared global database');
                                        ?>
                                        <span class="badge bg-<?= $badgeColor ?>">
                                            <i class="bi bi-database me-1"></i><?= ucfirst($dbType) ?>
                                        </span>
                                        <small class="text-muted ms-2">
                                            <?= $dbDescription ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="info-row">
                                    <div class="info-label">Container Name</div>
                                    <div class="info-value"><code><?= htmlspecialchars($site['container_name']) ?></code></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Created</div>
                                    <div class="info-value"><?= date('F j, Y g:i A', strtotime($site['created_at'])) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="status-badge status-<?= $containerStatus ?>">
                                            <i class="bi bi-circle-fill me-1"></i><?= ucfirst($containerStatus) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-boxes me-2"></i>Containers
                                <button class="btn btn-sm btn-outline-secondary float-end" onclick="refreshContainers()">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="containersListLoading">
                                    <div class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mt-2 mb-0 small text-muted">Loading containers...</p>
                                    </div>
                                </div>
                                <div id="containersList" style="display: none;">
                                    <!-- Containers will be loaded here -->
                                </div>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-lightning me-2"></i>Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-outline-primary w-100" onclick="viewSite('<?= $site['domain'] ?>', <?= $site['ssl'] ? 'true' : 'false' ?>)">
                                            <i class="bi bi-eye me-2"></i>Open Site
                                        </button>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-outline-success w-100" onclick="restartContainer()">
                                            <i class="bi bi-arrow-clockwise me-2"></i>Restart Container
                                        </button>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-outline-info w-100" onclick="viewLogs()">
                                            <i class="bi bi-terminal me-2"></i>View Logs
                                        </button>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-outline-warning w-100" onclick="backupSite()">
                                            <i class="bi bi-cloud-download me-2"></i>Backup Site
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-graph-up me-2"></i>Resource Usage
                                <small class="text-muted d-block" style="font-size: 0.75rem; font-weight: normal;">All containers combined</small>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted">Status</small>
                                    <h5 id="containerStatus"><?= ucfirst($containerStatus) ?></h5>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Uptime</small>
                                    <h5 id="containerUptime">Loading...</h5>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted"><i class="bi bi-cpu"></i> CPU</small>
                                        <small class="fw-bold" id="cpuUsage"><span class="spinner-border spinner-border-sm"></span></small>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-primary" id="cpuBar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small class="text-muted"><i class="bi bi-memory"></i> Memory</small>
                                        <small class="fw-bold" id="memoryUsage"><span class="spinner-border spinner-border-sm"></span></small>
                                    </div>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar bg-info" id="memoryBar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Volume Size</small>
                                    <h5 id="volumeSize">Loading...</h5>
                                </div>
                                <button class="btn btn-sm btn-outline-secondary w-100" onclick="refreshStats()">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                </button>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="bi bi-link-45deg me-2"></i>Quick Links
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="http://<?= htmlspecialchars($site['domain']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-globe me-1"></i>Visit Site
                                    </a>
                                    <?php if ($site['type'] === 'wordpress'): ?>
                                    <a href="http://<?= htmlspecialchars($site['domain']) ?>/wp-admin" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-wordpress me-1"></i>WP Admin
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Section -->
            <div id="settings-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-gear me-2"></i>General Settings
                    </div>
                    <div class="card-body">
                        <form id="settingsForm">
                            <div class="mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-control" id="siteName" value="<?= htmlspecialchars($site['name']) ?>">
                                <div class="form-text">Display name for your application</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Application Type</label>
                                <input type="text" class="form-control" value="<?= ucfirst($site['type']) ?>" disabled>
                                <div class="form-text">Type cannot be changed after creation</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- PHP Version (not applicable for MariaDB) -->
                <?php if ($site['type'] !== 'mariadb'): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-code-slash me-2"></i>PHP Version
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Current PHP Version</label>
                            <select class="form-select" id="phpVersionSelect">
                                <option value="8.4" <?= ($site['php_version'] ?? '8.3') === '8.4' ? 'selected' : '' ?>>PHP 8.4 (Latest)</option>
                                <option value="8.3" <?= ($site['php_version'] ?? '8.3') === '8.3' ? 'selected' : '' ?>>PHP 8.3 (Recommended)</option>
                                <option value="8.2" <?= ($site['php_version'] ?? '8.3') === '8.2' ? 'selected' : '' ?>>PHP 8.2</option>
                                <option value="8.1" <?= ($site['php_version'] ?? '8.3') === '8.1' ? 'selected' : '' ?>>PHP 8.1</option>
                                <option value="8.0" <?= ($site['php_version'] ?? '8.3') === '8.0' ? 'selected' : '' ?>>PHP 8.0</option>
                                <option value="7.4" <?= ($site['php_version'] ?? '8.3') === '7.4' ? 'selected' : '' ?>>PHP 7.4 (Legacy)</option>
                            </select>
                            <div class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Current version: <strong>PHP <?= htmlspecialchars($site['php_version'] ?? '8.3') ?></strong>
                            </div>
                        </div>
                        <button class="btn btn-warning" onclick="changePHPVersion()">
                            <i class="bi bi-arrow-repeat me-2"></i>Switch PHP Version
                        </button>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Changing PHP version will restart your container. Make sure your application is compatible with the selected version.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Environment Variables -->
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-code-square me-2"></i>Environment Variables
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> Changes to environment variables require a container restart to take effect.
                            <?php if ($site['type'] === 'laravel'): ?>
                            <br><strong>Laravel:</strong> These variables are set as Docker environment variables. To use them in Laravel, they're also synced to your <code>.env</code> file when you save.
                            <?php endif; ?>
                        </div>
                        
                        <div id="envVarsList">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading environment variables...</p>
                            </div>
                        </div>
                        
                        <button class="btn btn-success mt-3" onclick="showAddEnvVarModal()">
                            <i class="bi bi-plus-circle me-2"></i>Add Variable
                        </button>
                        <button class="btn btn-primary mt-3" onclick="saveEnvVars()">
                            <i class="bi bi-save me-2"></i>Save & Restart Container
                        </button>
                    </div>
                </div>
                
                <!-- GitHub Deployment (PHP & Laravel only) -->
                <?php if ($site['type'] === 'php' || $site['type'] === 'laravel'): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-github me-2"></i>GitHub Deployment
                    </div>
                    <div class="card-body">
                        <form id="githubForm">
                            <div class="mb-3">
                                <label class="form-label">Repository</label>
                                <input type="text" class="form-control" id="githubRepo" value="<?= htmlspecialchars($site['github_repo'] ?? '') ?>" placeholder="username/repo or https://github.com/username/repo">
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Leave empty to disable GitHub deployment and use SFTP instead
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Branch</label>
                                    <input type="text" class="form-control" id="githubBranch" value="<?= htmlspecialchars($site['github_branch'] ?? 'main') ?>" placeholder="main">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Personal Access Token</label>
                                    <input type="password" class="form-control" id="githubToken" placeholder="Leave empty to keep existing">
                                    <div class="form-text">
                                        Only needed for private repos. <a href="https://github.com/settings/tokens" target="_blank">Generate token</a>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($site['github_repo'])): ?>
                            <div class="alert alert-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">Last Commit:</small><br>
                                        <code><?= $site['github_last_commit'] ? substr($site['github_last_commit'], 0, 7) : '-' ?></code>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">Last Pull:</small><br>
                                        <span><?= $site['github_last_pull'] ? date('Y-m-d H:i:s', strtotime($site['github_last_pull'])) : 'Never' ?></span>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="checkForGithubUpdates()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Check for Updates
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="pullFromGithubRepo()">
                                        <i class="bi bi-download me-1"></i>Pull Latest Changes
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="forcePullFromGithubRepo()">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Force Pull (Override)
                                    </button>
                                    <?php if ($site['type'] === 'laravel'): ?>
                                    <button type="button" class="btn btn-sm btn-success" onclick="runLaravelBuild()">
                                        <i class="bi bi-hammer me-1"></i>Build Laravel
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning" onclick="fixLaravelPermissions()">
                                        <i class="bi bi-shield-lock me-1"></i>Fix Permissions
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Save GitHub Settings
                            </button>
                        </form>
                        
                        <div class="alert alert-secondary mt-3 mb-0">
                            <strong><i class="bi bi-shield-lock me-2"></i>Security:</strong> Tokens are encrypted with AES-256-GCM before storage.
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Container Management -->
                <div class="card mt-3 border-danger">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-box-seam me-2"></i>Container Actions
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Rebuilding the container will delete the current container and create a new one from the latest configuration. 
                            Persistent data in volumes (database, storage) will be preserved, but temporary files will be lost.
                            This is useful if you need to update system dependencies (like Node.js/PHP extensions).
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-danger" onclick="rebuildContainer()">
                                <i class="bi bi-arrow-repeat me-2"></i>Rebuild Container
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Domain Section -->
            <div id="domain-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-globe me-2"></i>Domain Configuration
                    </div>
                    <div class="card-body">
                        <form id="domainForm">
                            <div class="mb-3">
                                <label class="form-label">Domain</label>
                                <input type="text" class="form-control" id="siteDomain" value="<?= htmlspecialchars($site['domain']) ?>">
                                <div class="form-text">
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                    Changing the domain requires container restart and DNS/hosts file update
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="includeWww" <?= ($site['include_www'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="includeWww">
                                        Also include www subdomain
                                    </label>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    When checked, both <strong>domain.com</strong> and <strong>www.domain.com</strong> will be configured. 
                                    If SSL is enabled, the certificate will cover both domains.
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sslEnabled" <?= $site['ssl'] ? 'checked' : '' ?> onchange="toggleSSLOptions()">
                                    <label class="form-check-label" for="sslEnabled">
                                        Enable HTTPS (Let's Encrypt)
                                    </label>
                                </div>
                                <div class="form-text">Requires custom domain with valid DNS pointing to your server</div>
                            </div>
                            
                            <!-- SSL Configuration Options -->
                            <div id="sslConfigOptions" style="display: <?= $site['ssl'] ? 'block' : 'none' ?>;">
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">SSL Challenge Method</h6>
                                        <?php 
                                        $sslConfig = $site['ssl_config'] ? json_decode($site['ssl_config'], true) : null;
                                        $currentChallenge = $sslConfig['challenge'] ?? 'http';
                                        $currentProvider = $sslConfig['provider'] ?? '';
                                        $currentCredentials = $sslConfig['credentials'] ?? [];
                                        ?>
                                        <div class="mb-3">
                                            <select class="form-select" id="sslChallengeMethod" onchange="toggleSSLChallengeFields(this.value)">
                                                <option value="http" <?= $currentChallenge === 'http' ? 'selected' : '' ?>>HTTP Challenge (Port 80 required)</option>
                                                <option value="dns" <?= $currentChallenge === 'dns' ? 'selected' : '' ?>>DNS Challenge (Works behind firewall)</option>
                                            </select>
                                            <div class="form-text">
                                                <strong>HTTP:</strong> Simple, requires port 80 accessible<br>
                                                <strong>DNS:</strong> Works behind firewall, supports wildcards
                                            </div>
                                        </div>
                                        
                                        <!-- DNS Provider Options -->
                                        <div id="dnsProviderConfig" style="display: <?= $currentChallenge === 'dns' ? 'block' : 'none' ?>;">
                                            <div class="mb-3">
                                                <label class="form-label">DNS Provider</label>
                                                <select class="form-select" id="dnsProvider" onchange="showDNSProviderFields(this.value)">
                                                    <option value="">Select Provider</option>
                                                    <option value="cloudflare" <?= $currentProvider === 'cloudflare' ? 'selected' : '' ?>>Cloudflare</option>
                                                    <option value="route53" <?= $currentProvider === 'route53' ? 'selected' : '' ?>>AWS Route53</option>
                                                    <option value="digitalocean" <?= $currentProvider === 'digitalocean' ? 'selected' : '' ?>>DigitalOcean</option>
                                                </select>
                                            </div>
                                            
                                            <!-- Cloudflare Fields -->
                                            <div id="cloudflareFields" class="dns-provider-fields" style="display: <?= $currentProvider === 'cloudflare' ? 'block' : 'none' ?>;">
                                                <div class="mb-3">
                                                    <label class="form-label">Cloudflare API Token</label>
                                                    <input type="password" class="form-control" id="cfApiToken" placeholder="Enter new token or leave empty to keep existing">
                                                    <div class="form-text">
                                                        <?= !empty($currentCredentials['cf_api_token']) ? '<span class="text-success"><i class="bi bi-check-circle"></i> Token configured</span><br>' : '' ?>
                                                        <strong>Create token at:</strong> <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare Dashboard</a><br>
                                                        <strong>Required permissions:</strong> Zone → DNS → Edit, Zone → Zone → Read<br>
                                                        Leave empty to keep existing token
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Route53 Fields -->
                                            <div id="route53Fields" class="dns-provider-fields" style="display: <?= $currentProvider === 'route53' ? 'block' : 'none' ?>;">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">AWS Access Key ID</label>
                                                        <input type="text" class="form-control" id="awsAccessKey" placeholder="Leave empty to keep existing">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">AWS Secret Access Key</label>
                                                        <input type="password" class="form-control" id="awsSecretKey" placeholder="Leave empty to keep existing">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- DigitalOcean Fields -->
                                            <div id="digitaloceanFields" class="dns-provider-fields" style="display: <?= $currentProvider === 'digitalocean' ? 'block' : 'none' ?>;">
                                                <div class="mb-3">
                                                    <label class="form-label">DigitalOcean API Token</label>
                                                    <input type="password" class="form-control" id="doAuthToken" placeholder="Leave empty to keep existing">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Update Domain & SSL Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Container Section -->
            <div id="container-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-box me-2"></i>Container Management
                    </div>
                    <div class="card-body">
                        <p>Container actions and management</p>
                        <div class="d-grid gap-2">
                            <button class="btn btn-success" onclick="startContainer()">
                                <i class="bi bi-play-fill me-2"></i>Start Container
                            </button>
                            <button class="btn btn-warning" onclick="restartContainer()">
                                <i class="bi bi-arrow-clockwise me-2"></i>Restart Container
                            </button>
                            <button class="btn btn-danger" onclick="stopContainer()">
                                <i class="bi bi-stop-fill me-2"></i>Stop Container
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Files Section -->
            <div id="files-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-folder me-2"></i>Files & Volumes
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Volume:</strong> <code><?= $site['container_name'] ?>_data</code>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>File Manager</strong><br>
                            Browse and manage your site files directly in the container.
                        </div>
                        
                        <!-- File Browser -->
                        <div id="fileBrowser">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="navigateUp()">
                                        <i class="bi bi-arrow-up"></i> Up
                                    </button>
                                    <span class="ms-2" id="currentPath">/var/www/html</span>
                                </div>
                                <div>
                                    <button class="btn btn-sm btn-primary" onclick="showUploadModal()">
                                        <i class="bi bi-upload"></i> Upload
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="showNewFileModal()">
                                        <i class="bi bi-file-plus"></i> New File
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="showNewFolderModal()">
                                        <i class="bi bi-folder-plus"></i> New Folder
                                    </button>
                                </div>
                            </div>
                            
                            <!-- File List -->
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50"><i class="bi bi-file-earmark"></i></th>
                                            <th>Name</th>
                                            <th width="120">Size</th>
                                            <th width="180">Modified</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="fileList">
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                <p class="mt-2 text-muted">Loading files...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Be careful when editing or deleting files. Always backup before making changes.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Laravel Management Section -->
            <?php if ($site['type'] === 'laravel'): ?>
            <div id="laravel-section" class="content-section" style="display: none;">
                <div class="row">
                    <!-- Cache & Config -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">Cache & Config</div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary" onclick="executeLaravelCommand('cache:clear')">
                                        <i class="bi bi-trash me-2"></i>cache:clear
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="executeLaravelCommand('config:clear')">
                                        <i class="bi bi-eraser me-2"></i>config:clear
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="executeLaravelCommand('config:cache')">
                                        <i class="bi bi-archive me-2"></i>config:cache
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Database -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">Database</div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-info" onclick="executeLaravelCommand('migrate:status')">
                                        <i class="bi bi-info-circle me-2"></i>migrate:status
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="executeLaravelCommand('migrate')">
                                        <i class="bi bi-database-fill-up me-2"></i>migrate
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Storage & Views -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">Storage & Views</div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-success" onclick="executeLaravelCommand('storage:link')">
                                        <i class="bi bi-link-45deg me-2"></i>storage:link
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="executeLaravelCommand('view:clear')">
                                        <i class="bi bi-eye-slash me-2"></i>view:clear
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- General & Maintenance -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">General</div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-danger" onclick="executeLaravelCommand('down')">
                                        <i class="bi bi-cone-striped me-2"></i>down (Maintenance Mode)
                                    </button>
                                    <button class="btn btn-outline-success" onclick="executeLaravelCommand('up')">
                                        <i class="bi bi-check-circle me-2"></i>up (Live)
                                    </button>
                                    <button class="btn btn-warning" onclick="fixLaravelPermissions()">
                                        <i class="bi bi-shield-lock me-2"></i>Fix Permissions
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dependencies & Build -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">Dependencies & Build</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-2">
                                        <button class="btn btn-success w-100" onclick="executeShellCommand('composer install --no-interaction --optimize-autoloader --verbose')">
                                            <i class="bi bi-box-seam me-2"></i>Composer Install
                                        </button>
                                        <button class="btn btn-sm btn-outline-success w-100 mt-2" onclick="executeShellCommand('composer fund')">
                                            <i class="bi bi-info-circle me-1"></i>Composer Fund
                                        </button>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <button class="btn btn-info w-100" onclick="executeShellCommand('npm install --loglevel=info')">
                                            <i class="bi bi-download me-2"></i>Npm Install
                                        </button>
                                        <button class="btn btn-sm btn-outline-info w-100 mt-2" onclick="installNodeJs()">
                                            <i class="bi bi-plus-circle me-1"></i>Install Node.js
                                        </button>
                                        <button class="btn btn-sm btn-outline-info w-100 mt-2" onclick="executeShellCommand('npm fund')">
                                            <i class="bi bi-info-circle me-1"></i>Npm Fund
                                        </button>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <button class="btn btn-primary w-100" onclick="executeShellCommand('npm run build -- --logLevel info')">
                                            <i class="bi bi-hammer me-2"></i>Npm Run Build
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Environment Editor -->
                     <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Environment Configuration</span>
                                <button class="btn btn-sm btn-primary" onclick="openEnvEditor()">
                                    <i class="bi bi-pencil-square me-2"></i>Edit .env
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-secondary mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Manage your application's environment variables directly. 
                                    <strong>Note:</strong> Changes may require a config:cache clearing.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Artisan Console -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">Artisan Console</div>
                            <div class="card-body">
                                <div class="mb-0">
                                    <label class="form-label">Execute Artisan Command</label>
                                    <div class="input-group">
                                        <span class="input-group-text">php artisan</span>
                                        <input type="text" class="form-control" id="artisanCommand" placeholder="migrate:status" onkeypress="handleArtisanEnter(event)">
                                        <button class="btn btn-secondary" onclick="executeLaravelCommand()">
                                            <i class="bi bi-play-fill"></i> Run
                                        </button>
                                    </div>
                                    <div class="form-text">Enter command without 'php artisan' prefix.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-terminal-split me-2"></i>Activity Log</span>
                                <button class="btn btn-sm btn-outline-secondary" onclick="clearLaravelLog()">
                                    <i class="bi bi-eraser me-1"></i>Clear
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div id="laravelLogOutput" class="bg-dark text-light p-3" style="height: 400px; overflow-y: auto; font-family: 'Consolas', 'Monaco', monospace; font-size: 0.85rem; white-space: pre-wrap;">
<span class="text-muted">Ready for actions...</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Web Terminal Section -->
            <div id="terminal-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-terminal-fill me-2"></i>Web Terminal
                        </div>
                        <div class="d-flex align-items-center">
                            <select class="form-select form-select-sm me-2" id="terminalContainerSelect" style="width: 200px;">
                                <option value="<?= $site['container_name'] ?>"><?= $site['container_name'] ?> (App)</option>
                                <!-- Other containers loaded via JS -->
                            </select>
                            <button class="btn btn-sm btn-success" onclick="connectTerminal()">
                                <i class="bi bi-plug me-1"></i>Connect
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="terminal-container" style="height: 500px; background-color: #000;"></div>
                    </div>
                    <div class="card-footer text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        This is a non-interactive web console. Commands are executed in the container and output is returned.
                    </div>
                </div>
            </div>

            <!-- Logs Section -->
            <div id="logs-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-terminal me-2"></i>Container Logs
                        </div>
                        <button class="btn btn-sm btn-secondary" onclick="refreshLogs()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div id="log-terminal-container" style="height: 500px; background-color: #1e1e1e;"></div>
                    </div>
                </div>
            </div>

            <?php 
            // Show database section for any site with a database
            $showDatabaseSection = false;
            $dbType = $site['db_type'] ?? 'none';
            $isMariaDBInstance = ($site['type'] === 'mariadb');
            
            if ($isMariaDBInstance) {
                $showDatabaseSection = true;
                $dbType = 'mariadb';
            } elseif ($site['type'] === 'wordpress' && in_array($dbType, ['dedicated', 'custom'])) {
                $showDatabaseSection = true;
            } elseif ($site['type'] === 'php' && in_array($dbType, ['mysql', 'postgresql'])) {
                $showDatabaseSection = true;
            } elseif ($site['type'] === 'laravel' && in_array($dbType, ['mysql', 'postgresql'])) {
                $showDatabaseSection = true;
            }
            ?>
            <?php if ($showDatabaseSection): ?>
            <!-- Database Section -->
            <div id="database-section" class="content-section" style="display: none;">
                <div class="card mb-4">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-database me-2"></i>
                        <?php if ($isMariaDBInstance): ?>
                            MariaDB Database Instance
                        <?php else: ?>
                            <?= $dbType === 'custom' ? 'Custom External Database' : 'Dedicated Database' ?> (<?= strtoupper($dbType) ?>)
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-<?= $isMariaDBInstance ? 'success' : ($dbType === 'custom' ? 'warning' : 'info') ?>">
                            <i class="bi bi-info-circle me-2"></i>
                            <?php if ($isMariaDBInstance): ?>
                                <strong>Standalone MariaDB Instance:</strong> This is a dedicated database server that can be used by multiple applications.
                            <?php elseif ($dbType === 'custom'): ?>
                                This site connects to an external database server. You can edit the connection details below.
                            <?php else: ?>
                                This site has a dedicated <?= $dbType === 'postgresql' ? 'PostgreSQL' : 'MariaDB' ?> container running separately.
                            <?php endif; ?>
                        </div>

                        <h6 class="mb-3">Database Connection Information</h6>
                        
                        <?php if ($dbType === 'custom'): ?>
                        <!-- Custom Database - Editable Fields -->
                        <form id="customDbForm">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Database Host</label>
                                    <input type="text" class="form-control" id="dbHost" name="db_host" value="<?= htmlspecialchars($site['db_host'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>>
                                    <small class="text-muted">Database server hostname or IP</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Database Port</label>
                                    <input type="number" class="form-control" id="dbPort" name="db_port" value="<?= htmlspecialchars($site['db_port'] ?? 3306) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                                    <small class="text-muted">Default MySQL/MariaDB port is 3306</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Database Name</label>
                                    <input type="text" class="form-control" id="dbName" name="db_name" value="<?= htmlspecialchars($site['db_name'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>>
                                    <small class="text-muted">Name of the database</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Database User</label>
                                    <input type="text" class="form-control" id="dbUser" name="db_user" value="<?= htmlspecialchars($site['db_user'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>>
                                    <small class="text-muted">Database username</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Database Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="dbPassword" name="db_password" value="<?= htmlspecialchars($site['db_password'] ?? '') ?>" <?= $canEdit ? '' : 'readonly' ?>>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePasswordVisibility('dbPassword')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard('dbPassword')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Database password</small>
                            </div>
                            
                            <?php if ($canEdit): ?>
                            <button type="button" class="btn btn-primary" onclick="saveCustomDatabaseSettings()">
                                <i class="bi bi-save me-2"></i>Save Database Settings
                            </button>
                            <div class="alert alert-warning mt-3">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Note:</strong> Changing database settings will require restarting the container for changes to take effect.
                            </div>
                            <?php endif; ?>
                        </form>
                        <?php elseif ($isMariaDBInstance): ?>
                        <!-- MariaDB Instance - Show credentials -->
                        <?php 
                        $dbCredentials = json_decode($site['db_password'] ?? '{}', true);
                        $rootPassword = $dbCredentials['root'] ?? '';
                        $userPassword = $dbCredentials['user'] ?? '';
                        ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Database Host</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="dbHost" value="<?= htmlspecialchars($site['container_name']) ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbHost')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Container name for internal connections</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Database Port</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="dbPort" value="3306" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbPort')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Internal MariaDB port</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Database Name</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="dbName" value="<?= htmlspecialchars($site['db_name'] ?? 'defaultdb') ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbName')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Database User</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="dbUser" value="<?= htmlspecialchars($site['db_user'] ?? 'dbuser') ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbUser')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">User Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control font-monospace" id="dbPassword" value="<?= htmlspecialchars($userPassword) ?>" readonly>
                                <button class="btn btn-outline-secondary" onclick="togglePasswordVisibility('dbPassword')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbPassword')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">Password for regular database user</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Root Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control font-monospace" id="dbRootPassword" value="<?= htmlspecialchars($rootPassword) ?>" readonly>
                                <button class="btn btn-outline-secondary" onclick="togglePasswordVisibility('dbRootPassword')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbRootPassword')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">Root password for administrative access</small>
                        </div>
                        
                        <hr>
                        
                        <h6 class="mb-3">External Network Access</h6>
                        <?php if ($site['db_port'] ?? false): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>External Access Enabled:</strong> Port <?= htmlspecialchars($site['db_port']) ?> is exposed to external network
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">External Port</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?= htmlspecialchars($site['db_port']) ?>" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbPort')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">Connect from outside: mysql -h YOUR_SERVER_IP -P <?= htmlspecialchars($site['db_port']) ?> -u <?= htmlspecialchars($site['db_user'] ?? 'dbuser') ?> -p</small>
                        </div>
                        <?php if ($canEdit): ?>
                        <button type="button" class="btn btn-warning" onclick="toggleExternalAccess(false)">
                            <i class="bi bi-shield-lock me-2"></i>Disable External Access
                        </button>
                        <div class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            This will remove port exposure and make the database accessible only from the Docker network
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="alert alert-success">
                            <i class="bi bi-shield-check me-2"></i>
                            <strong>Secure Mode:</strong> Database is only accessible from the internal Docker network
                        </div>
                        <?php if ($canEdit): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Enable External Access</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="externalPort" placeholder="3306" value="3306" min="1024" max="65535">
                                <button type="button" class="btn btn-primary" onclick="toggleExternalAccess(true)">
                                    <i class="bi bi-globe me-2"></i>Enable
                                </button>
                            </div>
                            <small class="text-muted">Choose a port to expose (1024-65535). Make sure the port is not already in use.</small>
                        </div>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Security Warning:</strong> Enabling external access will expose your database to the internet. Make sure to use strong passwords and consider firewall rules.
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php else: ?>
                        <!-- Dedicated Database - Read-only Fields -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Database Host</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="dbHost" value="<?= htmlspecialchars($site['container_name']) ?>_db" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbHost')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Internal Docker network hostname</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Database Port</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="dbPort" value="<?= $dbType === 'postgresql' ? '5432' : '3306' ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbPort')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Default <?= $dbType === 'postgresql' ? 'PostgreSQL' : 'MariaDB' ?> port</small>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Database Name</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="dbName" value="<?= $site['type'] === 'wordpress' ? 'wordpress' : 'appdb' ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbName')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Database User</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="dbUser" value="<?= $site['type'] === 'wordpress' ? 'wordpress' : 'appuser' ?>" readonly>
                                    <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbUser')">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Database Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="dbPassword" value="<?= htmlspecialchars($site['db_password'] ?? '••••••••') ?>" readonly>
                                <button class="btn btn-outline-secondary" onclick="togglePasswordVisibility('dbPassword')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('dbPassword')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">Keep this password secure</small>
                        </div>
                        <?php endif; ?>

                        <hr>

                        <h6 class="mb-3">Database Management</h6>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <button class="btn btn-primary w-100" onclick="openDatabaseManager()">
                                    <i class="bi bi-database-gear me-2"></i>Open Database Manager
                                </button>
                                <small class="text-muted d-block mt-1">
                                    <i class="bi bi-shield-check me-1"></i>Secure access with temporary token (expires in 5 minutes)
                                </small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <button class="btn btn-outline-primary w-100" onclick="viewDatabaseLogs()">
                                    <i class="bi bi-terminal me-2"></i>View Database Logs
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <button class="btn btn-outline-success w-100" onclick="restartDatabase()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Restart Database
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <button class="btn btn-outline-info w-100" onclick="exportDatabase()">
                                    <i class="bi bi-download me-2"></i>Export Database
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <button class="btn btn-outline-warning w-100" onclick="showDatabaseStats()">
                                    <i class="bi bi-graph-up me-2"></i>Database Stats
                                </button>
                            </div>
                        </div>

                        <div id="databaseOutput" class="mt-3" style="display: none;">
                            <pre class="bg-dark text-light p-3 rounded" style="max-height: 300px; overflow-y: auto;" id="databaseOutputContent"></pre>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-terminal me-2"></i>Quick Access Commands
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Connect to your database using these commands:</p>
                        
                        <?php if ($isMariaDBInstance): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">MySQL CLI (from host)</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="mysqlCommand" 
                                       value="docker exec -it <?= htmlspecialchars($site['container_name']) ?> mysql -u <?= htmlspecialchars($site['db_user'] ?? 'dbuser') ?> -p <?= htmlspecialchars($site['db_name'] ?? 'defaultdb') ?>" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('mysqlCommand')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">Run this command in your terminal to access MySQL CLI</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Database Backup</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="backupCommand" 
                                       value="docker exec <?= htmlspecialchars($site['container_name']) ?> mysqldump -u <?= htmlspecialchars($site['db_user'] ?? 'dbuser') ?> -p <?= htmlspecialchars($site['db_name'] ?? 'defaultdb') ?> > backup.sql" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('backupCommand')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">Creates a SQL backup file</small>
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">MySQL CLI (from host)</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="mysqlCommand" 
                                       value="docker exec -it <?= htmlspecialchars($site['container_name']) ?>_db mysql -u wordpress -p wordpress" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('mysqlCommand')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">Run this command in your terminal to access MySQL CLI</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Database Backup</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="backupCommand" 
                                       value="docker exec <?= htmlspecialchars($site['container_name']) ?>_db mysqldump -u wordpress -p wordpress > backup.sql" readonly>
                                <button class="btn btn-outline-secondary" onclick="copyToClipboard('backupCommand')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            <small class="text-muted">Creates a SQL backup file</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Check if Redis container exists for this site
            $redisContainerName = $site['container_name'] . '_redis';
            exec("docker ps -a --filter name=" . escapeshellarg($redisContainerName) . " --format '{{.Names}}' 2>&1", $redisCheck, $redisReturnCode);
            $hasRedis = ($redisReturnCode === 0 && !empty($redisCheck) && trim($redisCheck[0]) === $redisContainerName);
            ?>

            <!-- Redis Section - Shows for all sites -->
            <div id="redis-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-lightning-charge me-2"></i>Redis Cache
                    </div>
                    <div class="card-body">
                        <?php if ($hasRedis): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Redis is enabled</strong> for caching and performance optimization.
                            </div>

                            <h6 class="mb-3">Redis Connection Information</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Redis Host</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="redisHost" value="<?= htmlspecialchars($redisContainerName) ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('redisHost')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Internal Docker network hostname</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Redis Port</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="redisPort" value="6379" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('redisPort')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Default Redis port</small>
                                </div>
                            </div>

                            <?php if ($site['type'] === 'wordpress'): ?>
                            <div class="alert alert-warning">
                                <strong><i class="bi bi-plugin me-2"></i>WordPress Plugin Required:</strong><br>
                                Install <strong>Redis Object Cache</strong> plugin from WordPress admin.<br>
                                The plugin will auto-detect Redis using the hostname above.
                            </div>
                            <?php elseif ($site['type'] === 'php'): ?>
                            <div class="alert alert-info">
                                <strong><i class="bi bi-code me-2"></i>PHP Redis Configuration:</strong><br>
                                Use the following code to connect to Redis in your PHP application:
                                <pre class="bg-dark text-light p-2 mt-2 rounded"><code>$redis = new Redis();
$redis->connect('<?= htmlspecialchars($redisContainerName) ?>', 6379);
// Now you can use $redis->set(), $redis->get(), etc.</code></pre>
                            </div>
                            <?php elseif ($site['type'] === 'laravel'): ?>
                            <div class="alert alert-info">
                                <strong><i class="bi bi-code me-2"></i>Laravel Redis Configuration:</strong><br>
                                Add to your <code>.env</code> file:
                                <pre class="bg-dark text-light p-2 mt-2 rounded"><code>REDIS_HOST=<?= htmlspecialchars($redisContainerName) ?>

REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis</code></pre>
                            </div>
                            <?php endif; ?>

                            <h6 class="mb-3">Redis Management</h6>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <button class="btn btn-outline-danger w-100" onclick="flushRedis()">
                                        <i class="bi bi-trash me-2"></i>Flush Cache
                                    </button>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <button class="btn btn-outline-success w-100" onclick="restartRedis()">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Restart Redis
                                    </button>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <button class="btn btn-outline-warning w-100" onclick="disableRedis()">
                                        <i class="bi bi-x-circle me-2"></i>Disable Redis
                                    </button>
                                </div>
                            </div>

                            <div id="redisOutput" class="mt-3" style="display: none;">
                                <pre class="bg-dark text-light p-3 rounded" id="redisOutputContent"></pre>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-secondary">
                                <i class="bi bi-info-circle me-2"></i>
                                Redis is not currently enabled for this site.
                            </div>

                            <p class="text-muted">
                                Enable Redis to improve performance with in-memory caching. 
                                Redis is great for:
                            </p>
                            <ul class="text-muted">
                                <li><strong>WordPress:</strong> Object caching, page caching</li>
                                <li><strong>PHP:</strong> Session storage, data caching</li>
                                <li><strong>Laravel:</strong> Cache, sessions, queues</li>
                            </ul>

                            <button class="btn btn-primary" onclick="enableRedis()">
                                <i class="bi bi-lightning-charge me-2"></i>Enable Redis
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SFTP Section -->
            <div id="sftp-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-hdd-network me-2"></i>SFTP Access
                    </div>
                    <div class="card-body">
                        <?php if ($site['sftp_enabled']): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>SFTP is enabled</strong> - You can access your files via SFTP
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Host</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sftpHost" value="<?= preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'your-server-ip') ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('sftpHost')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Use this IP/hostname to connect via SFTP</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Port</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sftpPort" value="<?= $site['sftp_port'] ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('sftpPort')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Username</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sftpUsername" value="<?= htmlspecialchars($site['sftp_username']) ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('sftpUsername')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="sftpPassword" value="<?= htmlspecialchars($site['sftp_password']) ?>" readonly>
                                        <button class="btn btn-outline-secondary" onclick="togglePasswordVisibility('sftpPassword')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="copyToClipboard('sftpPassword')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Connection String:</strong><br>
                                <code>sftp://<?= htmlspecialchars($site['sftp_username']) ?>@<?= $_SERVER['SERVER_ADDR'] ?? 'localhost' ?>:<?= $site['sftp_port'] ?></code>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-warning" onclick="regenerateSFTPPassword()">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Regenerate Password
                                </button>
                                <button class="btn btn-danger" onclick="disableSFTP()">
                                    <i class="bi bi-x-circle me-2"></i>Disable SFTP Access
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>SFTP is disabled</strong> - Enable SFTP to access your files remotely
                            </div>
                            
                            <p>SFTP (SSH File Transfer Protocol) allows you to securely access and manage your site files using an SFTP client like FileZilla, WinSCP, or Cyberduck.</p>
                            
                            <h6 class="mt-4">Features:</h6>
                            <ul>
                                <li>Secure file transfer over SSH</li>
                                <li>Direct access to your site's files</li>
                                <li>Upload, download, and edit files</li>
                                <li>Automatic credentials generation</li>
                            </ul>
                            
                            <button class="btn btn-primary" onclick="enableSFTP()">
                                <i class="bi bi-check-circle me-2"></i>Enable SFTP Access
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Backup Section -->
            <div id="backup-section" class="content-section" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-cloud-download me-2"></i>Backup & Restore
                    </div>
                    <div class="card-body">
                        <p>Backup and restore functionality coming soon...</p>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div id="danger-section" class="content-section" style="display: none;">
                <div class="card border-danger">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                    </div>
                    <div class="card-body">
                        <h6>Delete This Site</h6>
                        <p class="text-muted">Once you delete a site, there is no going back. Please be certain.</p>
                        <?php if ($userPermission === 'manage'): ?>
                        <button class="btn btn-danger" onclick="deleteSite(<?= $site['id'] ?>)">
                            <i class="bi bi-trash me-2"></i>Delete Site
                        </button>
                        <?php else: ?>
                        <button class="btn btn-danger" disabled title="Requires 'Manage' permission">
                            <i class="bi bi-lock me-2"></i>Delete Site (No Permission)
                        </button>
                        <small class="text-muted d-block mt-2">You need 'Manage' permission to delete this site.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    <!-- File Editor Modal -->
    <div class="modal fade" id="fileEditorModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>Edit File: <span id="editFileName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea id="fileEditorContent" class="form-control" rows="20" style="font-family: 'Courier New', monospace; font-size: 14px;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveFileContent()">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Environment Editor Modal -->
    <div class="modal fade" id="envEditorModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>Edit Environment Configuration (.env)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Be careful when editing this file. Invalid syntax can break your application.
                        Sensitive information like passwords and keys are stored here.
                    </div>
                    <div class="mb-3">
                        <textarea id="envEditorContent" class="form-control" rows="20" style="font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 14px; background-color: #1e1e1e; color: #d4d4d4;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveEnvFile()">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js?v=3.0.<?= time() ?>"></script>
    <script>
        const siteId = <?= $siteId ?>;
        const siteName = '<?= addslashes($site['name']) ?>';
        const containerName = '<?= addslashes($site['container_name']) ?>';
        const siteDomain = '<?= addslashes($site['domain']) ?>';
        const siteSSL = <?= $site['ssl'] ? 'true' : 'false' ?>;
        const userPermission = '<?= $userPermission ?>';
        const canEdit = <?= $canEdit ? 'true' : 'false' ?>;

        // Navigation function
        function navigateToSection(section) {
            // Skip if no section (e.g., external links like Docker Compose editor)
            if (!section) {
                return;
            }
            
            // Handle compose link separately (external)
            if (section === 'compose') {
                window.open('/compose-editor.php?site_id=<?= $siteId ?>', '_blank');
                return;
            }
            
            // Update active state in sidebar
            document.querySelectorAll('.sidebar-nav-item').forEach(i => i.classList.remove('active'));
            const sidebarItem = document.querySelector(`[data-section="${section}"]`);
            if (sidebarItem) {
                sidebarItem.classList.add('active');
            }
            
            // Update mobile select
            const mobileSelect = document.getElementById('mobile-nav-select');
            if (mobileSelect) {
                mobileSelect.value = section;
            }
            
            // Show section
            document.querySelectorAll('.content-section').forEach(s => s.style.display = 'none');
            const sectionElement = document.getElementById(section + '-section');
            if (sectionElement) {
                sectionElement.style.display = 'block';
            }
            
            // Update URL with clean path for bookmarking
            const siteId = <?= $siteId ?>;
            const newUrl = section === 'overview' ? `/edit/${siteId}/` : `/edit/${siteId}/${section}/`;
            window.history.pushState({}, '', newUrl);
            
            // Scroll to top on mobile
            if (window.innerWidth < 768) {
                window.scrollTo(0, 0);
            }
        }
        
        // Sidebar navigation
        document.querySelectorAll('.sidebar-nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const section = this.dataset.section;
                
                // Skip if no section (e.g., external links like Docker Compose editor)
                if (!section) {
                    return;
                }
                
                e.preventDefault();
                navigateToSection(section);
            });
        });
        
        // Mobile select navigation
        const mobileSelect = document.getElementById('mobile-nav-select');
        if (mobileSelect) {
            mobileSelect.addEventListener('change', function() {
                navigateToSection(this.value);
            });
        }
        
        // Load active tab from URL on page load
        const activeTab = '<?= htmlspecialchars($activeTab) ?>';
        if (activeTab && activeTab !== 'overview') {
            const tabLink = document.querySelector(`[data-section="${activeTab}"]`);
            if (tabLink) {
                tabLink.click();
            }
        }

        // Disable all forms and buttons for view-only users
        if (!canEdit) {
            // Disable all form inputs
            document.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(el => {
                el.disabled = true;
                el.classList.add('bg-light');
            });
            
            // Disable all submit buttons
            document.querySelectorAll('button[type="submit"], button.btn-primary, button.btn-success, button.btn-warning').forEach(btn => {
                if (!btn.classList.contains('btn-outline-secondary')) { // Keep "Back" button enabled
                    btn.disabled = true;
                    btn.classList.add('disabled');
                }
            });
            
            // Add click handler to show message
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    alert('You have view-only access. Contact an administrator for edit permissions.');
                });
            });
        }

        // Settings Form
        document.getElementById('settingsForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!canEdit) return;
            const name = document.getElementById('siteName').value;
            
            try {
                const response = await fetch('/api.php?action=update_site', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        site_id: siteId,
                        name: name,
                        domain: siteDomain,
                        ssl: siteSSL
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    alert('Settings updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        });
        
        // GitHub Form
        document.getElementById('githubForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const repo = document.getElementById('githubRepo').value;
            const branch = document.getElementById('githubBranch').value;
            const token = document.getElementById('githubToken').value;
            
            try {
                const response = await fetch('/api.php?action=update_site', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        site_id: siteId,
                        name: siteName,
                        domain: siteDomain,
                        ssl: siteSSL,
                        github_repo: repo,
                        github_branch: branch,
                        github_token: token
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    alert('GitHub settings updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        });
        
        // Check for GitHub updates
        async function checkForGithubUpdates() {
            try {
                const response = await fetch(`/api.php?action=check_github_updates&id=${siteId}`);
                const result = await response.json();
                
                if (result.success) {
                    if (result.has_updates) {
                        alert(`Updates available!\n\nLocal: ${result.local_commit}\nRemote: ${result.remote_commit}`);
                    } else {
                        alert('You\'re up to date! No new commits available.');
                    }
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }
        
        // Pull from GitHub
        async function pullFromGithubRepo() {
            if (!confirm('Pull latest changes from GitHub?\n\nThis will update all files in the container.')) {
                return;
            }
            
            try {
                const response = await fetch(`/api.php?action=pull_from_github&id=${siteId}`, {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message || 'Successfully pulled latest changes!');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }
        
        // Force Pull from GitHub (override local changes)
        async function forcePullFromGithubRepo() {
            if (!confirm('⚠️ WARNING: Force Pull from GitHub?\n\nThis will:\n- DISCARD ALL LOCAL CHANGES\n- Reset to remote repository state\n- Override all modified files\n\nThis action CANNOT be undone!\n\nAre you sure you want to continue?')) {
                return;
            }
            
            try {
                const response = await fetch(`/api.php?action=force_pull_from_github&id=${siteId}`, {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message || 'Successfully force pulled from GitHub!');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }
        
        // Run Laravel Build
        async function runLaravelBuild() {
            if (!confirm('Run Laravel build steps?\n\nThis will:\n- Install Composer dependencies\n- Run migrations\n- Install NPM dependencies\n- Build frontend assets\n- Cache configuration')) {
                return;
            }
            
            try {
                const response = await fetch(`/api.php?action=build_laravel&id=${siteId}`, {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Laravel build completed!\n\n' + (result.details || result.message));
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }
        
        // Fix Laravel Permissions
        async function fixLaravelPermissions() {
            if (!confirm('Fix Laravel file permissions?\n\nThis will:\n- Set ownership to web user (www or www-data)\n- Set directory permissions (755)\n- Set file permissions (644)\n- Set storage/cache permissions (775)')) {
                return;
            }
            
            try {
                const response = await fetch(`/api.php?action=fix_laravel_permissions&id=${siteId}`, {
                    method: 'POST'
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('Permissions fixed!\n\n' + (result.details || result.message));
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }

        // Domain Form
        document.getElementById('domainForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const domain = document.getElementById('siteDomain').value;
            const ssl = document.getElementById('sslEnabled').checked;
            const includeWww = document.getElementById('includeWww').checked;
            
            // Gather SSL configuration
            const sslConfig = {
                challenge: document.getElementById('sslChallengeMethod')?.value || 'http',
                provider: null,
                credentials: {}
            };
            
            if (sslConfig.challenge === 'dns') {
                sslConfig.provider = document.getElementById('dnsProvider')?.value;
                
                // Gather credentials based on provider
                if (sslConfig.provider === 'cloudflare') {
                    const token = document.getElementById('cfApiToken')?.value;
                    if (token) sslConfig.credentials.cf_api_token = token;
                } else if (sslConfig.provider === 'route53') {
                    const accessKey = document.getElementById('awsAccessKey')?.value;
                    const secretKey = document.getElementById('awsSecretKey')?.value;
                    if (accessKey) sslConfig.credentials.aws_access_key = accessKey;
                    if (secretKey) sslConfig.credentials.aws_secret_key = secretKey;
                } else if (sslConfig.provider === 'digitalocean') {
                    const token = document.getElementById('doAuthToken')?.value;
                    if (token) sslConfig.credentials.do_auth_token = token;
                }
            }
            
            if (!confirm('Updating SSL settings will require a container restart. Continue?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=update_site_ssl', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        site_id: siteId,
                        name: siteName,
                        domain: domain,
                        ssl: ssl,
                        include_www: includeWww,
                        ssl_config: sslConfig
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(result.message || 'SSL settings updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        });

        // Container actions
        function viewSite(domain, ssl) {
            const protocol = ssl ? 'https' : 'http';
            window.open(protocol + '://' + domain, '_blank');
        }

        async function changePHPVersion() {
            const newVersion = document.getElementById('phpVersionSelect').value;
            const currentVersion = '<?= htmlspecialchars($site['php_version'] ?? '8.3') ?>';
            
            if (newVersion === currentVersion) {
                showAlert('info', 'Already using PHP ' + newVersion);
                return;
            }
            
            if (!confirm(`Switch from PHP ${currentVersion} to PHP ${newVersion}?\n\nThis will:\n- Restart your container\n- Cause brief downtime\n- Require application compatibility\n\nContinue?`)) {
                return;
            }
            
            showAlert('info', 'Switching to PHP ' + newVersion + '...');
            
            try {
                const response = await fetch('/api.php?action=change_php_version', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        site_id: siteId,
                        php_version: newVersion
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'PHP version changed to ' + newVersion + '! Reloading...');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }

        async function restartContainer() {
            if (!confirm('Restart container ' + containerName + '?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=restart_container&id=' + siteId);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Server returned invalid JSON. The restart may have timed out.');
                }
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }

        async function startContainer() {
            try {
                const response = await fetch('/api.php?action=start_container&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }

        async function stopContainer() {
            if (!confirm('Stop container ' + containerName + '?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=stop_container&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }

        function viewLogs() {
            document.querySelector('[data-section="logs"]').click();
            // Allow tab switch animation to complete
            setTimeout(refreshLogs, 100);
        }

        let logTerm;
        let logFitAddon;

        function initLogTerminal() {
            if (logTerm) return;
            
            logTerm = new Terminal({
                cursorBlink: false,
                disableStdin: true, // Read-only
                theme: {
                    background: '#1e1e1e',
                    foreground: '#d4d4d4',
                    selectionBackground: 'rgba(255, 255, 255, 0.3)'
                },
                fontSize: 13,
                lineHeight: 1.2,
                fontFamily: 'Consolas, "Courier New", monospace',
                convertEol: true // Treat \n as \r\n
            });
            
            logFitAddon = new FitAddon.FitAddon();
            logTerm.loadAddon(logFitAddon);
            
            try {
                if (window.WebglAddon) {
                    const webglAddon = new window.WebglAddon.WebglAddon();
                    webglAddon.onContextLoss(e => webglAddon.dispose());
                    logTerm.loadAddon(webglAddon);
                }
            } catch (e) {}
            
            logTerm.open(document.getElementById('log-terminal-container'));
            logFitAddon.fit();
            
            window.addEventListener('resize', () => logFitAddon.fit());
        }
        
        // Initialize log terminal when tab is shown
        document.addEventListener('DOMContentLoaded', () => {
             const logsTab = document.querySelector('[data-section="logs"]');
             if (logsTab) {
                 logsTab.addEventListener('click', () => {
                     setTimeout(() => {
                         initLogTerminal();
                         if (!logTerm.element.textContent) { // If empty, load logs
                             refreshLogs();
                         } else {
                             logFitAddon.fit();
                         }
                     }, 100);
                 });
             }
        });

        async function refreshLogs() {
            if (!logTerm) initLogTerminal();
            
            logTerm.clear();
            logTerm.write('Loading logs...\r\n');
            
            try {
                const response = await fetch('/api.php?action=get_logs&id=' + siteId + '&lines=500');
                const result = await response.json();
                
                if (result.success) {
                    logTerm.clear();
                    if (result.logs) {
                        logTerm.write(result.logs);
                    } else {
                        logTerm.write('No logs available.');
                    }
                } else {
                    logTerm.write('\x1b[31mError loading logs: ' + (result.error || 'Unknown error') + '\x1b[0m');
                }
            } catch (error) {
                logTerm.write('\x1b[31mNetwork error: ' + error.message + '\x1b[0m');
            }
        }

        function backupSite() {
            document.querySelector('[data-section="backup"]').click();
        }

        async function refreshContainers() {
            const loading = document.getElementById('containersListLoading');
            const list = document.getElementById('containersList');
            
            loading.style.display = 'block';
            list.style.display = 'none';
            
            try {
                const response = await fetch('/api.php?action=get_site_containers&id=' + siteId);
                const result = await response.json();
                
                if (result.success && result.containers) {
                    let html = '<div class="list-group list-group-flush">';
                    
                    result.containers.forEach(container => {
                        const statusColor = container.status === 'running' ? 'success' : 
                                          container.status === 'exited' ? 'danger' : 'warning';
                        const statusIcon = container.status === 'running' ? 'play-circle-fill' : 
                                         container.status === 'exited' ? 'stop-circle-fill' : 'pause-circle-fill';
                        
                        html += `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="bi bi-box me-2"></i>${container.name}
                                        </h6>
                                        <small class="text-muted">${container.image}</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-${statusColor}">
                                            <i class="bi bi-${statusIcon} me-1"></i>${container.status}
                                        </span>
                                        ${container.uptime ? `<br><small class="text-muted">${container.uptime}</small>` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    list.innerHTML = html;
                    list.style.display = 'block';
                    loading.style.display = 'none';
                } else {
                    list.innerHTML = '<p class="text-muted mb-0">No containers found</p>';
                    list.style.display = 'block';
                    loading.style.display = 'none';
                }
            } catch (error) {
                list.innerHTML = '<p class="text-danger mb-0">Error loading containers: ' + error.message + '</p>';
                list.style.display = 'block';
                loading.style.display = 'none';
            }
        }

        async function refreshStats() {
            try {
                const response = await fetch('/api.php?action=get_stats&id=' + siteId);
                const result = await response.json();
                
                console.log('Stats response:', result); // Debug log
                
                if (result.success && result.stats) {
                    // Update status and uptime
                    if (result.stats.status) {
                        document.getElementById('containerStatus').textContent = result.stats.status.charAt(0).toUpperCase() + result.stats.status.slice(1);
                    }
                    
                    if (result.stats.uptime) {
                        document.getElementById('containerUptime').textContent = result.stats.uptime;
                    }
                    
                    if (result.stats.volume_size) {
                        document.getElementById('volumeSize').textContent = result.stats.volume_size;
                    }
                    
                    // Update CPU stats if available
                    if (result.stats.cpu && result.stats.cpu !== 'N/A') {
                        document.getElementById('cpuUsage').textContent = result.stats.cpu;
                        const cpuPercent = parseFloat(result.stats.cpu_percent) || 0;
                        document.getElementById('cpuBar').style.width = Math.min(cpuPercent, 100) + '%';
                    } else {
                        document.getElementById('cpuUsage').textContent = 'N/A';
                        document.getElementById('cpuBar').style.width = '0%';
                    }
                    
                    // Update Memory stats if available
                    if (result.stats.memory && result.stats.memory !== 'N/A') {
                        document.getElementById('memoryUsage').textContent = result.stats.memory;
                        const memPercent = parseFloat(result.stats.mem_percent) || 0;
                        document.getElementById('memoryBar').style.width = Math.min(memPercent, 100) + '%';
                    } else {
                        document.getElementById('memoryUsage').textContent = 'N/A';
                        document.getElementById('memoryBar').style.width = '0%';
                    }
                } else {
                    console.error('Error loading stats:', result.error || 'Unknown error');
                    // Set error state
                    document.getElementById('containerUptime').textContent = 'Error';
                    document.getElementById('volumeSize').textContent = 'Error';
                }
            } catch (error) {
                console.error('Network error:', error.message);
                // Set error state
                document.getElementById('containerUptime').textContent = 'Error';
                document.getElementById('volumeSize').textContent = 'Error';
            }
        }

        async function deleteSite(id) {
            if (!confirm('Are you sure you want to delete this site? This action cannot be undone!')) {
                return;
            }
            
            if (!confirm('Really delete? All data will be lost!')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=delete_site&id=' + id);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Site deleted successfully. Redirecting to dashboard...');
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 1500);
                } else {
                    showAlert('danger', result.error || 'Failed to delete site');
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        function showAlert(type, message) {
            const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            setTimeout(() => {
                const alert = document.querySelector('.alert:last-of-type');
                if (alert) {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                }
            }, 5000);
        }
        
        // Auto-refresh stats every 3 minutes
        setInterval(refreshStats, 180000);
        
        // Load stats and containers on page load
        refreshStats();
        refreshContainers();
        
        // Refresh containers every 10 seconds
        setInterval(refreshContainers, 10000);
        
        // SFTP Functions
        async function enableSFTP() {
            if (!confirm('Enable SFTP access for this site?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=enable_sftp&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function disableSFTP() {
            if (!confirm('Disable SFTP access? This will stop the SFTP container.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=disable_sftp&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function regenerateSFTPPassword() {
            if (!confirm('Regenerate SFTP password? The old password will no longer work.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=regenerate_sftp_password&id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('sftpPassword').value = result.password;
                    showAlert('success', result.message + ' New password: ' + result.password);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            document.execCommand('copy');
            showAlert('success', 'Copied to clipboard!');
        }
        
        function togglePasswordVisibility(elementId) {
            const element = document.getElementById(elementId);
            const button = event.target.closest('button');
            const icon = button.querySelector('i');
            
            if (element.type === 'password') {
                element.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                element.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Database Management Functions
        async function viewDatabaseLogs() {
            const output = document.getElementById('databaseOutput');
            const content = document.getElementById('databaseOutputContent');
            
            content.textContent = 'Loading database logs...';
            output.style.display = 'block';
            
            try {
                const response = await fetch(`/api.php?action=get_container_logs&container=${containerName}_db&lines=50`);
                const result = await response.json();
                
                if (result.success) {
                    content.textContent = result.logs || 'No logs available';
                } else {
                    content.textContent = 'Error: ' + (result.error || 'Failed to fetch logs');
                }
            } catch (error) {
                content.textContent = 'Network error: ' + error.message;
            }
        }
        
        async function restartDatabase() {
            if (!confirm('Are you sure you want to restart the database? This will briefly disconnect your site.')) {
                return;
            }
            
            showAlert('info', 'Restarting database...');
            
            try {
                // Use docker command directly to restart the database container
                const response = await fetch('/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'execute_docker_command',
                        command: `restart ${containerName}_db`
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Database restarted successfully!');
                } else {
                    showAlert('danger', 'Failed to restart database: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        function logLaravelActivity(message, type = 'info') {
            const logOutput = document.getElementById('laravelLogOutput');
            if (!logOutput) return;
            
            // Clear "Ready for actions..." if present
            if (logOutput.querySelector('.text-muted') && logOutput.children.length === 1) {
                logOutput.innerHTML = '';
            }
            
            const timestamp = new Date().toLocaleTimeString();
            let colorClass = 'text-light';
            
            if (type === 'error' || type === 'danger') colorClass = 'text-danger';
            else if (type === 'success') colorClass = 'text-success';
            else if (type === 'warning') colorClass = 'text-warning';
            else if (type === 'command') colorClass = 'text-info';
            
            const entry = document.createElement('div');
            entry.className = `mb-1 ${colorClass}`;
            
            // Format message to handle newlines nicely
            const formattedMessage = message.replace(/\n/g, '<br>');
            
            entry.innerHTML = `<span class="text-muted small me-2">[${timestamp}]</span> ${formattedMessage}`;
            
            logOutput.appendChild(entry);
            logOutput.scrollTop = logOutput.scrollHeight;
        }
        
        function clearLaravelLog() {
            const logOutput = document.getElementById('laravelLogOutput');
            if (logOutput) {
                logOutput.innerHTML = '<span class="text-muted">Ready for actions...</span>';
            }
        }
        
        function handleArtisanEnter(e) {
            if (e.key === 'Enter') {
                executeLaravelCommand();
            }
        }
        
        async function openEnvEditor() {
            const modal = new bootstrap.Modal(document.getElementById('envEditorModal'));
            document.getElementById('envEditorContent').value = 'Loading .env file...';
            modal.show();
            
            try {
                const response = await fetch(`/api.php?action=read_file&id=${siteId}&path=/var/www/html/.env`);
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('envEditorContent').value = result.content;
                } else {
                    document.getElementById('envEditorContent').value = '# Error loading .env file: ' + (result.error || 'Unknown error');
                    // If file doesn't exist, maybe show .env.example?
                    if (result.error && result.error.includes('No such file')) {
                         try {
                             const exResponse = await fetch(`/api.php?action=read_file&id=${siteId}&path=/var/www/html/.env.example`);
                             const exResult = await exResponse.json();
                             if (exResult.success) {
                                 document.getElementById('envEditorContent').value = exResult.content;
                                 showAlert('info', '.env not found, loaded .env.example instead.');
                             }
                         } catch (e) {}
                    }
                }
            } catch (error) {
                document.getElementById('envEditorContent').value = '# Network error: ' + error.message;
            }
        }
        
        async function saveEnvFile() {
            const content = document.getElementById('envEditorContent').value;
            
            if (!confirm('Are you sure you want to save changes to .env? This may affect your application.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=save_file', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: siteId,
                        path: '/var/www/html/.env',
                        content: content
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', '.env file saved successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('envEditorModal')).hide();
                    
                    // Ask if user wants to clear config cache
                    if (confirm('.env saved. Do you want to run config:cache to apply changes?')) {
                        executeLaravelCommand('config:cache');
                    }
                } else {
                    alert('Failed to save .env: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }

        async function executeLaravelCommand(cmd) {
            console.log('executeLaravelCommand called with type:', typeof cmd, 'value:', cmd);
            
            // If cmd is an object (event) or not a string, treat as empty unless it's a string
            if (typeof cmd !== 'string') {
                cmd = '';
            }
            
            let isCustom = false;
            if (!cmd) {
                const input = document.getElementById('artisanCommand');
                if (input) {
                    cmd = input.value.trim();
                    isCustom = true;
                }
            }
            
            if (!cmd) {
                alert('Please enter a command');
                return;
            }
            
            logLaravelActivity(`> php artisan ${cmd}`, 'command');
            
            try {
                const response = await fetch('/api.php?action=execute_laravel_command', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: siteId,
                        command: cmd
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    logLaravelActivity(result.output || 'Command executed successfully', 'success');
                    if (isCustom) {
                        const input = document.getElementById('artisanCommand');
                        if (input) input.value = '';
                    }
                } else {
                    logLaravelActivity(`Error: ${result.error || 'Unknown error'}\n${result.output || ''}`, 'error');
                }
            } catch (error) {
                logLaravelActivity(`Network error: ${error.message}`, 'error');
            }
        }
        
        async function fixLaravelPermissions() {
            if (!confirm('This will reset permissions for the Laravel application. Continue?')) {
                return;
            }
            
            logLaravelActivity('> Fixing permissions...', 'command');
            
            try {
                const response = await fetch(`/api.php?action=fix_laravel_permissions&id=${siteId}`);
                const result = await response.json();
                
                if (result.success) {
                    logLaravelActivity('Permissions fixed successfully!', 'success');
                    if (result.details) logLaravelActivity(result.details, 'info');
                } else {
                    logLaravelActivity(`Failed to fix permissions: ${result.error || 'Unknown error'}`, 'error');
                }
            } catch (error) {
                logLaravelActivity(`Network error: ${error.message}`, 'error');
            }
        }

        async function installNodeJs() {
            if (!confirm('Install Node.js (v20) and NPM in this container? This may take a minute.')) return;
            
            logLaravelActivity('> Installing Node.js and NPM...', 'command');
            
            try {
                const response = await fetch(`/api.php?action=install_nodejs&id=${siteId}`);
                const result = await response.json();
                
                if (result.success) {
                    logLaravelActivity(result.message, 'success');
                    if (result.output) logLaravelActivity(result.output, 'info');
                } else {
                    logLaravelActivity(`Failed to install: ${result.error || 'Unknown error'}`, 'error');
                }
            } catch (error) {
                logLaravelActivity(`Network error: ${error.message}`, 'error');
            }
        }

        async function executeShellCommand(cmd) {
             if (!confirm(`Run command: ${cmd}?`)) return;
             
             logLaravelActivity(`> ${cmd}`, 'command');
             logLaravelActivity('... Running (this may take a while) ...', 'info');
             
             try {
                const response = await fetch('/api.php?action=execute_shell_command', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        site_id: siteId,
                        container: containerName,
                        command: cmd,
                        cwd: '/var/www/html'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    logLaravelActivity(`Error parsing server response: ${text.substring(0, 200)}...`, 'error');
                    return;
                }
                
                if (result.success) {
                    const output = result.output ? result.output.trim() : 'Command executed (no output)';
                    if (result.exit_code === 0) {
                        logLaravelActivity(output, 'success');
                    } else {
                        logLaravelActivity(`Exited with code ${result.exit_code}:\n${output}`, 'error');
                    }
                } else {
                    logLaravelActivity(`Error: ${result.error || 'Unknown error'}`, 'error');
                }
             } catch (error) {
                 logLaravelActivity(`Network error: ${error.message}`, 'error');
             }
        }

        async function flushRedis() {
            if (!confirm('Are you sure you want to flush the Redis cache? This will clear all cached data.')) {
                return;
            }
            
            document.getElementById('redisOutput').style.display = 'block';
            document.getElementById('redisOutputContent').textContent = 'Flushing Redis cache...';
            
            try {
                const response = await fetch('/api.php?action=flush_redis&id=<?= $site['id'] ?>', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('redisOutputContent').textContent = 'Redis cache flushed successfully!';
                    showAlert('success', 'Redis cache cleared!');
                } else {
                    document.getElementById('redisOutputContent').textContent = 'Error: ' + (result.error || 'Unknown error');
                    showAlert('danger', 'Failed to flush Redis: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                document.getElementById('redisOutputContent').textContent = 'Network error: ' + error.message;
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function restartRedis() {
            if (!confirm('Are you sure you want to restart Redis? This will briefly clear the cache.')) {
                return;
            }
            
            document.getElementById('redisOutput').style.display = 'block';
            document.getElementById('redisOutputContent').textContent = 'Restarting Redis...';
            
            try {
                const response = await fetch('/api.php?action=restart_redis&id=<?= $site['id'] ?>', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('redisOutputContent').textContent = 'Redis restarted successfully!';
                    showAlert('success', 'Redis restarted!');
                } else {
                    document.getElementById('redisOutputContent').textContent = 'Error: ' + (result.error || 'Unknown error');
                    showAlert('danger', 'Failed to restart Redis: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                document.getElementById('redisOutputContent').textContent = 'Network error: ' + error.message;
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function enableRedis() {
            if (!confirm('Enable Redis caching for this site? A Redis container will be created.')) {
                return;
            }
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enabling...';
            btn.disabled = true;
            
            try {
                const response = await fetch('/api.php?action=enable_redis&id=<?= $site['id'] ?>', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Redis enabled successfully! Reloading page...');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Failed to enable Redis: ' + (result.error || 'Unknown error'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
        
        async function disableRedis() {
            if (!confirm('Disable Redis caching? The Redis container will be removed and all cached data will be lost.')) {
                return;
            }
            
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Disabling...';
            btn.disabled = true;
            
            try {
                const response = await fetch('/api.php?action=disable_redis&id=<?= $site['id'] ?>', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Redis disabled successfully! Reloading page...');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('danger', 'Failed to disable Redis: ' + (result.error || 'Unknown error'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
        
        async function openDatabaseManager() {
            try {
                showAlert('info', 'Generating secure access token...');
                
                const response = await fetch('/api.php?action=generate_db_token&site_id=' + siteId);
                const result = await response.json();
                
                if (result.success) {
                    // Open database manager in new tab
                    const url = '/database-manager.php?token=' + result.token;
                    window.open(url, '_blank', 'width=1200,height=800');
                    showAlert('success', 'Database Manager opened in new tab. Token expires in 5 minutes.');
                } else {
                    showAlert('danger', 'Failed to generate access token: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                showAlert('danger', 'Error: ' + error.message);
            }
        }
        
        async function exportDatabase() {
            showAlert('info', 'Exporting database... This may take a moment.');
            
            try {
                const response = await fetch(`/api.php?action=export_database&site_id=${siteId}`);
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Database exported! Download link: ' + result.file);
                    // Trigger download
                    window.location.href = result.download_url;
                } else {
                    showAlert('danger', 'Failed to export database: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function showDatabaseStats() {
            const output = document.getElementById('databaseOutput');
            const content = document.getElementById('databaseOutputContent');
            
            content.textContent = 'Loading database statistics...';
            output.style.display = 'block';
            
            try {
                const response = await fetch(`/api.php?action=get_database_stats&site_id=${siteId}`);
                const result = await response.json();
                
                if (result.success) {
                    content.textContent = result.stats || 'No statistics available';
                } else {
                    content.textContent = 'Error: ' + (result.error || 'Failed to fetch stats');
                }
            } catch (error) {
                content.textContent = 'Network error: ' + error.message;
            }
        }
        
        // File Manager Functions
        let currentPath = '/var/www/html';
        
        async function loadFiles(path = currentPath) {
            currentPath = path;
            document.getElementById('currentPath').textContent = path;
            
            // Show loading state
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading files...</p>
                    </td>
                </tr>
            `;
            
            try {
                console.log('Fetching files from:', path);
                const response = await fetch(`/api.php?action=list_files&id=${siteId}&path=${encodeURIComponent(path)}`);
                console.log('Response status:', response.status);
                
                const result = await response.json();
                console.log('API result:', result);
                
                if (result.success) {
                    displayFiles(result.files);
                } else {
                    fileList.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Error: ${result.error}</td></tr>`;
                    showAlert('danger', 'Error loading files: ' + result.error);
                }
            } catch (error) {
                console.error('File load error:', error);
                fileList.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Network error: ${error.message}</td></tr>`;
            }
        }

        // Laravel Functions
        async function runLaravelMigration() {
            if (!confirm('Run Laravel migrations?\n\nThis will execute "php artisan migrate --force". Ensure your database is ready.')) {
                return;
            }
            
            showAlert('info', 'Running migrations...');
            
            try {
                const response = await fetch('/api.php?action=execute_laravel_command', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: siteId,
                        command: 'migrate --force'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Migrations completed!');
                    document.getElementById('artisanOutput').style.display = 'block';
                    document.getElementById('artisanOutputContent').textContent = result.output;
                } else {
                    showAlert('danger', 'Migration failed: ' + result.error);
                    if (result.output) {
                        document.getElementById('artisanOutput').style.display = 'block';
                        document.getElementById('artisanOutputContent').textContent = result.output;
                    }
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }

        // Terminal Functions
        let term;
        let fitAddon;
        let currentContainer = containerName;
        
        function initTerminal() {
            if (term) return;
            
            term = new Terminal({
                cursorBlink: true,
                theme: {
                    background: '#1e1e1e', // Slightly lighter black for better contrast
                    foreground: '#d4d4d4', // Standard VS Code foreground
                    cursor: '#ffffff',
                    selectionBackground: 'rgba(255, 255, 255, 0.3)'
                },
                fontSize: 14,
                lineHeight: 1.2,
                fontFamily: 'Consolas, "Courier New", monospace', // Simpler stack, let browser resolve system mono
                allowTransparency: true
            });
            
            fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            
            // Try to load WebGL addon for better rendering
            try {
                if (window.WebglAddon) {
                    const webglAddon = new window.WebglAddon.WebglAddon();
                    webglAddon.onContextLoss(e => {
                        webglAddon.dispose();
                    });
                    term.loadAddon(webglAddon);
                }
            } catch (e) {
                console.warn('WebGL addon could not be loaded, falling back to canvas renderer', e);
            }
            
            term.open(document.getElementById('terminal-container'));
            fitAddon.fit();
            
            term.write('Welcome to WharfTales Web Console\r\n');
            term.write('Select a container and click Connect to start.\r\n\r\n');
            
            window.addEventListener('resize', () => fitAddon.fit());
            loadTerminalContainers();
        }
        
        async function loadTerminalContainers() {
            try {
                const response = await fetch('/api.php?action=get_site_containers&id=' + siteId);
                const result = await response.json();
                
                if (result.success && result.containers) {
                    const select = document.getElementById('terminalContainerSelect');
                    select.innerHTML = '';
                    
                    result.containers.forEach(c => {
                        const option = document.createElement('option');
                        option.value = c.name;
                        option.text = c.name + ' (' + c.image.split('/').pop().split(':')[0] + ')';
                        if (c.name === containerName) option.selected = true;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Failed to load containers for terminal', error);
            }
        }
        
        let terminalCwd = '/var/www/html';
        let commandHistory = [];
        let historyIndex = -1;
        let commandBuffer = '';
        
        function connectTerminal() {
            if (!term) initTerminal();
            
            const container = document.getElementById('terminalContainerSelect').value;
            currentContainer = container;
            
            term.reset();
            term.write(`Connecting to ${container}...\r\n`);
            term.write('Type a command and press Enter.\r\n');
            term.write(getPrompt());
            
            setupTerminalInput();
        }
        
        function getPrompt() {
            return `\r\n\u001b[1;32mwharftales\u001b[0m:\u001b[1;34m${terminalCwd}\u001b[0m$ `;
        }
        
        // Setup terminal input handler once
        document.addEventListener('DOMContentLoaded', () => {
             const terminalTab = document.querySelector('[data-section="terminal"]');
             if (terminalTab) {
                 terminalTab.addEventListener('click', () => {
                     setTimeout(() => {
                         initTerminal();
                     }, 100);
                 });
             }
        });
        
        function setupTerminalInput() {
            if (term._inputSetup) return;
            term._inputSetup = true;
            
            term.onData(e => {
                switch (e) {
                    case '\r': // Enter
                        term.write('\r\n');
                        if (commandBuffer.trim()) {
                            commandHistory.push(commandBuffer);
                            historyIndex = commandHistory.length;
                        }
                        executeTerminalCommand(commandBuffer);
                        commandBuffer = '';
                        break;
                    case '\u007F': // Backspace (DEL)
                        if (commandBuffer.length > 0) {
                            term.write('\b \b');
                            commandBuffer = commandBuffer.slice(0, -1);
                        }
                        break;
                    case '\u0003': // Ctrl+C
                        term.write('^C');
                        term.write(getPrompt());
                        commandBuffer = '';
                        break;
                    case '\u000c': // Ctrl+L
                        term.clear();
                        term.write(getPrompt());
                        commandBuffer = '';
                        break;
                    case '\u001b[A': // Up arrow
                        if (commandHistory.length > 0) {
                            if (historyIndex > 0) {
                                historyIndex--;
                            }
                            // Clear line
                            while (commandBuffer.length > 0) {
                                term.write('\b \b');
                                commandBuffer = commandBuffer.slice(0, -1);
                            }
                            commandBuffer = commandHistory[historyIndex];
                            term.write(commandBuffer);
                        }
                        break;
                    case '\u001b[B': // Down arrow
                        if (commandHistory.length > 0) {
                            if (historyIndex < commandHistory.length - 1) {
                                historyIndex++;
                                // Clear line
                                while (commandBuffer.length > 0) {
                                    term.write('\b \b');
                                    commandBuffer = commandBuffer.slice(0, -1);
                                }
                                commandBuffer = commandHistory[historyIndex];
                                term.write(commandBuffer);
                            } else {
                                historyIndex = commandHistory.length;
                                // Clear line
                                while (commandBuffer.length > 0) {
                                    term.write('\b \b');
                                    commandBuffer = commandBuffer.slice(0, -1);
                                }
                                commandBuffer = '';
                            }
                        }
                        break;
                    default:
                        // Handle normal characters
                        if (e.length === 1 && e >= ' ' && e <= '~') {
                            commandBuffer += e;
                            term.write(e);
                        }
                }
            });
        }
        
        async function executeTerminalCommand(cmd) {
            if (!cmd) {
                term.write(getPrompt());
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=execute_shell_command', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        site_id: siteId,
                        container: currentContainer,
                        command: cmd,
                        cwd: terminalCwd
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const output = (result.output || '').replace(/\n/g, '\r\n');
                    if (output) {
                        term.write(output);
                    }
                    // Update CWD if returned
                    if (result.cwd) {
                        terminalCwd = result.cwd;
                    }
                } else {
                    term.write(`\u001b[1;31mError: ${result.error}\u001b[0m\r\n`);
                }
            } catch (error) {
                term.write(`\u001b[1;31mNetwork error: ${error.message}\u001b[0m\r\n`);
            }
            
            term.write(getPrompt());
        }
        
        function displayFiles(files) {
            const fileList = document.getElementById('fileList');
            if (!files || files.length === 0) {
                fileList.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No files found</td></tr>';
                return;
            }
            
            fileList.innerHTML = files.map(file => {
                const icon = file.type === 'directory' ? 'bi-folder-fill text-warning' : 'bi-file-earmark text-primary';
                const size = file.type === 'directory' ? '-' : formatFileSize(file.size);
                
                return `
                    <tr>
                        <td><i class="bi ${icon}"></i></td>
                        <td>
                            ${file.type === 'directory' 
                                ? `<a href="#" onclick="loadFiles('${file.path}'); return false;">${file.name}</a>`
                                : file.name
                            }
                        </td>
                        <td>${size}</td>
                        <td><small>${file.modified}</small></td>
                        <td>
                            ${file.type === 'file' ? `
                                <button class="btn btn-sm btn-outline-info" onclick="editFile('${file.path}', '${file.name}')" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadFile('${file.path}')" title="Download">
                                    <i class="bi bi-download"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteFile('${file.path}')" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            ` : `
                                <button class="btn btn-sm btn-outline-primary" onclick="loadFiles('${file.path}')" title="Open">
                                    <i class="bi bi-folder-open"></i>
                                </button>
                            `}
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        function navigateUp() {
            const parts = currentPath.split('/').filter(p => p);
            parts.pop();
            const newPath = '/' + parts.join('/');
            loadFiles(newPath || '/var/www/html');
        }
        
        async function downloadFile(path) {
            window.open(`/api.php?action=download_file&id=${siteId}&path=${encodeURIComponent(path)}`, '_blank');
        }
        
        let currentEditFilePath = '';
        
        async function editFile(path, filename) {
            currentEditFilePath = path;
            document.getElementById('editFileName').textContent = filename;
            document.getElementById('fileEditorContent').value = 'Loading...';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('fileEditorModal'));
            modal.show();
            
            try {
                const response = await fetch('/api.php?action=read_file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: siteId, path: path})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('fileEditorContent').value = result.content;
                } else {
                    document.getElementById('fileEditorContent').value = 'Error loading file: ' + result.error;
                    showAlert('danger', 'Error loading file: ' + result.error);
                }
            } catch (error) {
                document.getElementById('fileEditorContent').value = 'Network error: ' + error.message;
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function saveFileContent() {
            const content = document.getElementById('fileEditorContent').value;
            
            try {
                const response = await fetch('/api.php?action=save_file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id: siteId,
                        path: currentEditFilePath,
                        content: content
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'File saved successfully');
                    bootstrap.Modal.getInstance(document.getElementById('fileEditorModal')).hide();
                    loadFiles(currentPath);
                } else {
                    showAlert('danger', 'Error saving file: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function deleteFile(path) {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=delete_file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: siteId, path: path})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'File deleted successfully');
                    loadFiles(currentPath);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        async function showUploadModal() {
            const input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.onchange = async (e) => {
                for (let file of e.target.files) {
                    const formData = new FormData();
                    formData.append('file', file);
                    formData.append('id', siteId);
                    formData.append('path', currentPath);
                    
                    try {
                        const response = await fetch('/api.php?action=upload_file', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            showAlert('success', `Uploaded: ${file.name}`);
                            loadFiles(currentPath);
                        } else {
                            showAlert('danger', 'Error: ' + result.error);
                        }
                    } catch (error) {
                        showAlert('danger', 'Error: ' + error.message);
                    }
                }
            };
            input.click();
        }
        
        async function showNewFileModal() {
            const filename = prompt('Enter file name (e.g., index.php):');
            if (!filename) return;
            const content = prompt('Enter file content (optional):') || '';
            
            try {
                const response = await fetch('/api.php?action=create_file', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: siteId, path: currentPath, filename: filename, content: content})
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('success', 'File created successfully');
                    loadFiles(currentPath);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Error: ' + error.message);
            }
        }
        
        async function showNewFolderModal() {
            const foldername = prompt('Enter folder name:');
            if (!foldername) return;
            
            try {
                const response = await fetch('/api.php?action=create_folder', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: siteId, path: currentPath, foldername: foldername})
                });
                const result = await response.json();
                if (result.success) {
                    showAlert('success', 'Folder created successfully');
                    loadFiles(currentPath);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Error: ' + error.message);
            }
        }
        
        // Load files when Files section is opened
        document.querySelector('[data-section="files"]')?.addEventListener('click', function() {
            console.log('Files section clicked, loading files...');
            loadFiles('/var/www/html');
        });
        
        // Environment Variables Functions
        let envVars = [];
        
        async function loadEnvVars() {
            try {
                const response = await fetch(`/api.php?action=get_env_vars&id=${siteId}`);
                const result = await response.json();
                
                if (result.success) {
                    envVars = result.env_vars;
                    console.log('Loaded environment variables:', envVars);
                    console.log('Total variables loaded:', envVars.length);
                    displayEnvVars();
                } else {
                    showAlert('danger', 'Error loading environment variables: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        function displayEnvVars() {
            const container = document.getElementById('envVarsList');
            
            if (envVars.length === 0) {
                container.innerHTML = '<p class="text-muted">No environment variables defined.</p>';
                return;
            }
            
            container.innerHTML = envVars.map((env, index) => {
                // Escape HTML entities
                const escapedKey = (env.key || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                const escapedValue = (env.value || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                
                return `
                    <div class="row mb-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" class="form-control" value="${escapedKey}" 
                                   onchange="updateEnvVar(${index}, 'key', this.value)" 
                                   placeholder="VARIABLE_NAME">
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="password" class="form-control" id="envValue${index}" value="${escapedValue}" 
                                       onchange="updateEnvVar(${index}, 'value', this.value)" 
                                       placeholder="value">
                                <button class="btn btn-outline-secondary" type="button" onclick="toggleEnvVisibility(${index})">
                                    <i class="bi bi-eye" id="envEye${index}"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-outline-danger" onclick="removeEnvVar(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function updateEnvVar(index, field, value) {
            envVars[index][field] = value;
        }
        
        function toggleEnvVisibility(index) {
            const input = document.getElementById(`envValue${index}`);
            const icon = document.getElementById(`envEye${index}`);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        function removeEnvVar(index) {
            if (confirm('Remove this environment variable?')) {
                envVars.splice(index, 1);
                displayEnvVars();
            }
        }
        
        function showAddEnvVarModal() {
            const key = prompt('Enter variable name (e.g., MY_VARIABLE):');
            if (!key) return;
            
            const value = prompt('Enter variable value:');
            if (value === null) return;
            
            envVars.push({key: key.toUpperCase(), value: value});
            displayEnvVars();
        }
        
        async function saveEnvVars() {
            if (!confirm('This will save changes and restart the container. Continue?')) {
                return;
            }
            
            const inputs = document.querySelectorAll('.env-var-key');
            const envVars = {};
            
            inputs.forEach(input => {
                const key = input.value.trim();
                const value = input.nextElementSibling.value; // Get the value input
                
                if (key) {
                    envVars[key] = value;
                }
            });
            
            try {
                const response = await fetch('/api.php?action=save_env_vars', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: siteId,
                        env_vars: envVars
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Raw response:', text);
                    throw new Error('Server returned invalid JSON. The process may have timed out but might still be running in background.');
                }
                
                if (result.success) {
                    showAlert('success', 'Environment variables saved and container restarted!');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('danger', 'Failed to save environment variables: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                showAlert('danger', 'Error: ' + error.message);
            }
        }
        
        async function rebuildContainer() {
            if (!confirm('Are you sure you want to REBUILD the container? This will stop the site, delete the container, and build a new one from scratch using the latest configuration/image. This may take a few minutes.')) {
                return;
            }
            
            if (!confirm('Double Check: Have you backed up any important data that is NOT in the persistent volumes?')) {
                return;
            }
            
            showAlert('info', 'Rebuilding container... This may take several minutes. Please do not close this page.');
            
            try {
                const response = await fetch('/api.php?action=rebuild_container', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: siteId })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    console.error('Raw response:', text);
                    throw new Error('Server returned invalid JSON. The process may have timed out but might still be running in background.');
                }
                
                if (result.success) {
                    showAlert('success', 'Container rebuilt successfully! Reloading...');
                    setTimeout(() => location.reload(), 3000);
                } else {
                    showAlert('danger', 'Failed to rebuild container: ' + (result.error || 'Unknown error') + (result.output ? '\nOutput: ' + result.output : ''));
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        // Load env vars when Settings section is opened
        document.querySelector('[data-section="settings"]').addEventListener('click', function() {
            loadEnvVars();
        });
        
        // Custom Database Settings
        async function saveCustomDatabaseSettings() {
            if (!confirm('Save database settings and restart container? This will cause brief downtime.')) {
                return;
            }
            
            const formData = new FormData(document.getElementById('customDbForm'));
            const data = {
                id: siteId,
                db_host: formData.get('db_host'),
                db_port: formData.get('db_port'),
                db_name: formData.get('db_name'),
                db_user: formData.get('db_user'),
                db_password: formData.get('db_password')
            };
            
            try {
                const response = await fetch('/api.php?action=update_custom_database', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Database settings saved and container restarted!');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        // Toggle external network access for MariaDB
        async function toggleExternalAccess(enable) {
            const action = enable ? 'enable' : 'disable';
            const message = enable 
                ? 'Enable external network access? This will expose your database to the internet.' 
                : 'Disable external network access? The database will only be accessible from the Docker network.';
            
            if (!confirm(message)) {
                return;
            }
            
            let port = null;
            if (enable) {
                port = document.getElementById('externalPort').value;
                if (!port || port < 1024 || port > 65535) {
                    showAlert('danger', 'Please enter a valid port number (1024-65535)');
                    return;
                }
            }
            
            showAlert('info', `${enable ? 'Enabling' : 'Disabling'} external access...`);
            
            try {
                const response = await fetch('/api.php?action=toggle_mariadb_external_access', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id: siteId,
                        enable: enable,
                        port: port
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', `External access ${enable ? 'enabled' : 'disabled'} successfully! Reloading...`);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert('danger', 'Error: ' + result.error);
                }
            } catch (error) {
                showAlert('danger', 'Network error: ' + error.message);
            }
        }
        
        // SSL Configuration Helper Functions
        function toggleSSLOptions() {
            const sslEnabled = document.getElementById('sslEnabled').checked;
            const sslConfigOptions = document.getElementById('sslConfigOptions');
            if (sslConfigOptions) {
                sslConfigOptions.style.display = sslEnabled ? 'block' : 'none';
            }
        }
        
        function toggleSSLChallengeFields(challenge) {
            const dnsProviderConfig = document.getElementById('dnsProviderConfig');
            if (dnsProviderConfig) {
                dnsProviderConfig.style.display = challenge === 'dns' ? 'block' : 'none';
            }
        }
        
        function showDNSProviderFields(provider) {
            // Hide all DNS provider fields
            document.querySelectorAll('.dns-provider-fields').forEach(el => {
                el.style.display = 'none';
            });
            
            // Show selected provider's fields
            if (provider) {
                const fieldId = provider + 'Fields';
                const field = document.getElementById(fieldId);
                if (field) {
                    field.style.display = 'block';
                }
            }
        }
        
        // Cloudflare auth toggle removed - now only uses API Token
    </script>
</body>
</html>

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

// Auto-send telemetry ping if due (non-blocking)
autoSendTelemetryPing();

// Check if setup wizard should be shown (for admins on fresh install)
$setupCompleted = getSetting($db, 'setup_completed', '0');
$skipSetup = isset($_GET['skip_setup']) && $_GET['skip_setup'] === '1';

// If user skipped setup, mark it as completed
if ($skipSetup && isAdmin()) {
    setSetting($db, 'setup_completed', '1');
    $setupCompleted = '1'; // Update local variable
}

// Redirect to setup wizard if not completed (check for '0', null, or empty)
if (($setupCompleted === '0' || $setupCompleted === null || $setupCompleted === '') && isAdmin() && !$skipSetup) {
    header('Location: /setup-wizard.php');
    exit;
}

// Get sites based on user role and permissions
if (isAdmin()) {
    $sites = getAllSites($db);
} else {
    $sites = getUserSites($_SESSION['user_id']);
}

$customWildcardDomain = getSetting($db, 'custom_wildcard_domain', '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Dashboard - WharfTales</title>
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

    <?php if (empty($sites)): ?>

    <?php endif; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-grid me-2"></i>Your Applications</h2>
                    <?php if (canCreateSites($_SESSION['user_id'])): ?>
                        <button class="btn btn-primary" onclick="showCreateModal()">
                            <i class="bi bi-plus me-2"></i>New App
                        </button>
                    <?php endif; ?>
                </div>

                <div class="row" id="apps">
                    <?php if (empty($sites)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-cloud text-muted" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">No applications yet</h3>
                            <?php if (canCreateSites($_SESSION['user_id'])): ?>
                                <p class="">Deploy your first application to get started</p>
                                <button class="btn btn-primary btn-lg" onclick="showCreateModal()">
                                    <i class="bi bi-plus-circle me-2"></i>Deploy Your First App
                                </button>
                            <?php else: ?>
                                <p class="text-muted">You don't have access to any applications yet.<br>Contact an administrator to grant you access.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sites as $site):
                            $containerStatus = getDockerContainerStatus($site['container_name']);
                            $sslConfigured = checkContainerSSLLabels($site['container_name']);
                            $certIssued = isset($site['ssl_cert_issued']) && $site['ssl_cert_issued'] == 1;

                            ?>
                            <div class="col-md-4 mb-4" data-site-id="<?= $site['id'] ?>">
                                <div class="card app-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title" onclick="window.location.href='/edit/<?= $site['id'] ?>/'"
                                                style="cursor: pointer;" title="Settings & Management">
                                                <?= htmlspecialchars($site['name']) ?>
                                            </h5>
                                            <span
                                                class="badge <?= $containerStatus == 'running' ? 'bg-success' : 'bg-warning' ?> status-badge">
                                                <i class="bi bi-circle-fill me-1"></i><?= ucfirst($containerStatus) ?>
                                            </span>
                                        </div>
                                        <p class="card-text text-muted card-apptype">
                                            <i
                                                class="bi bi-<?= getAppIcon($site['type']) ?> me-2"></i><?= ucfirst($site['type']) ?>
                                            <?php
                                            // Show database badge for any site with a database
                                            $dbType = $site['db_type'] ?? 'none';
                                            $showDbBadge = false;
                                            $dbLabel = '';

                                            if ($site['type'] === 'wordpress' && $dbType === 'dedicated') {
                                                $showDbBadge = true;
                                                $dbLabel = 'Dedicated DB';
                                            } elseif ($site['type'] === 'php' && in_array($dbType, ['mysql', 'postgresql'])) {
                                                $showDbBadge = true;
                                                $dbLabel = strtoupper($dbType) . ' DB';
                                            } elseif ($site['type'] === 'laravel' && in_array($dbType, ['mysql', 'postgresql'])) {
                                                $showDbBadge = true;
                                                $dbLabel = strtoupper($dbType) . ' DB';
                                            }
                                            ?>
                                            <?php if ($showDbBadge): ?>
                                                <span class="badge bg-info ms-2"
                                                    title="Database: <?= htmlspecialchars($dbLabel) ?>">
                                                    <i class="bi bi-database"></i> <?= htmlspecialchars($dbLabel) ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                        <div class="mb-3">
                                            <small class="text-muted">Domain:</small><br>
                                            <a href="<?= ($site['ssl'] ? 'https://' : 'http://') . $site['domain'] ?>"
                                                target="_blank" class="text-decoration-none" title="Open site in new tab">
                                                <?= $site['domain'] ?>
                                                <?php if ($sslConfigured): ?>
                                                    <i class="bi bi-shield-check text-success ms-1" title="Certificate issued"></i>

                                                <?php else: ?>

                                                <?php endif; ?>

                                            </a>
                                        </div>

                                        <div class="btn-group w-100" role="group">
                                            <button class="btn btn-outline-primary btn-sm"
                                                onclick="viewSite('<?= $site['domain'] ?>', <?= $site['ssl'] ? 'true' : 'false' ?>)"
                                                title="View Site">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-info btn-sm"
                                                onclick="window.location.href='/edit/<?= $site['id'] ?>/'"
                                                title="Settings & Management">
                                                <i class="bi bi-gear"></i>
                                            </button>
                                            <?php if (canAccessSite($_SESSION['user_id'], $site['id'], 'manage')): ?>
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteSite(<?= $site['id'] ?>)" title="Delete Site">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary btn-sm" disabled
                                                    title="Requires 'Manage' permission">
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create App Modal -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Deploy New Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createForm" onsubmit="createSite(event)">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Application Name</label>
                                <input type="text" class="form-control" name="name" required
                                    placeholder="My Awesome Site">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Application Type</label>
                                <select class="form-select" name="type" required
                                    onchange="toggleTypeOptions(this.value)">
                                    <option value="">Choose type...</option>
                                    <option value="wordpress">WordPress (Optimized)</option>
                                    <option value="php">PHP Application</option>
                                    <option value="laravel">Laravel</option>
                                    <option value="mariadb">MariaDB Database</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3" id="phpVersionRow" style="display:none;">
                            <div class="col-md-6">
                                <label class="form-label">PHP Version</label>
                                <select class="form-select" name="php_version">
                                    <option value="8.4" selected>PHP 8.4 (Latest)</option>
                                    <option value="8.3">PHP 8.3 (Recommended)</option>
                                    <option value="8.2">PHP 8.2</option>
                                    <option value="8.1">PHP 8.1</option>
                                    <option value="8.0">PHP 8.0</option>
                                    <option value="7.4">PHP 7.4 (Legacy)</option>
                                </select>
                                <div class="form-text">Choose PHP version for your application. Note: WordPress requires
                                    8.3 or lower</div>
                            </div>
                        </div>

                        <div class="row mb-3" id="domainSslRow">
                            <div class="col-md-6">
                                <label class="form-label">Domain</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="domain" id="domainInput"
                                        placeholder="mysite">
                                    <select class="form-select" name="domain_suffix" id="domainSuffix"
                                        style="max-width: 200px;" onchange="toggleSSLOptions(this.value)">
                                        <option value=".localhost">.localhost (Local)</option>
                                        <?php if (!empty($customWildcardDomain)): ?>
                                            <option value="<?= htmlspecialchars($customWildcardDomain) ?>">
                                                <?= htmlspecialchars($customWildcardDomain) ?> (Custom)
                                            </option>
                                        <?php endif; ?>
                                    <option value="custom">Custom Domain</option>
                                    </select>
                                </div>
                                <div class="form-text">For virtual servers, use port-based or IP access</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SSL Certificate</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="ssl" id="sslCheck"
                                        onchange="toggleSSLChallengeOptions()">
                                    <label class="form-check-label" for="sslCheck">
                                        Enable SSL (Let's Encrypt)
                                    </label>
                                </div>
                                <div class="form-text">Only available for custom domains</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_www"
                                        id="includeWwwCheck">
                                    <label class="form-check-label" for="includeWwwCheck">
                                        Also include www subdomain
                                    </label>
                                </div>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    When checked, both <strong>domain.com</strong> and <strong>www.domain.com</strong>
                                    will be configured.
                                    If SSL is enabled, the certificate will cover both domains.
                                </div>
                            </div>
                        </div>

                        <div id="sslChallengeOptions" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">SSL Challenge Method</label>
                                <select class="form-select" name="ssl_challenge" id="sslChallengeMethod"
                                    onchange="toggleDNSProviderOptions(this.value)">
                                    <option value="http">HTTP Challenge (Port 80 must be accessible)</option>
                                    <option value="dns">DNS Challenge (Works behind firewall, supports wildcards)
                                    </option>
                                </select>
                                <div class="form-text">
                                    <strong>HTTP:</strong> Simple, requires port 80 open to internet<br>
                                    <strong>DNS:</strong> Works anywhere, requires DNS provider API access
                                </div>
                            </div>

                            <div id="dnsProviderOptions" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>DNS Challenge Setup:</strong> You'll need API credentials from your DNS
                                    provider.
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">DNS Provider</label>
                                        <select class="form-select" name="dns_provider" id="dnsProvider"
                                            onchange="showDNSProviderFields(this.value)">
                                            <option value="">Choose provider...</option>
                                            <option value="cloudflare">Cloudflare</option>
                                            <option value="route53">AWS Route53</option>
                                            <option value="digitalocean">DigitalOcean</option>
                                            <option value="gcp">Google Cloud DNS</option>
                                            <option value="azure">Azure DNS</option>
                                            <option value="namecheap">Namecheap</option>
                                            <option value="godaddy">GoDaddy</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Cloudflare Fields -->
                                <div id="cloudflareFields" class="dns-provider-fields" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Cloudflare API Token</label>
                                        <input type="password" class="form-control" name="cf_api_token"
                                            placeholder="API Token with Zone:DNS:Edit permissions">
                                        <div class="form-text">
                                            <strong>Create token at:</strong> <a
                                                href="https://dash.cloudflare.com/profile/api-tokens"
                                                target="_blank">Cloudflare Dashboard</a><br>
                                            <strong>Required permissions:</strong> Zone → DNS → Edit, Zone → Zone →
                                            Read<br>
                                            <strong>Zone Resources:</strong> Include → Specific zone → your domain
                                        </div>
                                    </div>
                                </div>

                                <!-- Route53 Fields -->
                                <div id="route53Fields" class="dns-provider-fields" style="display: none;">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">AWS Access Key ID</label>
                                            <input type="text" class="form-control" name="aws_access_key"
                                                placeholder="AKIAIOSFODNN7EXAMPLE">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">AWS Secret Access Key</label>
                                            <input type="password" class="form-control" name="aws_secret_key"
                                                placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">AWS Region</label>
                                        <input type="text" class="form-control" name="aws_region"
                                            placeholder="us-east-1" value="us-east-1">
                                    </div>
                                </div>

                                <!-- DigitalOcean Fields -->
                                <div id="digitaloceanFields" class="dns-provider-fields" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">DigitalOcean API Token</label>
                                        <input type="password" class="form-control" name="do_auth_token"
                                            placeholder="dop_v1_...">
                                    </div>
                                    <div class="form-text mb-3">
                                        Generate token at: <a href="https://cloud.digitalocean.com/account/api/tokens"
                                            target="_blank">DigitalOcean API</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="customDomainField" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Custom Domain</label>
                                <input type="text" class="form-control" name="custom_domain" placeholder="example.com">
                                <div class="form-text">Make sure this domain points to your server's IP address</div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Testing on Virtual Server:</strong> Use port-based domains (e.g., :8080) or access
                            via IP address.
                            You can also add entries to your local hosts file for .test.local domains.
                        </div>

                        <div id="wordpressOptions" style="display: none;">
                            <hr>
                            <h6><i class="bi bi-wordpress text-primary me-2"></i>WordPress Configuration</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Admin Username</label>
                                    <input type="text" class="form-control" name="wp_admin" placeholder="admin">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Admin Password</label>
                                    <input type="password" class="form-control" name="wp_password"
                                        placeholder="Generate strong password">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin Email</label>
                                <input type="email" class="form-control" name="wp_email"
                                    placeholder="admin@example.com">
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="wp_optimize" id="wpOptimize"
                                    checked>
                                <label class="form-check-label" for="wpOptimize">
                                    Enable performance optimizations (Redis, OpCache, CDN-ready)
                                </label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Database Configuration</label>
                                <select class="form-select" name="wp_db_type" id="wpDbType"
                                    onchange="toggleCustomDbFields()">
                                    <option value="dedicated" selected>Dedicated Database (Separate MariaDB container
                                        per site)</option>
                                    <option value="custom">Custom Database (Connect to external database)</option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Dedicated:</strong> Complete isolation, automatic setup, recommended for
                                    most sites<br>
                                    <strong>Custom:</strong> Connect to an existing external database server
                                </div>
                            </div>

                            <!-- Custom Database Fields -->
                            <div id="customDbFields" style="display: none;">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Custom Database:</strong> Enter your external database connection details
                                    below.
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Database Host</label>
                                        <input type="text" class="form-control" name="wp_db_host"
                                            placeholder="db.example.com or IP address">
                                        <div class="form-text">Database server hostname or IP</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Database Port</label>
                                        <input type="number" class="form-control" name="wp_db_port" value="3306"
                                            placeholder="3306">
                                        <div class="form-text">Default MySQL/MariaDB port is 3306</div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Database Name</label>
                                        <input type="text" class="form-control" name="wp_db_name"
                                            placeholder="wordpress">
                                        <div class="form-text">Name of the database to use</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Database User</label>
                                        <input type="text" class="form-control" name="wp_db_user" placeholder="wp_user">
                                        <div class="form-text">Database username</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Database Password</label>
                                    <input type="password" class="form-control" name="wp_db_password"
                                        placeholder="Enter database password">
                                    <div class="form-text">Password for the database user</div>
                                </div>
                            </div>
                        </div>

                        <div id="phpOptions" style="display: none;">
                            <hr>
                            <h6><i class="bi bi-code-slash text-primary me-2"></i>PHP Configuration</h6>
                            <div class="mb-3">
                                <label class="form-label">Database</label>
                                <select class="form-select" name="php_db_type" id="phpDbType">
                                    <option value="none" selected>No Database</option>
                                    <option value="mysql">MySQL/MariaDB</option>
                                    <option value="postgres">PostgreSQL</option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Select a database if your PHP app needs one
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="php_redis" id="phpRedis">
                                <label class="form-check-label" for="phpRedis">
                                    <i class="bi bi-lightning-charge me-1"></i>Enable Redis Cache
                                </label>
                                <div class="form-text">Redis for session storage and data caching</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-github me-1"></i>GitHub Deployment
                                    (Optional)</label>
                                <input type="text" class="form-control" name="php_github_repo"
                                    placeholder="https://github.com/username/repo or username/repo">
                                <div class="form-text">Deploy from GitHub repository. Leave empty to upload files
                                    manually via SFTP.</div>
                            </div>
                            <div class="row mb-3" id="phpGithubOptions" style="display: none;">
                                <div class="col-md-6">
                                    <label class="form-label">Branch</label>
                                    <input type="text" class="form-control" name="php_github_branch" value="main"
                                        placeholder="main">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Personal Access Token (for private repos)</label>
                                    <input type="password" class="form-control" name="php_github_token"
                                        placeholder="ghp_...">
                                    <div class="form-text"><a href="https://github.com/settings/tokens"
                                            target="_blank">Generate token</a></div>
                                </div>
                            </div>
                        </div>

                        <div id="laravelOptions" style="display: none;">
                            <hr>
                            <h6><i class="bi bi-lightning text-primary me-2"></i>Laravel Configuration</h6>
                            <div class="mb-3">
                                <label class="form-label">Database</label>
                                <select class="form-select" name="laravel_db_type" id="laravelDbType">
                                    <option value="mysql" selected>MySQL/MariaDB (Recommended)</option>
                                    <option value="postgres">PostgreSQL</option>
                                    <option value="none">No Database</option>
                                </select>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Laravel works best with MySQL or PostgreSQL
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="laravel_redis" id="laravelRedis"
                                    checked>
                                <label class="form-check-label" for="laravelRedis">
                                    <i class="bi bi-lightning-charge me-1"></i>Enable Redis Cache
                                </label>
                                <div class="form-text">Redis for cache, sessions, and queues (Recommended)</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-github me-1"></i>GitHub Deployment
                                    (Optional)</label>
                                <input type="text" class="form-control" name="laravel_github_repo"
                                    placeholder="https://github.com/username/repo or username/repo">
                                <div class="form-text">Deploy from GitHub repository. Leave empty to upload files
                                    manually via SFTP.</div>
                            </div>
                            <div class="row mb-3" id="laravelGithubOptions" style="display: none;">
                                <div class="col-md-6">
                                    <label class="form-label">Branch</label>
                                    <input type="text" class="form-control" name="laravel_github_branch" value="main"
                                        placeholder="main">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Personal Access Token (for private repos)</label>
                                    <input type="password" class="form-control" name="laravel_github_token"
                                        placeholder="ghp_...">
                                    <div class="form-text"><a href="https://github.com/settings/tokens"
                                            target="_blank">Generate token</a></div>
                                </div>
                            </div>
                        </div>

                        <div id="mariadbOptions" style="display: none;">
                            <hr>
                            <h6><i class="bi bi-database text-primary me-2"></i>MariaDB Configuration</h6>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Standalone Database:</strong> Create a dedicated MariaDB instance that other
                                applications can connect to.
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Root Password</label>
                                <input type="password" class="form-control" name="mariadb_root_password"
                                    id="mariadbRootPassword" placeholder="Auto-generated secure password">
                                <div class="form-text">Leave empty to auto-generate a secure password</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Default Database Name</label>
                                <input type="text" class="form-control" name="mariadb_database" placeholder="defaultdb"
                                    value="defaultdb">
                                <div class="form-text">Initial database to create</div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Database User</label>
                                    <input type="text" class="form-control" name="mariadb_user" placeholder="dbuser"
                                        value="dbuser">
                                    <div class="form-text">Non-root database user</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">User Password</label>
                                    <input type="password" class="form-control" name="mariadb_password"
                                        id="mariadbPassword" placeholder="Auto-generated">
                                    <div class="form-text">Leave empty to auto-generate</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Port</label>
                                <input type="number" class="form-control" name="mariadb_port" value="3306"
                                    placeholder="3306">
                                <div class="form-text">
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                    External port for database access. Use different ports for multiple instances.
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="mariadb_expose"
                                    id="mariadbExpose">
                                <label class="form-check-label" for="mariadbExpose">
                                    <i class="bi bi-globe me-1"></i>Expose to external network
                                </label>
                                <div class="form-text">Allow connections from outside Docker network (less secure)</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-rocket me-2"></i>Deploy Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit App Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Application</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm" onsubmit="updateSite(event)">
                    <input type="hidden" name="site_id" id="editSiteId">
                    <input type="hidden" name="type" id="editType">
                    <input type="hidden" name="container_name" id="editContainerName">

                    <div class="modal-body">
                        <!-- Site Information -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-info-circle me-2"></i>Site Information</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Application Name</label>
                                    <input type="text" class="form-control" name="name" id="editName" required>
                                    <div class="form-text">Display name for your application</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Application Type</label>
                                    <input type="text" class="form-control" id="editTypeDisplay" disabled>
                                    <div class="form-text">Type cannot be changed after creation</div>
                                </div>
                            </div>
                        </div>

                        <!-- Domain Configuration -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-globe me-2"></i>Domain Configuration</h6>
                            <div class="mb-3">
                                <label class="form-label">Domain</label>
                                <input type="text" class="form-control" name="domain" id="editDomain" required>
                                <div class="form-text">
                                    <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                                    <strong>Warning:</strong> Changing the domain will update Traefik routing. Make sure
                                    to update your DNS/hosts file.
                                </div>
                            </div>
                        </div>

                        <!-- SSL & Status -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3"><i class="bi bi-shield-check me-2"></i>Security & Status</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">SSL Certificate</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="ssl" id="editSsl"
                                            role="switch">
                                        <label class="form-check-label" for="editSsl">
                                            Enable HTTPS (Let's Encrypt)
                                        </label>
                                    </div>
                                    <div class="form-text">Requires custom domain with valid DNS</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Container Status</label>
                                    <select class="form-select" name="status" id="editStatus" disabled>
                                        <option value="running">Running</option>
                                        <option value="stopped">Stopped</option>
                                    </select>
                                    <div class="form-text">Status is managed automatically</div>
                                </div>
                            </div>
                        </div>

                        <!-- GitHub Deployment -->
                        <div class="mb-4" id="editGithubSection" style="display:none;">
                            <h6 class="text-muted mb-3"><i class="bi bi-github me-2"></i>GitHub Deployment</h6>

                            <div class="mb-3">
                                <label class="form-label">Repository</label>
                                <input type="text" class="form-control" name="github_repo" id="editGithubRepo"
                                    placeholder="username/repo or https://github.com/username/repo">
                                <div class="form-text">Leave empty to disable GitHub deployment and use SFTP instead
                                </div>
                            </div>

                            <div class="row mb-3" id="editGithubOptions">
                                <div class="col-md-6">
                                    <label class="form-label">Branch</label>
                                    <input type="text" class="form-control" name="github_branch" id="editGithubBranch"
                                        value="main" placeholder="main">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Personal Access Token</label>
                                    <input type="password" class="form-control" name="github_token" id="editGithubToken"
                                        placeholder="Leave empty to keep existing">
                                    <div class="form-text">Only needed for private repos. <a
                                            href="https://github.com/settings/tokens" target="_blank">Generate token</a>
                                    </div>
                                </div>
                            </div>

                            <div id="editGithubInfo" class="alert alert-info" style="display:none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">Last Commit:</small><br>
                                        <code id="editGithubCommit">-</code>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">Last Pull:</small><br>
                                        <span id="editGithubLastPull">-</span>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        onclick="checkGithubUpdates()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Check for Updates
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="pullFromGithub()">
                                        <i class="bi bi-download me-1"></i>Pull Latest Changes
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Container Info -->
                        <div class="alert alert-secondary">
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">Container Name:</small><br>
                                    <code id="editContainerNameDisplay" class="text-dark"></code>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Created:</small><br>
                                    <span id="editCreatedAt"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/modals.php'; ?>
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js?v=3.0.<?= time() ?>"></script>
</body>

</html>
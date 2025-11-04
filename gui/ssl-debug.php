<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication
requireAuth();

$db = initDatabase();
$currentUser = getCurrentUser();

// Handle certificate status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['site_id'])) {
    $siteId = intval($_POST['site_id']);
    
    if ($_POST['action'] === 'mark_issued') {
        if (markCertificateIssued($db, $siteId)) {
            $successMessage = 'Certificate marked as issued successfully!';
        } else {
            $errorMessage = 'Failed to mark certificate as issued.';
        }
    } elseif ($_POST['action'] === 'mark_removed') {
        if (markCertificateRemoved($db, $siteId)) {
            $successMessage = 'Certificate marked as removed successfully!';
        } else {
            $errorMessage = 'Failed to mark certificate as removed.';
        }
    }
}

// Get all sites with SSL enabled
$sites = getAllSites($db);
$sslSites = array_filter($sites, function($site) {
    return $site['ssl'] == 1;
});

// Get Let's Encrypt email from docker-compose.yml or Traefik container
$dockerComposePath = '/opt/wharftales/docker-compose.yml';
clearstatcache(true, $dockerComposePath);
$dockerComposeContent = @file_get_contents($dockerComposePath);
if ($dockerComposeContent !== false) {
    preg_match('/acme\.email=([^\s"]+)/', $dockerComposeContent, $matches);
    $letsEncryptEmail = $matches[1] ?? 'Not configured';
} else {
    // Try to get from running container
    exec("docker inspect wharftales_traefik --format '{{range .Config.Cmd}}{{println .}}{{end}}' 2>&1 | grep 'acme.email' | cut -d'=' -f2", $emailOutput);
    $letsEncryptEmail = !empty($emailOutput[0]) ? trim($emailOutput[0]) : 'Cannot read configuration';
}

// Check if email is invalid
$emailIsInvalid = preg_match('/@(example\.(com|net|org)|test\.)/', $letsEncryptEmail);

// Check if ports are open
$port80Open = @fsockopen('127.0.0.1', 80, $errno, $errstr, 1);
$port443Open = @fsockopen('127.0.0.1', 443, $errno, $errstr, 1);

// Get Traefik logs
exec('docker logs wharftales_traefik --tail 50 2>&1', $traefikLogs);
$sslErrors = array_filter($traefikLogs, function($line) {
    return stripos($line, 'acme') !== false || stripos($line, 'certificate') !== false || stripos($line, 'error') !== false;
});

// Check acme.json
$acmeJsonPath = '/opt/wharftales/ssl/acme.json';
$acmeJsonExists = file_exists($acmeJsonPath);
$acmeJsonSize = $acmeJsonExists ? filesize($acmeJsonPath) : 0;
$acmeJsonEmpty = false;

// Check if acme.json is empty (only has template structure)
if ($acmeJsonExists && $acmeJsonSize > 0) {
    $acmeContent = @file_get_contents($acmeJsonPath);
    if ($acmeContent !== false) {
        $acmeJsonEmpty = (strpos($acmeContent, '"Certificates": null') !== false);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL Debug - WharfTales</title>
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
            <div class="col-md-12">
                <?php if ($emailIsInvalid): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>CRITICAL: Invalid Let's Encrypt Email!</h5>
                        <p class="mb-2">Your Let's Encrypt email is set to <strong><?= htmlspecialchars($letsEncryptEmail) ?></strong></p>
                        <p class="mb-2">Let's Encrypt will <strong>reject all certificate requests</strong> with example.com or test domains.</p>
                        <hr>
                        <p class="mb-0">
                            <strong>To fix:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Go to <a href="/settings.php" class="alert-link">Settings → SSL Configuration</a></li>
                                <li>Update the email to a real address (e.g., admin@yourdomain.com)</li>
                                <li>Restart Traefik: <code>cd /opt/wharftales && docker-compose restart traefik</code></li>
                            </ol>
                        </p>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= $successMessage ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= $errorMessage ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-shield-lock me-2"></i>SSL Debug Information</h2>
                    <a href="/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <!-- Configuration Check -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-gear me-2"></i>SSL Configuration
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4"><strong>Let's Encrypt Email:</strong></div>
                            <div class="col-md-8">
                                <code><?= htmlspecialchars($letsEncryptEmail) ?></code>
                                <?php if ($emailIsInvalid): ?>
                                    <span class="badge bg-danger ms-2">INVALID - Certificates will FAIL!</span>
                                    <br><small class="text-danger">Let's Encrypt rejects example.com and test domains</small>
                                <?php elseif ($letsEncryptEmail === 'Not configured'): ?>
                                    <span class="badge bg-warning ms-2">Not configured</span>
                                <?php else: ?>
                                    <span class="badge bg-success ms-2">Valid</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4"><strong>Port 80 (HTTP):</strong></div>
                            <div class="col-md-8">
                                <?php if ($port80Open): ?>
                                    <span class="badge bg-success">Open</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Closed or Blocked</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4"><strong>Port 443 (HTTPS):</strong></div>
                            <div class="col-md-8">
                                <?php if ($port443Open): ?>
                                    <span class="badge bg-success">Open</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Closed or Blocked</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4"><strong>ACME Storage:</strong></div>
                            <div class="col-md-8">
                                <?php if ($acmeJsonExists): ?>
                                    <?php if ($acmeJsonEmpty): ?>
                                        <span class="badge bg-warning">Empty (No certificates yet)</span>
                                        <small class="text-muted">(<?= number_format($acmeJsonSize) ?> bytes)</small>
                                        <br><small class="text-muted">Certificates will be requested automatically when sites are deployed</small>
                                    <?php else: ?>
                                        <span class="badge bg-success">Contains certificates</span>
                                        <small class="text-muted">(<?= number_format($acmeJsonSize) ?> bytes)</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning">Not created yet</span>
                                    <br><small class="text-muted">Will be created automatically by Traefik</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sites with SSL -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-list me-2"></i>Sites with SSL Enabled (<?= count($sslSites) ?>)
                    </div>
                    <div class="card-body">
                        <?php if (empty($sslSites)): ?>
                            <p class="text-muted">No sites with SSL enabled</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Site Name</th>
                                            <th>Domain</th>
                                            <th>Container</th>
                                            <th>SSL Status</th>
                                            <th>Certificate</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sslSites as $site): 
                                            $containerStatus = getDockerContainerStatus($site['container_name']);
                                            $sslConfigured = checkContainerSSLLabels($site['container_name']);
                                            $certIssued = isset($site['ssl_cert_issued']) && $site['ssl_cert_issued'] == 1;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($site['name']) ?></td>
                                            <td>
                                                <a href="https://<?= htmlspecialchars($site['domain']) ?>" target="_blank">
                                                    <?= htmlspecialchars($site['domain']) ?>
                                                </a>
                                            </td>
                                            <td><code><?= htmlspecialchars($site['container_name']) ?></code></td>
                                            <td>
                                                <?php if ($sslConfigured): ?>
                                                    <span class="badge bg-success">SSL Configured</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">SSL Not Configured</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($certIssued): ?>
                                                    <span class="badge bg-success">
                                                        <i class="bi bi-shield-check"></i> Issued
                                                    </span>
                                                    <?php if (isset($site['ssl_cert_issued_at'])): ?>
                                                        <br><small class="text-muted"><?= date('M d, Y', strtotime($site['ssl_cert_issued_at'])) ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="bi bi-hourglass-split"></i> Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="testDomain('<?= htmlspecialchars($site['domain']) ?>')">
                                                    <i class="bi bi-search"></i> Test
                                                </button>
                                                <?php if (!$certIssued): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                                        <input type="hidden" name="action" value="mark_issued">
                                                        <button type="submit" class="btn btn-sm btn-success" title="Mark certificate as issued">
                                                            <i class="bi bi-check-circle"></i> Mark Issued
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="site_id" value="<?= $site['id'] ?>">
                                                        <input type="hidden" name="action" value="mark_removed">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Mark certificate as removed">
                                                            <i class="bi bi-x-circle"></i> Mark Removed
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent SSL Errors -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-exclamation-triangle me-2"></i>Recent SSL/ACME Errors
                    </div>
                    <div class="card-body">
                        <?php if (empty($sslErrors)): ?>
                            <p class="text-success"><i class="bi bi-check-circle me-2"></i>No SSL errors found in recent logs</p>
                        <?php else: ?>
                            <div class="bg-dark text-light p-3" style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 0.85rem;">
                                <?php foreach ($sslErrors as $error): ?>
                                    <?= htmlspecialchars($error) ?><br>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Troubleshooting Guide -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-question-circle me-2"></i>Troubleshooting Guide
                    </div>
                    <div class="card-body">
                        <h6>Common SSL Issues:</h6>
                        <ol>
                            <li>
                                <strong>Email contains "example.com"</strong>
                                <ul>
                                    <li>Go to Settings → SSL Configuration</li>
                                    <li>Change to a real email address</li>
                                    <li>Restart Traefik</li>
                                    <li>Delete <code>/opt/wharftales/ssl/acme.json</code> and restart</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Domain not pointing to server</strong>
                                <ul>
                                    <li>Check DNS: <code>nslookup yourdomain.com</code></li>
                                    <li>Ensure A record points to your server IP</li>
                                    <li>Wait for DNS propagation (up to 48 hours)</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Ports 80/443 blocked</strong>
                                <ul>
                                    <li>Check firewall: <code>sudo ufw status</code></li>
                                    <li>Allow ports: <code>sudo ufw allow 80/tcp && sudo ufw allow 443/tcp</code></li>
                                    <li>Check cloud provider security groups</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Certificate not renewing</strong>
                                <ul>
                                    <li>Delete acme.json: <code>sudo rm /opt/wharftales/ssl/acme.json</code></li>
                                    <li>Restart Traefik: <code>docker-compose restart traefik</code></li>
                                    <li>Check logs: <code>docker logs wharftales_traefik -f</code></li>
                                </ul>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/modals.php'; ?>
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js?v=3.0.<?= time() ?>"></script>
    <script>
        async function testDomain(domain) {
            alert('Testing domain: ' + domain + '\n\nThis will open in a new tab.');
            window.open('https://' + domain, '_blank');
        }
    </script>
</body>
</html>

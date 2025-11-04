<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require authentication
requireAuth();

// Check if debug mode is enabled
$debugEnabled = file_exists('/tmp/wharftales_debug_enabled');

// Handle enable/disable toggle
if (isset($_POST['toggle_debug'])) {
    if ($debugEnabled) {
        unlink('/tmp/wharftales_debug_enabled');
        $debugEnabled = false;
        $message = 'Debug mode disabled';
    } else {
        file_put_contents('/tmp/wharftales_debug_enabled', '1');
        $debugEnabled = true;
        $message = 'Debug mode enabled';
    }
}

$db = initDatabase();
$currentUser = getCurrentUser();
$sites = getAllSites($db);

// Get system info
function getSystemInfo() {
    $info = [];
    
    // Docker version
    exec('docker --version 2>&1', $dockerVersion);
    $info['docker_version'] = $dockerVersion[0] ?? 'N/A';
    
    // Docker compose version
    exec('docker compose version 2>&1', $composeVersion);
    $info['compose_version'] = $composeVersion[0] ?? 'N/A';
    
    // Disk usage
    exec("df -h / | tail -1 | awk '{print $2, $3, $4, $5}'", $diskUsage);
    $diskParts = explode(' ', $diskUsage[0] ?? '');
    $info['disk_total'] = $diskParts[0] ?? 'N/A';
    $info['disk_used'] = $diskParts[1] ?? 'N/A';
    $info['disk_available'] = $diskParts[2] ?? 'N/A';
    $info['disk_percent'] = $diskParts[3] ?? 'N/A';
    
    // Memory usage
    exec("free -h | grep Mem | awk '{print $2, $3, $4}'", $memUsage);
    $memParts = explode(' ', $memUsage[0] ?? '');
    $info['mem_total'] = $memParts[0] ?? 'N/A';
    $info['mem_used'] = $memParts[1] ?? 'N/A';
    $info['mem_available'] = $memParts[2] ?? 'N/A';
    
    // CPU info
    exec("nproc", $cpuCount);
    $info['cpu_cores'] = $cpuCount[0] ?? 'N/A';
    
    // Uptime
    exec("uptime -p", $uptime);
    $info['uptime'] = $uptime[0] ?? 'N/A';
    
    return $info;
}

function getContainerDetails($containerName) {
    $details = [];
    
    // Container stats
    exec("docker stats --no-stream --format '{{.CPUPerc}}|{{.MemUsage}}|{{.NetIO}}|{{.BlockIO}}' $containerName 2>&1", $stats);
    if (!empty($stats[0]) && strpos($stats[0], 'Error') === false) {
        $parts = explode('|', $stats[0]);
        $details['cpu'] = $parts[0] ?? 'N/A';
        $details['memory'] = $parts[1] ?? 'N/A';
        $details['network'] = $parts[2] ?? 'N/A';
        $details['disk_io'] = $parts[3] ?? 'N/A';
    } else {
        $details['cpu'] = 'N/A';
        $details['memory'] = 'N/A';
        $details['network'] = 'N/A';
        $details['disk_io'] = 'N/A';
    }
    
    // Container inspect
    exec("docker inspect $containerName --format '{{.State.Status}}|{{.State.StartedAt}}|{{.RestartCount}}|{{.Config.Image}}' 2>&1", $inspect);
    if (!empty($inspect[0]) && strpos($inspect[0], 'Error') === false) {
        $parts = explode('|', $inspect[0]);
        $details['status'] = $parts[0] ?? 'N/A';
        $details['started_at'] = isset($parts[1]) ? date('Y-m-d H:i:s', strtotime($parts[1])) : 'N/A';
        $details['restart_count'] = $parts[2] ?? '0';
        $details['image'] = $parts[3] ?? 'N/A';
    }
    
    // Get volumes
    exec("docker inspect $containerName --format '{{range .Mounts}}{{.Type}}:{{.Source}}->{{.Destination}}|{{end}}' 2>&1", $volumes);
    $details['volumes'] = [];
    if (!empty($volumes[0]) && strpos($volumes[0], 'Error') === false) {
        $volumeList = explode('|', trim($volumes[0], '|'));
        foreach ($volumeList as $vol) {
            if (!empty($vol)) {
                $details['volumes'][] = $vol;
            }
        }
    }
    
    // Get volume sizes
    $details['volume_sizes'] = [];
    foreach ($details['volumes'] as $vol) {
        $volParts = explode('->', $vol);
        if (count($volParts) == 2) {
            list($type, $source) = explode(':', $volParts[0]);
            if ($type === 'volume') {
                exec("docker system df -v | grep '$source' | awk '{print $3}' 2>&1", $size);
                $details['volume_sizes'][$source] = $size[0] ?? 'N/A';
            } elseif ($type === 'bind' && is_dir($source)) {
                exec("du -sh '$source' 2>&1 | awk '{print $1}'", $size);
                $details['volume_sizes'][$source] = $size[0] ?? 'N/A';
            }
        }
    }
    
    // Get ports
    exec("docker port $containerName 2>&1", $ports);
    $details['ports'] = $ports;
    
    // Get environment variables
    exec("docker inspect $containerName --format '{{range .Config.Env}}{{.}}|{{end}}' 2>&1", $env);
    $details['env'] = [];
    if (!empty($env[0]) && strpos($env[0], 'Error') === false) {
        $envList = explode('|', trim($env[0], '|'));
        foreach ($envList as $e) {
            if (!empty($e)) {
                $details['env'][] = $e;
            }
        }
    }
    
    return $details;
}

$systemInfo = getSystemInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - WharfTales</title>
          <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="WharfTales" />
<link rel="manifest" href="/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <style>
        .debug-card {
            margin-bottom: 1.5rem;
        }
        .debug-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 0.875rem;
        }
        .debug-value {
            color: #1f2937;
            font-family: 'Courier New', monospace;
        }
        .env-var {
            font-size: 0.8rem;
            font-family: 'Courier New', monospace;
            padding: 0.25rem 0.5rem;
            background: #f3f4f6;
            border-radius: 0.25rem;
            margin: 0.25rem 0;
        }
        .volume-item {
            font-size: 0.85rem;
            padding: 0.5rem;
            background: #f9fafb;
            border-left: 3px solid #4b5563;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bug me-2"></i>Debug Information</h2>
            <form method="POST" class="d-inline">
                <button type="submit" name="toggle_debug" class="btn btn-<?= $debugEnabled ? 'danger' : 'success' ?>">
                    <i class="bi bi-<?= $debugEnabled ? 'x-circle' : 'check-circle' ?> me-2"></i>
                    <?= $debugEnabled ? 'Disable' : 'Enable' ?> Debug Mode
                </button>
            </form>
        </div>

        <?php if (isset($message)): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!$debugEnabled): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Debug mode is disabled.</strong> Enable it to view detailed system and container information.
        </div>
        <?php else: ?>

        <!-- System Information -->
        <div class="card debug-card">
            <div class="card-header">
                <i class="bi bi-server me-2"></i>System Information
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="debug-label">Docker Version</div>
                        <div class="debug-value"><?= htmlspecialchars($systemInfo['docker_version']) ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="debug-label">Docker Compose</div>
                        <div class="debug-value"><?= htmlspecialchars($systemInfo['compose_version']) ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="debug-label">CPU Cores</div>
                        <div class="debug-value"><?= htmlspecialchars($systemInfo['cpu_cores']) ?></div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="debug-label">Uptime</div>
                        <div class="debug-value"><?= htmlspecialchars($systemInfo['uptime']) ?></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="debug-label">Disk Usage</div>
                        <div class="debug-value">
                            <?= htmlspecialchars($systemInfo['disk_used']) ?> / <?= htmlspecialchars($systemInfo['disk_total']) ?>
                            (<?= htmlspecialchars($systemInfo['disk_percent']) ?> used, <?= htmlspecialchars($systemInfo['disk_available']) ?> available)
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="debug-label">Memory Usage</div>
                        <div class="debug-value">
                            <?= htmlspecialchars($systemInfo['mem_used']) ?> / <?= htmlspecialchars($systemInfo['mem_total']) ?>
                            (<?= htmlspecialchars($systemInfo['mem_available']) ?> available)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sites and Containers -->
        <?php foreach ($sites as $site): 
            $containerDetails = getContainerDetails($site['container_name']);
        ?>
        <div class="card debug-card">
            <div class="card-header">
                <i class="bi bi-box me-2"></i><?= htmlspecialchars($site['name']) ?>
                <span class="badge bg-<?= $containerDetails['status'] === 'running' ? 'success' : 'secondary' ?> ms-2">
                    <?= htmlspecialchars($containerDetails['status'] ?? 'unknown') ?>
                </span>
            </div>
            <div class="card-body">
                <!-- Basic Info -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="debug-label">Container Name</div>
                        <div class="debug-value"><?= htmlspecialchars($site['container_name']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="debug-label">Type</div>
                        <div class="debug-value"><?= htmlspecialchars($site['type']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="debug-label">Domain</div>
                        <div class="debug-value"><?= htmlspecialchars($site['domain']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="debug-label">SSL</div>
                        <div class="debug-value"><?= $site['ssl'] ? 'Enabled' : 'Disabled' ?></div>
                    </div>
                </div>

                <!-- Container Stats -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="debug-label">CPU Usage</div>
                        <div class="debug-value"><?= htmlspecialchars($containerDetails['cpu'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="debug-label">Memory Usage</div>
                        <div class="debug-value"><?= htmlspecialchars($containerDetails['memory'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="debug-label">Network I/O</div>
                        <div class="debug-value"><?= htmlspecialchars($containerDetails['network'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="debug-label">Disk I/O</div>
                        <div class="debug-value"><?= htmlspecialchars($containerDetails['disk_io'] ?? 'N/A') ?></div>
                    </div>
                </div>

                <!-- Container Details -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="debug-label">Image</div>
                        <div class="debug-value"><?= htmlspecialchars($containerDetails['image'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="debug-label">Started At</div>
                        <div class="debug-value"><?= htmlspecialchars($containerDetails['started_at'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="debug-label">Restart Count</div>
                        <div class="debug-value"><?= htmlspecialchars($containerDetails['restart_count'] ?? '0') ?></div>
                    </div>
                </div>

                <!-- Volumes -->
                <?php if (!empty($containerDetails['volumes'])): ?>
                <div class="mb-3">
                    <div class="debug-label mb-2">Volumes</div>
                    <?php foreach ($containerDetails['volumes'] as $volume): 
                        list($typeSource, $dest) = explode('->', $volume);
                        list($type, $source) = explode(':', $typeSource);
                        $size = $containerDetails['volume_sizes'][$source] ?? 'N/A';
                    ?>
                    <div class="volume-item">
                        <strong>Type:</strong> <?= htmlspecialchars($type) ?><br>
                        <strong>Source:</strong> <code><?= htmlspecialchars($source) ?></code><br>
                        <strong>Destination:</strong> <code><?= htmlspecialchars($dest) ?></code><br>
                        <strong>Size:</strong> <?= htmlspecialchars($size) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Ports -->
                <?php if (!empty($containerDetails['ports'])): ?>
                <div class="mb-3">
                    <div class="debug-label mb-2">Port Mappings</div>
                    <?php foreach ($containerDetails['ports'] as $port): ?>
                    <div class="env-var"><?= htmlspecialchars($port) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Environment Variables -->
                <?php if (!empty($containerDetails['env'])): ?>
                <div class="mb-3">
                    <div class="debug-label mb-2">Environment Variables (<?= count($containerDetails['env']) ?>)</div>
                    <details>
                        <summary class="btn btn-sm btn-outline-secondary mb-2">Show/Hide</summary>
                        <?php foreach ($containerDetails['env'] as $env): ?>
                        <div class="env-var"><?= htmlspecialchars($env) ?></div>
                        <?php endforeach; ?>
                    </details>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php endif; ?>
    </div>

    <?php include 'includes/modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js?v=3.0.<?= time() ?>"></script>
</body>
</html>

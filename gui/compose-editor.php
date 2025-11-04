<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect to SSL URL if dashboard has SSL enabled and request is from :9000
redirectToSSLIfEnabled();

// Require authentication and admin role
requireAuth();
$currentUser = getCurrentUser();

// Only admins can edit compose files
if ($currentUser['role'] !== 'admin') {
    header('Location: /');
    exit;
}

$db = initDatabase();

$successMessage = '';
$errorMessage = '';
$siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : null;

// Get site info if editing site config
$site = null;
if ($siteId) {
    $site = getSiteById($db, $siteId);
    if (!$site) {
        $errorMessage = 'Site not found';
        $siteId = null;
    }
}

// Load current config
$config = getComposeConfig($db, $siteId);
$currentYaml = $config ? $config['compose_yaml'] : '';

// If no config found, show error message
if (!$config && $siteId === null) {
    $errorMessage = 'Main Traefik configuration not found in database. Please run the migration script.';
} elseif (!$config && $siteId !== null) {
    $errorMessage = 'Site configuration not found in database.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['compose_yaml'])) {
    $newYaml = $_POST['compose_yaml'];
    
    try {
        // Basic YAML validation (check for common syntax errors)
        if (empty(trim($newYaml))) {
            throw new Exception('YAML content cannot be empty');
        }
        
        // Save to database
        saveComposeConfig($db, $newYaml, $currentUser['id'], $siteId);
        
        // Generate file
        $filePath = generateComposeFile($db, $siteId);
        
        if ($filePath) {
            $successMessage = 'Docker Compose configuration saved successfully! File generated at: ' . $filePath;
            $currentYaml = $newYaml;
            
            // Reload config
            $config = getComposeConfig($db, $siteId);
        } else {
            $errorMessage = 'Configuration saved to database but failed to generate file';
        }
    } catch (Exception $e) {
        $errorMessage = 'Failed to save configuration: ' . $e->getMessage();
    }
}

$pageTitle = ($siteId && $site) ? "Edit Compose: {$site['name']}" : 'Edit Main Traefik Config';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - WharfTales</title>
          <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="WharfTales" />
<link rel="manifest" href="/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">
    <style>
        #compose_yaml {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            line-height: 1.5;
            tab-size: 2;
        }
        .editor-toolbar {
            background: #f8f9fa;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
        }
        .editor-info {
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-file-earmark-code me-2"></i>
                        <?= htmlspecialchars($pageTitle) ?>
                    </h2>
                    <div>
                        <?php if ($siteId): ?>
                            <a href="/site-details.php?id=<?= $siteId ?>" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i>Back to Site
                            </a>
                        <?php endif; ?>
                        <a href="/settings.php" class="btn btn-outline-secondary">
                            <i class="bi bi-gear me-1"></i>Settings
                        </a>
                    </div>
                </div>

                <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Editing raw YAML can break your deployment. Only edit if you know what you're doing.
                    </div>
                    <div class="card-body p-0">
                        <div class="editor-toolbar">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="editor-info">
                                    <?php if ($config): ?>
                                        Last updated: <?= date('Y-m-d H:i:s', strtotime($config['updated_at'])) ?>
                                        <?php if ($config['updated_by']): ?>
                                            by User ID: <?= $config['updated_by'] ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-warning">No configuration found in database</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="badge bg-secondary">YAML</span>
                                    <span class="badge bg-info"><?= $siteId ? 'Site Config' : 'Main Config' ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST">
                            <textarea 
                                name="compose_yaml" 
                                id="compose_yaml" 
                                class="form-control border-0 rounded-0" 
                                rows="30" 
                                required
                                spellcheck="false"
                            ><?= htmlspecialchars($currentYaml) ?></textarea>
                            
                            <div class="p-3 bg-light border-top">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Changes will be saved to database and the docker-compose.yml file will be regenerated.
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-secondary me-2" onclick="resetEditor()">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-1"></i>Save Configuration
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($siteId): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-terminal me-2"></i>Quick Actions
                    </div>
                    <div class="card-body">
                        <button class="btn btn-warning" onclick="restartSite()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Restart Site Containers
                        </button>
                        <button class="btn btn-info ms-2" onclick="viewLogs()">
                            <i class="bi bi-file-text me-1"></i>View Logs
                        </button>
                    </div>
                </div>
                <?php else: ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="bi bi-terminal me-2"></i>Quick Actions
                    </div>
                    <div class="card-body">
                        <button class="btn btn-warning" onclick="restartTraefik()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Restart Traefik
                        </button>
                        <button class="btn btn-info ms-2" onclick="viewTraefikLogs()">
                            <i class="bi bi-file-text me-1"></i>View Traefik Logs
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
        const originalYaml = <?= json_encode($currentYaml) ?>;
        
        function resetEditor() {
            if (confirm('Reset to last saved version? Any unsaved changes will be lost.')) {
                document.getElementById('compose_yaml').value = originalYaml;
            }
        }
        
        async function restartTraefik() {
            if (!confirm('Restart Traefik? This may cause brief downtime for all sites.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=restart_traefik', { method: 'POST' });
                const result = await response.json();
                
                if (result.success) {
                    alert('Traefik restarted successfully!');
                } else {
                    alert('Failed to restart Traefik: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        function viewTraefikLogs() {
            window.location.href = '/traefik-logs.php';
        }
        
        async function restartSite() {
            if (!confirm('Restart site containers? This will cause brief downtime.')) {
                return;
            }
            
            try {
                const response = await fetch('/api.php?action=restart_site&id=<?= $siteId ?>', { method: 'POST' });
                const result = await response.json();
                
                if (result.success) {
                    alert('Site restarted successfully!');
                } else {
                    alert('Failed to restart site: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        function viewLogs() {
            window.location.href = '/site-details.php?id=<?= $siteId ?>#logs';
        }
        
        // Warn before leaving with unsaved changes
        let formChanged = false;
        document.getElementById('compose_yaml').addEventListener('input', function() {
            formChanged = (this.value !== originalYaml);
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
        
        document.querySelector('form').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>

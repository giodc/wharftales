<?php
/**
 * Quick setup for local update testing
 * Access this file once via browser, then delete it
 */

require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require admin access
if (!isLoggedIn() || !isAdmin()) {
    die('Access denied. Admin login required.');
}

$db = initDatabase();

// Set to local file
$localPath = '/opt/wharftales/versions.json';
setSetting($db, 'versions_url', $localPath);

$currentUrl = getSetting($db, 'versions_url');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update System Setup</title>
          <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="WharfTales" />
<link rel="manifest" href="/site.webmanifest" /></div>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">✓ Update System Configured</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <strong>Success!</strong> The update system is now configured to use a local versions.json file.
                </div>
                
                <h5>Current Configuration:</h5>
                <ul>
                    <li><strong>Versions URL:</strong> <code><?= htmlspecialchars($currentUrl) ?></code></li>
                    <li><strong>Current Version:</strong> <code><?= getCurrentVersion() ?></code></li>
                </ul>
                
                <h5>Next Steps:</h5>
                <ol>
                    <li>Go to <a href="/settings.php">Settings → System Updates</a></li>
                    <li>Click "Check Now" to test the update check</li>
                    <li>The system will read from the local file instead of GitHub</li>
                </ol>
                
                <h5>To Switch to GitHub Later:</h5>
                <ol>
                    <li>Commit and push <code>versions.json</code> to your repository</li>
                    <li>Go to Settings → System Updates</li>
                    <li>Change "Versions URL" to:<br>
                        <code>https://raw.githubusercontent.com/giodc/wharftales/main/versions.json</code>
                    </li>
                    <li>Click "Save Update Settings"</li>
                </ol>
                
                <div class="alert alert-warning mt-3">
                    <strong>Security Note:</strong> Delete this file after setup:<br>
                    <code>rm /opt/wharftales/gui/setup-local-updates.php</code>
                </div>
                
                <a href="/settings.php" class="btn btn-primary">Go to Settings</a>
            </div>
        </div>
    </div>
</body>
</html>

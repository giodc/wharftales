#!/usr/bin/env php
<?php
/**
 * Fix Dashboard SSL 404 Error
 * 
 * This script:
 * 1. Checks current dashboard domain in database
 * 2. Updates docker-compose.yml with correct Traefik labels
 * 3. Fixes the port typo (808080 -> 8080)
 * 4. Applies changes and restarts the web-gui container
 */

echo "=== WharfTales Dashboard SSL 404 Fix ===\n\n";

// Load functions
require_once '/var/www/html/includes/functions.php';

// Initialize database
$db = initDatabase();

// Get current settings
$dashboardDomain = getSetting($db, 'dashboard_domain', '');
$dashboardSSL = getSetting($db, 'dashboard_ssl', '0');

echo "Current Database Settings:\n";
echo "  Domain: " . ($dashboardDomain ?: '(not set)') . "\n";
echo "  SSL Enabled: " . ($dashboardSSL === '1' ? 'YES' : 'NO') . "\n\n";

if (empty($dashboardDomain)) {
    echo "❌ ERROR: No dashboard domain configured in database.\n";
    echo "   Please set a domain first in Settings > Dashboard Domain\n";
    exit(1);
}

// Get main config from database
echo "Fetching docker-compose configuration from database...\n";
$config = getComposeConfig($db, null);

if (!$config) {
    echo "❌ ERROR: Main configuration not found in database.\n";
    exit(1);
}

$content = $config['compose_yaml'];

// Check for web-gui service
if (!preg_match('/web-gui:/', $content)) {
    echo "❌ ERROR: web-gui service not found in docker-compose.yml\n";
    exit(1);
}

echo "✓ Configuration loaded\n\n";

// Build correct Traefik labels
echo "Building Traefik labels...\n";
$labels = "\n    labels:\n";
$labels .= "      - traefik.enable=true\n";
$labels .= "      - traefik.http.routers.webgui.rule=Host(`{$dashboardDomain}`)\n";
$labels .= "      - traefik.http.routers.webgui.entrypoints=web\n";
$labels .= "      - traefik.http.services.webgui.loadbalancer.server.port=8080\n";

if ($dashboardSSL === '1') {
    $labels .= "      - traefik.http.routers.webgui-secure.rule=Host(`{$dashboardDomain}`)\n";
    $labels .= "      - traefik.http.routers.webgui-secure.entrypoints=websecure\n";
    $labels .= "      - traefik.http.routers.webgui-secure.tls=true\n";
    $labels .= "      - traefik.http.routers.webgui-secure.tls.certresolver=letsencrypt\n";
    $labels .= "      - traefik.http.middlewares.webgui-redirect.redirectscheme.scheme=https\n";
    $labels .= "      - traefik.http.middlewares.webgui-redirect.redirectscheme.permanent=true\n";
    $labels .= "      - traefik.http.routers.webgui.middlewares=webgui-redirect\n";
    echo "  ✓ SSL labels included (HTTPS redirect enabled)\n";
} else {
    echo "  ✓ HTTP-only labels\n";
}

// Update docker-compose.yml
echo "\nUpdating docker-compose.yml...\n";

// First, remove any existing labels section for web-gui
$pattern = '/(web-gui:.*?)(labels:.*?)(networks:)/s';
if (preg_match($pattern, $content)) {
    // Labels exist, replace them
    echo "  - Replacing existing labels\n";
    $content = preg_replace(
        '/(web-gui:.*?)(labels:.*?)(networks:)/s',
        '$1' . $labels . '    $3',
        $content
    );
} else {
    // No labels, add them before networks
    echo "  - Adding new labels\n";
    $content = preg_replace(
        '/(web-gui:.*?)(    networks:)/s',
        '$1' . $labels . '$2',
        $content
    );
}

// Get current user (admin)
$stmt = $db->query("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    echo "❌ ERROR: No admin user found\n";
    exit(1);
}

// Save to database and regenerate file
try {
    echo "\nSaving configuration to database...\n";
    saveComposeConfig($db, $content, $currentUser['id'], null);
    
    echo "Regenerating docker-compose.yml file...\n";
    generateComposeFile($db, null);
    
    echo "✓ Configuration updated successfully!\n\n";
} catch (Exception $e) {
    echo "❌ ERROR: Failed to save configuration: " . $e->getMessage() . "\n";
    exit(1);
}

// Restart web-gui container
echo "=== Next Steps ===\n\n";
echo "1. Restart the web-gui container to apply changes:\n";
echo "   cd /opt/wharftales && docker-compose restart web-gui\n\n";

echo "2. Wait 10-30 seconds for Traefik to pick up the new labels\n\n";

echo "3. Test the dashboard:\n";
echo "   HTTP:  http://{$dashboardDomain}\n";
if ($dashboardSSL === '1') {
    echo "   HTTPS: https://{$dashboardDomain}\n";
}
echo "\n";

echo "4. Check Traefik logs if issues persist:\n";
echo "   docker logs wharftales_traefik --tail 50\n\n";

// Offer to restart automatically
echo "Would you like to restart the web-gui container now? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) == 'y' || trim($line) == 'yes') {
    echo "\nRestarting web-gui container...\n";
    $output = [];
    $returnCode = 0;
    exec('cd /opt/wharftales && docker-compose restart web-gui 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✓ Container restarted successfully!\n";
        echo "\nWaiting 10 seconds for startup...\n";
        sleep(10);
        
        echo "\nTesting HTTP access...\n";
        $httpTest = @file_get_contents("http://{$dashboardDomain}", false, stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]));
        
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0];
            echo "Response: {$statusLine}\n";
            
            if (strpos($statusLine, '200') !== false) {
                echo "✓ Dashboard is accessible!\n";
            } elseif (strpos($statusLine, '404') !== false) {
                echo "⚠ Still getting 404. Check Traefik logs:\n";
                echo "   docker logs wharftales_traefik --tail 20\n";
            } elseif (strpos($statusLine, '301') !== false || strpos($statusLine, '302') !== false) {
                echo "✓ Redirecting to HTTPS (expected with SSL enabled)\n";
            }
        }
    } else {
        echo "❌ Failed to restart container:\n";
        echo implode("\n", $output) . "\n";
    }
}

echo "\n=== Fix Complete ===\n";

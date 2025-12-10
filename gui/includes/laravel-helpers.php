<?php
/**
 * Laravel Helper Functions
 */

/**
 * Fix Laravel permissions
 * 
 * @param string $containerName Docker container name
 * @return array Result with success status
 */
function fixLaravelPermissions($containerName) {
    $results = [];
    
    $results[] = "Fixing Laravel permissions...";
    
    // Determine web user
    // Prioritize 'www' (often UID 1000) as it's commonly used for the main process in Docker
    // www-data exists by default in most images so it's a less reliable indicator
    exec("docker exec {$containerName} id -u www > /dev/null 2>&1", $void, $wwwReturn);
    
    if ($wwwReturn === 0) {
        $webUser = 'www';
    } else {
        exec("docker exec {$containerName} id -u www-data > /dev/null 2>&1", $void, $wwwDataReturn);
        $webUser = ($wwwDataReturn === 0) ? 'www-data' : 'www-data';
    }
    
    $results[] = "Target user: {$webUser}";
    
    // Set ownership
    // Must use -u root to change ownership
    exec("docker exec -u root {$containerName} chown -R {$webUser}:{$webUser} /var/www/html 2>&1", $output, $return);
    if ($return === 0) {
        $results[] = "✓ Set ownership to {$webUser}";
    } else {
        // If chown failed, capture output for debugging
        $error = implode("\n", $output);
        return ['success' => false, 'message' => 'Failed to set ownership: ' . $error];
    }
    
    // Set directory permissions (755)
    exec("docker exec -u root {$containerName} find /var/www/html -type d -exec chmod 755 {} \\; 2>&1", $output, $return);
    if ($return === 0) {
        $results[] = "✓ Set directory permissions (755)";
    }
    
    // Set file permissions (644)
    exec("docker exec -u root {$containerName} find /var/www/html -type f -exec chmod 644 {} \\; 2>&1", $output, $return);
    if ($return === 0) {
        $results[] = "✓ Set file permissions (644)";
    }
    
    // Storage and cache need write permissions (775)
    exec("docker exec -u root {$containerName} sh -c 'chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>&1'", $output, $return);
    if ($return === 0) {
        $results[] = "✓ Set storage/cache permissions (775)";
    }
    
    // Ensure index.php files are readable
    exec("docker exec -u root {$containerName} chmod 644 /var/www/html/index.php 2>/dev/null", $output, $return);
    exec("docker exec -u root {$containerName} chmod 644 /var/www/html/public/index.php 2>/dev/null", $output, $return);
    $results[] = "✓ Ensured index.php files are readable";
    
    return [
        'success' => true,
        'message' => 'Permissions fixed successfully',
        'details' => implode("\n", $results)
    ];
}

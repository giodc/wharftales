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
    
    // Set ownership to www-data
    exec("docker exec {$containerName} chown -R www-data:www-data /var/www/html", $output, $return);
    if ($return === 0) {
        $results[] = "✓ Set ownership to www-data";
    } else {
        return ['success' => false, 'message' => 'Failed to set ownership'];
    }
    
    // Set directory permissions (755)
    exec("docker exec {$containerName} find /var/www/html -type d -exec chmod 755 {} \\;", $output, $return);
    if ($return === 0) {
        $results[] = "✓ Set directory permissions (755)";
    }
    
    // Set file permissions (644)
    exec("docker exec {$containerName} find /var/www/html -type f -exec chmod 644 {} \\;", $output, $return);
    if ($return === 0) {
        $results[] = "✓ Set file permissions (644)";
    }
    
    // Storage and cache need write permissions (775)
    exec("docker exec {$containerName} sh -c 'chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>&1'", $output, $return);
    if ($return === 0) {
        $results[] = "✓ Set storage/cache permissions (775)";
    }
    
    // Ensure index.php files are readable
    exec("docker exec {$containerName} chmod 644 /var/www/html/index.php 2>/dev/null", $output, $return);
    exec("docker exec {$containerName} chmod 644 /var/www/html/public/index.php 2>/dev/null", $output, $return);
    $results[] = "✓ Ensured index.php files are readable";
    
    return [
        'success' => true,
        'message' => 'Permissions fixed successfully',
        'details' => implode("\n", $results)
    ];
}

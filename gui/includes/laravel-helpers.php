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
    $webUser = getContainerWebUser($containerName);
    
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
    
    // Fix node_modules and vendor executables
    // node_modules/.bin contains symlinks, and we need to make actual bin files executable
    // This includes .js files, native binaries (esbuild, etc), and other executables
    exec("docker exec -u root {$containerName} sh -c 'if [ -d /var/www/html/node_modules ]; then find /var/www/html/node_modules -path \"*/bin/*\" -type f -exec chmod 755 {} \\; 2>/dev/null; fi' 2>&1", $output, $return);
    exec("docker exec -u root {$containerName} sh -c 'if [ -d /var/www/html/node_modules/.bin ]; then chmod -R 755 /var/www/html/node_modules/.bin; fi' 2>&1", $output, $return);
    exec("docker exec -u root {$containerName} sh -c 'if [ -d /var/www/html/vendor/bin ]; then chmod -R 755 /var/www/html/vendor/bin; fi' 2>&1", $output, $return);
    $results[] = "✓ Set executable permissions for node_modules and vendor executables (including native binaries)";
    
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

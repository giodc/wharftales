<?php
/**
 * WharfTales Update Configuration
 */

define('UPDATE_ENABLED', true);
define('AUTO_UPDATE_ENABLED', false); // Set to true to enable automatic updates
define('UPDATE_CHECK_INTERVAL', 3600); // Check for updates every hour (in seconds)
define('GIT_REMOTE', 'origin');
define('GIT_BRANCH', 'main');
define('UPDATE_LOG_FILE', '/app/data/update.log');

// Version file location
define('VERSIONS_JSON_FILE', '/var/www/html/../versions.json');

/**
 * Get current version
 */
function getCurrentVersion() {
    if (file_exists(VERSIONS_JSON_FILE)) {
        $content = file_get_contents(VERSIONS_JSON_FILE);
        $json = json_decode($content, true);
        if (isset($json['wharftales']['latest'])) {
            return $json['wharftales']['latest'];
        }
    }
    
    return '0.0.0';
}

/**
 * Get remote version from Git
 */
function getRemoteVersion() {
    try {
        // Fetch latest from remote
        exec('cd /var/www/html/.. && git fetch ' . GIT_REMOTE . ' ' . GIT_BRANCH . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            return null;
        }
        
        // Get remote versions.json file content
        exec('cd /var/www/html/.. && git show ' . GIT_REMOTE . '/' . GIT_BRANCH . ':versions.json 2>&1', $versionOutput, $returnCode);
        
        if ($returnCode === 0 && !empty($versionOutput)) {
            $jsonContent = implode("\n", $versionOutput);
            $json = json_decode($jsonContent, true);
            if (isset($json['wharftales']['latest'])) {
                return $json['wharftales']['latest'];
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting remote version: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if update is available
 */
function isUpdateAvailable() {
    $currentVersion = getCurrentVersion();
    $remoteVersion = getRemoteVersion();
    
    if ($remoteVersion === null) {
        return false;
    }
    
    return version_compare($remoteVersion, $currentVersion, '>');
}

/**
 * Get Git status
 */
function getGitStatus() {
    exec('cd /var/www/html/.. && git status --porcelain 2>&1', $output, $returnCode);
    
    // Filter out files that should be ignored (data/, logs/, etc.)
    $ignoredPatterns = [
        'data/',
        'logs/',
        'ssl/',
        'volumes/',
        '.env',
        'docker-compose.yml',
        'nginx/sites/',
        '*.log',
        '*.tmp',
        '*.backup',
        '*.bak'
    ];
    
    $relevantChanges = [];
    foreach ($output as $line) {
        $shouldIgnore = false;
        foreach ($ignoredPatterns as $pattern) {
            if (strpos($line, $pattern) !== false) {
                $shouldIgnore = true;
                break;
            }
        }
        if (!$shouldIgnore && !empty(trim($line))) {
            $relevantChanges[] = $line;
        }
    }
    
    return [
        'has_changes' => !empty($relevantChanges),
        'changes' => $relevantChanges,
        'all_changes' => $output,
        'is_git_repo' => $returnCode === 0
    ];
}

/**
 * Get last update check time
 */
function getLastUpdateCheck() {
    $cacheFile = '/app/data/last_update_check';
    if (file_exists($cacheFile)) {
        return (int)file_get_contents($cacheFile);
    }
    return 0;
}

/**
 * Set last update check time
 */
function setLastUpdateCheck($timestamp = null) {
    $cacheFile = '/app/data/last_update_check';
    file_put_contents($cacheFile, $timestamp ?? time());
}

/**
 * Log update activity
 */
function logUpdate($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents(UPDATE_LOG_FILE, $logMessage, FILE_APPEND);
}

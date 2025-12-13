#!/usr/bin/env php
<?php
/**
 * WharfTales Update Checker - Cron Script
 * This script checks for updates and optionally auto-updates
 * Run via cron: (every 5 minutes)
 * Crontab entry: star-slash-5 * * * * /usr/bin/php /opt/wharftales/scripts/check-updates-cron.php
 */

// Change to the correct directory
chdir('/var/www/html');

require_once '/var/www/html/includes/functions.php';

try {
    $db = initDatabase();
    
    // Check if update checks are enabled
    $updateCheckEnabled = getSetting($db, 'update_check_enabled', '1');
    if ($updateCheckEnabled !== '1') {
        echo "Update checks are disabled\n";
        exit(0);
    }
    
    // Check for updates (respects frequency settings)
    $updateInfo = checkForUpdates(false);
    
    if (isset($updateInfo['error'])) {
        error_log("WharfTales update check failed: " . $updateInfo['error']);
        exit(1);
    }
    
    echo "Current version: " . ($updateInfo['current_version'] ?? 'unknown') . "\n";
    echo "Latest version: " . ($updateInfo['latest_version'] ?? 'unknown') . "\n";
    
    if ($updateInfo['update_available'] ?? false) {
        echo "Update available!\n";
        
        // Check if auto-update is enabled
        $autoUpdateEnabled = getSetting($db, 'auto_update_enabled', '0');
        
        if ($autoUpdateEnabled === '1') {
            echo "Auto-update is enabled, triggering update...\n";
            
            // Check if update is already in progress
            $updateStatus = getUpdateStatus();
            if ($updateStatus['in_progress']) {
                echo "Update already in progress, skipping\n";
                exit(0);
            }
            
            // Trigger the update
            $result = triggerUpdate(false);
            
            if ($result['success']) {
                echo "Update triggered successfully\n";
                echo "Log file: " . $result['log_file'] . "\n";
            } else {
                error_log("WharfTales auto-update failed: " . $result['error']);
                exit(1);
            }
        } else {
            echo "Auto-update is disabled, notification set\n";
        }
    } else {
        echo "No updates available\n";
    }
    
    exit(0);
    
} catch (Exception $e) {
    error_log("WharfTales update check script error: " . $e->getMessage());
    exit(1);
}

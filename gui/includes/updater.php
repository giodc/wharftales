<?php
/**
 * WharfTales Update Manager
 */

require_once 'update-config.php';

class Updater {
    private $backupDir = '/app/data/backups';
    
    /**
     * Perform system update
     */
    public function performUpdate() {
        try {
            logUpdate("Starting update process...");
            
            // Check if Git repository
            $gitStatus = getGitStatus();
            if (!$gitStatus['is_git_repo']) {
                throw new Exception("Not a Git repository");
            }
            
            // Check for local changes
            if ($gitStatus['has_changes']) {
                logUpdate("Warning: Local changes detected, stashing...");
                exec('cd /var/www/html/.. && git stash 2>&1', $output, $returnCode);
                if ($returnCode !== 0) {
                    throw new Exception("Failed to stash local changes");
                }
            }
            
            // Create backup
            $this->createBackup();
            
            // Pull latest changes
            logUpdate("Pulling latest changes from " . GIT_REMOTE . "/" . GIT_BRANCH);
            exec('cd /var/www/html/.. && git pull ' . GIT_REMOTE . ' ' . GIT_BRANCH . ' 2>&1', $output, $returnCode);
            
            if ($returnCode !== 0) {
                logUpdate("Error: Git pull failed - " . implode("\n", $output));
                throw new Exception("Failed to pull updates: " . implode("\n", $output));
            }
            
            logUpdate("Successfully pulled updates");
            
            // Run post-update tasks
            $this->runPostUpdateTasks();
            
            // Update version cache
            setLastUpdateCheck(time());
            
            $newVersion = getCurrentVersion();
            logUpdate("Update completed successfully! New version: $newVersion");
            
            return [
                'success' => true,
                'message' => "Updated to version $newVersion",
                'version' => $newVersion,
                'output' => $output
            ];
            
        } catch (Exception $e) {
            logUpdate("Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create backup before update
     */
    private function createBackup() {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $version = getCurrentVersion();
        $backupFile = $this->backupDir . "/backup_v{$version}_{$timestamp}.tar.gz";
        
        logUpdate("Creating backup: $backupFile");
        
        // Backup important files
        exec("cd /var/www/html/.. && tar -czf $backupFile gui/ data/ docker-compose.yml versions.json 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            logUpdate("Warning: Backup creation failed");
        } else {
            logUpdate("Backup created successfully");
        }
        
        // Keep only last 5 backups
        $this->cleanOldBackups(5);
    }
    
    /**
     * Clean old backups
     */
    private function cleanOldBackups($keep = 5) {
        $backups = glob($this->backupDir . '/backup_*.tar.gz');
        if (count($backups) > $keep) {
            usort($backups, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            $toDelete = array_slice($backups, $keep);
            foreach ($toDelete as $file) {
                unlink($file);
                logUpdate("Deleted old backup: " . basename($file));
            }
        }
    }
    
    /**
     * Run post-update tasks
     */
    private function runPostUpdateTasks() {
        logUpdate("Running post-update tasks...");
        
        // Fix permissions
        exec('cd /var/www/html/.. && chmod -R 755 gui/includes gui/js gui/css 2>&1');
        exec('cd /var/www/html/.. && chmod 644 gui/includes/*.php gui/*.php gui/css/*.css gui/js/*.js 2>&1');
        
        // Clear any PHP opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
            logUpdate("Cleared OPcache");
        }
        
        logUpdate("Post-update tasks completed");
    }
    
    /**
     * Get update information
     */
    public function getUpdateInfo() {
        $currentVersion = getCurrentVersion();
        $remoteVersion = getRemoteVersion();
        $updateAvailable = isUpdateAvailable();
        $gitStatus = getGitStatus();
        
        return [
            'current_version' => $currentVersion,
            'remote_version' => $remoteVersion,
            'update_available' => $updateAvailable,
            'has_local_changes' => $gitStatus['has_changes'],
            'is_git_repo' => $gitStatus['is_git_repo'],
            'last_check' => getLastUpdateCheck(),
            'auto_update_enabled' => AUTO_UPDATE_ENABLED
        ];
    }
    
    /**
     * Get update changelog
     */
    public function getChangelog($limit = 10) {
        try {
            exec('cd /var/www/html/.. && git log --oneline -' . $limit . ' ' . GIT_REMOTE . '/' . GIT_BRANCH . ' 2>&1', $output, $returnCode);
            
            if ($returnCode === 0) {
                return $output;
            }
            return [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get update logs
     */
    public function getUpdateLogs($lines = 50) {
        if (file_exists(UPDATE_LOG_FILE)) {
            $logs = file(UPDATE_LOG_FILE);
            return array_slice($logs, -$lines);
        }
        return [];
    }
}

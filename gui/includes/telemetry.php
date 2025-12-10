<?php
/**
 * Telemetry System
 * Privacy-respecting installation tracking (opt-in)
 */

/**
 * Get or create installation ID
 */
function getInstallationId() {
    $idFile = '/app/data/installation_id.txt';
    
    if (file_exists($idFile)) {
        return trim(file_get_contents($idFile));
    }
    
    // Generate new UUID
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    file_put_contents($idFile, $uuid);
    return $uuid;
}

/**
 * Check if telemetry is enabled
 */
function isTelemetryEnabled() {
    $db = initAuthDatabase();
    $stmt = $db->query("SELECT value FROM settings WHERE key = 'telemetry_enabled'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result && $result['value'] === '1';
}

/**
 * Set telemetry preference
 */
function setTelemetryEnabled($enabled) {
    $db = initAuthDatabase();
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('telemetry_enabled', ?)");
    $stmt->execute([$enabled ? '1' : '0']);
}

/**
 * Get telemetry endpoint URL
 */
function getTelemetryEndpoint() {
    // Default endpoint - you can change this to your own server
    return 'https://telemetry.wharftales.org/api/ping';
}

/**
 * Send telemetry ping
 */
function sendTelemetryPing() {
    if (!isTelemetryEnabled()) {
        return ['success' => false, 'message' => 'Telemetry is disabled'];
    }
    
    try {
        $db = initDatabase();
        
        // Get version
        $version = 'unknown';
        if (file_exists('/var/www/html/../versions.json')) {
            $content = file_get_contents('/var/www/html/../versions.json');
            $json = json_decode($content, true);
            if (isset($json['wharftales']['latest'])) {
                $version = $json['wharftales']['latest'];
            }
        } elseif (file_exists('/var/www/html/../VERSION')) {
             $version = trim(file_get_contents('/var/www/html/../VERSION'));
        }
        
        // Get site count
        $stmt = $db->query("SELECT COUNT(*) as count FROM sites");
        $siteCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Prepare data
        $data = [
            'installation_id' => getInstallationId(),
            'version' => $version,
            'php_version' => PHP_VERSION,
            'site_count' => (int)$siteCount,
            'timestamp' => time()
        ];
        
        // Send ping
        $endpoint = getTelemetryEndpoint();
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 second timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            // Update last ping time
            $db = initAuthDatabase();
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('telemetry_last_ping', ?)");
            $stmt->execute([time()]);
            
            return ['success' => true, 'message' => 'Telemetry ping sent'];
        } else {
            return ['success' => false, 'message' => 'Failed to send ping: HTTP ' . $httpCode];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Check if ping is due (once per day)
 */
function shouldSendPing() {
    if (!isTelemetryEnabled()) {
        return false;
    }
    
    $db = initAuthDatabase();
    $stmt = $db->query("SELECT value FROM settings WHERE key = 'telemetry_last_ping'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return true; // Never pinged before
    }
    
    $lastPing = (int)$result['value'];
    $dayAgo = time() - (24 * 60 * 60);
    
    return $lastPing < $dayAgo;
}

/**
 * Auto-send ping if due (call this on dashboard load)
 */
function autoSendTelemetryPing() {
    if (shouldSendPing()) {
        // Send in background (non-blocking)
        sendTelemetryPing();
    }
}

<?php
/**
 * Health Check Endpoint
 * Returns 200 OK if the system is healthy
 * Used by monitoring systems and post-update validation
 */

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => time(),
    'checks' => []
];

$allHealthy = true;

// Check 1: Database accessible
try {
    $db = new PDO('sqlite:/app/data/database.sqlite');
    $result = $db->query('SELECT COUNT(*) FROM settings');
    $count = $result->fetchColumn();
    $health['checks']['database'] = [
        'status' => 'ok',
        'settings_count' => $count
    ];
} catch (Exception $e) {
    $health['checks']['database'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
    $allHealthy = false;
}

// Check 2: Required directories writable
$directories = [
    '/app/data',
    '/app/data/logs'
];

foreach ($directories as $dir) {
    if (!is_writable($dir)) {
        $health['checks']['filesystem'][$dir] = [
            'status' => 'error',
            'error' => 'not writable'
        ];
        $allHealthy = false;
    } else {
        $health['checks']['filesystem'][$dir] = ['status' => 'ok'];
    }
}

// Check 3: Version file readable
if (file_exists('../VERSION')) {
    $version = trim(file_get_contents('../VERSION'));
    $health['checks']['version'] = [
        'status' => 'ok',
        'version' => $version
    ];
} else {
    $health['checks']['version'] = [
        'status' => 'warning',
        'error' => 'VERSION file not found'
    ];
}

// Set overall status
if (!$allHealthy) {
    $health['status'] = 'unhealthy';
    http_response_code(503);
} else {
    http_response_code(200);
}

echo json_encode($health, JSON_PRETTY_PRINT);

<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/api_debug.log');

file_put_contents('/tmp/api_debug.log', "\n=== REQUEST ===\n", FILE_APPEND);

ob_start();
header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    file_put_contents('/tmp/api_debug.log', "ERROR: $errstr in $errfile:$errline\n", FILE_APPEND);
    if (stripos($errstr, 'session') !== false && stripos($errstr, 'headers') !== false) {
        file_put_contents('/tmp/api_debug.log', "  -> Skipped\n", FILE_APPEND);
        return true;
    }
    file_put_contents('/tmp/api_debug.log', "  -> Returning error\n", FILE_APPEND);
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $errstr]);
    ob_end_flush();
    exit;
});

file_put_contents('/tmp/api_debug.log', "Loading...\n", FILE_APPEND);

try {
    require_once 'includes/functions.php';
    file_put_contents('/tmp/api_debug.log', "  functions OK\n", FILE_APPEND);
    require_once 'includes/auth.php';
    file_put_contents('/tmp/api_debug.log', "  auth OK\n", FILE_APPEND);
} catch (Throwable $e) {
    file_put_contents('/tmp/api_debug.log', "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    ob_end_flush();
    exit;
}

file_put_contents('/tmp/api_debug.log', "Checking auth...\n", FILE_APPEND);

if (!isLoggedIn()) {
    file_put_contents('/tmp/api_debug.log', "  Not logged in\n", FILE_APPEND);
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

file_put_contents('/tmp/api_debug.log', "  Logged in\n", FILE_APPEND);

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);
file_put_contents('/tmp/api_debug.log', "Action: $action\n", FILE_APPEND);

if ($action === 'update_setting') {
    try {
        $db = initDatabase();
        $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime("now"))');
        $stmt->execute([$input['key'], $input['value'] ?? '']);
        
        file_put_contents('/tmp/api_debug.log', "  Saved\n", FILE_APPEND);
        echo json_encode(['success' => true, 'message' => 'Saved']);
    } catch (Throwable $e) {
        file_put_contents('/tmp/api_debug.log', "  ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

ob_end_flush();
file_put_contents('/tmp/api_debug.log', "=== END ===\n", FILE_APPEND);

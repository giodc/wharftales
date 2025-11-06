<?php
/**
 * Test Update Check - Diagnostic Page
 * Access via: http://your-server:9000/test-update-check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>WharfTales Update Check Diagnostic</h1>";
echo "<pre>";

// Test 1: Check if file_get_contents works
echo "=== Test 1: Basic file_get_contents ===" . PHP_EOL;
$url = 'https://raw.githubusercontent.com/giodc/wharftales/refs/heads/master/versions.json';
echo "URL: $url" . PHP_EOL;

$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'WharfTales/Test',
        'ignore_errors' => true
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
]);

echo "Fetching..." . PHP_EOL;
$start = microtime(true);
$response = @file_get_contents($url, false, $context);
$duration = microtime(true) - $start;

if ($response === false) {
    echo "FAILED: Could not fetch file" . PHP_EOL;
    $error = error_get_last();
    echo "Error: " . print_r($error, true) . PHP_EOL;
} else {
    echo "SUCCESS: Fetched in " . round($duration, 2) . " seconds" . PHP_EOL;
    echo "Size: " . strlen($response) . " bytes" . PHP_EOL;
    echo "Content:" . PHP_EOL;
    echo $response . PHP_EOL;
}

// Test 2: Check HTTP headers
echo PHP_EOL . "=== Test 2: HTTP Headers ===" . PHP_EOL;
if (isset($http_response_header)) {
    foreach ($http_response_header as $header) {
        echo $header . PHP_EOL;
    }
} else {
    echo "No HTTP headers available" . PHP_EOL;
}

// Test 3: Test with curl
echo PHP_EOL . "=== Test 3: Using CURL ===" . PHP_EOL;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WharfTales/Test');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $duration = microtime(true) - $start;
    $info = curl_getinfo($ch);
    
    if ($response === false) {
        echo "FAILED: " . curl_error($ch) . PHP_EOL;
    } else {
        echo "SUCCESS: Fetched in " . round($duration, 2) . " seconds" . PHP_EOL;
        echo "HTTP Code: " . $info['http_code'] . PHP_EOL;
        echo "Size: " . strlen($response) . " bytes" . PHP_EOL;
    }
    curl_close($ch);
} else {
    echo "CURL not available" . PHP_EOL;
}

// Test 4: Check DNS resolution
echo PHP_EOL . "=== Test 4: DNS Resolution ===" . PHP_EOL;
$host = 'raw.githubusercontent.com';
$ip = gethostbyname($host);
if ($ip === $host) {
    echo "FAILED: Could not resolve $host" . PHP_EOL;
} else {
    echo "SUCCESS: $host resolves to $ip" . PHP_EOL;
}

// Test 5: Check allow_url_fopen
echo PHP_EOL . "=== Test 5: PHP Configuration ===" . PHP_EOL;
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'enabled' : 'disabled') . PHP_EOL;
echo "default_socket_timeout: " . ini_get('default_socket_timeout') . PHP_EOL;
echo "max_execution_time: " . ini_get('max_execution_time') . PHP_EOL;

// Test 6: Check network connectivity
echo PHP_EOL . "=== Test 6: Network Connectivity ===" . PHP_EOL;
$socket = @fsockopen('raw.githubusercontent.com', 443, $errno, $errstr, 10);
if ($socket) {
    echo "SUCCESS: Can connect to raw.githubusercontent.com:443" . PHP_EOL;
    fclose($socket);
} else {
    echo "FAILED: Cannot connect - Error $errno: $errstr" . PHP_EOL;
}

echo "</pre>";
echo "<p><a href='settings.php'>← Back to Settings</a></p>";
?>

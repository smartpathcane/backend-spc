<?php
/**
 * SmartPath Cane - IoT Device HTTP Gateway
 * 
 * This script acts as an HTTP endpoint for IoT hardware devices (like ESP32/ESP8266)
 * that may not support modern HTTPS/SSL certificates.
 * 
 * It receives the HTTP POST request from the hardware and securely forwards it
 * to the main HTTPS API endpoint.
 */

// Allow CORS for the gateway
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit();
}

// Get the raw POST data
$jsonData = file_get_contents('php://input');

// Validate JSON
$data = json_decode($jsonData, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // If it's not JSON, try normal POST array (application/x-www-form-urlencoded)
    if (!empty($_POST)) {
        $data = $_POST;
        $jsonData = json_encode($data);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload format.']);
        exit();
    }
}

// Ensure essential fields exist for cane location
if (empty($data['device_serial']) || !isset($data['latitude']) || !isset($data['longitude'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: device_serial, latitude, longitude']);
    exit();
}

// Load environment configuration to find the main API URL
$env = require_once __DIR__ . '/../../bootstrap/config/env.php';

// Determine the target HTTPS API URL
$baseUrl = $env['APP_URL'];
// Ensure the URL doesn't have a trailing slash
$baseUrl = rtrim($baseUrl, '/');
$targetApiUrl = $baseUrl . '/api/cane/location';

// Initialize cURL to forward the request
$ch = curl_init($targetApiUrl);

// Set cURL options for forwarding
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

// Forward headers, particularly Content-Type
$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: SmartPath-Cane-HTTP-Gateway/1.0'
];

// Forward Authorization header if provided by the device
$reqHeaders = getallheaders();
if (isset($reqHeaders['Authorization'])) {
    $headers[] = 'Authorization: ' . $reqHeaders['Authorization'];
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// In development environments without valid SSL, we might need to disable verification
if (isset($env['APP_ENV']) && $env['APP_ENV'] !== 'production') {
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
}

// Execute the proxy request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    // cURL error occurred
    $errorMsg = curl_error($ch);
    http_response_code(502); // Bad Gateway
    echo json_encode([
        'success' => false, 
        'error' => 'Gateway forwarding failed', 
        'details' => $errorMsg,
        'target_url' => $targetApiUrl
    ]);
} else {
    // Forward the exact HTTP response code and body from the main API
    http_response_code($httpCode);
    echo $response;
}

curl_close($ch);

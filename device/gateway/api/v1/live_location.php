<?php
/**
 * SmartPath Cane - IoT Device HTTP API Endpoint
 * 
 * This file handles live location updates directly from the IoT hardware 
 * (ESP32/Arduino) sending data over standard HTTP.
 */

// 1. Force HTTP connection for compatibility with simple IoT modules
require_once __DIR__ . '/../../force_http.php';

// 2. Allow CORS for the gateway
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 3. Handle Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 4. Load the core application dependencies needed to save data
require_once __DIR__ . '/../../../../bootstrap/middleware/cors.php';
require_once __DIR__ . '/../../../../database/database.php';
require_once __DIR__ . '/../../../../app/controller/CaneController.php';

// 5. Initialize the controller and process the POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $controller = new CaneController();
    $controller->updateLocation();
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
}

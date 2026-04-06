<?php
/**
 * SmartPath Cane - Backend API Entry Point
 * Hostinger Deployment | Supabase Database
 */

// Prevent HTML error output - always return JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../bootstrap/middleware/cors.php';
handleCORS();

// Set JSON headers AFTER CORS
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Custom error handler to return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server Error',
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Exception',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

// Load database
require_once __DIR__ . '/../database/database.php';

// Load controllers
require_once __DIR__ . '/../app/Auth/AuthController.php';
require_once __DIR__ . '/../app/controller/DeviceController.php';
require_once __DIR__ . '/../app/controller/AlertController.php';
require_once __DIR__ . '/../app/controller/CaneController.php';

// Get request info
$method = $_SERVER['REQUEST_METHOD'];

// Check for path in query string (for index.php?path=/api/auth/login)
if (isset($_GET['path']) && !empty($_GET['path'])) {
    $path = $_GET['path'];
} else {
    // Normal path parsing
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    // Remove script name if present (e.g., /public/index.php)
    if (strpos($uri, '/index.php') !== false) {
        $uri = substr($uri, 0, strpos($uri, '/index.php'));
    }
    
    // Remove the base path to get the route
    $basePath = '/smartpathcane/backend-spc/public';
    if (strpos($uri, $basePath) === 0) {
        $path = substr($uri, strlen($basePath));
    } else {
        // For production (Hostinger) - check if path contains backend-spc
        $prodBasePath = '/backend-spc/public';
        if (strpos($uri, $prodBasePath) === 0) {
            $path = substr($uri, strlen($prodBasePath));
        } elseif (strpos($uri, '/public/') === 0) {
            $path = substr($uri, strlen('/public'));
        } elseif ($uri === '/public') {
            $path = '/';
        } else {
            $path = $uri;
        }
    }
}

// Ensure path starts with /
if (empty($path) || $path === '/index.php' || $path === '/public/index.php') {
    $path = '/';
}

// Debug logging for cane requests
if (strpos($path, '/cane') !== false) {
    error_log('CANE DEBUG - URI: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
    error_log('CANE DEBUG - Parsed path: ' . $path);
    error_log('CANE DEBUG - Method: ' . $method);
}

// Initialize controllers
$authController = new AuthController();
$deviceController = new DeviceController();
$alertController = new AlertController();
$caneController = new CaneController();

// Router
switch (true) {
    // API Info - Hide endpoints in production
    case $path === '/' || $path === '/api':
        $isProduction = ($env['APP_ENV'] ?? 'unknown') === 'production';
        
        $response = [
            'name' => 'SmartPath Cane API',
            'version' => '1.0.0',
            'status' => 'running',
            'timestamp' => date('c'),
            'maintenance' => ($env['MAINTENANCE_MODE'] ?? false) === true,
        ];
        
        // Only show endpoints in development
        if (!$isProduction) {
            $response['endpoints'] = [
                'auth' => [
                    'POST /api/auth/register' => 'User registration',
                    'POST /api/auth/login' => 'User login',
                    'GET /api/auth/me' => 'Get current user',
                    'POST /api/auth/logout' => 'User logout'
                ],
                'devices' => [
                    'GET /api/devices' => 'List devices',
                    'POST /api/devices' => 'Create device',
                    'GET /api/devices/{id}' => 'Get device',
                    'PUT /api/devices/{id}' => 'Update device',
                    'DELETE /api/devices/{id}' => 'Delete device',
                    'GET /api/devices/{id}/history' => 'Location history'
                ],
                'alerts' => [
                    'GET /api/alerts' => 'List alerts',
                    'POST /api/alerts' => 'Create alert',
                    'GET /api/alerts/{id}' => 'Get alert',
                    'PUT /api/alerts/{id}/acknowledge' => 'Acknowledge alert',
                    'PUT /api/alerts/{id}/resolve' => 'Resolve alert'
                ]
            ];
        }
        
        jsonResponse($response);
        break;

    // Public app status (for frontend maintenance gate; no auth)
    case $path === '/api/status' && $method === 'GET':
        jsonResponse([
            'maintenance' => ($env['MAINTENANCE_MODE'] ?? false) === true,
            'message' => (string)($env['MAINTENANCE_MESSAGE'] ?? 'We are temporarily unavailable. Please try again later.'),
        ]);
        break;
        
    // Auth Routes
    case $path === '/api/auth/register' && $method === 'POST':
        $authController->register();
        break;
        
    case $path === '/api/auth/login' && $method === 'POST':
        $authController->login();
        break;
        
    case $path === '/api/auth/me' && $method === 'GET':
        $authController->me();
        break;
        
    case $path === '/api/auth/logout' && $method === 'POST':
        $authController->logout();
        break;
        
    // Device Routes
    case $path === '/api/devices' && $method === 'GET':
        $deviceController->index();
        break;
        
    case $path === '/api/devices' && $method === 'POST':
        $deviceController->store();
        break;
        
    case preg_match('/^\/api\/devices\/([^\/]+)$/', $path, $matches) && $method === 'GET':
        $deviceController->show($matches[1]);
        break;
        
    case preg_match('/^\/api\/devices\/([^\/]+)$/', $path, $matches) && $method === 'PUT':
        $deviceController->update($matches[1]);
        break;
        
    case preg_match('/^\/api\/devices\/([^\/]+)$/', $path, $matches) && $method === 'DELETE':
        $deviceController->destroy($matches[1]);
        break;
        
    case preg_match('/^\/api\/devices\/([^\/]+)\/history$/', $path, $matches) && $method === 'GET':
        $deviceController->locationHistory($matches[1]);
        break;
        
    // Alert Routes
    case $path === '/api/alerts' && $method === 'GET':
        $alertController->index();
        break;
        
    case $path === '/api/alerts' && $method === 'POST':
        $alertController->store();
        break;
        
    case preg_match('/^\/api\/alerts\/([^\/]+)$/', $path, $matches) && $method === 'GET':
        $alertController->show($matches[1]);
        break;
        
    case preg_match('/^\/api\/alerts\/([^\/]+)\/acknowledge$/', $path, $matches) && $method === 'PUT':
        $alertController->acknowledge($matches[1]);
        break;
        
    case preg_match('/^\/api\/alerts\/([^\/]+)\/resolve$/', $path, $matches) && $method === 'PUT':
        $alertController->resolve($matches[1]);
        break;
        
    // Cane Device Routes (for physical cane)
    case $path === '/api/cane/location' && $method === 'POST':
        $caneController->updateLocation();
        break;
        
    case $path === '/api/cane/sos' && $method === 'POST':
        $caneController->sendSOS();
        break;
        
    case preg_match('/^\/api\/cane\/location\/([^\/]+)$/', $path, $matches) && $method === 'GET':
        $caneController->getLiveLocation($matches[1]);
        break;
        
    case $path === '/api/cane/devices' && $method === 'GET':
        $caneController->getUserDevicesWithLocation();
        break;
        
    // Test Route
    case $path === '/api/test':
        try {
            $result = Database::from('users')->limit(1)->execute();
            jsonResponse([
                'status' => 'success',
                'message' => 'Supabase connection working',
                'data' => $result['data'] ?? null
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'status' => 'error',
                'message' => 'Supabase connection failed',
                'error' => $e->getMessage()
            ], 500);
        }
        break;
        
    // Debug Route for Cane
    case $path === '/api/cane/debug':
        try {
            // Test device lookup
            $device = Database::from('devices')
                ->eq('device_serial', 'SPC-DEV-001')
                ->single();
            
            jsonResponse([
                'status' => 'success',
                'message' => 'Cane debug endpoint working',
                'device_lookup' => $device,
                'php_version' => PHP_VERSION,
                'env_loaded' => isset($env)
            ]);
        } catch (Exception $e) {
            jsonResponse([
                'status' => 'error',
                'message' => 'Cane debug failed',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
        break;
        
    // 404 Not Found
    default:
        http_response_code(404);
        jsonResponse(['error' => 'Endpoint not found', 'path' => $path]);
}

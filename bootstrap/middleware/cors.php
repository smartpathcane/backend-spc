<?php
/**
 * CORS Middleware
 * SmartPath Cane - Handles Cross-Origin Resource Sharing
 * Backend: Hostinger | Frontend: Netlify
 */

// Load environment
$env = require __DIR__ . '/../config/env.php';

/**
 * Handle CORS headers
 */
function handleCORS(): void {
    global $env;
    
    $allowedOrigins = explode(',', $env['CORS_ALLOWED_ORIGINS']);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Debug logging (remove in production)
    error_log("CORS Debug - Origin: " . $origin);
    error_log("CORS Debug - Allowed: " . json_encode($allowedOrigins));
    
    // Check if origin is allowed
    if (in_array('*', $allowedOrigins)) {
        header('Access-Control-Allow-Origin: *');
    } elseif (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
    } else {
        // Allow the specific Netlify origin for now
        header('Access-Control-Allow-Origin: https://smartpath-cane.netlify.app');
        header('Access-Control-Allow-Credentials: true');
    }
    
    // Set CORS headers
    header('Access-Control-Allow-Methods: ' . $env['CORS_ALLOWED_METHODS']);
    header('Access-Control-Allow-Headers: ' . $env['CORS_ALLOWED_HEADERS']);
    header('Access-Control-Max-Age: 86400'); // 24 hours cache
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Send JSON response with proper headers
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Send error response
 */
function errorResponse(string $message, int $statusCode = 400): void {
    jsonResponse(['error' => $message, 'success' => false], $statusCode);
}

/**
 * Send success response
 */
function successResponse(array $data = [], string $message = 'Success'): void {
    jsonResponse(['data' => $data, 'message' => $message, 'success' => true]);
}

// Apply CORS headers
handleCORS();

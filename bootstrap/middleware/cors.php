<?php
/**
 * CORS Middleware
 * SmartPath Cane - Handles Cross-Origin Resource Sharing
 * Backend: Hostinger | Frontend: Netlify
 */

// Load environment
$env = require __DIR__ . '/../config/env.php';

/**
 * True if Origin is any https site hosted on Netlify (*.netlify.app).
 */
function cors_is_netlify_origin(string $origin): bool {
    return (bool) preg_match('#^https://[a-z0-9][a-z0-9.\-]*\.netlify\.app$#i', $origin);
}

/**
 * Handle CORS headers
 */
function handleCORS(): void {
    global $env;
    
    $allowedOrigins = array_map('trim', explode(',', $env['CORS_ALLOWED_ORIGINS'] ?? '*'));
    $fe = trim((string)($env['FRONTEND_URL'] ?? ''));
    if ($fe !== '' && ($p = parse_url($fe)) && !empty($p['scheme']) && !empty($p['host'])) {
        $feOrigin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if ($feOrigin !== '://' && !in_array($feOrigin, $allowedOrigins, true)) {
            $allowedOrigins[] = $feOrigin;
        }
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    $allow = false;
    if (in_array('*', $allowedOrigins, true)) {
        $allow = true;
    } elseif ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        $allow = true;
    } elseif ($origin !== '' && cors_is_netlify_origin($origin)) {
        $allow = true;
    }
    
    if ($allow && $origin !== '') {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
    } elseif ($allow && $origin === '' && in_array('*', $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: *');
    } elseif ($origin !== '') {
        error_log('CORS rejected origin: ' . $origin);
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

// CORS is applied by calling handleCORS() from public/index.php (single place, no duplicate headers).

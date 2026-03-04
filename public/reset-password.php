<?php
/**
 * Password Reset Script
 * For development use only - reset a user's password
 */

require_once __DIR__ . '/../database/database.php';

header('Content-Type: application/json');

// Only allow in development
$env = require __DIR__ . '/../bootstrap/config/env.php';
if ($env['APP_ENV'] !== 'development') {
    http_response_code(403);
    echo json_encode(['error' => 'Only available in development']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$newPassword = $input['password'] ?? '';

if (empty($email) || empty($newPassword)) {
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

// Hash new password
$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

// Update user
$result = Database::update('users', 
    ['password_hash' => $passwordHash],
    'email=eq.' . $email
);

if ($result['status'] < 400) {
    echo json_encode(['success' => true, 'message' => 'Password updated']);
} else {
    echo json_encode(['error' => 'Failed to update password', 'details' => $result]);
}

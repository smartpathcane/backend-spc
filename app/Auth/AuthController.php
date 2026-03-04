<?php
/**
 * Authentication Controller
 * SmartPath Cane - Handles user login, register, logout
 */

require_once __DIR__ . '/../../database/database.php';

class AuthController {
    
    /**
     * User registration
     */
    public function register(): void {
        try {
            // Get request body
            $input = $this->getInput();
            
            // Validate input
            if (empty($input['email']) || empty($input['password'])) {
                errorResponse('Email and password are required', 400);
            }
            
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                errorResponse('Invalid email format', 400);
            }
            
            if (strlen($input['password']) < 6) {
                errorResponse('Password must be at least 6 characters', 400);
            }
            
            // Check if user exists
            $existing = Database::from('users')
                ->eq('email', $input['email'])
                ->single();
                
            if (!empty($existing['data'])) {
                errorResponse('Email already registered', 409);
            }
            
            // Create user in Supabase Auth (handles password storage)
            $metadata = [
                'full_name' => $input['fullname'] ?? '',
                'role' => 'user'
            ];
            
            $authResult = Database::authSignUp($input['email'], $input['password'], $metadata);
            
            if ($authResult['status'] >= 400) {
                $errorMsg = $authResult['data']['error_description'] ?? $authResult['data']['message'] ?? json_encode($authResult['data']) ?? 'Registration failed';
                errorResponse($errorMsg, 400);
            }
            
            $supabaseUid = $authResult['data']['user']['id'] ?? null;
            
            // Create user in database (NO password_hash - Supabase Auth handles it)
            $userData = [
                'auth_uid' => $supabaseUid,
                'username' => $this->generateUsername($input['email']),
                'email' => $input['email'],
                'first_name' => $input['fullname'] ?? '',
                'role' => 'user',
                'status' => 'active'
            ];
            
            $result = Database::insert('users', $userData);
            
            if ($result['status'] >= 400) {
                errorResponse('Failed to create user profile: ' . json_encode($result['data']), 500);
            }
            
            // Generate JWT
            $token = $this->generateJWT($result['data'][0]['id'] ?? null, $input['email']);
            
            successResponse([
                'user' => [
                    'id' => $result['data'][0]['id'] ?? null,
                    'email' => $input['email'],
                    'fullname' => $input['fullname'] ?? ''
                ],
                'token' => $token
            ], 'Registration successful');
            
        } catch (Exception $e) {
            errorResponse('Registration error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * User login
     */
    public function login(): void {
        try {
            $input = $this->getInput();
            
            // Validate input
            if (empty($input['email']) || empty($input['password'])) {
                errorResponse('Email and password are required', 400);
            }
            
            // Authenticate with Supabase Auth
            $authResult = Database::authSignIn($input['email'], $input['password']);
            
            if ($authResult['status'] >= 400) {
                errorResponse('Invalid credentials', 401);
            }
            
            $supabaseUid = $authResult['data']['user']['id'] ?? null;
            
            // Get user from database using auth_uid
            $result = Database::from('users')
                ->eq('auth_uid', $supabaseUid)
                ->single();
            
            if (empty($result['data'])) {
                errorResponse('User profile not found', 404);
            }
            
            $user = $result['data'];
            
            // Update last login
            Database::update('users', 
                ['last_login_at' => date('c')], 
                'id=eq.' . $user['id']
            );
            
            // Generate JWT
            $token = $this->generateJWT($user['id'], $user['email']);
            
            successResponse([
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'username' => $user['username'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role']
                ],
                'token' => $token
            ], 'Login successful');
            
        } catch (Exception $e) {
            errorResponse('Login error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get current user
     */
    public function me(): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            $result = Database::from('users')
                ->eq('id', $userId)
                ->single();
            
            if (empty($result['data'])) {
                errorResponse('User not found', 404);
            }
            
            $user = $result['data'];
            
            successResponse([
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role'],
                'status' => $user['status']
            ]);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * User logout
     */
    public function logout(): void {
        // JWT is stateless, client just needs to delete token
        successResponse([], 'Logout successful');
    }
    
    /**
     * Get input data from request
     */
    private function getInput(): array {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }
    
    /**
     * Generate JWT token
     */
    private function generateJWT($userId, $email): string {
        $env = require __DIR__ . '/../../bootstrap/config/env.php';
        
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $time = time();
        $payload = json_encode([
            'iss' => $env['APP_NAME'],
            'iat' => $time,
            'exp' => $time + ($env['JWT_EXPIRATION'] ?? 86400),
            'sub' => $userId,
            'email' => $email
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $env['JWT_SECRET'], true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Get authenticated user ID from JWT
     */
    private function getAuthUserId() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        if (!$payload || $payload['exp'] < time()) {
            return null;
        }
        
        return $payload['sub'] ?? null;
    }
    
    /**
     * Generate username from email
     */
    private function generateUsername($email): string {
        $base = explode('@', $email)[0];
        $random = rand(100, 999);
        return $base . $random;
    }
}

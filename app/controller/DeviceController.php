<?php
/**
 * Device Controller
 * SmartPath Cane - Handles device CRUD operations
 */

require_once __DIR__ . '/../../database/database.php';

class DeviceController {
    
    /**
     * Get all devices for authenticated user
     */
    public function index(): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            $result = Database::from('devices')
                ->eq('user_id', $userId)
                ->execute();
            
            successResponse($result['data'] ?? []);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single device
     */
    public function show($id): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            $result = Database::from('devices')
                ->eq('id', $id)
                ->eq('user_id', $userId)
                ->single();
            
            if (empty($result['data'])) {
                errorResponse('Device not found', 404);
            }
            
            successResponse($result['data']);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create new device
     */
    public function store(): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            $input = $this->getInput();
            
            // Validate
            if (empty($input['device_serial'])) {
                errorResponse('Device serial is required', 400);
            }
            
            // Check if serial exists
            $existing = Database::from('devices')
                ->eq('device_serial', $input['device_serial'])
                ->single();
                
            if (!empty($existing['data'])) {
                // If device exists but has no user_id, assign it to current user
                if (empty($existing['data']['user_id'])) {
                    $updateResult = Database::update('devices', [
                        'user_id' => $userId,
                        'device_name' => $input['device_name'] ?? $existing['data']['device_name'],
                        'device_model' => $input['device_model'] ?? $existing['data']['device_model'],
                        'updated_at' => date('c')
                    ], 'id=eq.' . $existing['data']['id']);
                    
                    if ($updateResult['status'] >= 400) {
                        errorResponse('Failed to claim device', 500);
                    }
                    
                    // Return updated device
                    $updated = Database::from('devices')
                        ->eq('id', $existing['data']['id'])
                        ->single();
                    
                    successResponse($updated['data'], 'Device claimed successfully');
                    return;
                }
                
                // If device already has a user, check if it's the current user
                if ($existing['data']['user_id'] === $userId) {
                    errorResponse('Device already registered to your account', 409);
                } else {
                    errorResponse('Device serial already registered to another user', 409);
                }
            }
            
            $deviceData = [
                'device_serial' => $input['device_serial'],
                'device_name' => $input['device_name'] ?? null,
                'device_model' => $input['device_model'] ?? 'SPC-001',
                'user_id' => $userId,
                'status' => 'active',
                'firmware_version' => $input['firmware_version'] ?? null,
                'battery_level' => $input['battery_level'] ?? 100
            ];
            
            $result = Database::insert('devices', $deviceData);
            
            if ($result['status'] >= 400) {
                errorResponse('Failed to create device', 500);
            }
            
            successResponse($result['data'][0] ?? $result['data'], 'Device created successfully', 201);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update device
     */
    public function update($id): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            // Check ownership
            $existing = Database::from('devices')
                ->eq('id', $id)
                ->eq('user_id', $userId)
                ->single();
                
            if (empty($existing['data'])) {
                errorResponse('Device not found', 404);
            }
            
            $input = $this->getInput();
            
            $updateData = [
                'device_name' => $input['device_name'] ?? null,
                'status' => $input['status'] ?? null,
                'firmware_version' => $input['firmware_version'] ?? null,
                'battery_level' => $input['battery_level'] ?? null,
                'last_location_lat' => $input['last_location_lat'] ?? null,
                'last_location_lng' => $input['last_location_lng'] ?? null,
                'updated_at' => date('c')
            ];
            
            // Remove null values
            $updateData = array_filter($updateData, fn($v) => $v !== null);
            
            $result = Database::update('devices', $updateData, 'id=eq.' . $id);
            
            successResponse($result['data'][0] ?? $result['data'], 'Device updated successfully');
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete device
     */
    public function destroy($id): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            // Check ownership
            $existing = Database::from('devices')
                ->eq('id', $id)
                ->eq('user_id', $userId)
                ->single();
                
            if (empty($existing['data'])) {
                errorResponse('Device not found', 404);
            }
            
            Database::delete('devices', 'id=eq.' . $id);
            
            successResponse([], 'Device deleted successfully');
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get device location history
     */
    public function locationHistory($id): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            // Check ownership
            $device = Database::from('devices')
                ->eq('id', $id)
                ->eq('user_id', $userId)
                ->single();
                
            if (empty($device['data'])) {
                errorResponse('Device not found', 404);
            }
            
            $result = Database::from('locations')
                ->eq('device_id', $id)
                ->order('recorded_at', 'desc')
                ->limit(100)
                ->execute();
            
            successResponse($result['data'] ?? []);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get input data
     */
    private function getInput(): array {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }
    
    /**
     * Get authenticated user ID
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
        
        $env = require __DIR__ . '/../../bootstrap/config/env.php';
        
        // Verify signature
        $signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], $env['JWT_SECRET'], true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if (!hash_equals($base64Signature, $parts[2])) {
            return null;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        if (!$payload || $payload['exp'] < time()) {
            return null;
        }
        
        return $payload['sub'] ?? null;
    }
}

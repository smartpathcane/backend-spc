<?php
/**
 * Cane Controller
 * SmartPath Cane - Handles device location updates and SOS from physical cane
 */

require_once __DIR__ . '/../../database/database.php';

class CaneController {
    
    /**
     * Update or insert device location (UPSERT - one row per device)
     */
    public function updateLocation(): void {
        try {
            $input = $this->getInput();
            
            // Debug log
            error_log('Cane updateLocation called with: ' . json_encode($input));
            
            // Validate required fields
            if (empty($input['device_serial']) || !isset($input['latitude']) || !isset($input['longitude'])) {
                error_log('Cane validation failed: missing fields');
                errorResponse('Device serial, latitude and longitude are required', 400);
                return;
            }
            
            // Find or create device
            $device = Database::from('devices')
                ->eq('device_serial', $input['device_serial'])
                ->single();
            
            error_log('Device lookup result: ' . json_encode($device));
            
            $deviceId = null;
            
            if (empty($device['data']) || (is_array($device['data']) && empty($device['data']['id']))) {
                // Auto-create device if not exists
                $deviceData = [
                    'device_serial' => $input['device_serial'],
                    'device_name' => $input['device_name'] ?? 'Auto-registered Cane',
                    'device_model' => $input['device_model'] ?? 'SPC-001',
                    'status' => 'active',
                    'battery_level' => $input['battery_level'] ?? 100,
                    'registered_at' => date('c'),
                    'created_at' => date('c'),
                    'updated_at' => date('c')
                ];
                
                // Try to get user ID from auth header if available
                $userId = $this->getAuthUserId();
                if ($userId) {
                    $deviceData['user_id'] = $userId;
                }
                
                $result = Database::insert('devices', $deviceData);
                
                error_log('Device insert result: ' . json_encode($result));
                
                if ($result['status'] >= 400) {
                    error_log('Cane device insert failed: ' . json_encode($result));
                    errorResponse('Failed to auto-register device: ' . ($result['data']['message'] ?? $result['error'] ?? 'Unknown error'), 500);
                    return;
                }
                
                $deviceId = $result['data'][0]['id'] ?? $result['data']['id'] ?? null;
            } else {
                $deviceId = $device['data']['id'];
                
                // Update device battery and last location
                Database::update('devices', [
                    'battery_level' => $input['battery_level'] ?? $device['data']['battery_level'],
                    'last_location_lat' => $input['latitude'],
                    'last_location_lng' => $input['longitude'],
                    'last_location_at' => date('c'),
                    'last_connected_at' => date('c')
                ], 'id=eq.' . $deviceId);
            }
            
            // UPSERT location - insert or update on conflict
            // This ensures only ONE row per device (live tracking)
            try {
                $locationData = [
                    'device_id' => $deviceId,
                    'latitude' => $input['latitude'],
                    'longitude' => $input['longitude'],
                    'accuracy' => $input['accuracy'] ?? null,
                    'altitude' => $input['altitude'] ?? null,
                    'speed' => $input['speed'] ?? null,
                    'recorded_at' => date('c')
                ];
                
                // Use upsert to handle race conditions
                $result = Database::getClient()->upsert('live_locations', $locationData, 'device_id');
                
                if ($result['status'] >= 400) {
                    error_log('Live location upsert failed: ' . json_encode($result));
                } else {
                    error_log('Live location upsert success: ' . json_encode($result['data'] ?? 'OK'));
                }
            } catch (Exception $e) {
                error_log('Live location error: ' . $e->getMessage());
                // Continue - live_locations table might not exist
            }
            
            // Also save to history table for tracking
            $historyData = [
                'device_id' => $deviceId,
                'latitude' => $input['latitude'],
                'longitude' => $input['longitude'],
                'accuracy' => $input['accuracy'] ?? null,
                'altitude' => $input['altitude'] ?? null,
                'speed' => $input['speed'] ?? null
            ];
            
            try {
                Database::insert('locations', $historyData);
            } catch (Exception $e) {
                error_log('Location history insert error: ' . $e->getMessage());
            }
            
            successResponse([
                'device_id' => $deviceId,
                'latitude' => $input['latitude'],
                'longitude' => $input['longitude']
            ], 'Location updated');
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Send SOS Alert
     */
    public function sendSOS(): void {
        try {
            $input = $this->getInput();
            
            if (empty($input['device_serial']) || !isset($input['latitude']) || !isset($input['longitude'])) {
                errorResponse('Device serial and location are required', 400);
                return;
            }
            
            // Find device
            $device = Database::from('devices')
                ->eq('device_serial', $input['device_serial'])
                ->single();
            
            if (empty($device['data'])) {
                errorResponse('Device not found', 404);
                return;
            }
            
            $deviceId = $device['data']['id'];
            $userId = $device['data']['user_id'];
            
            // If device is not associated with a user, try to get user from auth header
            if (!$userId) {
                $authenticatedUserId = $this->getAuthUserId();
                if ($authenticatedUserId) {
                    $userId = $authenticatedUserId;
                    // Update the device to associate it with the user
                    Database::update('devices', ['user_id' => $userId], 'id=eq.' . $deviceId);
                }
            }
            
            // Create SOS alert
            $alertData = [
                'device_id' => $deviceId,
                'user_id' => $userId,
                'alert_type' => 'sos',
                'message' => $input['message'] ?? 'SOS Button Pressed',
                'latitude' => $input['latitude'],
                'longitude' => $input['longitude'],
                'status' => 'pending',
                'created_at' => date('c')
            ];
            
            error_log('SOS Alert Data: ' . json_encode($alertData));
            $result = Database::insert('sos_alerts', $alertData);
            error_log('SOS Insert Result: ' . json_encode($result));
            
            if ($result['status'] >= 400) {
                error_log('SOS Insert Error: ' . ($result['error'] ?? 'Unknown error'));
                errorResponse('Failed to create alert', 500);
                return;
            }
            
            successResponse($result['data'][0] ?? $result['data'], 'SOS Alert sent successfully', 201);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get live location for a device
     */
    public function getLiveLocation($deviceSerial): void {
        try {
            $device = Database::from('devices')
                ->eq('device_serial', $deviceSerial)
                ->single();
            
            if (empty($device['data'])) {
                errorResponse('Device not found', 404);
                return;
            }
            
            $location = Database::from('live_locations')
                ->eq('device_id', $device['data']['id'])
                ->single();
            
            if (empty($location['data'])) {
                errorResponse('No location data', 404);
                return;
            }
            
            successResponse([
                'device' => $device['data'],
                'location' => $location['data']
            ]);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get all devices with live locations for current user
     */
    public function getUserDevicesWithLocation(): void {
        try {
            error_log('getUserDevicesWithLocation called');
            
            // Get current user from session/token
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';
            $userId = null;
            
            // For now, return all devices with live locations
            // In production, filter by user_id
            $devices = Database::from('devices')
                ->eq('status', 'active')
                ->execute();
            
            error_log('Devices query result: ' . json_encode($devices));
            
            if (empty($devices['data'])) {
                successResponse([]);
                return;
            }
            
            $devicesWithLocation = [];
            foreach ($devices['data'] as $device) {
                $location = Database::from('live_locations')
                    ->eq('device_id', $device['id'])
                    ->single();
                
                $devicesWithLocation[] = [
                    'device' => $device,
                    'location' => $location['data'] ?? null,
                    'has_location' => !empty($location['data'])
                ];
            }
            
            successResponse($devicesWithLocation);
            
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
     * Get authenticated user ID from JWT token
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

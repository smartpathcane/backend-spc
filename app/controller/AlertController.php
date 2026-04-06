<?php
/**
 * Alert Controller
 * SmartPath Cane - Handles SOS alerts and notifications
 */

require_once __DIR__ . '/../../database/database.php';

class AlertController {
    
    /**
     * Get all alerts for user
     */
    public function index(): void {
        try {
            // Get all alerts from Supabase (bypassing user filter for now)
            // TODO: Implement proper user-to-Supabase-UUID mapping
            $result = Database::from('sos_alerts')
                ->order('created_at', 'desc')
                ->execute();
            
            $alerts = $result['data'] ?? [];
            
            successResponse($alerts);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get single alert
     */
    public function show($id): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            $result = Database::from('sos_alerts')
                ->eq('id', $id)
                ->single();
            
            if (empty($result['data'])) {
                errorResponse('Alert not found', 404);
            }
            
            // Verify user owns the device
            $alert = $result['data'];
            $device = Database::from('devices')
                ->eq('id', $alert['device_id'])
                ->eq('user_id', $userId)
                ->single();
                
            if (empty($device['data'])) {
                errorResponse('Unauthorized', 403);
            }
            
            successResponse($alert);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Create new alert (from device)
     */
    public function store(): void {
        try {
            $input = $this->getInput();
            
            // Validate
            if (empty($input['device_id'])) {
                errorResponse('Device ID is required', 400);
            }
            
            // Get device info
            $device = Database::from('devices')
                ->eq('id', $input['device_id'])
                ->single();
                
            if (empty($device['data'])) {
                errorResponse('Device not found', 404);
            }
            
            $alertData = [
                'device_id' => $input['device_id'],
                'alert_type' => $input['alert_type'] ?? 'sos',
                'status' => 'active',
                'sensor_data' => $input['sensor_data'] ?? null
            ];
            
            $result = Database::insert('sos_alerts', $alertData);
            
            if ($result['status'] >= 400) {
                errorResponse('Failed to create alert', 500);
            }
            
            successResponse($result['data'][0] ?? $result['data'], 'Alert created successfully', 201);
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Acknowledge alert
     */
    public function acknowledge($id): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            // Get alert
            $alert = Database::from('sos_alerts')
                ->eq('id', $id)
                ->single();
                
            if (empty($alert['data'])) {
                errorResponse('Alert not found', 404);
            }
            
            // Verify ownership
            $device = Database::from('devices')
                ->eq('id', $alert['data']['device_id'])
                ->eq('user_id', $userId)
                ->single();
                
            if (empty($device['data'])) {
                errorResponse('Unauthorized', 403);
            }
            
            $updateData = [
                'status' => 'acknowledged',
                'acknowledged_by' => $userId,
                'acknowledged_at' => date('c'),
                'updated_at' => date('c')
            ];
            
            $result = Database::update('sos_alerts', $updateData, 'id=eq.' . $id);
            
            successResponse($result['data'][0] ?? $result['data'], 'Alert acknowledged');
            
        } catch (Exception $e) {
            errorResponse('Error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Resolve alert
     */
    public function resolve($id): void {
        try {
            $userId = $this->getAuthUserId();
            
            if (!$userId) {
                errorResponse('Unauthorized', 401);
            }
            
            // Get alert
            $alert = Database::from('sos_alerts')
                ->eq('id', $id)
                ->single();
                
            if (empty($alert['data'])) {
                errorResponse('Alert not found', 404);
            }
            
            // Verify ownership
            $device = Database::from('devices')
                ->eq('id', $alert['data']['device_id'])
                ->eq('user_id', $userId)
                ->single();
                
            if (empty($device['data'])) {
                errorResponse('Unauthorized', 403);
            }
            
            $updateData = [
                'status' => 'resolved',
                'resolved_at' => date('Y-m-d H:i:s')
            ];
            
            $result = Database::update('sos_alerts', $updateData, 'id=eq.' . $id);
            
            successResponse($result['data'][0] ?? $result['data'], 'Alert resolved');
            
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

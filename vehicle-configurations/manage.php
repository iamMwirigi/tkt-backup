<?php
require_once '../config/database.php';
require_once '../utils/response.php';

header('Content-Type: application/json');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle different request methods
switch ($method) {
    case 'GET':
        // Get all configurations
        $stmt = $conn->query("SELECT * FROM vehicle_configurations");
        $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, [
            'error' => false,
            'data' => $configurations
        ]);
        break;
        
    case 'POST':
        // Get request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendResponse(400, [
                'error' => true,
                'message' => 'Invalid JSON data'
            ]);
        }
        
        // Create new configuration
        validateRequiredFields([
            'company_id',
            'vehicle_type',
            'total_seats',
            'row_count',
            'column_count',
            'layout'
        ], $data);
        
        // Validate layout JSON
        if (!is_array($data['layout'])) {
            sendResponse(400, [
                'error' => true,
                'message' => 'layout must be a valid JSON array'
            ]);
        }
        
        // Check if configuration exists for this vehicle type and company
        $stmt = $conn->prepare("SELECT id FROM vehicle_configurations WHERE vehicle_type = ? AND company_id = ?");
        $stmt->execute([$data['vehicle_type'], $data['company_id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing configuration
            $stmt = $conn->prepare("
                UPDATE vehicle_configurations 
                SET total_seats = ?,
                    row_count = ?,
                    column_count = ?,
                    layout = ?
                WHERE vehicle_type = ? AND company_id = ?
            ");
            
            $stmt->execute([
                $data['total_seats'],
                $data['row_count'],
                $data['column_count'],
                json_encode($data['layout']),
                $data['vehicle_type'],
                $data['company_id']
            ]);

            sendResponse(200, [
                'error' => false,
                'message' => 'Vehicle configuration updated successfully',
                'id' => $existing['id']
            ]);
        } else {
            // Create new configuration
            $stmt = $conn->prepare("
                INSERT INTO vehicle_configurations (
                    company_id,
                    vehicle_type,
                    total_seats,
                    row_count,
                    column_count,
                    layout
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['company_id'],
                $data['vehicle_type'],
                $data['total_seats'],
                $data['row_count'],
                $data['column_count'],
                json_encode($data['layout'])
            ]);

            sendResponse(201, [
                'error' => false,
                'message' => 'Vehicle configuration created successfully',
                'id' => $conn->lastInsertId()
            ]);
        }
        break;
        
    case 'PUT':
        // Get request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendResponse(400, [
                'error' => true,
                'message' => 'Invalid JSON data'
            ]);
        }
        
        // Update configuration
        validateRequiredFields(['id', 'company_id'], $data);
        
        $stmt = $conn->prepare("
            UPDATE vehicle_configurations 
            SET vehicle_type = ?,
                total_seats = ?,
                row_count = ?,
                column_count = ?,
                layout = ?
            WHERE id = ? AND company_id = ?
        ");
        
        $stmt->execute([
            $data['vehicle_type'],
            $data['total_seats'],
            $data['row_count'],
            $data['column_count'],
            json_encode($data['layout']),
            $data['id'],
            $data['company_id']
        ]);
        
        sendResponse(200, [
            'error' => false,
            'message' => 'Vehicle configuration updated successfully'
        ]);
        break;
        
    case 'DELETE':
        // Get configuration ID from query string
        $id = $_GET['id'] ?? null;
        $company_id = $_GET['company_id'] ?? null;
        
        if (!$id || !$company_id) {
            sendResponse(400, [
                'error' => true,
                'message' => 'Configuration ID and company_id are required'
            ]);
        }
        
        // Delete configuration
        $stmt = $conn->prepare("DELETE FROM vehicle_configurations WHERE id = ? AND company_id = ?");
        $stmt->execute([$id, $company_id]);
        
        sendResponse(200, [
            'error' => false,
            'message' => 'Vehicle configuration deleted successfully'
        ]);
        break;
        
    default:
        sendResponse(405, [
            'error' => true,
            'message' => 'Method not allowed'
        ]);
        break;
}

function validateRequiredFields($requiredFields, $data) {
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(400, [
                'error' => true,
                'message' => "Missing or empty required field: $field"
            ]);
        }
    }
} 
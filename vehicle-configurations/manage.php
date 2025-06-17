<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate company_id
    if (!isset($data['company_id'])) {
        sendResponse(400, [
            'error' => true,
            'message' => 'company_id is required'
        ]);
    }
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Create new configuration
            validateRequiredFields([
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
            
            // Check if configuration exists for this vehicle type
            $stmt = $conn->prepare("SELECT id FROM vehicle_configurations WHERE vehicle_type = ?");
            $stmt->execute([$data['vehicle_type']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing configuration
                $stmt = $conn->prepare("
                    UPDATE vehicle_configurations 
                    SET total_seats = ?,
                        row_count = ?,
                        column_count = ?,
                        layout = ?
                    WHERE vehicle_type = ?
                ");
                
                $stmt->execute([
                    $data['total_seats'],
                    $data['row_count'],
                    $data['column_count'],
                    json_encode($data['layout']),
                    $data['vehicle_type']
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
                        vehicle_type,
                        total_seats,
                        row_count,
                        column_count,
                        layout
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
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
            // Update configuration
            validateRequiredFields(['id'], $data);
            
            // Check if configuration exists
            $stmt = $conn->prepare("SELECT * FROM vehicle_configurations WHERE id = ?");
            $stmt->execute([$data['id']]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle configuration not found'
                ]);
            }
            
            // Build update query
            $updates = [];
            $params = [];
            
            $allowed_fields = [
                'vehicle_type',
                'total_seats',
                'row_count',
                'column_count',
                'layout'
            ];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'layout' && !is_array($data[$field])) {
                        sendResponse(400, [
                            'error' => true,
                            'message' => 'layout must be a valid JSON array'
                        ]);
                    }
                    $updates[] = "$field = ?";
                    $params[] = $field === 'layout' ? json_encode($data[$field]) : $data[$field];
                }
            }
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            
            // Update configuration
            $stmt = $conn->prepare("
                UPDATE vehicle_configurations 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            
            $stmt->execute($params);
            
            // Get updated configuration
            $stmt = $conn->prepare("SELECT * FROM vehicle_configurations WHERE id = ?");
            $stmt->execute([$data['id']]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle configuration updated successfully',
                'data' => [
                    'configuration' => $config
                ]
            ]);
            break;
            
        case 'DELETE':
            // Delete configuration
            validateRequiredFields(['id'], $data);
            
            // Check if configuration exists
            $stmt = $conn->prepare("SELECT * FROM vehicle_configurations WHERE id = ?");
            $stmt->execute([$data['id']]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle configuration not found'
                ]);
            }
            
            // Delete configuration
            $stmt = $conn->prepare("DELETE FROM vehicle_configurations WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle configuration deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(405, [
                'error' => true,
                'message' => 'Method not allowed'
            ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
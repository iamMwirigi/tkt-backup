<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify company exists
    $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
    $stmt->execute([$data['company_id']]);
    if (!$stmt->fetch()) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Company not found'
        ]);
    }
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Validate required fields
            $required_fields = ['company_id', 'vehicle_id', 'route', 'total_tickets', 'gross_amount', 'net_amount', 'created_by'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Verify vehicle belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM vehicles 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['vehicle_id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle not found or does not belong to company'
                ]);
            }
            
            // Verify user exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$data['created_by']]);
            if (!$stmt->fetch()) {
                // Create a default user if it doesn't exist
                $stmt = $conn->prepare("
                    INSERT INTO users (company_id, name, email, password, role) 
                    VALUES (?, 'System User', 'system@example.com', ?, 'clerk')
                ");
                $stmt->execute([
                    $data['company_id'],
                    password_hash('system123', PASSWORD_DEFAULT)
                ]);
                $data['created_by'] = $conn->lastInsertId();
            }
            
            // Insert new delivery
            $stmt = $conn->prepare("
                INSERT INTO deliveries (
                    company_id,
                    vehicle_id,
                    route,
                    total_tickets,
                    gross_amount,
                    deductions,
                    net_amount,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['company_id'],
                $data['vehicle_id'],
                $data['route'],
                $data['total_tickets'],
                $data['gross_amount'],
                json_encode($data['deductions'] ?? []),
                $data['net_amount'],
                $data['created_by']
            ]);
            
            $delivery_id = $conn->lastInsertId();
            
            // Get the created delivery
            $stmt = $conn->prepare("
                SELECT 
                    d.id,
                    d.company_id,
                    d.vehicle_id,
                    v.plate_number,
                    d.route,
                    d.total_tickets,
                    d.gross_amount,
                    d.deductions,
                    d.net_amount,
                    d.created_by,
                    u.name as created_by_name,
                    d.created_at
                FROM deliveries d
                LEFT JOIN vehicles v ON d.vehicle_id = v.id
                LEFT JOIN users u ON d.created_by = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$delivery_id]);
            $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Delivery created successfully',
                'data' => [
                    'delivery' => $delivery
                ]
            ]);
            break;
            
        case 'PUT':
            // Validate required fields
            $required_fields = ['company_id', 'id'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Check if delivery exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM deliveries 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Delivery not found or does not belong to company'
                ]);
            }
            
            // If vehicle_id is being updated, verify it belongs to company
            if (isset($data['vehicle_id'])) {
                $stmt = $conn->prepare("
                    SELECT id 
                    FROM vehicles 
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$data['vehicle_id'], $data['company_id']]);
                if (!$stmt->fetch()) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Vehicle not found or does not belong to company'
                    ]);
                }
            }
            
            // Build update query dynamically based on provided fields
            $update_fields = [];
            $params = [];
            
            $allowed_fields = [
                'vehicle_id',
                'route',
                'total_tickets',
                'gross_amount',
                'deductions',
                'net_amount'
            ];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'deductions') {
                        $update_fields[] = "$field = ?";
                        $params[] = json_encode($data[$field]);
                    } else {
                        $update_fields[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
            }
            
            if (empty($update_fields)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            // Add id and company_id to params
            $params[] = $data['id'];
            $params[] = $data['company_id'];
            
            // Update delivery
            $stmt = $conn->prepare("
                UPDATE deliveries 
                SET " . implode(', ', $update_fields) . "
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute($params);
            
            // Get the updated delivery
            $stmt = $conn->prepare("
                SELECT 
                    d.id,
                    d.company_id,
                    d.vehicle_id,
                    v.plate_number,
                    d.route,
                    d.total_tickets,
                    d.gross_amount,
                    d.deductions,
                    d.net_amount,
                    d.created_by,
                    u.name as created_by_name,
                    d.created_at
                FROM deliveries d
                LEFT JOIN vehicles v ON d.vehicle_id = v.id
                LEFT JOIN users u ON d.created_by = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$data['id']]);
            $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Delivery updated successfully',
                'data' => [
                    'delivery' => $delivery
                ]
            ]);
            break;
            
        case 'DELETE':
            // Validate required fields
            $required_fields = ['company_id', 'id'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Check if delivery exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM deliveries 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Delivery not found or does not belong to company'
                ]);
            }
            
            // Delete delivery
            $stmt = $conn->prepare("
                DELETE FROM deliveries 
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([$data['id'], $data['company_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Delivery deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(405, [
                'error' => true,
                'message' => 'Method not allowed'
            ]);
            break;
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
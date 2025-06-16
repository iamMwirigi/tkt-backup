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
            $required_fields = ['company_id', 'vehicle_id', 'seat_number', 'status'];
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
            
            // Check if seat already exists
            $stmt = $conn->prepare("
                SELECT id 
                FROM vehicle_seats 
                WHERE vehicle_id = ? AND seat_number = ?
            ");
            $stmt->execute([$data['vehicle_id'], $data['seat_number']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Seat number already exists for this vehicle'
                ]);
            }
            
            // Insert new seat
            $stmt = $conn->prepare("
                INSERT INTO vehicle_seats (
                    company_id,
                    vehicle_id,
                    seat_number,
                    status
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['company_id'],
                $data['vehicle_id'],
                $data['seat_number'],
                $data['status']
            ]);
            
            $seat_id = $conn->lastInsertId();
            
            // Get the created seat
            $stmt = $conn->prepare("
                SELECT 
                    vs.id,
                    vs.company_id,
                    vs.vehicle_id,
                    v.plate_number,
                    vs.seat_number,
                    vs.status,
                    vs.created_at
                FROM vehicle_seats vs
                LEFT JOIN vehicles v ON vs.vehicle_id = v.id
                WHERE vs.id = ?
            ");
            $stmt->execute([$seat_id]);
            $seat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Vehicle seat created successfully',
                'data' => [
                    'seat' => $seat
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
            
            // Check if seat exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM vehicle_seats 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle seat not found or does not belong to company'
                ]);
            }
            
            // Build update query dynamically based on provided fields
            $update_fields = [];
            $params = [];
            
            $allowed_fields = [
                'seat_number',
                'status'
            ];
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $update_fields[] = "$field = ?";
                    $params[] = $data[$field];
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
            
            // Update seat
            $stmt = $conn->prepare("
                UPDATE vehicle_seats 
                SET " . implode(', ', $update_fields) . "
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute($params);
            
            // Get the updated seat
            $stmt = $conn->prepare("
                SELECT 
                    vs.id,
                    vs.company_id,
                    vs.vehicle_id,
                    v.plate_number,
                    vs.seat_number,
                    vs.status,
                    vs.created_at
                FROM vehicle_seats vs
                LEFT JOIN vehicles v ON vs.vehicle_id = v.id
                WHERE vs.id = ?
            ");
            $stmt->execute([$data['id']]);
            $seat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle seat updated successfully',
                'data' => [
                    'seat' => $seat
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
            
            // Check if seat exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM vehicle_seats 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Vehicle seat not found or does not belong to company'
                ]);
            }
            
            // Delete seat
            $stmt = $conn->prepare("
                DELETE FROM vehicle_seats 
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([$data['id'], $data['company_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle seat deleted successfully'
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
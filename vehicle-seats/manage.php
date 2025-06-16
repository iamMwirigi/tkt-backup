<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Validate action
if (!isset($data['action']) || !in_array($data['action'], ['create', 'update', 'delete'])) {
    sendResponse(400, [
        'error' => true,
        'message' => 'Invalid action. Must be one of: create, update, delete'
    ]);
}

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
    
    switch ($data['action']) {
        case 'create':
            // Validate required fields
            $required_fields = ['company_id', 'vehicle_id', 'seat_number', 'position'];
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
            
            // Check if seat number already exists for this vehicle
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
                    vehicle_id,
                    seat_number,
                    position,
                    is_reserved
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['vehicle_id'],
                $data['seat_number'],
                $data['position'],
                $data['is_reserved'] ?? 0
            ]);
            
            $seat_id = $conn->lastInsertId();
            
            // Get the created seat
            $stmt = $conn->prepare("
                SELECT 
                    vs.id,
                    vs.vehicle_id,
                    v.plate_number,
                    vs.seat_number,
                    vs.position,
                    vs.is_reserved,
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
            
        case 'update':
            // Validate required fields
            $required_fields = ['company_id', 'id', 'vehicle_id', 'seat_number', 'position'];
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
            
            // Check if seat exists and belongs to vehicle
            $stmt = $conn->prepare("
                SELECT id 
                FROM vehicle_seats 
                WHERE id = ? AND vehicle_id = ?
            ");
            $stmt->execute([$data['id'], $data['vehicle_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Seat not found or does not belong to vehicle'
                ]);
            }
            
            // Check if new seat number already exists for this vehicle
            $stmt = $conn->prepare("
                SELECT id 
                FROM vehicle_seats 
                WHERE vehicle_id = ? AND seat_number = ? AND id != ?
            ");
            $stmt->execute([$data['vehicle_id'], $data['seat_number'], $data['id']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Seat number already exists for this vehicle'
                ]);
            }
            
            // Update seat
            $stmt = $conn->prepare("
                UPDATE vehicle_seats 
                SET 
                    seat_number = ?,
                    position = ?,
                    is_reserved = ?
                WHERE id = ? AND vehicle_id = ?
            ");
            
            $stmt->execute([
                $data['seat_number'],
                $data['position'],
                $data['is_reserved'] ?? 0,
                $data['id'],
                $data['vehicle_id']
            ]);
            
            // Get the updated seat
            $stmt = $conn->prepare("
                SELECT 
                    vs.id,
                    vs.vehicle_id,
                    v.plate_number,
                    vs.seat_number,
                    vs.position,
                    vs.is_reserved,
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
            
        case 'delete':
            // Validate required fields
            $required_fields = ['company_id', 'id', 'vehicle_id'];
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
            
            // Check if seat exists and belongs to vehicle
            $stmt = $conn->prepare("
                SELECT id 
                FROM vehicle_seats 
                WHERE id = ? AND vehicle_id = ?
            ");
            $stmt->execute([$data['id'], $data['vehicle_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Seat not found or does not belong to vehicle'
                ]);
            }
            
            // Delete seat
            $stmt = $conn->prepare("
                DELETE FROM vehicle_seats 
                WHERE id = ? AND vehicle_id = ?
            ");
            
            $stmt->execute([$data['id'], $data['vehicle_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Vehicle seat deleted successfully'
            ]);
            break;
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
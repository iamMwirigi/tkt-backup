<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get company_id from either GET parameters or request body
$company_id = $_GET['company_id'] ?? null;
if (!$company_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $company_id = $data['company_id'] ?? null;
}

// Validate company_id
if (!$company_id) {
    sendResponse(400, [
        'error' => true,
        'message' => 'company_id is required'
    ]);
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify company exists
    $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    if (!$stmt->fetch()) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Company not found'
        ]);
    }
    
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($method) {
        case 'POST':
            // Create new trip details
            if (!isset($data['trip_id'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'trip_id is required'
                ]);
            }
            
            // Verify trip exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['trip_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Trip not found or does not belong to your company'
                ]);
            }
            
            // Add any additional trip details
            if (isset($data['notes'])) {
                $stmt = $conn->prepare("UPDATE trips SET notes = ? WHERE id = ? AND company_id = ?");
                $stmt->execute([$data['notes'], $data['trip_id'], $company_id]);
            }
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Trip details created successfully'
            ]);
            break;
            
        case 'PUT':
            // Update trip details
            if (!isset($data['trip_id'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'trip_id is required'
                ]);
            }
            
            // Verify trip exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['trip_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Trip not found or does not belong to your company'
                ]);
            }
            
            // Update trip details
            $updates = [];
            $params = [];
            
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
            }
            
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (isset($data['arrival_time'])) {
                $updates[] = "arrival_time = ?";
                $params[] = $data['arrival_time'];
            }
            
            if (!empty($updates)) {
                $params[] = $data['trip_id'];
                $params[] = $company_id;
                
                $stmt = $conn->prepare("UPDATE trips SET " . implode(", ", $updates) . " WHERE id = ? AND company_id = ?");
                $stmt->execute($params);
            }
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Trip details updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete trip details
            if (!isset($data['trip_id'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'trip_id is required'
                ]);
            }
            
            // Verify trip exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM trips WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['trip_id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Trip not found or does not belong to your company'
                ]);
            }
            
            // Delete trip details (clear notes and other details)
            $stmt = $conn->prepare("UPDATE trips SET notes = NULL WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['trip_id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Trip details deleted successfully'
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
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
            $required_fields = ['company_id', 'office_id', 'name', 'phone'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Verify office belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM offices 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['office_id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Office not found or does not belong to company'
                ]);
            }
            
            // Insert new attendant
            $stmt = $conn->prepare("
                INSERT INTO office_attendants (
                    company_id,
                    office_id,
                    name,
                    email,
                    phone,
                    id_number,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['company_id'],
                $data['office_id'],
                $data['name'],
                $data['email'] ?? null,
                $data['phone'],
                $data['id_number'] ?? null,
                $data['status'] ?? 'active'
            ]);
            
            $attendant_id = $conn->lastInsertId();
            
            // Get the created attendant
            $stmt = $conn->prepare("
                SELECT 
                    oa.id,
                    oa.company_id,
                    c.name as company_name,
                    oa.office_id,
                    o.name as office_name,
                    oa.name,
                    oa.email,
                    oa.phone,
                    oa.id_number,
                    oa.status,
                    oa.created_at
                FROM office_attendants oa
                LEFT JOIN companies c ON oa.company_id = c.id
                LEFT JOIN offices o ON oa.office_id = o.id
                WHERE oa.id = ?
            ");
            $stmt->execute([$attendant_id]);
            $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Office attendant created successfully',
                'data' => [
                    'attendant' => $attendant
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
            
            // Check if attendant exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM office_attendants 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Office attendant not found or does not belong to company'
                ]);
            }
            
            // If office_id is being updated, verify it belongs to company
            if (isset($data['office_id'])) {
                $stmt = $conn->prepare("
                    SELECT id 
                    FROM offices 
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$data['office_id'], $data['company_id']]);
                if (!$stmt->fetch()) {
                    sendResponse(404, [
                        'error' => true,
                        'message' => 'Office not found or does not belong to company'
                    ]);
                }
            }
            
            // Build update query dynamically based on provided fields
            $update_fields = [];
            $params = [];
            
            $allowed_fields = [
                'office_id',
                'name',
                'email',
                'phone',
                'id_number',
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
            
            // Update attendant
            $stmt = $conn->prepare("
                UPDATE office_attendants 
                SET " . implode(', ', $update_fields) . "
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute($params);
            
            // Get the updated attendant
            $stmt = $conn->prepare("
                SELECT 
                    oa.id,
                    oa.company_id,
                    c.name as company_name,
                    oa.office_id,
                    o.name as office_name,
                    oa.name,
                    oa.email,
                    oa.phone,
                    oa.id_number,
                    oa.status,
                    oa.created_at
                FROM office_attendants oa
                LEFT JOIN companies c ON oa.company_id = c.id
                LEFT JOIN offices o ON oa.office_id = o.id
                WHERE oa.id = ?
            ");
            $stmt->execute([$data['id']]);
            $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Office attendant updated successfully',
                'data' => [
                    'attendant' => $attendant
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
            
            // Check if attendant exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM office_attendants 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Office attendant not found or does not belong to company'
                ]);
            }
            
            // Delete attendant
            $stmt = $conn->prepare("
                DELETE FROM office_attendants 
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([$data['id'], $data['company_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Office attendant deleted successfully'
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
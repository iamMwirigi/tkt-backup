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
            $required_fields = ['company_id', 'title', 'description', 'fine_amount'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Insert new offense
            $stmt = $conn->prepare("
                INSERT INTO offenses (
                    company_id,
                    title,
                    description,
                    fine_amount
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['company_id'],
                $data['title'],
                $data['description'],
                $data['fine_amount']
            ]);
            
            $offense_id = $conn->lastInsertId();
            
            // Get the created offense
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    company_id,
                    title,
                    description,
                    fine_amount
                FROM offenses
                WHERE id = ?
            ");
            $stmt->execute([$offense_id]);
            $offense = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Offense created successfully',
                'data' => [
                    'offense' => $offense
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
            
            // Check if offense exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM offenses 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Offense not found or does not belong to company'
                ]);
            }
            
            // Build update query dynamically based on provided fields
            $update_fields = [];
            $params = [];
            
            $allowed_fields = [
                'title',
                'description',
                'fine_amount'
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
            
            // Update offense
            $stmt = $conn->prepare("
                UPDATE offenses 
                SET " . implode(', ', $update_fields) . "
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute($params);
            
            // Get the updated offense
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    company_id,
                    title,
                    description,
                    fine_amount
                FROM offenses
                WHERE id = ?
            ");
            $stmt->execute([$data['id']]);
            $offense = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Offense updated successfully',
                'data' => [
                    'offense' => $offense
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
            
            // Check if offense exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM offenses 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Offense not found or does not belong to company'
                ]);
            }
            
            // Delete offense
            $stmt = $conn->prepare("
                DELETE FROM offenses 
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([$data['id'], $data['company_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Offense deleted successfully'
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
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
            $required_fields = ['company_id', 'title', 'description', 'fine_amount'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Validate fine_amount is numeric and positive
            if (!is_numeric($data['fine_amount']) || $data['fine_amount'] <= 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'fine_amount must be a positive number'
                ]);
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
            
        case 'update':
            // Validate required fields
            $required_fields = ['company_id', 'id', 'title', 'description', 'fine_amount'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Validate fine_amount is numeric and positive
            if (!is_numeric($data['fine_amount']) || $data['fine_amount'] <= 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'fine_amount must be a positive number'
                ]);
            }
            
            // Verify offense exists and belongs to company
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
            
            // Update offense
            $stmt = $conn->prepare("
                UPDATE offenses 
                SET 
                    title = ?,
                    description = ?,
                    fine_amount = ?
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([
                $data['title'],
                $data['description'],
                $data['fine_amount'],
                $data['id'],
                $data['company_id']
            ]);
            
            // Get the updated offense
            $stmt = $conn->prepare("
                SELECT 
                    id,
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
            
        case 'delete':
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
            
            // Verify offense exists and belongs to company
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
            
            // Check if offense is being used in any tickets
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM tickets 
                WHERE offense_id = ?
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete offense as it is being used in tickets'
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
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
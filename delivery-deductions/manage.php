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
            $required_fields = ['company_id', 'label', 'default_amount'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => "$field is required"
                    ]);
                }
            }
            
            // Check if deduction with same label exists for company
            $stmt = $conn->prepare("
                SELECT id 
                FROM delivery_deductions 
                WHERE company_id = ? AND label = ?
            ");
            $stmt->execute([$data['company_id'], $data['label']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'A deduction with this label already exists'
                ]);
            }
            
            // Insert new deduction
            $stmt = $conn->prepare("
                INSERT INTO delivery_deductions (
                    company_id,
                    label,
                    default_amount,
                    is_required
                ) VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['company_id'],
                $data['label'],
                $data['default_amount'],
                $data['is_required'] ?? 1
            ]);
            
            $deduction_id = $conn->lastInsertId();
            
            // Get the created deduction
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    company_id,
                    label,
                    default_amount,
                    is_required
                FROM delivery_deductions 
                WHERE id = ?
            ");
            $stmt->execute([$deduction_id]);
            $deduction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'Delivery deduction created successfully',
                'data' => [
                    'deduction' => $deduction
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
            
            // Check if deduction exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM delivery_deductions 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Delivery deduction not found or does not belong to company'
                ]);
            }
            
            // If label is being updated, check for duplicates
            if (isset($data['label'])) {
                $stmt = $conn->prepare("
                    SELECT id 
                    FROM delivery_deductions 
                    WHERE company_id = ? AND label = ? AND id != ?
                ");
                $stmt->execute([$data['company_id'], $data['label'], $data['id']]);
                if ($stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'A deduction with this label already exists'
                    ]);
                }
            }
            
            // Build update query dynamically based on provided fields
            $update_fields = [];
            $params = [];
            
            $allowed_fields = [
                'label',
                'default_amount',
                'is_required'
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
            
            // Update deduction
            $stmt = $conn->prepare("
                UPDATE delivery_deductions 
                SET " . implode(', ', $update_fields) . "
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute($params);
            
            // Get the updated deduction
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    company_id,
                    label,
                    default_amount,
                    is_required
                FROM delivery_deductions 
                WHERE id = ?
            ");
            $stmt->execute([$data['id']]);
            $deduction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Delivery deduction updated successfully',
                'data' => [
                    'deduction' => $deduction
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
            
            // Check if deduction exists and belongs to company
            $stmt = $conn->prepare("
                SELECT id 
                FROM delivery_deductions 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$data['id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'Delivery deduction not found or does not belong to company'
                ]);
            }
            
            // Delete deduction
            $stmt = $conn->prepare("
                DELETE FROM delivery_deductions 
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([$data['id'], $data['company_id']]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'Delivery deduction deleted successfully'
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
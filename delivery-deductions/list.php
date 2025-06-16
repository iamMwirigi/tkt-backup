<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get parameters from both GET and request body
    $request_data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Get company_id from either GET or request body
    $company_id = $_GET['company_id'] ?? $request_data['company_id'] ?? null;
    
    if (!$company_id) {
        sendResponse(400, [
            'error' => true,
            'message' => 'Company ID is required'
        ]);
    }
    
    // Verify company exists
    $stmt = $conn->prepare("SELECT id FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    if (!$stmt->fetch()) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Company not found'
        ]);
    }
    
    // Get all deductions for the company
    $stmt = $conn->prepare("
        SELECT 
            id,
            company_id,
            label,
            default_amount,
            is_required
        FROM delivery_deductions 
        WHERE company_id = ?
        ORDER BY label ASC
    ");
    
    $stmt->execute([$company_id]);
    $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(200, [
        'success' => true,
        'data' => [
            'deductions' => $deductions
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
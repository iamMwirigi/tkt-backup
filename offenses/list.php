<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get company_id from GET parameters
$company_id = $_GET['company_id'] ?? null;

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
    
    // Get all offenses for the company
    $stmt = $conn->prepare("
        SELECT 
            id,
            title,
            description,
            fine_amount
        FROM offenses 
        WHERE company_id = ?
        ORDER BY title ASC
    ");
    $stmt->execute([$company_id]);
    $offenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(200, [
        'success' => true,
        'message' => 'Offenses retrieved successfully',
        'data' => [
            'offenses' => $offenses
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
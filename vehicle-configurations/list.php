<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

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
    
    // Build query with filters
    $where = [];
    $params = [];
    
    if (isset($_GET['vehicle_type'])) {
        $where[] = 'vehicle_type = ?';
        $params[] = $_GET['vehicle_type'];
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get configurations
    $stmt = $conn->prepare("
        SELECT * FROM vehicle_configurations
        $where_clause
        ORDER BY vehicle_type ASC
    ");
    $stmt->execute($params);
    $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse layout JSON for each configuration
    foreach ($configurations as &$config) {
        $config['layout'] = json_decode($config['layout'], true);
    }
    
    sendResponse(200, [
        'success' => true,
        'message' => 'Vehicle configurations retrieved successfully',
        'data' => [
            'configurations' => $configurations
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
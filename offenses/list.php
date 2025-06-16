<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get parameters from either GET parameters or request body
$company_id = $_GET['company_id'] ?? null;
$filters = [];

// Get all possible filter parameters
$filter_fields = ['id', 'title', 'description', 'fine_amount'];
foreach ($filter_fields as $field) {
    $filters[$field] = $_GET[$field] ?? null;
}

if (!$company_id || empty(array_filter($filters))) {
    $data = json_decode(file_get_contents('php://input'), true);
    $company_id = $company_id ?? $data['company_id'] ?? null;
    
    // Get filters from request body
    foreach ($filter_fields as $field) {
        $filters[$field] = $filters[$field] ?? $data[$field] ?? null;
    }
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
    
    // Build the query with filters
    $query = "
        SELECT 
            id,
            title,
            description,
            fine_amount
        FROM offenses 
        WHERE company_id = ?
    ";
    $params = [$company_id];
    
    // Add filters to query
    foreach ($filters as $field => $value) {
        if ($value !== null) {
            if ($field === 'fine_amount') {
                $query .= " AND $field = ?";
                $params[] = $value;
            } else {
                $query .= " AND $field LIKE ?";
                $params[] = "%$value%";
            }
        }
    }
    
    $query .= " ORDER BY title ASC";
    
    // Execute query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
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
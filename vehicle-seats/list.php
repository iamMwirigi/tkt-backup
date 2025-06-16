<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get parameters from either GET parameters or request body
$company_id = $_GET['company_id'] ?? null;
$filters = [];

// Get all possible filter parameters
$filter_fields = [
    'id',
    'vehicle_id',
    'seat_number',
    'position',
    'is_reserved'
];

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
            vs.id,
            vs.vehicle_id,
            v.plate_number,
            vs.seat_number,
            vs.position,
            vs.is_reserved,
            vs.created_at
        FROM vehicle_seats vs
        LEFT JOIN vehicles v ON vs.vehicle_id = v.id
        WHERE v.company_id = ?
    ";
    $params = [$company_id];
    
    // Add filters to query
    foreach ($filters as $field => $value) {
        if ($value !== null) {
            if (in_array($field, ['id', 'vehicle_id', 'is_reserved'])) {
                $query .= " AND vs.$field = ?";
                $params[] = $value;
            } else {
                $query .= " AND vs.$field LIKE ?";
                $params[] = "%$value%";
            }
        }
    }
    
    $query .= " ORDER BY vs.seat_number ASC";
    
    // Execute query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $seats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(200, [
        'success' => true,
        'message' => 'Vehicle seats retrieved successfully',
        'data' => [
            'seats' => $seats
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
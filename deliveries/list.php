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
    'route',
    'total_tickets',
    'gross_amount',
    'net_amount',
    'created_by',
    'created_at'
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
            d.id,
            d.vehicle_id,
            v.plate_number,
            d.route,
            d.total_tickets,
            d.gross_amount,
            d.deductions,
            d.net_amount,
            d.created_by,
            u.name as created_by_name,
            d.created_at
        FROM deliveries d
        LEFT JOIN vehicles v ON d.vehicle_id = v.id
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.company_id = ?
    ";
    $params = [$company_id];
    
    // Add filters to query
    foreach ($filters as $field => $value) {
        if ($value !== null) {
            if (in_array($field, ['id', 'vehicle_id', 'total_tickets', 'created_by'])) {
                $query .= " AND d.$field = ?";
                $params[] = $value;
            } elseif (in_array($field, ['gross_amount', 'net_amount'])) {
                $query .= " AND d.$field = ?";
                $params[] = $value;
            } elseif ($field === 'created_at') {
                $query .= " AND DATE(d.created_at) = ?";
                $params[] = $value;
            } else {
                $query .= " AND d.$field LIKE ?";
                $params[] = "%$value%";
            }
        }
    }
    
    $query .= " ORDER BY d.created_at DESC";
    
    // Execute query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process deductions JSON
    foreach ($deliveries as &$delivery) {
        if ($delivery['deductions']) {
            $delivery['deductions'] = json_decode($delivery['deductions'], true);
        }
    }
    
    sendResponse(200, [
        'success' => true,
        'message' => 'Deliveries retrieved successfully',
        'data' => [
            'deliveries' => $deliveries
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
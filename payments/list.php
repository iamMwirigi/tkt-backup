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
    'booking_id',
    'payment_method',
    'transaction_reference',
    'amount',
    'status',
    'paid_at',
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
            p.id,
            p.company_id,
            p.booking_id,
            b.customer_name,
            b.customer_phone,
            p.payment_method,
            p.transaction_reference,
            p.amount,
            p.status,
            p.paid_at,
            p.created_at
        FROM payments p
        LEFT JOIN bookings b ON p.booking_id = b.id
        WHERE p.company_id = ?
    ";
    $params = [$company_id];
    
    // Add filters to query
    foreach ($filters as $field => $value) {
        if ($value !== null) {
            if (in_array($field, ['id', 'booking_id', 'amount', 'status'])) {
                $query .= " AND p.$field = ?";
                $params[] = $value;
            } elseif (in_array($field, ['paid_at', 'created_at'])) {
                $query .= " AND DATE(p.$field) = ?";
                $params[] = $value;
            } else {
                $query .= " AND p.$field LIKE ?";
                $params[] = "%$value%";
            }
        }
    }
    
    $query .= " ORDER BY p.created_at DESC";
    
    // Execute query
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(200, [
        'success' => true,
        'message' => 'Payments retrieved successfully',
        'data' => [
            'payments' => $payments
        ]
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get parameters from either GET parameters or request body
$company_id = $_GET['company_id'] ?? null;
$filters = [];

// Get all possible filter parameters
$filter_fields = [
    'start_date',
    'end_date',
    'office_id',
    'vehicle_id',
    'route_id',
    'trip_id',
    'status'
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
    
    // Base where clause for date filtering
    $date_where = "DATE(t.issued_at) BETWEEN ? AND ?";
    $date_params = [
        $filters['start_date'] ?? date('Y-m-d'),
        $filters['end_date'] ?? date('Y-m-d')
    ];
    
    // Additional filters
    $additional_filters = [];
    $additional_params = [];
    
    if (!empty($filters['office_id'])) {
        $additional_filters[] = "u.office_id = ?";
        $additional_params[] = $filters['office_id'];
    }
    
    if (!empty($filters['vehicle_id'])) {
        $additional_filters[] = "t.vehicle_id = ?";
        $additional_params[] = $filters['vehicle_id'];
    }
    
    if (!empty($filters['route_id'])) {
        $additional_filters[] = "t.route_id = ?";
        $additional_params[] = $filters['route_id'];
    }
    
    if (!empty($filters['trip_id'])) {
        $additional_filters[] = "t.trip_id = ?";
        $additional_params[] = $filters['trip_id'];
    }
    
    if (!empty($filters['status'])) {
        $additional_filters[] = "t.status = ?";
        $additional_params[] = $filters['status'];
    }
    
    $additional_where = !empty($additional_filters) ? " AND " . implode(" AND ", $additional_filters) : "";
    
    // 1. Ticket Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN t.status = 'paid' THEN 1 ELSE 0 END) as paid_tickets,
            SUM(CASE WHEN t.status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_tickets,
            COALESCE(SUM(CASE WHEN t.status = 'paid' THEN fare_amount ELSE 0 END), 0) as total_sales
        FROM tickets t
        LEFT JOIN bookings b ON t.booking_id = b.id
        LEFT JOIN users u ON t.officer_id = u.id
        WHERE t.company_id = ? AND $date_where $additional_where
    ");
    $stmt->execute(array_merge([$company_id], $date_params, $additional_params));
    $ticket_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Booking Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(CASE WHEN b.status = 'booked' THEN 1 ELSE 0 END) as active_bookings,
            SUM(CASE WHEN b.status = 'converted' THEN 1 ELSE 0 END) as converted_bookings,
            COALESCE(SUM(CASE WHEN b.status = 'booked' THEN fare_amount ELSE 0 END), 0) as total_booking_amount
        FROM bookings b
        WHERE b.company_id = ? AND DATE(b.booked_at) BETWEEN ? AND ? $additional_where
    ");
    $stmt->execute(array_merge([$company_id], $date_params, $additional_params));
    $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 3. Trip Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_trips,
            SUM(CASE WHEN tr.status = 'pending' THEN 1 ELSE 0 END) as pending_trips,
            SUM(CASE WHEN tr.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_trips,
            SUM(CASE WHEN tr.status = 'completed' THEN 1 ELSE 0 END) as completed_trips
        FROM trips tr
        WHERE tr.company_id = ? AND DATE(tr.created_at) BETWEEN ? AND ? $additional_where
    ");
    $stmt->execute(array_merge([$company_id], $date_params, $additional_params));
    $trip_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 4. Office-wise Statistics
    $stmt = $conn->prepare("
        SELECT 
            o.id as office_id,
            o.name as office_name,
            COUNT(DISTINCT t.id) as total_trips,
            COUNT(DISTINCT b.id) as total_bookings,
            COALESCE(SUM(CASE WHEN t.status = 'paid' THEN b.fare_amount ELSE 0 END), 0) as total_sales
        FROM offices o
        LEFT JOIN users u ON o.id = u.office_id
        LEFT JOIN tickets t ON u.id = t.officer_id
        LEFT JOIN bookings b ON t.booking_id = b.id
        WHERE o.company_id = ? AND (DATE(t.issued_at) BETWEEN ? AND ? OR t.id IS NULL) $additional_where
        GROUP BY o.id, o.name
    ");
    $stmt->execute(array_merge([$company_id], $date_params, $additional_params));
    $office_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fare Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_fares,
            COALESCE(SUM(amount), 0) as total_fare_amount,
            COUNT(DISTINCT destination_id) as total_destinations,
            COUNT(DISTINCT r.id) as total_routes
        FROM fares f
        JOIN destinations d ON f.destination_id = d.id
        JOIN routes r ON d.route_id = r.id
        WHERE r.company_id = ?
    ");
    $stmt->execute([$company_id]);
    $fare_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Combine all statistics
    $statistics = [
        'filters' => array_filter($filters),
        'tickets' => [
            'total' => (int)$ticket_stats['total_tickets'],
            'paid' => (int)$ticket_stats['paid_tickets'],
            'unpaid' => (int)$ticket_stats['unpaid_tickets'],
            'total_sales' => (float)$ticket_stats['total_sales']
        ],
        'bookings' => [
            'total' => (int)$booking_stats['total_bookings'],
            'cancelled' => (int)$booking_stats['cancelled_bookings'],
            'active' => (int)$booking_stats['active_bookings'],
            'converted' => (int)$booking_stats['converted_bookings'],
            'total_amount' => (float)$booking_stats['total_booking_amount']
        ],
        'trips' => [
            'total' => (int)$trip_stats['total_trips'],
            'pending' => (int)$trip_stats['pending_trips'],
            'in_progress' => (int)$trip_stats['in_progress_trips'],
            'completed' => (int)$trip_stats['completed_trips']
        ],
        'fares' => [
            'total_fares' => (int)$fare_stats['total_fares'],
            'total_amount' => (float)$fare_stats['total_fare_amount'],
            'total_destinations' => (int)$fare_stats['total_destinations'],
            'total_routes' => (int)$fare_stats['total_routes']
        ],
        'offices' => array_map(function($office) {
            return [
                'office_id' => (int)$office['office_id'],
                'office_name' => $office['office_name'],
                'total_trips' => (int)$office['total_trips'],
                'total_bookings' => (int)$office['total_bookings'],
                'total_sales' => (float)$office['total_sales']
            ];
        }, $office_stats)
    ];
    
    sendResponse(200, [
        'success' => true,
        'statistics' => $statistics
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
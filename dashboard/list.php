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
    
    // Get date filters
    $start_date = $_GET['start_date'] ?? date('Y-m-d');
    $end_date = $_GET['end_date'] ?? $start_date;
    
    // Base where clause for date filtering
    $date_where = "DATE(created_at) BETWEEN ? AND ?";
    $date_params = [$start_date, $end_date];
    
    // 1. Ticket Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_tickets,
            SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_tickets,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN fare_amount ELSE 0 END), 0) as total_sales
        FROM tickets t
        LEFT JOIN bookings b ON t.booking_id = b.id
        WHERE t.company_id = ? AND $date_where
    ");
    $stmt->execute(array_merge([$company_id], $date_params));
    $ticket_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Booking Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(CASE WHEN status = 'booked' THEN 1 ELSE 0 END) as active_bookings,
            SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_bookings,
            COALESCE(SUM(CASE WHEN status = 'booked' THEN fare_amount ELSE 0 END), 0) as total_booking_amount
        FROM bookings
        WHERE company_id = ? AND $date_where
    ");
    $stmt->execute(array_merge([$company_id], $date_params));
    $booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 3. Trip Statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_trips,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_trips,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_trips,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trips
        FROM trips
        WHERE company_id = ? AND $date_where
    ");
    $stmt->execute(array_merge([$company_id], $date_params));
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
        WHERE o.company_id = ? AND ($date_where OR t.id IS NULL)
        GROUP BY o.id, o.name
    ");
    $stmt->execute(array_merge([$company_id], $date_params));
    $office_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine all statistics
    $statistics = [
        'date_range' => [
            'start_date' => $start_date,
            'end_date' => $end_date
        ],
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
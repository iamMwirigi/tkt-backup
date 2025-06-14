<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Validate company_id
if (!isset($_GET['company_id'])) {
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
    $stmt->execute([$_GET['company_id']]);
    if (!$stmt->fetch()) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Company not found'
        ]);
    }
    
    if (isset($_GET['id'])) {
        // Get single booking
        $stmt = $conn->prepare("
            SELECT 
                b.*,
                t.trip_code,
                t.departure_time,
                v.plate_number,
                v.vehicle_type,
                d.name as destination_name,
                r.name as route_name,
                u.name as booked_by
            FROM bookings b
            LEFT JOIN trips t ON b.trip_id = t.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN destinations d ON b.destination_id = d.id
            LEFT JOIN routes r ON t.route_id = r.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.id = ? AND b.company_id = ?
        ");
        $stmt->execute([$_GET['id'], $_GET['company_id']]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            sendResponse(404, [
                'error' => true,
                'message' => 'Booking not found or does not belong to your company'
            ]);
        }
        
        sendResponse(200, [
            'success' => true,
            'booking' => $booking
        ]);
    } else {
        // Get all bookings with filters
        $where = ['b.company_id = ?'];
        $params = [$_GET['company_id']];
        
        if (isset($_GET['trip_id'])) {
            $where[] = 'b.trip_id = ?';
            $params[] = $_GET['trip_id'];
        }
        
        if (isset($_GET['status'])) {
            $where[] = 'b.status = ?';
            $params[] = $_GET['status'];
        }
        
        if (isset($_GET['date'])) {
            $where[] = 'DATE(b.booked_at) = ?';
            $params[] = $_GET['date'];
        }
        
        if (isset($_GET['vehicle_id'])) {
            $where[] = 'b.vehicle_id = ?';
            $params[] = $_GET['vehicle_id'];
        }
        
        if (isset($_GET['user_id'])) {
            $where[] = 'b.user_id = ?';
            $params[] = $_GET['user_id'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stmt = $conn->prepare("
            SELECT 
                b.*,
                t.trip_code,
                t.departure_time,
                v.plate_number,
                v.vehicle_type,
                d.name as destination_name,
                r.name as route_name,
                u.name as booked_by
            FROM bookings b
            LEFT JOIN trips t ON b.trip_id = t.id
            LEFT JOIN vehicles v ON b.vehicle_id = v.id
            LEFT JOIN destinations d ON b.destination_id = d.id
            LEFT JOIN routes r ON t.route_id = r.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE $where_clause
            ORDER BY b.booked_at DESC
        ");
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, [
            'success' => true,
            'bookings' => $bookings
        ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Get company_id and trip_id from either GET parameters or request body
$company_id = $_GET['company_id'] ?? null;
$trip_id = $_GET['trip_id'] ?? null;

if (!$company_id || !$trip_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    $company_id = $company_id ?? ($data['company_id'] ?? null);
    $trip_id = $trip_id ?? ($data['trip_id'] ?? null);
}

// Validate company_id
if (!$company_id) {
    sendResponse(400, [
        'error' => true,
        'message' => 'company_id is required'
    ]);
}

// Validate trip_id
if (!$trip_id) {
    sendResponse(400, [
        'error' => true,
        'message' => 'trip_id is required'
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
    
    // Get trip details with route and vehicle info
    $stmt = $conn->prepare("
        SELECT 
            t.*,
            r.name as route_name,
            r.description as route_description,
            v.plate_number,
            v.vehicle_type
        FROM trips t
        LEFT JOIN routes r ON t.route_id = r.id
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        WHERE t.id = ? AND t.company_id = ?
    ");
    $stmt->execute([$trip_id, $company_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$trip) {
        sendResponse(404, [
            'error' => true,
            'message' => 'Trip not found or does not belong to your company'
        ]);
    }
    
    // Get all bookings for this trip
    $stmt = $conn->prepare("
        SELECT 
            b.*,
            d.name as destination_name,
            f.amount as fare_amount
        FROM bookings b
        LEFT JOIN destinations d ON b.destination_id = d.id
        LEFT JOIN fares f ON b.destination_id = f.destination_id
        WHERE b.trip_id = ? AND b.company_id = ?
        ORDER BY b.booked_at DESC
    ");
    $stmt->execute([$trip_id, $company_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total fare amount
    $total_fare = 0;
    foreach ($bookings as $booking) {
        $total_fare += floatval($booking['fare_amount']);
    }
    
    // Combine all information
    $trip_details = [
        'trip' => [
            'id' => $trip['id'],
            'trip_code' => $trip['trip_code'],
            'route_name' => $trip['route_name'],
            'route_description' => $trip['route_description'],
            'vehicle_plate' => $trip['plate_number'],
            'vehicle_type' => $trip['vehicle_type'],
            'driver_name' => $trip['driver_name'],
            'conductor_name' => $trip['conductor_name'],
            'departure_time' => $trip['departure_time'],
            'arrival_time' => $trip['arrival_time'],
            'status' => $trip['status'],
            'notes' => $trip['notes'],
            'created_at' => $trip['created_at'],
            'created_via' => $trip['created_via']
        ],
        'bookings' => $bookings,
        'total_fare' => $total_fare,
        'total_bookings' => count($bookings)
    ];
    
    sendResponse(200, [
        'success' => true,
        'trip_details' => $trip_details
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
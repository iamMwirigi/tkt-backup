<?php
// FOR DEBUGGING ONLY - !! REMOVE OR DISABLE FOR PRODUCTION !!
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
        } catch (Exception $e) {
            error_log("Dotenv Error in trips/list.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Use dummy values for testing
$user_id = 1;
$company_id = 1;
$user_role = 'admin';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get all trips for the company with their details
    $stmt = $conn->prepare("
        SELECT t.*,
               v.plate_number,
               v.vehicle_type,
               r.name as route_name,
               r.description as route_description,
               (
                   SELECT COUNT(*) 
                   FROM bookings b 
                   WHERE b.trip_id = t.id 
                   AND b.status = 'booked'
               ) as booked_seats,
               (
                   SELECT COUNT(*) 
                   FROM vehicle_seats vs 
                   WHERE vs.vehicle_id = t.vehicle_id
               ) as total_seats
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.company_id = ?
        ORDER BY t.departure_time DESC
    ");
    $stmt->execute([$company_id]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate available seats for each trip
    foreach ($trips as &$trip) {
        $trip['available_seats'] = $trip['total_seats'] - $trip['booked_seats'];
    }

    sendResponse(200, [
        'success' => true,
        'message' => 'Trips retrieved successfully',
        'data' => [
            'trips' => $trips
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?> 
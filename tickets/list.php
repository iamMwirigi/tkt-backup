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
            error_log("Dotenv Error in tickets/list.php: " . $e->getMessage());
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

    // Get query parameters
    $vehicle_id = $_GET['vehicle_id'] ?? null;
    $trip_id = $_GET['trip_id'] ?? null;
    $officer_id = $_GET['officer_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $included_in_delivery = $_GET['included_in_delivery'] ?? null;
    $date_from = $_GET['date_from'] ?? null;
    $date_to = $_GET['date_to'] ?? null;

    // Build query
    $query = "
        SELECT t.*, 
            v.plate_number,
            tr.trip_code,
            u.name as officer_name,
            o.title as offense_title,
            d.name as destination_name
        FROM tickets t
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        LEFT JOIN trips tr ON t.trip_id = tr.id
        LEFT JOIN users u ON t.officer_id = u.id
        LEFT JOIN offenses o ON t.offense_id = o.id
        LEFT JOIN destinations d ON t.destination_id = d.id
        WHERE t.company_id = ?
    ";
    
    $params = [$company_id];
    
    if ($vehicle_id) {
        $query .= " AND t.vehicle_id = ?";
        $params[] = $vehicle_id;
    }
    
    if ($trip_id) {
        $query .= " AND t.trip_id = ?";
        $params[] = $trip_id;
    }
    
    if ($officer_id) {
        $query .= " AND t.officer_id = ?";
        $params[] = $officer_id;
    }
    
    if ($status) {
        $query .= " AND t.status = ?";
        $params[] = $status;
    }
    
    if ($included_in_delivery !== null) {
        $query .= " AND t.included_in_delivery = ?";
        $params[] = $included_in_delivery;
    }
    
    if ($date_from) {
        $query .= " AND DATE(t.issued_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $query .= " AND DATE(t.issued_at) <= ?";
        $params[] = $date_to;
    }
    
    $query .= " ORDER BY t.issued_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(200, [
        'success' => true,
        'message' => 'Tickets retrieved successfully',
        'data' => [
            'tickets' => $tickets
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?> 
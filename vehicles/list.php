<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug logging
error_log("Session contents: " . print_r($_SESSION, true));
error_log("Cookie contents: " . print_r($_COOKIE, true));

// Authentication disabled for testing
/*
// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Unauthorized access. Please login first.',
        'debug' => [
            'session_id' => session_id(),
            'session_exists' => !empty($_SESSION),
            'session_vars' => array_keys($_SESSION)
        ]
    ]);
    exit;
}
*/

require_once '../config/db.php';
require_once '../utils/functions.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get vehicles for the company with owner information
    $stmt = $conn->prepare("
        SELECT 
            v.id,
            v.plate_number,
            v.vehicle_type,
            v.created_at,
            vo.name as owner_name,
            vo.phone as owner_phone,
            vo.id_number as owner_id_number
        FROM vehicles v
        LEFT JOIN vehicle_owners vo ON v.owner_id = vo.id
        WHERE v.company_id = ?
        ORDER BY v.created_at DESC
    ");
    
    $stmt->execute([$_SESSION['company_id']]);
    $vehicles = $stmt->fetchAll();

    // Get seat information for each vehicle
    foreach ($vehicles as &$vehicle) {
        $stmt = $conn->prepare("
            SELECT 
                seat_number,
                position,
                is_reserved
            FROM vehicle_seats
            WHERE vehicle_id = ?
            ORDER BY seat_number
        ");
        $stmt->execute([$vehicle['id']]);
        $vehicle['seats'] = $stmt->fetchAll();
    }

    echo json_encode([
        'error' => false,
        'message' => 'Vehicles retrieved successfully',
        'data' => $vehicles
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'error' => true,
        'message' => 'Failed to retrieve vehicles',
        'details' => $e->getMessage()
    ]);
} 
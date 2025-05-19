<?php
require_once '../config/db.php';
require_once '../utils/functions.php';

header('Content-Type: application/json');

// Check authentication and device
$user_id = checkAuth();
$device_id = checkDevice();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['error' => 'Method not allowed']);
}

$data = json_decode(file_get_contents('php://input'), true);
validateRequiredFields([
    'vehicle_id',
    'route_id',
    'driver_name',
    'conductor_name',
    'departure_time'
], $data);

$db = new Database();
$conn = $db->getConnection();

// Verify device
if (!verifyDevice($conn, $user_id, $device_id)) {
    sendResponse(401, ['error' => 'Invalid device']);
}

try {
    // Get company prefix and route code
    $stmt = $conn->prepare("
        SELECT c.name as company_name, r.name as route_name 
        FROM users u 
        JOIN companies c ON u.company_id = c.id 
        JOIN routes r ON r.id = ? 
        WHERE u.id = ?
    ");
    $stmt->execute([$data['route_id'], $user_id]);
    $info = $stmt->fetch();

    if (!$info) {
        sendResponse(404, ['error' => 'Route not found']);
    }

    // Generate trip code
    $company_prefix = substr(preg_replace('/[^A-Z]/', '', strtoupper($info['company_name'])), 0, 3);
    $route_code = substr(preg_replace('/[^A-Z]/', '', strtoupper($info['route_name'])), 0, 3);
    $trip_code = generateTripCode($company_prefix, $route_code, $data['departure_time']);

    // Create trip
    $stmt = $conn->prepare("
        INSERT INTO trips (
            trip_code, company_id, vehicle_id, route_id, 
            driver_name, conductor_name, departure_time, 
            status, created_via
        ) VALUES (
            ?, (SELECT company_id FROM users WHERE id = ?), ?, ?, 
            ?, ?, ?, 
            'pending', 'mobile'
        )
    ");

    $stmt->execute([
        $trip_code,
        $user_id,
        $data['vehicle_id'],
        $data['route_id'],
        $data['driver_name'],
        $data['conductor_name'],
        $data['departure_time']
    ]);

    $trip_id = $conn->lastInsertId();

    sendResponse(201, [
        'message' => 'Trip created successfully',
        'trip' => [
            'id' => $trip_id,
            'trip_code' => $trip_code,
            'status' => 'pending'
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
?> 
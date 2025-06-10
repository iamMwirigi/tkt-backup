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
            error_log("Dotenv Error in trips/create.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
// $auth = checkAuth();
// $user_id = $auth['user_id'];
// $company_id = $auth['company_id'];

// Authentication disabled for testing
/*
// Check device
$device_id = checkDevice();
if (!$device_id) {
    sendResponse(400, ['error' => true, 'message' => 'Device ID is required']);
}
*/

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verify device
    if (!verifyDevice($conn, $user_id, $device_id)) {
        sendResponse(400, ['error' => true, 'message' => 'Invalid device']);
    }

    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required_fields = ['vehicle_id', 'route_id', 'driver_name', 'conductor_name', 'departure_time'];
    validateRequiredFields($required_fields, $data);

    // Start transaction
    $conn->beginTransaction();

    // Validate vehicle
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE id = ? AND company_id = ?");
    $stmt->execute([$data['vehicle_id'], $company_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        throw new Exception("Vehicle not found or doesn't belong to your company");
    }

    // Validate route
    $stmt = $conn->prepare("SELECT * FROM routes WHERE id = ? AND company_id = ?");
    $stmt->execute([$data['route_id'], $company_id]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        throw new Exception("Route not found or doesn't belong to your company");
    }

    // Get company prefix and route code
    $stmt = $conn->prepare("
        SELECT c.name as company_name, r.name as route_name 
        FROM companies c 
        JOIN routes r ON r.id = ? 
        WHERE c.id = ?
    ");
    $stmt->execute([$data['route_id'], $company_id]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        throw new Exception("Could not get company or route information");
    }

    // Generate company prefix and route code
    $company_prefix = substr(preg_replace('/[^A-Z]/', '', strtoupper($info['company_name'])), 0, 3);
    $route_code = substr(preg_replace('/[^A-Z]/', '', strtoupper($info['route_name'])), 0, 3);

    // Generate trip code
    $trip_code = generateTripCode($company_prefix, $route_code, $data['departure_time'], $conn);

    // Create trip
    $stmt = $conn->prepare("INSERT INTO trips (
        trip_code, company_id, vehicle_id, route_id,
        driver_name, conductor_name, departure_time,
        status, created_via
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'mobile')");

    $stmt->execute([
        $trip_code,
        $company_id,
        $data['vehicle_id'],
        $data['route_id'],
        $data['driver_name'],
        $data['conductor_name'],
        $data['departure_time']
    ]);
    $trip_id = $conn->lastInsertId();

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Trip created successfully',
        'trip_id' => $trip_id,
        'trip_code' => $trip_code
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?> 
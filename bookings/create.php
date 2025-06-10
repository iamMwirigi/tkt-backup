<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// For development: display all errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Autoload Composer packages and load environment variables
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    } catch (Exception $e) {
        error_log("Dotenv loading error: " . $e->getMessage());
    }
}

// Include utility functions and database configuration
require_once __DIR__ . '/../utils/functions.php';
require_once __DIR__ . '/../config/db.php';    

// Set common headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Consider restricting in production
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Authentication disabled for testing
// ensureLoggedIn();

$db = new Database();
$conn = $db->getConnection();

// Use dummy values for testing
$company_id = 1; // $_SESSION['company_id'];
$user_id = 1; // $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['error' => true, 'message' => 'Method not allowed.']);
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, ['error' => true, 'message' => 'Invalid JSON input.']);
    }

    // Validate required fields - aligning with api/bookings/index.php and database schema
    validateRequiredFields(['trip_id', 'customer_name', 'customer_phone', 'destination_id', 'seat_number', 'fare_amount'], $data);

    $trip = getValidTripOrFail($conn, $data['trip_id'], $company_id);
    $vehicle_id = $trip['vehicle_id']; // Get vehicle_id from the validated trip

    getValidDestinationOrFail($conn, $data['destination_id'], $company_id); // Validates destination

    if (!isSeatAvailable($conn, $data['trip_id'], $vehicle_id, $data['seat_number'], $company_id)) {
        sendResponse(409, ['error' => true, 'message' => 'Seat ' . htmlspecialchars($data['seat_number']) . ' is already booked for this trip.']);
    }
    
    if (!is_numeric($data['fare_amount']) || $data['fare_amount'] < 0) {
        sendResponse(400, ['error' => true, 'message' => 'Invalid fare amount.']);
    }

    // Insert the booking
    $stmt = $conn->prepare("
        INSERT INTO bookings (company_id, trip_id, vehicle_id, user_id, customer_name, customer_phone, destination_id, seat_number, fare_amount, status)
        VALUES (:company_id, :trip_id, :vehicle_id, :user_id, :customer_name, :customer_phone, :destination_id, :seat_number, :fare_amount, :status)
    ");
    $stmt->execute([
        ':company_id' => $company_id,
        ':trip_id' => $data['trip_id'],
        ':vehicle_id' => $vehicle_id,
        ':user_id' => $user_id,
        ':customer_name' => $data['customer_name'],
        ':customer_phone' => $data['customer_phone'],
        ':destination_id' => $data['destination_id'],
        ':seat_number' => $data['seat_number'],
        ':fare_amount' => $data['fare_amount'],
        ':status' => $data['status'] ?? 'booked'
    ]);

    $booking_id = $conn->lastInsertId();

    sendResponse(201, ['success' => true, 'message' => 'Booking created successfully.', 'booking_id' => $booking_id]);

} catch (PDOException $e) {
    error_log("PDOException in bookings/create.php: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
    sendResponse(500, ['error' => true, 'message' => 'Database operation failed.', 'details' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Exception in bookings/create.php: " . $e->getMessage());
    sendResponse(500, ['error' => true, 'message' => 'An unexpected error occurred.', 'details' => $e->getMessage()]);
}
?> 
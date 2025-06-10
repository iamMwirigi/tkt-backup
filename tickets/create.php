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
            error_log("Dotenv Error in tickets/create.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
$auth = checkAuth();
$user_id = $auth['user_id'];
$company_id = $auth['company_id'];

// Get request body
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['trip_id', 'destination_id', 'seat_number', 'fare_amount'];
validateRequiredFields($required_fields, $data);

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();

    // Validate trip
    $trip = getValidTripOrFail($conn, $data['trip_id'], $company_id);
    
    // Validate destination
    $destination = getValidDestinationOrFail($conn, $data['destination_id'], $company_id);
    
    // Check if seat is available
    if (!isSeatAvailable($conn, $data['trip_id'], $trip['vehicle_id'], $data['seat_number'], $company_id)) {
        throw new Exception("Seat {$data['seat_number']} is already taken for this trip");
    }

    // Create ticket
    $stmt = $conn->prepare("INSERT INTO tickets (
        company_id, vehicle_id, trip_id, officer_id, destination_id,
        route, location, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'unpaid')");

    $location = $data['location'] ?? 'Terminal';
    $stmt->execute([
        $company_id,
        $trip['vehicle_id'],
        $data['trip_id'],
        $user_id,
        $data['destination_id'],
        $destination['destination_name'],
        $location
    ]);
    $ticket_id = $conn->lastInsertId();

    // Create payment record
    $stmt = $conn->prepare("INSERT INTO payments (
        company_id, ticket_id, amount, payment_method,
        transaction_id, status, created_by
    ) VALUES (?, ?, ?, ?, ?, 'pending', ?)");

    $payment_method = $data['payment_method'] ?? 'cash';
    $transaction_id = $data['transaction_id'] ?? null;
    $stmt->execute([
        $company_id,
        $ticket_id,
        $data['fare_amount'],
        $payment_method,
        $transaction_id,
        $user_id
    ]);

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Ticket created successfully',
        'ticket_id' => $ticket_id
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
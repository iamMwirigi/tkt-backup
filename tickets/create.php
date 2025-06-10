<?php
session_start();
header('Content-Type: application/json');

// Use absolute paths from project root
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/functions.php';

// Check if user is logged in
if (!checkAuth()) {
    http_response_code(401);
    echo json_encode(['error' => true, 'message' => 'Unauthorized. Please login.']);
    exit;
}

// Get user data from session
$user = $_SESSION['user'];
$company_id = $user['company_id'];

// Get request body
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['trip_id', 'destination_id', 'seat_number', 'fare_amount'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get trip details
    $trip_id = $data['trip_id'];
    $stmt = $conn->prepare("SELECT t.*, v.plate_number, r.name as route_name 
                           FROM trips t 
                           JOIN vehicles v ON t.vehicle_id = v.id 
                           JOIN routes r ON t.route_id = r.id 
                           WHERE t.id = ? AND t.company_id = ?");
    $stmt->bind_param("ii", $trip_id, $company_id);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();

    if (!$trip) {
        throw new Exception("Trip not found or doesn't belong to your company");
    }

    // Check if seat is available
    $seat_number = $data['seat_number'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets 
                           WHERE trip_id = ? AND seat_number = ?");
    $stmt->bind_param("is", $trip_id, $seat_number);
    $stmt->execute();
    $seat_check = $stmt->get_result()->fetch_assoc();

    if ($seat_check['count'] > 0) {
        throw new Exception("Seat $seat_number is already taken for this trip");
    }

    // Validate destination
    $destination_id = $data['destination_id'];
    $stmt = $conn->prepare("SELECT * FROM destinations 
                           WHERE id = ? AND route_id = ?");
    $stmt->bind_param("ii", $destination_id, $trip['route_id']);
    $stmt->execute();
    $destination = $stmt->get_result()->fetch_assoc();

    if (!$destination) {
        throw new Exception("Invalid destination for this route");
    }

    // Create ticket
    $stmt = $conn->prepare("INSERT INTO tickets (
        company_id, vehicle_id, trip_id, officer_id, destination_id,
        route, location, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'unpaid')");

    $location = $data['location'] ?? 'Terminal';
    $stmt->bind_param(
        "iiiiiss",
        $company_id,
        $trip['vehicle_id'],
        $trip_id,
        $user['id'],
        $destination_id,
        $trip['route_name'],
        $location
    );
    $stmt->execute();
    $ticket_id = $conn->insert_id;

    // Create payment record
    $stmt = $conn->prepare("INSERT INTO payments (
        company_id, ticket_id, amount, payment_method,
        transaction_id, status, created_by
    ) VALUES (?, ?, ?, ?, ?, 'pending', ?)");

    $payment_method = $data['payment_method'] ?? 'cash';
    $transaction_id = $data['transaction_id'] ?? null;
    $stmt->bind_param(
        "iidssi",
        $company_id,
        $ticket_id,
        $data['fare_amount'],
        $payment_method,
        $transaction_id,
        $user['id']
    );
    $stmt->execute();

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
    $conn->rollback();
    
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
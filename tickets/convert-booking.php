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
if (!isset($data['booking_id']) || empty($data['booking_id'])) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Missing required field: booking_id']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get booking details
    $booking_id = $data['booking_id'];
    $stmt = $conn->prepare("SELECT b.*, t.route_id, t.vehicle_id, r.name as route_name 
                           FROM bookings b 
                           JOIN trips t ON b.trip_id = t.id 
                           JOIN routes r ON t.route_id = r.id 
                           WHERE b.id = ? AND b.company_id = ?");
    $stmt->bind_param("ii", $booking_id, $company_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        throw new Exception("Booking not found or doesn't belong to your company");
    }

    // Check if booking is already converted
    if ($booking['status'] === 'converted') {
        throw new Exception("This booking has already been converted to a ticket");
    }

    // Check if seat is available (unless override is specified)
    $seat_number = $booking['seat_number'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM tickets 
                           WHERE trip_id = ? AND seat_number = ?");
    $stmt->bind_param("is", $booking['trip_id'], $seat_number);
    $stmt->execute();
    $seat_check = $stmt->get_result()->fetch_assoc();

    if ($seat_check['count'] > 0 && !isset($data['override_seat'])) {
        throw new Exception("Seat $seat_number is already taken for this trip. Use override_seat=true to force conversion.");
    }

    // Create ticket
    $stmt = $conn->prepare("INSERT INTO tickets (
        company_id, vehicle_id, trip_id, officer_id, destination_id,
        booking_id, route, location, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')");

    $location = $data['location'] ?? 'Terminal';
    $stmt->bind_param(
        "iiiiiss",
        $company_id,
        $booking['vehicle_id'],
        $booking['trip_id'],
        $user['id'],
        $booking['destination_id'],
        $booking_id,
        $booking['route_name'],
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
        $booking['fare_amount'],
        $payment_method,
        $transaction_id,
        $user['id']
    );
    $stmt->execute();

    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET status = 'converted' WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking converted to ticket successfully',
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
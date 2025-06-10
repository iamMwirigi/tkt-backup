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
            error_log("Dotenv Error in tickets/convert-booking.php: " . $e->getMessage());
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
if (!isset($data['booking_id']) || empty($data['booking_id'])) {
    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Missing required field: booking_id']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();

    // Get booking details
    $booking_id = $data['booking_id'];
    $stmt = $conn->prepare("SELECT b.*, t.route_id, t.vehicle_id, r.name as route_name 
                           FROM bookings b 
                           JOIN trips t ON b.trip_id = t.id 
                           JOIN routes r ON t.route_id = r.id 
                           WHERE b.id = ? AND b.company_id = ?");
    $stmt->execute([$booking_id, $company_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

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
    $stmt->execute([$booking['trip_id'], $seat_number]);
    $seat_check = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($seat_check['count'] > 0 && !isset($data['override_seat'])) {
        throw new Exception("Seat $seat_number is already taken for this trip. Use override_seat=true to force conversion.");
    }

    // Create ticket
    $stmt = $conn->prepare("INSERT INTO tickets (
        company_id, vehicle_id, trip_id, officer_id, destination_id,
        booking_id, route, location, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')");

    $location = $data['location'] ?? 'Terminal';
    $stmt->execute([
        $company_id,
        $booking['vehicle_id'],
        $booking['trip_id'],
        $user_id,
        $booking['destination_id'],
        $booking_id,
        $booking['route_name'],
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
        $booking['fare_amount'],
        $payment_method,
        $transaction_id,
        $user_id
    ]);

    // Update booking status
    $stmt = $conn->prepare("UPDATE bookings SET status = 'converted' WHERE id = ?");
    $stmt->execute([$booking_id]);

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
    if (isset($conn)) {
        $conn->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
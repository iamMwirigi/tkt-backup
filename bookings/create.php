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
    'trip_id',
    'customer_name',
    'customer_phone',
    'destination_id',
    'seat_number'
], $data);

$db = new Database();
$conn = $db->getConnection();

// Verify device
if (!verifyDevice($conn, $user_id, $device_id)) {
    sendResponse(401, ['error' => 'Invalid device']);
}

try {
    // Start transaction
    $conn->beginTransaction();

    // Check if seat is available
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM bookings 
        WHERE trip_id = ? AND seat_number = ? AND status = 'booked'
    ");
    $stmt->execute([$data['trip_id'], $data['seat_number']]);
    $seat_check = $stmt->fetch();

    if ($seat_check['count'] > 0) {
        $conn->rollBack();
        sendResponse(400, ['error' => 'Seat already booked']);
    }

    // Get fare amount
    $stmt = $conn->prepare("
        SELECT amount 
        FROM fares 
        WHERE destination_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$data['destination_id']]);
    $fare = $stmt->fetch();

    if (!$fare) {
        $conn->rollBack();
        sendResponse(400, ['error' => 'Fare not found for destination']);
    }

    // Get vehicle ID from trip
    $stmt = $conn->prepare("SELECT vehicle_id FROM trips WHERE id = ?");
    $stmt->execute([$data['trip_id']]);
    $trip = $stmt->fetch();

    if (!$trip) {
        $conn->rollBack();
        sendResponse(404, ['error' => 'Trip not found']);
    }

    // Create booking
    $stmt = $conn->prepare("
        INSERT INTO bookings (
            company_id, trip_id, vehicle_id, user_id,
            customer_name, customer_phone, destination_id,
            seat_number, fare_amount, status
        ) VALUES (
            (SELECT company_id FROM users WHERE id = ?), ?, ?, ?,
            ?, ?, ?,
            ?, ?, 'booked'
        )
    ");

    $stmt->execute([
        $user_id,
        $data['trip_id'],
        $trip['vehicle_id'],
        $user_id,
        $data['customer_name'],
        $data['customer_phone'],
        $data['destination_id'],
        $data['seat_number'],
        $fare['amount']
    ]);

    $booking_id = $conn->lastInsertId();

    // Create SMS reminder
    $stmt = $conn->prepare("
        INSERT INTO sms_reminders (
            company_id, booking_id, phone_number,
            message, status, scheduled_at
        ) VALUES (
            (SELECT company_id FROM users WHERE id = ?), ?, ?,
            ?, 'pending', DATE_ADD(NOW(), INTERVAL 1 DAY)
        )
    ");

    $message = sprintf(
        "Reminder: Your trip to %s departs tomorrow at %s.",
        $data['destination_name'],
        date('h:i A', strtotime($data['departure_time']))
    );

    $stmt->execute([
        $user_id,
        $booking_id,
        $data['customer_phone'],
        $message
    ]);

    $conn->commit();

    sendResponse(201, [
        'message' => 'Booking created successfully',
        'booking' => [
            'id' => $booking_id,
            'seat_number' => $data['seat_number'],
            'fare_amount' => $fare['amount']
        ]
    ]);

} catch (PDOException $e) {
    $conn->rollBack();
    sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
?> 
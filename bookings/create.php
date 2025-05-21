<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../security/auth.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get the request body
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['trip_id', 'passenger_name', 'passenger_phone', 'seat_number'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Insert the booking
    $stmt = $pdo->prepare("INSERT INTO bookings (trip_id, passenger_name, passenger_phone, seat_number, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([
        $data['trip_id'],
        $data['passenger_name'],
        $data['passenger_phone'],
        $data['seat_number']
    ]);

    $booking_id = $pdo->lastInsertId();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $booking_id
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 
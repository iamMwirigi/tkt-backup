<?php
// From 'api/trips/' go up two levels to 'tkt_dev/', then into 'includes/'
require_once __DIR__ . '/../../includes/auth_check.php';

// $pdo, $user_id, and $company_id are available from auth_check.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate input (essential!)
    if (!isset($data['vehicle_id'], $data['route_id'], $data['departure_time'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required trip data: vehicle_id, route_id, departure_time.']);
        exit;
    }

    $vehicle_id = $data['vehicle_id'];
    $route_id = $data['route_id'];
    $departure_time = $data['departure_time'];
    // $company_id is already available from auth_check.php

    try {
        $stmt = $pdo->prepare("INSERT INTO trips (vehicle_id, route_id, departure_time, company_id, created_by_user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$vehicle_id, $route_id, $departure_time, $company_id, $user_id]);
        $trip_id = $pdo->lastInsertId();

        echo json_encode(['message' => 'Trip created successfully', 'trip_id' => $trip_id]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Trip creation failed: " . $e->getMessage()); // Log error
        echo json_encode(['error' => 'Trip creation failed due to a server error.']);
    }
} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
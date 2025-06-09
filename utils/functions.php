<?php
// /opt/lampp/htdocs/tkt-backup/utils/functions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sends a JSON response with a specific HTTP status code and exits.
 * @param int $statusCode HTTP status code.
 * @param array $data Data to be JSON encoded.
 */
function sendResponse($statusCode, $data) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
    }
    echo json_encode($data);
    exit;
}

/**
 * Validates if all required fields are present and not empty in the data array.
 * Sends a 400 response if validation fails.
 * @param array $required Array of required field names.
 * @param array $data Associative array of input data.
 */
function validateRequiredFields($required, $data) {
    foreach ($required as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            sendResponse(400, ['error' => true, 'message' => "Missing or empty required field: $field"]);
        }
    }
}

/**
 * Ensures that a user is logged in by checking session variables.
 * Sends a 401 response if not logged in.
 */
function ensureLoggedIn() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
        sendResponse(401, ['error' => true, 'message' => 'Unauthorized. Please login.']);
    }
    // You can add role checks here if needed for more granular access control
    // Example:
    // if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'clerk'])) {
    //     sendResponse(403, ['error' => true, 'message' => 'Forbidden. Insufficient privileges for this action.']);
    // }
}

/**
 * Checks if a specific seat is available for a given trip and vehicle.
 * @param PDO $conn Database connection object.
 * @param int $trip_id
 * @param int $vehicle_id
 * @param string $seat_number
 * @param int $company_id
 * @param int|null $exclude_booking_id To exclude a specific booking (e.g., when updating a seat).
 * @return bool True if seat is available, false otherwise.
 */
function isSeatAvailable($conn, $trip_id, $vehicle_id, $seat_number, $company_id, $exclude_booking_id = null) {
    $sql = "SELECT COUNT(*) as count 
            FROM bookings 
            WHERE trip_id = :trip_id 
              AND vehicle_id = :vehicle_id 
              AND seat_number = :seat_number 
              AND company_id = :company_id
              AND status = 'booked'"; // Only check against 'booked' seats
    
    $params = [
        ':trip_id' => $trip_id,
        ':vehicle_id' => $vehicle_id,
        ':seat_number' => $seat_number,
        ':company_id' => $company_id
    ];

    if ($exclude_booking_id !== null) {
        $sql .= " AND id != :exclude_booking_id";
        $params[':exclude_booking_id'] = $exclude_booking_id;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] == 0;
}

/**
 * Fetches trip details and validates against company_id.
 * Sends 404 response if trip not found or doesn't belong to the company.
 * @param PDO $conn Database connection object.
 * @param int $trip_id
 * @param int $company_id
 * @return array Trip details.
 */
function getValidTripOrFail($conn, $trip_id, $company_id) {
    $stmt = $conn->prepare("SELECT * FROM trips WHERE id = :trip_id AND company_id = :company_id");
    $stmt->execute([':trip_id' => $trip_id, ':company_id' => $company_id]);
    $trip = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$trip) {
        sendResponse(404, ['error' => true, 'message' => "Trip not found or does not belong to your company."]);
    }
    return $trip;
}

/**
 * Fetches destination details and validates its route against company_id.
 * Sends 404/403 response if validation fails.
 * @param PDO $conn Database connection object.
 * @param int $destination_id
 * @param int $company_id
 * @return array Destination details.
 */
function getValidDestinationOrFail($conn, $destination_id, $company_id) {
    $stmt = $conn->prepare("
        SELECT d.id, d.name as destination_name, d.route_id, r.company_id as route_company_id
        FROM destinations d
        JOIN routes r ON d.route_id = r.id
        WHERE d.id = :destination_id
    ");
    $stmt->execute([':destination_id' => $destination_id]);
    $destination = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$destination) {
        sendResponse(404, ['error' => true, 'message' => "Destination not found."]);
    }
    if ($destination['route_company_id'] != $company_id) {
        sendResponse(403, ['error' => true, 'message' => "Destination's route does not belong to your company."]);
    }
    return $destination;
}

?>
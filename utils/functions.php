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
 * Checks if user is authenticated and returns user_id.
 * Sends a 401 response if not authenticated.
 * @return array Array containing user_id and company_id
 */
function checkAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
        sendResponse(401, ['error' => true, 'message' => 'Unauthorized. Please login.']);
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'company_id' => $_SESSION['company_id']
    ];
}

/**
 * Checks if device ID is provided in headers.
 * Sends a 400 response if not provided.
 * @return string The device ID
 */
function checkDevice() {
    // Get all headers
    $headers = getallheaders();
    error_log("Received headers: " . print_r($headers, true));
    
    // Check for device ID in various possible header formats
    $device_id = null;
    
    // Check X-Device-ID (case insensitive)
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'x-device-id') {
            $device_id = $value;
            break;
        }
    }
    
    // Check device_kubwa (case insensitive)
    if (!$device_id) {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'device_kubwa') {
                $device_id = $value;
                break;
            }
        }
    }
    
    // Check HTTP_X_DEVICE_ID
    if (!$device_id && isset($_SERVER['HTTP_X_DEVICE_ID'])) {
        $device_id = $_SERVER['HTTP_X_DEVICE_ID'];
    }
    
    // Check HTTP_DEVICE_KUBWA
    if (!$device_id && isset($_SERVER['HTTP_DEVICE_KUBWA'])) {
        $device_id = $_SERVER['HTTP_DEVICE_KUBWA'];
    }
    
    if ($device_id) {
        error_log("Found device ID: " . $device_id);
        return $device_id;
    }
    
    // If no device ID found, send error with debug info
    error_log("No device ID found in headers");
    sendResponse(400, [
        'error' => true, 
        'message' => 'Device ID is required',
        'debug' => [
            'headers' => $headers,
            'server_vars' => array_filter($_SERVER, function($key) {
                return strpos($key, 'HTTP_') === 0;
            }, ARRAY_FILTER_USE_KEY)
        ]
    ]);
}

/**
 * Verifies if the device is registered for the user.
 * @param PDO $conn Database connection object
 * @param int $user_id The user's ID
 * @param string $device_id The device ID to verify
 * @return bool True if device is valid, false otherwise
 */
function verifyDevice($conn, $user_id, $device_id) {
    try {
        // First check if device exists and is active
        $stmt = $conn->prepare("
            SELECT id, user_id 
            FROM devices 
            WHERE device_uuid = ? AND is_active = 1
        ");
        $stmt->execute([$device_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            error_log("Device not found or inactive: " . $device_id);
            return false;
        }

        // If device has no user_id, it's a new device - register it
        if (!$device['user_id']) {
            $stmt = $conn->prepare("
                UPDATE devices 
                SET user_id = ?, company_id = (SELECT company_id FROM users WHERE id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$user_id, $user_id, $device['id']]);
            error_log("Registered new device: " . $device_id . " for user: " . $user_id);
            return true;
        }

        // If device has a user_id, check if it matches
        if ($device['user_id'] != $user_id) {
            error_log("Device belongs to different user. Device user: " . $device['user_id'] . ", Request user: " . $user_id);
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("Error verifying device: " . $e->getMessage());
        return false;
    }
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

/**
 * Generates a unique trip code based on company prefix, route code, and departure time.
 * @param string $company_prefix Three-letter company code
 * @param string $route_code Three-letter route code
 * @param string $departure_time Departure time in Y-m-d H:i:s format
 * @param PDO $conn Database connection object
 * @return string Generated trip code
 */
function generateTripCode($company_prefix, $route_code, $departure_time, $conn) {
    // Convert departure time to date
    $date = new DateTime($departure_time);
    $date_str = $date->format('Ymd');
    
    // Get the last trip code for today
    $stmt = $conn->prepare("
        SELECT trip_code 
        FROM trips 
        WHERE trip_code LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $pattern = $company_prefix . '-' . $route_code . '-' . $date_str . '%';
    $stmt->execute([$pattern]);
    $last_trip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Generate sequence number
    $sequence = 1;
    if ($last_trip) {
        // Extract sequence from last trip code
        $parts = explode('-', $last_trip['trip_code']);
        if (count($parts) >= 4) {
            $sequence = intval($parts[3]) + 1;
        }
    }
    
    // Format: COMPANY-ROUTE-YYYYMMDD-SEQ
    return sprintf(
        '%s-%s-%s-%d',
        $company_prefix,
        $route_code,
        $date_str,
        $sequence
    );
}

?>
<?php

/**
 * Verifies if the device ID sent in the header is registered.
 *
 * @param PDO $pdo The PDO database connection object.
 * @return int The company_id associated with the device.
 *             Outputs JSON error and exits if verification fails.
 */
function verify_device(PDO $pdo): int {
    $device_id = $_SERVER['HTTP_X_DEVICE_ID'] ?? null;

    if (!$device_id) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Missing X-Device-ID header']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT company_id FROM devices WHERE device_id = ? AND status = 'active'"); // Assuming you have a status column
        $stmt->execute([$device_id]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$device) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Device not registered or inactive']);
            exit;
        }
        return (int)$device['company_id'];
    } catch (PDOException $e) {
        http_response_code(500);
        // In production, log this error instead of echoing details
        error_log("Device verification failed: " . $e->getMessage());
        echo json_encode(['error' => 'Device verification failed. Please contact support.']);
        exit;
    }
}

?>
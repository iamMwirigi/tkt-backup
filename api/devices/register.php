<?php
// Correct path to your database configuration file
// From 'api/devices/' go up two levels to 'tkt_dev/', then into 'config/'
require_once __DIR__ . '/../../config/database.php';


header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['device_id']) && isset($data['company_id'])) {
        $device_id = $data['device_id'];
        $company_id = $data['company_id'];

        // Basic validation (you should add more robust validation)
        if (empty($device_id) || empty($company_id) || !is_numeric($company_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid device_id or company_id.']);
            return;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO devices (device_id, company_id) VALUES (?, ?)");
            $stmt->execute([$device_id, $company_id]);

            echo json_encode(['message' => 'Device registered successfully']);
        } catch (PDOException $e) {
            http_response_code(500);
            // In production, log this error instead of echoing details
            error_log("Device registration failed: " . $e->getMessage());
            echo json_encode(['error' => 'Device registration failed. It might already be registered or an internal error occurred.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Missing device_id or company_id']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
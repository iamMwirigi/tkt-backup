<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/db.php';
require_once '../utils/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['error' => 'Method not allowed']);
}

$data = json_decode(file_get_contents('php://input'), true);
validateRequiredFields(['device_uuid', 'device_name'], $data);

$db = new Database();
$conn = $db->getConnection();

try {
    // Check if device already exists
    // $stmt = $conn->prepare("SELECT id FROM devices WHERE device_uuid = ?");
    // $stmt->execute([$data['device_uuid']]);
    // $existing_device = $stmt->fetch();

    // if ($existing_device) {
    //     sendResponse(400, ['error' => 'Device already registered']);
    // }

    // Register new device
    $stmt = $conn->prepare("
        INSERT INTO devices (device_uuid, device_name, is_active) 
        VALUES (?, ?, 1)
    ");
    $stmt->execute([
        $data['device_uuid'],
        $data['device_name']
    ]);

    sendResponse(201, [
        'message' => 'Device registered successfully',
        'device_id' => $conn->lastInsertId()
    ]);

} catch (PDOException $e) {
    sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
?>
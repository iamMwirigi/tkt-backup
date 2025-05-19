<?php
require_once '../config/db.php';
require_once '../utils/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, ['error' => 'Method not allowed']);
}

$data = json_decode(file_get_contents('php://input'), true);
validateRequiredFields(['email', 'password', 'device_id'], $data);

$db = new Database();
$conn = $db->getConnection();

try {
    // Get user
    $stmt = $conn->prepare("
        SELECT u.*, c.name as company_name 
        FROM users u 
        JOIN companies c ON u.company_id = c.id 
        WHERE u.email = ?
    ");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['password'], $user['password'])) {
        sendResponse(401, ['error' => 'Invalid credentials']);
    }

    // Check if device exists
    $stmt = $conn->prepare("SELECT id FROM devices WHERE device_uuid = ?");
    $stmt->execute([$data['device_id']]);
    $device = $stmt->fetch();

    if (!$device) {
        // Register new device
        $stmt = $conn->prepare("
            INSERT INTO devices (company_id, user_id, device_uuid, device_name) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['company_id'],
            $user['id'],
            $data['device_id'],
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device'
        ]);
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['company_id'] = $user['company_id'];
    $_SESSION['role'] = $user['role'];

    sendResponse(200, [
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'company' => $user['company_name']
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(500, ['error' => 'Database error: ' . $e->getMessage()]);
}
?> 
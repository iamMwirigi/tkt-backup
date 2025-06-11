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
            error_log("Dotenv Error in routes/list.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Authentication disabled for testing
// Check if user is logged in
// $auth = checkAuth();
// $user_id = $auth['user_id'];
// $company_id = $auth['company_id'];
// $user_role = $_SESSION['role'] ?? '';

// Use dummy values for testing
$user_id = 1;
$company_id = 1;
$user_role = 'admin';
$device_id = 'TEST_DEVICE_123';

// For non-admin users, device ID is required
/*
if ($user_role !== 'admin') {
    $device_id = checkDevice();
    if (!$device_id) {
        sendResponse(400, ['error' => true, 'message' => 'Device ID is required for non-admin users']);
    }
}
*/

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verify device for non-admin users
    if ($user_role !== 'admin' && !verifyDevice($conn, $user_id, $device_id)) {
        sendResponse(400, ['error' => true, 'message' => 'Invalid device']);
    }

    // Get all routes for the company
    $stmt = $conn->prepare("
        SELECT r.*, 
               COUNT(DISTINCT d.id) as destination_count
        FROM routes r
        LEFT JOIN destinations d ON r.id = d.route_id
        WHERE r.company_id = ?
        GROUP BY r.id
        ORDER BY r.name ASC
    ");
    $stmt->execute([$company_id]);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(200, [
        'success' => true,
        'message' => 'Routes retrieved successfully',
        'data' => [
            'routes' => $routes
        ]
    ]);

} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 




 
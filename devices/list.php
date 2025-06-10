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
            error_log("Dotenv Error in devices/list.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
$auth = checkAuth();
$user_id = $auth['user_id'];
$company_id = $auth['company_id'];
$user_role = $_SESSION['role'] ?? '';

// Only admin users can view all devices
/*
if ($user_role !== 'admin') {
    sendResponse(403, [
        'error' => true,
        'message' => 'Only admin users can view all devices'
    ]);
}
*/

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get all devices for the company
    $stmt = $conn->prepare("
        SELECT 
            d.id,
            d.device_uuid,
            d.device_name,
            d.is_active,
            d.registered_at,
            u.name as registered_by
        FROM devices d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.company_id = ?
        ORDER BY d.registered_at DESC
    ");
    $stmt->execute([$company_id]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formatted_devices = array_map(function($device) {
        return [
            'id' => $device['id'],
            'device_id' => $device['device_uuid'],
            'name' => $device['device_name'],
            'status' => $device['is_active'] ? 'active' : 'inactive',
            'registered_by' => $device['registered_by'],
            'registered_at' => $device['registered_at']
        ];
    }, $devices);
    
    sendResponse(200, [
        'devices' => $formatted_devices
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
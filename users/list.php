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
            error_log("Dotenv Error in users/list.php: " . $e->getMessage());
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

// Authentication disabled for testing
/*
// Only admin users can view all users
if ($user_role !== 'admin') {
    sendResponse(403, [
        'error' => true,
        'message' => 'Only admin users can view all users'
    ]);
}
*/

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get all users for the company with their office details
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.role,
            u.created_at,
            o.name as office_name,
            o.location as office_location,
            (
                SELECT COUNT(*) 
                FROM devices d 
                WHERE d.user_id = u.id
            ) as registered_devices,
            (
                SELECT COUNT(*) 
                FROM bookings b 
                WHERE b.user_id = u.id
            ) as total_bookings
        FROM users u
        LEFT JOIN offices o ON u.office_id = o.id
        WHERE u.company_id = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$company_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $formatted_users = array_map(function($user) {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'office' => [
                'name' => $user['office_name'],
                'location' => $user['office_location']
            ],
            'stats' => [
                'registered_devices' => (int)$user['registered_devices'],
                'total_bookings' => (int)$user['total_bookings']
            ],
            'created_at' => $user['created_at']
        ];
    }, $users);
    
    sendResponse(200, [
        'users' => $formatted_users
    ]);
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
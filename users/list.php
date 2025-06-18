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

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate company_id
    if (!isset($data['company_id'])) {
        sendResponse(400, [
            'error' => true,
            'message' => 'company_id is required'
        ]);
    }
    
    // Get users
    if (isset($_GET['id'])) {
        // Get single user
        $stmt = $conn->prepare("
            SELECT u.*, c.name as company_name, o.name as office_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN offices o ON u.office_id = o.id
            WHERE u.id = ? AND u.company_id = ?
        ");
        $stmt->execute([$_GET['id'], $data['company_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            sendResponse(404, [
                'error' => true,
                'message' => 'User not found'
            ]);
        }
        
        sendResponse(200, [
            'success' => true,
            'user' => $user
        ]);
    } else {
        // Get all users with filters
        $where = ['u.company_id = ?'];
        $params = [$data['company_id']];
        
        if (isset($_GET['office_id'])) {
            $where[] = 'u.office_id = ?';
            $params[] = $_GET['office_id'];
        }
        
        if (isset($_GET['role'])) {
            $where[] = 'u.role = ?';
            $params[] = $_GET['role'];
        }
        
        if (isset($_GET['name'])) {
            $where[] = 'u.name LIKE ?';
            $params[] = '%' . $_GET['name'] . '%';
        }
        
        if (isset($_GET['email'])) {
            $where[] = 'u.email LIKE ?';
            $params[] = '%' . $_GET['email'] . '%';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stmt = $conn->prepare("
            SELECT u.*, c.name as company_name, o.name as office_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN offices o ON u.office_id = o.id
            WHERE $where_clause
            ORDER BY u.created_at DESC
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the response to use user_id consistently
        $formatted_users = array_map(function($user) {
            return [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'company_id' => $user['company_id'],
                'office_id' => $user['office_id'],
                'company_name' => $user['company_name'],
                'office_name' => $user['office_name'],
                'created_at' => $user['created_at']
            ];
        }, $users);
        
        sendResponse(200, [
            'success' => true,
            'users' => $formatted_users
        ]);
    }
} catch (Exception $e) {
    sendResponse(500, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
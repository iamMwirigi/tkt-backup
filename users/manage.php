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
            error_log("Dotenv Error in users/manage.php: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/functions.php';

header('Content-Type: application/json');

// Use dummy values for testing
$user_id = 1;
$company_id = 1;
$user_role = 'admin';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Create new user
            validateRequiredFields(['name', 'email', 'password', 'role'], $data);
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Email already exists'
                ]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO users (company_id, name, email, password, role, office_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $company_id,
                $data['name'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'],
                $data['office_id'] ?? null
            ]);
            
            sendResponse(201, [
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'PUT':
            // Update existing user
            validateRequiredFields(['id'], $data);
            
            // Verify user belongs to company
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'User not found or does not belong to your company'
                ]);
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['email'])) {
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['password'])) {
                $updates[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            if (isset($data['role'])) {
                $updates[] = "role = ?";
                $params[] = $data['role'];
            }
            if (isset($data['office_id'])) {
                $updates[] = "office_id = ?";
                $params[] = $data['office_id'];
            }
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            $params[] = $data['id'];
            $params[] = $company_id;
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET " . implode(", ", $updates) . "
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute($params);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'User updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete user
            validateRequiredFields(['id'], $data);
            
            // Verify user belongs to company
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'User not found or does not belong to your company'
                ]);
            }
            
            // Check if user has any active bookings
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM bookings 
                WHERE user_id = ? AND status != 'completed'
            ");
            $stmt->execute([$data['id']]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Cannot delete user with active bookings'
                ]);
            }
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['id'], $company_id]);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(405, [
                'error' => true,
                'message' => 'Method not allowed'
            ]);
    }
    
} catch (Exception $e) {
    sendResponse(400, [
        'error' => true,
        'message' => $e->getMessage()
    ]);
} 
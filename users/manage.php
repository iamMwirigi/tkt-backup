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
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO users (company_id, office_id, name, email, password, role) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['company_id'],
                $data['office_id'] ?? null,
                $data['name'],
                $data['email'],
                $hashed_password,
                $data['role']
            ]);
            
            $user_id = $conn->lastInsertId();
            
            sendResponse(201, [
                'success' => true,
                'message' => 'User created successfully',
                'user' => [
                    'user_id' => $user_id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['role'],
                    'company_id' => $data['company_id'],
                    'office_id' => $data['office_id'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
            break;
            
        case 'PUT':
            // Update user
            if (!isset($data['user_id'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'User ID is required'
                ]);
            }
            
            // Check if user exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['user_id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'User not found'
                ]);
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updates[] = 'name = ?';
                $params[] = $data['name'];
            }
            
            if (isset($data['email'])) {
                // Check if new email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $data['user_id']]);
                if ($stmt->fetch()) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Email already exists'
                    ]);
                }
                $updates[] = 'email = ?';
                $params[] = $data['email'];
            }
            
            if (isset($data['password'])) {
                $updates[] = 'password = ?';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (isset($data['role'])) {
                $updates[] = 'role = ?';
                $params[] = $data['role'];
            }
            
            if (isset($data['office_id'])) {
                $updates[] = 'office_id = ?';
                $params[] = $data['office_id'];
            }
            
            if (empty($updates)) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'No fields to update'
                ]);
            }
            
            $params[] = $data['user_id'];
            $params[] = $data['company_id'];
            
            $stmt = $conn->prepare("
                UPDATE users 
                SET " . implode(', ', $updates) . "
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
            if (!isset($data['user_id'])) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'User ID is required'
                ]);
            }
            
            // Check if user exists and belongs to company
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['user_id'], $data['company_id']]);
            if (!$stmt->fetch()) {
                sendResponse(404, [
                    'error' => true,
                    'message' => 'User not found'
                ]);
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['user_id'], $data['company_id']]);
            
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
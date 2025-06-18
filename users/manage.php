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
            $stmt = $conn->prepare("SELECT id, email FROM users WHERE id = ? AND company_id = ?");
            $stmt->execute([$data['user_id'], $data['company_id']]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing_user) {
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
            
            // Get updated user data
            $stmt = $conn->prepare("
                SELECT u.*, c.name as company_name, o.name as office_name 
                FROM users u 
                LEFT JOIN companies c ON u.company_id = c.id 
                LEFT JOIN offices o ON u.office_id = o.id 
                WHERE u.id = ? AND u.company_id = ?
            ");
            $stmt->execute([$data['user_id'], $data['company_id']]);
            $updated_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            sendResponse(200, [
                'success' => true,
                'message' => 'User updated successfully',
                'user' => [
                    'user_id' => $updated_user['id'],
                    'name' => $updated_user['name'],
                    'email' => $updated_user['email'],
                    'role' => $updated_user['role'],
                    'company_id' => $updated_user['company_id'],
                    'office_id' => $updated_user['office_id'],
                    'company_name' => $updated_user['company_name'],
                    'office_name' => $updated_user['office_name'],
                    'created_at' => $updated_user['created_at']
                ]
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
            
            try {
                // Check for any references to this user in other tables
                $tables = [
                    'bookings' => 'user_id',
                    'tickets' => 'officer_id',
                    'deliveries' => 'created_by',
                    'devices' => 'user_id'
                ];
                $references = [];
                
                foreach ($tables as $table => $column) {
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
                    $stmt->execute([$data['user_id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result['count'] > 0) {
                        $references[] = $table;
                    }
                }
                
                if (!empty($references)) {
                    sendResponse(400, [
                        'error' => true,
                        'message' => 'Cannot delete user because they have existing records in: ' . implode(', ', $references) . '. Please delete or reassign these records first.'
                    ]);
                }
                
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND company_id = ?");
                $stmt->execute([$data['user_id'], $data['company_id']]);
                
                sendResponse(200, [
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            } catch (PDOException $e) {
                sendResponse(400, [
                    'error' => true,
                    'message' => 'Database error: ' . $e->getMessage()
                ]);
            }
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